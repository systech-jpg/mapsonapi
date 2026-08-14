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
