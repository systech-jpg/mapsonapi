<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan login ERP lewat kode QR.
 *
 * Sisi ERP (custom/qrlogin di project mapsonerp) membuat baris di
 * llxjp_qr_login lalu menggambar QR berisi "MAPSONLOGIN:<token>". Aplikasi ini
 * yang menyetujuinya, memakai identitas petugas yang SUDAH masuk di PWA — itu
 * sebabnya endpoint di bawah berada di dalam middleware dolibarr.auth.
 *
 * Kedua aplikasi berbagi satu database (mapsonerpdb), jadi tidak ada server
 * perantara: yang ditulis di sini langsung terbaca oleh halaman login ERP.
 *
 * Kolom datetime ditulis dengan Carbon::now() — waktu SERVER, bukan UTC —
 * mengikuti cara Dolibarr menyimpan waktu. Sisi ERP memakai
 * $db->idate(dol_now()) yang juga menghasilkan waktu server, jadi keduanya
 * membandingkan angka yang setara. (Lihat CLAUDE.md bagian 4 nomor 7.)
 */
class QrLoginController extends Controller
{
    /** Status baris di llxjp_qr_login. */
    private const MENUNGGU = 0;
    private const DISETUJUI = 1;
    private const TERPAKAI = 2;
    private const DITOLAK = 3;

    private const PESAN_BELUM_DIPASANG =
        'Login lewat kode QR belum diaktifkan di server ini. Pasang modul qrlogin di ERP lebih dulu.';

    /**
     * Keterangan satu permintaan login, untuk layar konfirmasi di PWA.
     */
    public function info(Request $request, string $token)
    {
        if (! $this->tabelSiap()) {
            return $this->errorResponse(self::PESAN_BELUM_DIPASANG, 503);
        }

        $baris = $this->cari($token);

        if (! $baris) {
            return $this->errorResponse('Kode QR tidak dikenal.', 404);
        }

        $kedaluwarsa = Carbon::parse($baris->date_expiry);

        if ($kedaluwarsa->isPast()) {
            return $this->errorResponse('Kode QR sudah kedaluwarsa. Muat ulang kodenya di layar komputer.', 410);
        }

        if ((int) $baris->status !== self::MENUNGGU) {
            return $this->errorResponse($this->kalimatStatus((int) $baris->status), 409);
        }

        return $this->successResponse([
            'token' => $baris->token,
            'ip' => $baris->ip,
            'peramban' => $this->namaPeramban($baris->user_agent),
            // (int), bukan angka mentah: diffInSeconds mengembalikan pecahan
            // (118.919757) dan layar PWA menampilkannya apa adanya.
            'sisa_detik' => max(0, (int) Carbon::now()->diffInSeconds($kedaluwarsa, false)),
        ], 'Permintaan login ditemukan.');
    }

    /**
     * Menyetujui: komputer di seberang sana boleh masuk sebagai saya.
     */
    public function approve(Request $request, string $token)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (! $user) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        if (! $this->tabelSiap()) {
            return $this->errorResponse(self::PESAN_BELUM_DIPASANG, 503);
        }

        $baris = $this->cari($token);

        if (! $baris) {
            return $this->errorResponse('Kode QR tidak dikenal.', 404);
        }

        if (Carbon::parse($baris->date_expiry)->isPast()) {
            return $this->errorResponse('Kode QR sudah kedaluwarsa. Muat ulang kodenya di layar komputer.', 410);
        }

        /*
         * "where status = MENUNGGU" pada UPDATE itu sendiri yang menjadi kunci.
         * Dua ketukan beruntun — atau dua ponsel yang memindai QR yang sama —
         * hanya menyisakan satu pemenang; yang kedua mengubah 0 baris. Memeriksa
         * status lebih dulu lalu meng-update terpisah membuat keduanya lolos.
         */
        $terubah = DB::table('llxjp_qr_login')
            ->where('rowid', $baris->rowid)
            ->where('status', self::MENUNGGU)
            ->update([
                'status' => self::DISETUJUI,
                'fk_user' => $user->rowid,
                'date_approval' => Carbon::now(),
            ]);

        if ($terubah < 1) {
            return $this->errorResponse('Kode QR ini sudah dipakai. Muat ulang kodenya di layar komputer.', 409);
        }

        $nama = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: $user->login;

        return $this->successResponse([
            'login' => $user->login,
            'nama' => $nama,
        ], 'Login di komputer disetujui.');
    }

    /**
     * Menolak. Dipakai saat petugas merasa tidak sedang membuka ERP di mana pun
     * — pertanda kodenya milik orang lain.
     */
    public function reject(Request $request, string $token)
    {
        if (! $this->tabelSiap()) {
            return $this->errorResponse(self::PESAN_BELUM_DIPASANG, 503);
        }

        $baris = $this->cari($token);

        if (! $baris) {
            return $this->errorResponse('Kode QR tidak dikenal.', 404);
        }

        DB::table('llxjp_qr_login')
            ->where('rowid', $baris->rowid)
            ->where('status', self::MENUNGGU)
            ->update(['status' => self::DITOLAK]);

        return $this->successResponse(null, 'Permintaan login ditolak.');
    }

    /**
     * Tabel llxjp_qr_login ada atau tidak.
     *
     * Tabel itu dibuat oleh modul qrlogin DI SISI ERP, bukan oleh migrasi
     * aplikasi ini. Jadi server yang menarik kode PWA tanpa memasang modul
     * ERP-nya tetap punya ketiga endpoint ini — dan tanpa penjagaan di bawah,
     * memanggilnya menghasilkan 500 "Base table or view not found", pesan yang
     * tidak menunjukkan penyebab sebenarnya.
     *
     * Dijalankan hanya di ketiga endpoint QR, jadi satu query tambahan ini
     * tidak menyentuh jalur mana pun yang dipakai sehari-hari.
     */
    private function tabelSiap(): bool
    {
        return Schema::hasTable('llxjp_qr_login');
    }

    /**
     * Mencari baris berdasarkan token.
     *
     * Bentuk token diperiksa lebih dulu: tanpa itu, alamat sembarangan ikut
     * menjadi query ke database berkali-kali.
     */
    private function cari(string $token)
    {
        if (! preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }

        return DB::table('llxjp_qr_login')->where('token', $token)->first();
    }

    private function kalimatStatus(int $status): string
    {
        return match ($status) {
            self::DISETUJUI => 'Kode QR ini sudah disetujui sebelumnya.',
            self::TERPAKAI => 'Kode QR ini sudah dipakai masuk.',
            self::DITOLAK => 'Kode QR ini sudah ditolak.',
            default => 'Kode QR tidak bisa dipakai.',
        };
    }

    /**
     * Nama peramban yang bisa dibaca orang.
     *
     * Bukan penguraian user agent yang lengkap — string aslinya panjang dan
     * penuh istilah teknis, sedangkan yang dibutuhkan petugas cuma pengenal
     * kasar untuk mencocokkan dengan komputer di depannya.
     */
    private function namaPeramban(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        if ($ua === '') {
            return 'Peramban tidak dikenal';
        }

        // Urutannya penting: Edge dan Opera menyebut "Chrome" di user agent-nya,
        // dan Chrome menyebut "Safari". Yang paling khusus harus diperiksa dulu.
        $peta = [
            'Edg/' => 'Microsoft Edge',
            'OPR/' => 'Opera',
            'Firefox/' => 'Mozilla Firefox',
            'Chrome/' => 'Google Chrome',
            'Safari/' => 'Safari',
        ];

        $nama = 'Peramban lain';

        foreach ($peta as $penanda => $label) {
            if (str_contains($ua, $penanda)) {
                $nama = $label;
                break;
            }
        }

        $sistem = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $sistem ? $nama . ' di ' . $sistem : $nama;
    }
}
