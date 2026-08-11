<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi saldo akhir: bandingkan hasil scan API (ProductController::scan)
 * dengan Saldo Akhir dashboard ERP (custom/exportdatainvoice/report_all_stock.php).
 */
class CekSaldoStok extends Command
{
    protected $signature = 'stok:cek
                            {kode : Ref / barcode produk, cth: 102036549}
                            {--start= : Bulan awal periode dashboard (YYYY-MM). Default: 11 bulan lalu}
                            {--end= : Bulan akhir periode dashboard (YYYY-MM). Default: bulan ini}';

    protected $description = 'Bandingkan saldo akhir dashboard ERP vs hasil scan API untuk satu produk';

    public function handle(): int
    {
        $kode = $this->argument('kode');

        $product = DB::table('llxjp_product')
            ->where('ref', $kode)
            ->orWhere('barcode', $kode)
            ->select('rowid', 'ref', 'label', 'barcode', 'stock', 'entity')
            ->first();

        if (!$product) {
            $this->error("Produk '$kode' tidak ditemukan (dicari di kolom ref & barcode).");
            return self::FAILURE;
        }

        $id = (int) $product->rowid;

        // Periode default = sama dengan default report_all_stock.php (12 bulan terakhir)
        $start = new \DateTime(($this->option('start') ?: date('Y-m', strtotime('-11 month'))) . '-01');
        $end   = new \DateTime(($this->option('end') ?: date('Y-m')) . '-01');
        $end->modify('last day of this month')->setTime(23, 59, 59);

        $months = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }

        $sqlStart = $start->format('Y-m-d');
        $sqlEnd   = $end->format('Y-m-d H:i:s');

        $this->line('');
        $this->info("PRODUK  : {$product->ref} — {$product->label}");
        $this->line("rowid={$product->rowid}  barcode={$product->barcode}  entity={$product->entity}  p.stock={$product->stock}");
        $this->line('PERIODE : ' . $start->format('Y-m') . ' s/d ' . $end->format('Y-m'));
        $this->line('');

        $movRows = $this->movementRows($id);
        $urRows  = $this->usageRows($id);

        // ---- Versi dashboard: saldo awal + mutasi di dalam periode ----
        $saldoAwal = 0.0;
        foreach ($movRows as $r) {
            if ($r->datem < "$sqlStart 00:00:00") $saldoAwal += (float) $r->eff;
        }
        foreach ($urRows as $r) {
            if ($r->datem < "$sqlStart 00:00:00") $saldoAwal -= (float) $r->eff;
        }

        $in = array_fill_keys($months, 0.0);
        $out = array_fill_keys($months, 0.0);
        foreach ($movRows as $r) {
            $ym = substr((string) $r->datem, 0, 7);
            if (!isset($in[$ym])) continue;
            if ($r->value > 0) $in[$ym] += (float) $r->eff;
            elseif ($r->value < 0) $out[$ym] += abs((float) $r->eff);
        }
        foreach ($urRows as $r) {
            $ym = substr((string) $r->datem, 0, 7);
            if (!isset($out[$ym])) continue;
            $out[$ym] += (float) $r->eff;
        }

        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        foreach ($months as $ym) {
            $totalIn += $in[$ym];
            $totalOut += $out[$ym];
            if ($in[$ym] || $out[$ym]) {
                $rows[] = [$ym, $this->n($in[$ym]), $this->n($out[$ym])];
            }
        }
        $this->table(['Bulan', 'IN', 'OUT'], $rows);

        $saldoDashboard = $saldoAwal + $totalIn - $totalOut;

        // ---- Versi API: seluruh riwayat s/d akhir bulan berjalan (cutoff sama dengan ProductController::scan) ----
        $cutoff = date('Y-m-t 23:59:59');
        $totMov = array_sum(array_map(fn($r) => $r->datem !== null && $r->datem <= $cutoff ? (float) $r->eff : 0.0, $movRows));
        $totUr  = array_sum(array_map(fn($r) => $r->datem !== null && $r->datem <= $cutoff ? (float) $r->eff : 0.0, $urRows));
        $saldoApi = $totMov - $totUr;

        $allTime = array_sum(array_map(fn($r) => (float) $r->eff, $movRows))
                 - array_sum(array_map(fn($r) => (float) $r->eff, $urRows));

        $this->line('SALDO AWAL (< ' . $sqlStart . ')  : ' . $this->n($saldoAwal));
        $this->line('TOTAL IN / OUT periode      : ' . $this->n($totalIn) . ' / ' . $this->n($totalOut));
        $this->line('');
        $this->info('SALDO AKHIR (dashboard ERP) : ' . $this->n($saldoDashboard));
        $this->info('STOK SCAN (API, s/d ' . substr($cutoff, 0, 10) . ') : ' . $this->n($saldoApi) . "   [mov=" . $this->n($totMov) . ", usage=" . $this->n($totUr) . "]");
        $this->line('Tanpa batas tanggal (all-time)                : ' . $this->n($allTime));
        $this->line('p.stock (field cache Dolibarr, TIDAK dipotong usage_report): ' . $this->n((float) $product->stock));
        $this->line('');

        $selisih = $saldoApi - $saldoDashboard;
        if (abs($selisih) < 0.0001) {
            $this->info('OK — kedua angka SAMA. Kalau HP masih menampilkan angka lain, berarti response API lama (cache/deploy) atau cache di aplikasi.');
            return self::SUCCESS;
        }

        $this->error('SELISIH scan - dashboard = ' . $this->n($selisih));
        $this->line('');
        $this->warn('Transaksi DI LUAR periode dashboard (dihitung scan, tidak dihitung dashboard):');

        $luar = [];
        foreach ($movRows as $r) {
            if ($r->datem > $sqlEnd) {
                $luar[] = ['stock_mouvement', $r->rowid, $r->datem, $this->n((float) $r->eff), substr(str_replace("\n", ' ', (string) $r->label), 0, 45)];
            }
        }
        foreach ($urRows as $r) {
            if ($r->datem > $sqlEnd) {
                $luar[] = ['usage_report', $r->rowid, $r->datem, $this->n(-(float) $r->eff), 'usage ur=' . $r->ur_id];
            }
        }
        foreach ($movRows as $r) {
            if ($r->datem === null) $luar[] = ['stock_mouvement', $r->rowid, 'NULL', $this->n((float) $r->eff), 'datem kosong'];
        }
        foreach ($urRows as $r) {
            if ($r->datem === null) $luar[] = ['usage_report', $r->rowid, 'NULL', $this->n(-(float) $r->eff), 'date_creation kosong'];
        }

        if ($luar) {
            $this->table(['Sumber', 'rowid', 'Tanggal', 'Efek', 'Keterangan'], $luar);
            $this->line('=> Perbaiki tanggal transaksi di atas, atau lebarkan periode laporan.');
        } else {
            $this->line('  (tidak ada) — selisih BUKAN karena tanggal.');
            $this->line('  Cek apakah server API menjalankan versi kode terbaru: lihat storage/logs/scan_debug.log.');
        }

        return self::FAILURE;
    }

    /** Baris stock_mouvement, sudah di-expand lewat product_association (sama seperti report_all_stock.php). */
    private function movementRows(int $id): array
    {
        return DB::select(
            "SELECT sm.rowid, sm.datem, sm.value, sm.fk_product, sm.label,
                    (sm.value * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS eff
             FROM llxjp_stock_mouvement sm
             LEFT JOIN llxjp_product_association pa ON pa.fk_product_pere = sm.fk_product
             LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
             WHERE COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) = ?
             ORDER BY sm.datem",
            [$id]
        );
    }

    /** Baris usage_report_det yang dihitung sebagai OUT (filter identik dengan report_all_stock.php). */
    private function usageRows(int $id): array
    {
        return DB::select(
            "SELECT urd.rowid, ur.rowid AS ur_id, ur.date_creation AS datem, urd.qty_used, ur.fk_so,
                    (urd.qty_used * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS eff
             FROM llxjp_usage_report_det urd
             JOIN llxjp_usage_report ur ON ur.rowid = urd.fk_usage_report
             LEFT JOIN llxjp_product_association pa ON pa.fk_product_pere = urd.fk_product
             LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
             WHERE urd.qty_used > 0
               AND (ur.fk_so IS NULL OR ur.fk_so = 0 OR NOT EXISTS (
                   SELECT 1 FROM llxjp_element_element ee
                   JOIN llxjp_expedition ex ON ex.rowid = ee.fk_target
                   WHERE ee.sourcetype = 'commande' AND ee.targettype = 'shipping'
                     AND ee.fk_source = ur.fk_so AND ex.fk_statut > 0
               ))
               AND COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) = ?
             ORDER BY ur.date_creation",
            [$id]
        );
    }

    private function n(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
