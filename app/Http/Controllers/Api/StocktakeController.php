<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stocktake (stock opname).
 *
 * Modul ini SENGAJA hanya mengisi angka hitungan fisik. Pembuatan dokumen,
 * penarikan barisnya (tombol "Tarik Data"), validasi, dan approve tetap
 * dikerjakan di ERP custom/stocktake — itu pekerjaan meja, bukan pekerjaan
 * lapangan, dan approve menerbitkan pergerakan stok yang tidak boleh dipicu
 * dari ponsel.
 *
 * Status dokumen mengikuti Stocktake::$status di
 * custom/stocktake/class/stocktake.class.php:
 *   0 = Draft      baris boleh diisi
 *   1 = Validated  terkunci
 *   2 = Approved   stok sudah dikoreksi lewat approve_and_adjust()
 *
 * DUA HAL YANG BERUBAH DARI VERSI LAMA ENDPOINT INI:
 *
 * 1. Tidak ada lagi pemisahan angka Counter dan Verifikator. Kolom
 *    counter_qty_rak/tray/container/physical dan fk_user_counter_update /
 *    fk_user_verifikator_update sudah tidak ada di llxjp_stocktake_det, jadi
 *    yang tersisa satu set angka saja — persis seperti halaman card.php di
 *    ERP.
 *
 *    Peran juga tidak lagi menyembunyikan qty_theoretical. Sempat begitu,
 *    dengan alasan angka sistem membuat petugas menyalin, bukan menghitung.
 *    Alasan itu gugur di sini: yang memakai modul ini verifikator, dan dia
 *    toh bisa membuka angka yang sama di halaman stocktake ERP kapan saja.
 *    Menyembunyikannya cuma menambah percabangan tanpa menutup apa pun.
 *
 * 2. Tidak ada lagi gerbang jadwal llxjp_userstocktake. Kedua tabelnya kosong
 *    dan tidak ada satu pun berkas di custom/ yang mengisinya, sehingga
 *    gerbang itu selalu berujung 404. Principal turun pangkat menjadi
 *    PENYARING yang datanya diambil dari isi dokumen itu sendiri.
 *
 * Karena kolom penanda "sudah dihitung" ikut hilang, status terisi diturunkan
 * dari qty_physical > 0 (lihat const TERISI). Akibatnya barang yang memang
 * berjumlah nol tidak bisa dibedakan dari barang yang belum disentuh sama
 * sekali. ERP punya keterbatasan yang sama, jadi angka progres di sini dan di
 * ERP tetap sepakat.
 */
class StocktakeController extends Controller
{
    /** Baris per halaman bila klien tidak mengirim per_page. */
    private const PER_PAGE = 50;

    /** Batas atas per_page, supaya satu dokumen tidak ditarik sekaligus. */
    private const MAX_PER_PAGE = 200;

    /** Batas jumlah baris dalam satu kali simpan. */
    private const MAX_SIMPAN = 200;

    /**
     * Penanda baris sudah dihitung. Ditulis sekali di sini karena dipakai di
     * empat query berbeda dan harus selalu sama — kalau salah satunya beda,
     * angka progres di daftar dokumen tidak cocok dengan angka di halaman
     * kerjanya.
     *
     * Yang diperiksa qty_physical, BUKAN jumlah rak + tray + container.
     * Dokumen lama dihitung dengan mengisi qty fisik langsung tanpa memecahnya
     * ke tiga tempat itu: STK/2601/0001 punya 239 baris berisi qty fisik dan
     * NOL baris berincian. Dengan aturan rincian, halamannya berbunyi "0 dari
     * 270 terhitung" padahal di sebelahnya tertulis "Fisik 36.346".
     *
     * Aman menggantinya: di dua dokumen yang memakai rincian, kedua aturan
     * memberi angka yang sama persis (294/294 dan 316/316), dan tidak ada satu
     * pun baris berincian yang qty fisiknya nol — endpoint ini dan card.php ERP
     * sama-sama selalu menulis ulang qty_physical sebagai jumlah ketiganya.
     */
    private const TERISI = 'sd.qty_physical > 0';

    /* ------------------------------------------------------------------
     | Endpoint
     * ------------------------------------------------------------------ */

    /**
     * GET /api/stocktake
     *
     * Daftar dokumen stocktake, terbaru dulu, lengkap dengan progres
     * pengisiannya. Tidak dipaginasi: ERP membuat dokumen ini paling banyak
     * dua belas kali setahun per gudang.
     *
     * Query param opsional:
     *   ?status=0|1|2   saring per status dokumen
     */
    public function index(Request $request)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $query = DB::table('llxjp_stocktake as t')
            ->leftJoin('llxjp_entrepot as w', 'w.rowid', '=', 't.fk_warehouse')
            ->where('t.entity', 1)
            ->select(
                't.rowid',
                't.ref',
                't.label',
                't.stocktake_date',
                't.period_month',
                't.period_year',
                't.type',
                't.status',
                'w.ref as warehouse_name'
            )
            ->orderByDesc('t.stocktake_date')
            ->orderByDesc('t.rowid');

        // is_numeric, bukan !empty: status=0 (Draft) adalah saringan yang sah.
        $status = $request->query('status');
        if (is_numeric($status)) {
            $query->where('t.status', (int) $status);
        }

        $dokumen = $query->get();

        // Progres seluruh dokumen diambil sekali, bukan satu query per baris.
        $progres = DB::table('llxjp_stocktake_det as sd')
            ->whereIn('sd.fk_stocktake', $dokumen->pluck('rowid'))
            ->groupBy('sd.fk_stocktake')
            ->selectRaw('sd.fk_stocktake, COUNT(*) as total, SUM(CASE WHEN '.self::TERISI.' THEN 1 ELSE 0 END) as terisi')
            ->get()
            ->keyBy('fk_stocktake');

        $hasil = $dokumen->map(function ($d) use ($progres) {
            $p = $progres->get($d->rowid);

            return [
                'rowid' => (int) $d->rowid,
                'ref' => $d->ref,
                'label' => $d->label,
                'warehouse_name' => $d->warehouse_name,
                'stocktake_date' => $d->stocktake_date,
                'periode' => $this->labelPeriode($d),
                'status' => (int) $d->status,
                'status_label' => $this->labelStatus((int) $d->status),
                'boleh_isi' => (int) $d->status === 0,
                'total_baris' => (int) ($p->total ?? 0),
                'baris_terisi' => (int) ($p->terisi ?? 0),
            ];
        })->values();

        return $this->successResponse($hasil, 'Berhasil mengambil daftar stocktake.');
    }

    /**
     * GET /api/stocktake/{id}
     *
     * Kepala dokumen, ringkasan, dan daftar principal untuk penyaring. Baris
     * barangnya tidak ikut — itu tugas /baris yang dipaginasi.
     */
    public function show(Request $request, $id)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $dokumen = $this->ambilDokumen($id);
        if (! $dokumen) {
            return $this->errorResponse('Dokumen stocktake tidak ditemukan.', 404);
        }

        /*
         * COALESCE(s.rowid, 0): dua baris di STK/2608/0001 punya principal '0'
         * yang tidak cocok dengan societe mana pun. Dengan INNER JOIN keduanya
         * hilang diam-diam dari daftar dan tidak akan pernah dihitung siapa pun.
         */
        $principals = DB::table('llxjp_stocktake_det as sd')
            ->leftJoin('llxjp_product_extrafields as pe', 'pe.fk_object', '=', 'sd.fk_product')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 'pe.principal')
            ->where('sd.fk_stocktake', $dokumen->rowid)
            ->groupBy(DB::raw('COALESCE(s.rowid, 0)'), DB::raw("COALESCE(s.nom, 'Lainnya')"))
            ->selectRaw("COALESCE(s.rowid, 0) as id, COALESCE(s.nom, 'Lainnya') as nama, COUNT(*) as total, SUM(CASE WHEN ".self::TERISI." THEN 1 ELSE 0 END) as terisi")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'nama' => $p->nama,
                'total' => (int) $p->total,
                'terisi' => (int) $p->terisi,
            ])
            ->values();

        return $this->successResponse([
            'dokumen' => $this->bentukDokumen($dokumen),
            'ringkasan' => $this->ringkasan($dokumen->rowid),
            'principals' => $principals,
        ], 'Berhasil mengambil dokumen stocktake.');
    }

    /**
     * GET /api/stocktake/{id}/baris
     *
     * Query param opsional:
     *   ?principal=8          id societe; 0 berarti kelompok "Lainnya"
     *   ?cari=                cocokkan ke ref, nama, atau barcode produk
     *   ?hanya=belum|selisih  baris yang belum diisi / yang berselisih
     *   ?page=1&per_page=50
     *
     * Scan barcode TIDAK punya endpoint sendiri: barcode hasil kamera cukup
     * dimasukkan ke ?cari=, karena hasilnya baris yang sama persis dan petugas
     * tetap perlu melihat kolom isian di sebelahnya.
     */
    public function lines(Request $request, $id)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $dokumen = $this->ambilDokumen($id);
        if (! $dokumen) {
            return $this->errorResponse('Dokumen stocktake tidak ditemukan.', 404);
        }

        $perPage = $request->query('per_page', self::PER_PAGE);
        $perPage = is_numeric($perPage) ? (int) $perPage : self::PER_PAGE;
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $page = $request->query('page', 1);
        $page = is_numeric($page) ? (int) $page : 1;
        $page = max(1, $page);

        $query = $this->queryBaris($dokumen->rowid, $request);

        $total = (clone $query)->count();

        $baris = $query
            ->orderBy('p.ref')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($b) => $this->bentukBaris($b))
            ->values();

        return $this->successResponse($baris, 'Berhasil mengambil baris stocktake.', 200, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ]);
    }

    /**
     * POST /api/stocktake/{id}/baris
     *
     * Menyimpan sekumpulan baris sekaligus:
     *   { "baris": [ {"det_id":1, "qty_rak":2, "qty_tray":0, "qty_container":1} ] }
     *
     * qty_physical TIDAK diterima dari klien — selalu dihitung ulang di sini
     * sebagai rak + tray + container, sama dengan calcQty() di card.php ERP.
     * Kalau klien boleh mengirimnya, angka rinciannya bisa tidak sama dengan
     * totalnya dan halaman variance jadi tidak bisa dipercaya.
     */
    public function saveLines(Request $request, $id)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $this->normalkanKoma($request);

        $request->validate([
            'baris' => 'required|array|min:1|max:'.self::MAX_SIMPAN,
            'baris.*.det_id' => 'required|integer',
            'baris.*.qty_rak' => 'nullable|numeric|min:0',
            'baris.*.qty_tray' => 'nullable|numeric|min:0',
            'baris.*.qty_container' => 'nullable|numeric|min:0',
            'baris.*.catatan' => 'nullable|string|max:255',
        ]);

        $dokumen = $this->ambilDokumen($id);
        if (! $dokumen) {
            return $this->errorResponse('Dokumen stocktake tidak ditemukan.', 404);
        }

        if ((int) $dokumen->status !== 0) {
            return $this->errorResponse(
                'Dokumen '.$dokumen->ref.' sudah '.$this->labelStatus((int) $dokumen->status)
                .' sehingga isiannya tidak bisa diubah lagi.',
                409
            );
        }

        $kiriman = $request->input('baris');

        // Baris diambil sekali dengan whereIn, bukan satu SELECT per baris:
        // satu halaman kerja bisa mengirim 50 baris sekaligus.
        $dikenal = DB::table('llxjp_stocktake_det')
            ->where('fk_stocktake', $dokumen->rowid)
            ->whereIn('rowid', array_column($kiriman, 'det_id'))
            ->pluck('rowid')
            ->all();
        $dikenal = array_flip($dikenal);

        $tersimpan = 0;
        $dilewati = [];

        DB::beginTransaction();

        try {
            foreach ($kiriman as $b) {
                $detId = (int) $b['det_id'];

                // Baris milik dokumen lain ditolak per baris, bukan
                // menggagalkan seluruh kiriman: yang lain tetap tersimpan dan
                // nomornya dilaporkan balik supaya bisa ditelusuri.
                if (! isset($dikenal[$detId])) {
                    $dilewati[] = $detId;

                    continue;
                }

                $rak = $this->angka($b['qty_rak'] ?? null);
                $tray = $this->angka($b['qty_tray'] ?? null);
                $container = $this->angka($b['qty_container'] ?? null);

                $isian = [
                    'qty_rak' => $rak,
                    'qty_tray' => $tray,
                    'qty_container' => $container,
                    'qty_physical' => $rak + $tray + $container,
                ];

                // Catatan hanya ditimpa bila memang dikirim. Halaman kerja
                // menyembunyikannya di balik satu tombol kecil, jadi kebanyakan
                // baris tidak membawanya dan catatan lama tidak boleh terhapus.
                if (array_key_exists('catatan', $b)) {
                    $isian['note'] = (string) ($b['catatan'] ?? '');
                }

                DB::table('llxjp_stocktake_det')->where('rowid', $detId)->update($isian);
                $tersimpan++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->errorResponse('Gagal menyimpan hitungan: '.$e->getMessage(), 500);
        }

        return $this->successResponse([
            'tersimpan' => $tersimpan,
            'dilewati' => $dilewati,
            'ringkasan' => $this->ringkasan($dokumen->rowid),
        ], $tersimpan.' baris tersimpan.');
    }

    /* ------------------------------------------------------------------
     | Pembungkus untuk aplikasi Android yang sudah beredar
     |
     | Keempat endpoint lama tetap hidup supaya APK yang sudah terpasang tidak
     | perlu menunggu rilis baru. Bentuk responsnya dipertahankan; isinya
     | sekarang diambil dari dokumen Draft terbaru, bukan dari jadwal
     | llxjp_userstocktake yang tabelnya kosong.
     * ------------------------------------------------------------------ */

    /** GET /api/stocktake/products */
    public function getProducts(Request $request)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $dokumen = $this->dokumenDraftAktif();
        if (! $dokumen) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }

        $baris = $this->queryBaris($dokumen->rowid, $request)
            ->orderBy('p.ref')
            ->get()
            ->map(fn ($b) => $this->bentukProdukLama($b))
            ->values();

        return $this->successResponse([
            'stocktake_id' => (int) $dokumen->rowid,
            'detail_id' => null,   // jadwal sudah tidak dipakai
            'date_stocktake' => $dokumen->stocktake_date,
            'fk_principal' => 0,   // tidak ada lagi penugasan principal
            'principal_name' => $dokumen->label ?: $dokumen->ref,
            'total_items' => $baris->count(),
            'products' => $baris,
        ], 'Berhasil mengambil data produk stocktake');
    }

    /** POST /api/stocktake/update — body { products: [ {fk_product, qty_rak, ...} ] } */
    public function updateProducts(Request $request)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $request->validate([
            'products' => 'required|array',
            'products.*.fk_product' => 'required',
        ]);

        $dokumen = $this->dokumenDraftAktif();
        if (! $dokumen) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }

        // Android mengirim rowid/ref produk, bukan rowid baris stocktake_det.
        // Diterjemahkan di sini supaya saveLines() punya satu bentuk masukan.
        $kunci = collect($request->input('products'))
            ->pluck('fk_product')
            ->map(fn ($v) => (string) $v)
            ->all();

        $petaProduk = DB::table('llxjp_product')
            ->where(function ($q) use ($kunci) {
                $q->whereIn('rowid', $kunci)->orWhereIn('ref', $kunci);
            })
            ->get(['rowid', 'ref']);

        $keDet = DB::table('llxjp_stocktake_det')
            ->where('fk_stocktake', $dokumen->rowid)
            ->whereIn('fk_product', $petaProduk->pluck('rowid'))
            ->pluck('rowid', 'fk_product');

        $baris = [];
        foreach ($request->input('products') as $p) {
            $kode = (string) $p['fk_product'];
            $produk = $petaProduk->first(fn ($x) => (string) $x->rowid === $kode || (string) $x->ref === $kode);

            if (! $produk || ! isset($keDet[$produk->rowid])) {
                continue;
            }

            $baris[] = [
                'det_id' => (int) $keDet[$produk->rowid],
                'qty_rak' => $p['qty_rak'] ?? 0,
                'qty_tray' => $p['qty_tray'] ?? 0,
                'qty_container' => $p['qty_container'] ?? 0,
            ];
        }

        if (empty($baris)) {
            return $this->errorResponse('Tidak ada produk yang cocok dengan dokumen stocktake yang aktif.', 422);
        }

        $request->merge(['baris' => $baris]);

        return $this->saveLines($request, $dokumen->rowid);
    }

    /** POST /api/stocktake/scan — body { barcode } */
    public function scanProduct(Request $request)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $request->validate(['barcode' => 'required|string']);

        $dokumen = $this->dokumenDraftAktif();
        if (! $dokumen) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }

        $baris = $this->queryBaris($dokumen->rowid, $request)
            ->where('p.barcode', trim($request->input('barcode')))
            ->first();

        if (! $baris) {
            return $this->errorResponse(
                'Barcode ini tidak ada di dokumen '.$dokumen->ref.'. Barang yang saldonya nol memang tidak ikut ditarik ERP.',
                404
            );
        }

        return $this->successResponse($this->bentukProdukLama($baris), 'Berhasil menemukan data produk.');
    }

    /**
     * GET /api/stocktake/history
     *
     * Riwayat = dokumen yang sudah Validated atau Approved. Tidak ada lagi
     * penelusuran "jadwal mana yang cocok dengan dokumen mana" seperti versi
     * lama; satu dokumen ERP adalah satu baris riwayat.
     */
    public function getHistory(Request $request)
    {
        if ($galat = $this->tolakBilaTidakBerhak($request)) {
            return $galat;
        }

        $dokumen = DB::table('llxjp_stocktake')
            ->where('entity', 1)
            ->whereIn('status', [1, 2])
            ->orderByDesc('stocktake_date')
            ->orderByDesc('rowid')
            ->get();

        $hasil = [];

        foreach ($dokumen as $d) {
            $baris = $this->queryBaris($d->rowid, $request)
                ->orderBy('p.ref')
                ->get();

            $hasil[] = [
                'schedule_id' => (int) $d->rowid,
                'date_stocktake' => $d->stocktake_date,
                'fk_principal' => 0,
                'principal_name' => $d->label ?: $d->ref,
                'schedule_status' => (int) $d->status,
                'signature_counter' => null,
                'signature_verifikator' => null,
                'total_items' => $baris->count(),
                'total_qty' => (float) $baris->sum('qty_physical'),
                'products' => $baris->map(fn ($b) => $this->bentukProdukLama($b))->values(),
            ];
        }

        return $this->successResponse($hasil, 'Berhasil mengambil riwayat stocktake.');
    }

    /* ------------------------------------------------------------------
     | Pembantu
     * ------------------------------------------------------------------ */

    /**
     * Query dasar baris stocktake beserta seluruh penyaringnya.
     *
     * LEFT JOIN ke extrafields dan societe, bukan INNER: produk tanpa baris
     * extrafield atau dengan principal yang tidak dikenal tetap harus muncul,
     * kalau tidak barang itu tidak akan pernah bisa dihitung dari mana pun.
     */
    private function queryBaris(int $stocktakeId, Request $request)
    {
        $kolom = [
            'sd.rowid as det_id',
            'sd.fk_product',
            'sd.qty_rak',
            'sd.qty_tray',
            'sd.qty_container',
            'sd.qty_physical',
            'sd.note',
            'p.ref',
            'p.label',
            'p.barcode',
            's.rowid as principal_id',
            's.nom as principal_name',
            'sd.qty_theoretical',
        ];

        $query = DB::table('llxjp_stocktake_det as sd')
            ->join('llxjp_product as p', 'p.rowid', '=', 'sd.fk_product')
            ->leftJoin('llxjp_product_extrafields as pe', 'pe.fk_object', '=', 'p.rowid')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 'pe.principal')
            ->where('sd.fk_stocktake', $stocktakeId)
            ->select($kolom);

        $principal = $request->query('principal');
        if (is_numeric($principal)) {
            if ((int) $principal === 0) {
                $query->whereNull('s.rowid');
            } else {
                $query->where('s.rowid', (int) $principal);
            }
        }

        $cari = trim((string) $request->query('cari', ''));
        if ($cari !== '') {
            $query->where(function ($q) use ($cari) {
                $q->where('p.ref', 'like', '%'.$cari.'%')
                    ->orWhere('p.label', 'like', '%'.$cari.'%')
                    ->orWhere('p.barcode', 'like', '%'.$cari.'%');
            });
        }

        $hanya = $request->query('hanya');
        if ($hanya === 'belum') {
            $query->whereRaw('NOT ('.self::TERISI.')');
        } elseif ($hanya === 'selisih') {
            $query->whereRaw('ROUND(sd.qty_physical, 4) <> ROUND(sd.qty_theoretical, 4)');
        }

        return $query;
    }

    private function ringkasan(int $stocktakeId): array
    {
        $r = DB::table('llxjp_stocktake_det as sd')
            ->where('sd.fk_stocktake', $stocktakeId)
            ->selectRaw(
                'COUNT(*) as total, '
                .'SUM(CASE WHEN '.self::TERISI.' THEN 1 ELSE 0 END) as terisi, '
                .'SUM(sd.qty_physical) as fisik, '
                .'SUM(sd.qty_theoretical) as teori, '
                .'SUM(CASE WHEN ROUND(sd.qty_physical, 4) <> ROUND(sd.qty_theoretical, 4) THEN 1 ELSE 0 END) as baris_selisih'
            )
            ->first();

        return [
            'total_baris' => (int) ($r->total ?? 0),
            'terisi' => (int) ($r->terisi ?? 0),
            'belum' => (int) ($r->total ?? 0) - (int) ($r->terisi ?? 0),
            'total_fisik' => (float) ($r->fisik ?? 0),
            'total_teori' => (float) ($r->teori ?? 0),
            'baris_selisih' => (int) ($r->baris_selisih ?? 0),
        ];
    }

    private function bentukDokumen($d): array
    {
        return [
            'rowid' => (int) $d->rowid,
            'ref' => $d->ref,
            'label' => $d->label,
            'warehouse_name' => $d->warehouse_name ?? null,
            'stocktake_date' => $d->stocktake_date,
            'periode' => $this->labelPeriode($d),
            'catatan' => $d->note,
            'status' => (int) $d->status,
            'status_label' => $this->labelStatus((int) $d->status),
            'boleh_isi' => (int) $d->status === 0,
        ];
    }

    private function bentukBaris($b): array
    {
        return [
            'det_id' => (int) $b->det_id,
            'product_id' => (int) $b->fk_product,
            'ref' => $b->ref,
            'label' => $b->label,
            'barcode' => $b->barcode,
            'principal_id' => (int) ($b->principal_id ?? 0),
            'principal_name' => $b->principal_name ?: 'Lainnya',
            'qty_rak' => (float) $b->qty_rak,
            'qty_tray' => (float) $b->qty_tray,
            'qty_container' => (float) $b->qty_container,
            'qty_physical' => (float) $b->qty_physical,
            'catatan' => (string) ($b->note ?? ''),
            'sudah_diisi' => (float) $b->qty_physical > 0,
            'qty_theoretical' => (float) $b->qty_theoretical,
            'selisih' => (float) $b->qty_physical - (float) $b->qty_theoretical,
        ];
    }

    /** Bentuk lama yang dipahami ProductsItem di Android. */
    private function bentukProdukLama($b): array
    {
        return [
            'rowid' => (int) $b->fk_product,
            'det_id' => (int) $b->det_id,
            'ref' => $b->ref,
            'label' => $b->label,
            'barcode' => $b->barcode ?? null,
            'principal_name' => $b->principal_name ?: 'Lainnya',
            'qty_rak' => (float) $b->qty_rak,
            'qty_tray' => (float) $b->qty_tray,
            'qty_container' => (float) $b->qty_container,
            'qty_physical' => (float) $b->qty_physical,
            'is_updated' => (float) $b->qty_physical > 0,
            'qty_theoretical' => (float) $b->qty_theoretical,
        ];
    }

    private function ambilDokumen($id)
    {
        return DB::table('llxjp_stocktake as t')
            ->leftJoin('llxjp_entrepot as w', 'w.rowid', '=', 't.fk_warehouse')
            ->where('t.rowid', (int) $id)
            ->where('t.entity', 1)
            ->select('t.*', 'w.ref as warehouse_name')
            ->first();
    }

    /**
     * Dokumen Draft terbaru, untuk keempat endpoint lama yang tidak membawa id.
     * Versi lama memakai ->where('status', 0)->first() tanpa urutan, sehingga
     * dokumen mana yang terpilih bergantung pada urutan baris di tabel.
     */
    private function dokumenDraftAktif()
    {
        return DB::table('llxjp_stocktake as t')
            ->leftJoin('llxjp_entrepot as w', 'w.rowid', '=', 't.fk_warehouse')
            ->where('t.entity', 1)
            ->where('t.status', 0)
            ->orderByDesc('t.stocktake_date')
            ->orderByDesc('t.rowid')
            ->select('t.*', 'w.ref as warehouse_name')
            ->first();
    }

    /** Peran yang boleh membuka modul ini sama dengan versi lama. */
    private function tolakBilaTidakBerhak(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (! $user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $grup = $user->groups->pluck('nom')->toArray();

        if (in_array('Warehouse', $grup) || in_array('Verifikator', $grup) || (isset($user->admin) && $user->admin == 1)) {
            return null;
        }

        return $this->errorResponse('User tidak memiliki akses ke modul Stocktake.', 403);
    }

    /**
     * Papan ketik ponsel Indonesia memberi koma sebagai pemisah desimal,
     * sedangkan validasi numeric hanya menerima titik. Ditukar sebelum
     * divalidasi supaya "1,5" tidak ditolak sebagai bukan angka.
     */
    private function normalkanKoma(Request $request): void
    {
        $baris = $request->input('baris');

        if (! is_array($baris)) {
            return;
        }

        foreach ($baris as $i => $b) {
            foreach (['qty_rak', 'qty_tray', 'qty_container'] as $kolom) {
                if (isset($b[$kolom]) && is_string($b[$kolom])) {
                    $baris[$i][$kolom] = str_replace(',', '.', trim($b[$kolom]));
                }
            }
        }

        $request->merge(['baris' => $baris]);
    }

    /** Isian kosong berarti nol, bukan "biarkan nilai lama". */
    private function angka($nilai): float
    {
        if ($nilai === null || $nilai === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $nilai);
    }

    private function labelStatus(int $status): string
    {
        switch ($status) {
            case 0: return 'Draft';
            case 1: return 'Validated';
            case 2: return 'Approved';
            default: return 'Unknown';
        }
    }

    /** "Bulanan 08/2026", mengikuti tipe dokumen di ERP. */
    private function labelPeriode($d): string
    {
        $tipe = [1 => 'Bulanan', 2 => 'Semester', 3 => 'Tahunan'][(int) ($d->type ?? 0)] ?? 'Periode';

        if (empty($d->period_year)) {
            return $tipe;
        }

        return $tipe.' '.str_pad((string) ($d->period_month ?? 0), 2, '0', STR_PAD_LEFT).'/'.$d->period_year;
    }
}
