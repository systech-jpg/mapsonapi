<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Pencatatan riwayat aktivitas Tindakan & Usage Report dari sisi mobile.
 *
 * Halaman Log di ERP (custom/tindakanmedis/info.php dan info_usage.php) membaca
 * dua tabel append-only ini. Selama endpoint mobile hanya meng-UPDATE kolom
 * header (fk_user_valid, date_tarik, dan sejenisnya), aksi dari mobile tidak
 * pernah muncul di sana: kolom header cuma punya satu slot per jenis kejadian
 * dan langsung tertimpa begitu dokumen direvisi lalu maju lagi.
 *
 * Skema tabel didefinisikan di custom/tindakanmedis/sql/ pada repo ERP dan
 * dibuat otomatis oleh Tindakan::addLog() / UsageReport::addLog(). Trait ini
 * sengaja TIDAK ikut membuat tabel: DDL memicu implicit commit di MySQL dan
 * akan menutup transaksi pemanggil lebih awal tanpa disadari.
 */
trait LogsDolibarrActivity
{
    /** Asal aksi. Nilainya harus sama persis dengan konstanta di sisi ERP. */
    private const LOG_SOURCE_MOBILE = 'MOBILE';

    private const TINDAKAN_LOG_TABLE = 'llxjp_tindakan_activity_log';
    private const USAGE_LOG_TABLE    = 'llxjp_usage_report_log';
    private const FORECAST_LOG_TABLE = 'llxjp_forecast_activity_log';

    /**
     * Waktu sekarang dalam konvensi penyimpanan Dolibarr: waktu server, BUKAN UTC.
     *
     * Dulu di sini dipakai Carbon::now('UTC') dengan anggapan Dolibarr menyimpan
     * GMT. Itu keliru, dan buktinya bisa ditelusuri sendiri:
     *
     *   1. Semua kolom datetime ditulis lewat DoliDB::idate($ts, $gm = 'tzserver')
     *      -- lihat core/db/DoliDB.class.php, parameter defaultnya 'tzserver',
     *      bahkan ada TODO yang mengakui idealnya 'gmt' tapi belum diubah.
     *   2. Zona server dipaksa dari konstanta MAIN_SERVER_TZ (Asia/Jakarta)
     *      lewat date_default_timezone_set() di core/class/conf.class.php.
     *   3. Berkas bukti PICKUP_20260818_103029.png dan baris log PICKUP-nya
     *      sama-sama bertanda 10:30:29, padahal berkas dinamai date('Ymd_His')
     *      yang jelas waktu lokal.
     *
     * Karena baris dari mobile memakai UTC, riwayatnya tersimpan 7 jam lebih
     * MUNDUR daripada baris ERP. Halaman history.php mengurutkan dengan
     * ORDER BY datelog ASC, sehingga aksi mobile yang terjadi belakangan
     * tampil di urutan paling awal -- persis keluhan "log tidak sesuai proses".
     *
     * Syaratnya: config/app.php harus memakai zona yang sama dengan
     * MAIN_SERVER_TZ di ERP. Keduanya kini Asia/Jakarta.
     */
    protected function dolibarrNow()
    {
        return Carbon::now();
    }

    /**
     * Mencatat satu kejadian pada Jadwal Tindakan.
     *
     * @param int         $tindakanId
     * @param string      $action       CREATE|UPDATE|VALIDATE|ARRIVAL|... (lihat Tindakan::getLogActionLabel)
     * @param object|null $user         hasil middleware CheckDolibarrApiKey
     * @param string      $note
     * @param int|null    $statusAfter  status dokumen sesudah kejadian
     */
    protected function logTindakanActivity($tindakanId, $action, $user = null, $note = '', $statusAfter = null)
    {
        $this->writeActivityLog(self::TINDAKAN_LOG_TABLE, 'fk_tindakan', $tindakanId, $action, $user, $note, $statusAfter);
    }

    /**
     * Mencatat satu kejadian pada Usage Report.
     *
     * @param int    $usageId
     * @param string $action  CREATE|SAVE_LINES|VALIDATE|TARIK_BARANG|... (lihat UsageReport::getLogActionLabel)
     */
    protected function logUsageActivity($usageId, $action, $user = null, $note = '', $statusAfter = null)
    {
        $this->writeActivityLog(self::USAGE_LOG_TABLE, 'fk_usage_report', $usageId, $action, $user, $note, $statusAfter);
    }

    /**
     * Mencatat satu kejadian pada dokumen Forecast.
     *
     * @param int    $forecastId
     * @param string $action  CREATE|SAVE_LINE|PRODUCT_ADD|... (lihat Forecast::getLogActionLabel)
     */
    protected function logForecastActivity($forecastId, $action, $user = null, $note = '', $statusAfter = null)
    {
        $this->writeActivityLog(self::FORECAST_LOG_TABLE, 'fk_forecast', $forecastId, $action, $user, $note, $statusAfter);
    }

    /**
     * Insert baris riwayat.
     *
     * Kegagalan pencatatan sengaja ditelan dan hanya masuk log aplikasi:
     * riwayat bersifat pelengkap, dan lebih baik kehilangan satu baris daripada
     * menggagalkan validasi yang sudah sah — apalagi endpoint ini dipanggil dari
     * lapangan dengan koneksi seadanya.
     */
    private function writeActivityLog($table, $fkColumn, $fkValue, $action, $user, $note, $statusAfter)
    {
        if (empty($fkValue)) return;

        try {
            DB::table($table)->insert([
                $fkColumn      => (int) $fkValue,
                'action'       => $action,
                'note'         => $note,
                // Kolom PK di llxjp_user adalah rowid, bukan id. Memakai
                // $user->id di sini menghasilkan NULL diam-diam, dan baris log
                // jadi tidak punya pemilik.
                'fk_user'      => ($user && $user->rowid) ? (int) $user->rowid : 0,
                'datelog'      => $this->dolibarrNow(),
                'status_after' => is_null($statusAfter) ? null : (int) $statusAfter,
                'source'       => self::LOG_SOURCE_MOBILE,
            ]);
        } catch (\Exception $e) {
            Log::warning('Gagal mencatat riwayat aktivitas ('.$table.'/'.$action.'): '.$e->getMessage());
        }
    }
}
