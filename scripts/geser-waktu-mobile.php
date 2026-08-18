<?php

/**
 * Perbaikan sekali jalan: memajukan 7 jam waktu yang terlanjur ditulis mobile
 * dalam UTC, padahal Dolibarr menyimpan waktu server (Asia/Jakarta).
 *
 * Penjelasan lengkap sebab dan buktinya ada di
 * App\Traits\LogsDolibarrActivity::dolibarrNow().
 *
 * ---------------------------------------------------------------------------
 * CARA PAKAI (tiga langkah, urutannya mengikat)
 * ---------------------------------------------------------------------------
 *
 *   1. CATAT batas, SEBELUM kode baru aktif:
 *        php artisan tinker --execute="\$MODE='catat'; require 'scripts/geser-waktu-mobile.php';"
 *
 *   2. Deploy kode baru, lalu bersihkan cache.
 *
 *   3. LIHAT rencananya, lalu jalankan:
 *        php artisan tinker --execute="\$MODE='rencana';  require 'scripts/geser-waktu-mobile.php';"
 *        php artisan tinker --execute="\$MODE='jalankan'; require 'scripts/geser-waktu-mobile.php';"
 *
 * ---------------------------------------------------------------------------
 * KENAPA HARUS DICATAT DULU
 * ---------------------------------------------------------------------------
 * Baris yang ditulis kode BARU sudah benar dan tidak boleh ikut digeser.
 * Keduanya sama-sama bertanda source = 'MOBILE', jadi yang membedakan hanya
 * urutan penulisannya. Langkah 1 mencatat rowid terakhir sebelum kode baru
 * aktif; pergeseran hanya menyentuh rowid sampai batas itu.
 *
 * Berkas batas juga menjadi pengaman sekali jalan. Setelah dieksekusi ia diberi
 * tanda selesai dan penjalanan berikutnya ditolak — kalau tidak, eksekusi kedua
 * akan menggeser lagi menjadi 14 jam, dan setelah tergeser tidak ada lagi cara
 * membedakan mana yang sudah dan mana yang belum.
 */

use Illuminate\Support\Facades\DB;

$MODE = isset($MODE) ? $MODE : 'rencana';
$GESER_DETIK = 7 * 3600;

/** Batas toleransi selisih waktu saat mencocokkan kolom dokumen dengan baris log. */
$TOLERANSI_DETIK = 120;

$berkasBatas = storage_path('app/geser-waktu-mobile.json');

$tabel = [
    [
        'log' => 'llxjp_tindakan_activity_log', 'fk' => 'fk_tindakan',
        'dok' => 'llxjp_tindakan', 'pk' => 'id',
        'kolom' => ['CREATE' => 'datec', 'VALIDATE' => 'datev', 'ARRIVAL' => 'date_arrival'],
    ],
    [
        'log' => 'llxjp_usage_report_log', 'fk' => 'fk_usage_report',
        'dok' => 'llxjp_usage_report', 'pk' => 'rowid',
        'kolom' => ['CREATE' => 'date_creation', 'VALIDATE' => 'datev', 'TARIK_BARANG' => 'date_tarik'],
    ],
    [
        'log' => 'llxjp_forecast_activity_log', 'fk' => 'fk_forecast',
        'dok' => 'llxjp_forecast', 'pk' => 'rowid',
        'kolom' => ['CREATE' => 'datec'],
    ],
];

// --- Mode periksa: laporan keadaan, tidak menulis apa pun ------------------

if ($MODE === 'periksa') {
    $doc = config('services.erp.doc_root');

    echo "ERP_DOC_ROOT   : " . ($doc ?: '(KOSONG — tarik barang akan ditolak)') . "\n";
    echo "foldernya ada? : " . ($doc && is_dir($doc) ? 'ya' : 'TIDAK') . "\n";
    echo "app.timezone   : " . config('app.timezone') . "\n";
    echo "MAIN_SERVER_TZ : " . (DB::table('llxjp_const')->where('name', 'MAIN_SERVER_TZ')->value('value') ?: '(kosong)') . "\n";
    echo "jam server     : " . date('Y-m-d H:i:s') . "\n";
    echo "berkas batas   : " . (file_exists($berkasBatas) ? 'ADA' : 'BELUM DICATAT') . "\n";

    if (file_exists($berkasBatas)) {
        $s = json_decode(file_get_contents($berkasBatas), true);
        echo "  dicatat pada : {$s['dicatat_pada']}\n";
        echo "  sudah jalan  : " . (!empty($s['sudah_dijalankan']) ? 'YA (' . ($s['dijalankan_pada'] ?? '?') . ')' : 'belum') . "\n";
    }

    // Baris mobile terbaru menjawab pertanyaan paling penting: apakah ada tulisan
    // BARU (sudah benar) setelah kode di-deploy. Baris yang jamnya mendekati jam
    // server berarti ditulis kode baru dan TIDAK boleh ikut digeser.
    echo "\nBaris log mobile terbaru:\n";
    foreach ($tabel as $t) {
        $baris = DB::table($t['log'])->where('source', 'MOBILE')
            ->orderByDesc('rowid')->limit(2)->get(['rowid', 'action', 'datelog']);

        echo "  {$t['log']}:\n";
        foreach ($baris as $b) {
            $selisihJam = round((time() - strtotime($b->datelog)) / 3600, 1);
            echo "    #{$b->rowid} {$b->action} {$b->datelog} ({$selisihJam} jam lalu)\n";
        }
    }

    echo "\nCara membaca: bila baris teratas berjarak sekitar 7 jam ATAU LEBIH dan\n";
    echo "tidak ada aktivitas mobile sejak deploy, semuanya masih gaya lama dan aman\n";
    echo "dicatat sekarang. Bila ada baris yang jaraknya beberapa menit saja, itu\n";
    echo "tulisan kode baru yang sudah benar — laporkan dulu sebelum menggeser.\n";

    return;
}

// --- Langkah 1: catat batas ------------------------------------------------

if ($MODE === 'catat') {
    if (file_exists($berkasBatas)) {
        echo "DITOLAK: {$berkasBatas} sudah ada. Hapus dulu bila memang ingin mencatat ulang.\n";
        return;
    }

    $batas = [];
    foreach ($tabel as $t) {
        $batas[$t['log']] = (int) DB::table($t['log'])->max('rowid');
    }

    file_put_contents($berkasBatas, json_encode([
        'dicatat_pada' => date('Y-m-d H:i:s'),
        'batas_rowid' => $batas,
        'sudah_dijalankan' => false,
    ], JSON_PRETTY_PRINT));

    echo "Batas tercatat di {$berkasBatas}:\n";
    foreach ($batas as $nama => $rowid) {
        echo "  {$nama} <= {$rowid}\n";
    }
    echo "\nSilakan lanjut deploy kode baru.\n";

    return;
}

// --- Pemeriksaan sebelum menggeser -----------------------------------------

if (!file_exists($berkasBatas)) {
    echo "DITOLAK: {$berkasBatas} belum ada. Jalankan mode 'catat' lebih dulu.\n";
    return;
}

$status = json_decode(file_get_contents($berkasBatas), true);

if (!empty($status['sudah_dijalankan'])) {
    echo "DITOLAK: sudah pernah dijalankan pada {$status['dijalankan_pada']}.\n";
    echo "Menjalankan ulang akan menggeser data yang sudah benar menjadi 14 jam.\n";
    return;
}

$tzErp = DB::table('llxjp_const')->where('name', 'MAIN_SERVER_TZ')->value('value');
$tzApp = config('app.timezone');

echo "MAIN_SERVER_TZ (ERP) : " . ($tzErp ?: '(kosong)') . "\n";
echo "app.timezone (Laravel): {$tzApp}\n\n";

if ($tzErp !== $tzApp) {
    echo "DITOLAK: zona waktu ERP dan Laravel berbeda. Samakan dulu — selisih 7 jam\n";
    echo "yang diasumsikan skrip ini hanya berlaku bila keduanya Asia/Jakarta.\n";
    return;
}

// --- Susun rencana ---------------------------------------------------------

$rencana = [];
$dilewati = [];

foreach ($tabel as $t) {
    $batas = (int) ($status['batas_rowid'][$t['log']] ?? 0);

    $barisLog = DB::table($t['log'])
        ->where('source', 'MOBILE')
        ->where('rowid', '<=', $batas)
        ->whereIn('action', array_keys($t['kolom']))
        ->orderBy('rowid')
        ->get();

    foreach ($barisLog as $log) {
        $kolom = $t['kolom'][$log->action];
        $dok = DB::table($t['dok'])->where($t['pk'], $log->{$t['fk']})->first();

        if (!$dok || empty($dok->{$kolom})) {
            $dilewati[] = "{$t['dok']}#{$log->{$t['fk']}}.{$kolom} — kolomnya kosong";
            continue;
        }

        $selisih = abs(strtotime($dok->{$kolom}) - strtotime($log->datelog));

        // Nilai yang sudah jauh berbeda dari baris log mobile berarti tahap itu
        // pernah diulang lewat ERP. Yang berlaku adalah tulisan ERP, dan itu
        // sudah benar sejak awal — jangan disentuh.
        if ($selisih > $TOLERANSI_DETIK) {
            $dilewati[] = "{$t['dok']}#{$log->{$t['fk']}}.{$kolom} — nilainya {$dok->{$kolom}}, "
                . "beda {$selisih} detik dari log mobile ({$log->datelog}); kemungkinan ditulis ulang lewat ERP";
            continue;
        }

        $rencana[$t['dok'] . '|' . $log->{$t['fk']} . '|' . $kolom] = [
            'tabel' => $t['dok'], 'pk' => $t['pk'], 'id' => $log->{$t['fk']},
            'kolom' => $kolom, 'dari' => $dok->{$kolom},
            'jadi' => date('Y-m-d H:i:s', strtotime($dok->{$kolom}) + $GESER_DETIK),
        ];
    }
}

echo "=== KOLOM DOKUMEN (" . count($rencana) . ") ===\n";
foreach ($rencana as $r) {
    printf("%-24s #%-6s %-14s %s  ->  %s\n", $r['tabel'], $r['id'], $r['kolom'], $r['dari'], $r['jadi']);
}

echo "\n=== DILEWATI (" . count($dilewati) . ") ===\n";
foreach ($dilewati as $d) {
    echo "  - {$d}\n";
}

// Jaring pengaman terakhir, dan yang paling menentukan: kejadian yang sudah
// lewat tidak mungkin bergeser ke masa depan. Kalau ada satu saja yang melewati
// waktu sekarang, hampir pasti datanya sudah pernah digeser dan penggeseran
// kedua akan menjadikannya 14 jam.
//
// Pengaman ini ditambahkan setelah kejadian nyata: penanda "sudah dijalankan"
// gagal tertulis, skrip terjalankan dua kali, dan seluruh riwayat maju 14 jam.
$masaDepan = array_filter($rencana, fn ($r) => strtotime($r['jadi']) > time());

if (!empty($masaDepan)) {
    echo "\nDITOLAK: " . count($masaDepan) . " nilai akan jatuh SETELAH waktu sekarang, contohnya\n";
    $c = reset($masaDepan);
    echo "  {$c['tabel']}#{$c['id']}.{$c['kolom']}: {$c['dari']} -> {$c['jadi']}\n";
    echo "Kejadian yang sudah lewat tidak mungkin berada di masa depan. Besar kemungkinan\n";
    echo "data ini SUDAH pernah digeser. Periksa dulu sebelum melanjutkan.\n";
    return;
}

echo "\n=== BARIS LOG ===\n";
$jumlahLog = 0;
foreach ($tabel as $t) {
    $n = DB::table($t['log'])->where('source', 'MOBILE')
        ->where('rowid', '<=', (int) ($status['batas_rowid'][$t['log']] ?? 0))->count();
    $jumlahLog += $n;
    echo "  {$t['log']}: {$n} baris\n";
}

if ($MODE !== 'jalankan') {
    echo "\n(RENCANA SAJA — belum ada yang ditulis. Ulangi dengan \$MODE='jalankan'.)\n";
    return;
}

// --- Eksekusi --------------------------------------------------------------

DB::transaction(function () use ($rencana, $tabel, $status, $GESER_DETIK) {
    foreach ($rencana as $r) {
        DB::table($r['tabel'])->where($r['pk'], $r['id'])->update([$r['kolom'] => $r['jadi']]);
    }

    foreach ($tabel as $t) {
        DB::table($t['log'])
            ->where('source', 'MOBILE')
            ->where('rowid', '<=', (int) ($status['batas_rowid'][$t['log']] ?? 0))
            ->update(['datelog' => DB::raw("DATE_ADD(datelog, INTERVAL {$GESER_DETIK} SECOND)")]);
    }
});

$status['sudah_dijalankan'] = true;
$status['dijalankan_pada'] = date('Y-m-d H:i:s');
$status['kolom_digeser'] = count($rencana);
file_put_contents($berkasBatas, json_encode($status, JSON_PRETTY_PRINT));

echo "\nSELESAI. " . count($rencana) . " kolom dokumen dan {$jumlahLog} baris log dimajukan 7 jam.\n";
echo "JANGAN dijalankan ulang. Penanda selesai tersimpan di {$berkasBatas}.\n";
