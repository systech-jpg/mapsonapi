<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client untuk memanggil endpoint di routes/api.php dari sisi web/PWA.
 *
 * Token Dolibarr (api_key) diambil otomatis dari session server, sehingga
 * tidak pernah menyentuh JavaScript. Endpoint API tidak diubah sama sekali —
 * middleware dolibarr.auth membacanya dari header Authorization: Bearer.
 */
class Api
{
    public static function client(): PendingRequest
    {
        $baseUrl = config('services.backend.url');

        // Tanpa penjagaan ini, API_BASE_URL yang belum diisi muncul sebagai
        // TypeError di dalam Http client — pesan yang tidak menunjukkan
        // penyebab sebenarnya dan menyulitkan penelusuran di server.
        if (blank($baseUrl)) {
            throw new RuntimeException(
                'API_BASE_URL belum diisi di .env. Isi dengan URL API aplikasi ini '
                . '(contoh: https://domain-anda.com/api), lalu jalankan php artisan config:cache.'
            );
        }

        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->timeout(20)
            // Hanya ulangi saat koneksi gagal. Status 4xx/5xx sengaja tidak
            // di-retry supaya POST tidak terkirim dua kali dan 401 langsung
            // ditangani di bawah.
            ->retry(2, 200, fn ($e) => $e instanceof ConnectionException, throw: false);

        if ($token = session('api_token')) {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * GET yang otomatis melempar ke halaman login saat token kedaluwarsa.
     */
    public static function get(string $path, array $query = []): array
    {
        return self::handle(self::client()->get($path, $query));
    }

    public static function post(string $path, array $data = []): array
    {
        return self::handle(self::client()->post($path, $data));
    }

    /**
     * POST multipart untuk endpoint yang menerima berkas (mis. bukti foto
     * tarik barang). Isinya dikirim sebagai string, bukan path, karena berkas
     * unggahan Livewire masih berada di direktori sementara yang bisa
     * dibersihkan kapan saja.
     */
    public static function unggah(string $path, string $field, string $isi, string $namaBerkas, array $data = []): array
    {
        return self::handle(
            self::client()->attach($field, $isi, $namaBerkas)->post($path, $data)
        );
    }

    /**
     * Meneruskan gambar dari endpoint API ke browser.
     *
     * Berkasnya ada di folder dokumen Dolibarr di balik endpoint ber-Authorization,
     * sedangkan browser tidak pernah memegang api_key. Jadi diambil server ke
     * server lalu diteruskan apa adanya. Kegagalan dikembalikan sebagai pesan di
     * halaman detail, bukan berkas gambar rusak berisi JSON.
     */
    public static function teruskanGambar(string $path, int $tindakanId, string $namaBerkas)
    {
        $response = self::client()->get($path);

        $tipe = (string) $response->header('Content-Type');

        if ($response->failed() || ! str_starts_with(strtolower($tipe), 'image/')) {
            $pesan = $response->json('message') ?? 'Bukti foto belum ada.';

            return redirect()->route('tindakan.detail', $tindakanId)->with('galat', $pesan);
        }

        return response($response->body(), 200, [
            'Content-Type' => $tipe,
            'Content-Disposition' => 'inline; filename="' . $namaBerkas . '-' . $tindakanId . '"',
        ]);
    }

    /**
     * Dipakai endpoint yang memang menuntut PUT (mis. ubah jadwal tindakan);
     * mengirimnya sebagai POST akan dijawab 405 oleh router.
     */
    public static function put(string $path, array $data = []): array
    {
        return self::handle(self::client()->put($path, $data));
    }

    /**
     * Buang session lalu lempar ke login bila API menolak token.
     */
    protected static function handle(Response $response): array
    {
        if ($response->status() === 401) {
            session()->flush();

            abort(redirect()->route('login')->with('pesan', 'Sesi berakhir, silakan masuk lagi.'));
        }

        $response->throw();

        return $response->json() ?? [];
    }
}
