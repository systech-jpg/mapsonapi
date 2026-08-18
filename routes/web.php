<?php

use App\Http\Controllers\AuthController;
use App\Models\DolibarrUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');

    if (function_exists('opcache_reset')) {
        opcache_reset();
    }

    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(function ($route) {
        return $route->uri();
    });
    return response()->json([
        'cache_cleared' => true,
        'opcache_reset' => function_exists('opcache_reset'),
        'routes' => $routes
    ]);
});

/*
| Manifest disajikan lewat route, bukan sebagai file statis di public/, karena
| nginx tidak mengenal ekstensi .webmanifest dan mengirimkannya sebagai
| application/octet-stream. Lewat route, Content-Type-nya benar di server mana
| pun tanpa perlu mengubah konfigurasi nginx di setiap environment.
*/
Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name' => 'Mapson Field Service',
        'short_name' => 'Mapson',
        'description' => 'Stocktake, tindakan, sales order, forecast, dan SPH dalam satu aplikasi.',
        'start_url' => '/?source=pwa',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#F8F3F6',
        'theme_color' => '#BC9E68',
        'lang' => 'id',
        'icons' => [
            ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('api.auth')->group(function () {
    /*
    | Daftar menu sengaja statis. Endpoint /api/menus memang memfilter per role,
    | tapi isinya menargetkan Android (route-nya berupa nama Activity seperti
    | "HomeActivity", ikonnya URL gambar), sehingga tidak bisa dipetakan ke route
    | web di bawah. Lihat catatan di README bila nanti dibuat sumber menu khusus web.
    */
    Route::get('/', function () {
        $menu = [
            ['label' => 'Stocktake',   'route' => 'stocktake',   'icon' => 'bi-list-ul'],
            ['label' => 'Tindakan',    'route' => 'tindakan',    'icon' => 'bi-file-earmark-plus-fill'],
            ['label' => 'Sales Order', 'route' => 'sales-order', 'icon' => 'bi-file-earmark-text-fill'],
            ['label' => 'Forecast',    'route' => 'forecast',    'icon' => 'bi-graph-up-arrow'],
            ['label' => 'SPH',         'route' => 'sph',         'icon' => 'bi-file-earmark-ruled-fill'],
        ];

        return view('home', compact('menu'));
    })->name('home');

    Route::get('/pesan', fn () => view('pesan'))->name('pesan');

    /*
    | Penyimpanan push subscription.
    |
    | Dokumen merancang route ini sebagai perantara ke endpoint API ber-auth:sanctum,
    | karena mengasumsikan frontend dan API adalah dua aplikasi terpisah. Di sini
    | keduanya satu aplikasi dan tidak ada Sanctum, jadi datanya disimpan langsung
    | dan ditautkan ke DolibarrUser — tanpa menyentuh routes/api.php sama sekali.
    */
    Route::post('/push/subscribe', function (Request $request) {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $user = DolibarrUser::find(session('api_user.rowid'));

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.'], 403);
        }

        $user->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aesgcm'
        );

        return response()->json(['success' => true]);
    })->name('push.subscribe');
    Route::get('/stocktake', fn () => view('stocktake.index'))->name('stocktake');
    Route::get('/tindakan', fn () => view('tindakan.index'))->name('tindakan');
    Route::get('/tindakan/buat', fn () => view('tindakan.form', ['id' => null]))->name('tindakan.buat');

    Route::get('/tindakan/{id}', fn (int $id) => view('tindakan.detail', ['id' => $id]))
        ->whereNumber('id')
        ->name('tindakan.detail');

    Route::get('/tindakan/{id}/ubah', fn (int $id) => view('tindakan.form', ['id' => $id]))
        ->whereNumber('id')
        ->name('tindakan.ubah');

    /*
    | Pratinjau laporan pemakaian sebelum divalidasi. Halaman terpisah, bukan
    | dialog, supaya alamatnya bisa dibuka lagi setelah halaman ditutup --
    | validasi sering ditunda sampai barang selesai dihitung di ruang operasi.
    */
    Route::get('/tindakan/{id}/pratinjau', fn (int $id) => view('tindakan.pratinjau', ['id' => $id]))
        ->whereNumber('id')
        ->name('tindakan.pratinjau');

    /*
    | Surat jalan PDF.
    |
    | Berkasnya hanya ada di endpoint API yang menuntut header Authorization,
    | sementara browser tidak pernah memegang api_key (disimpan di session
    | server). Jadi PDF-nya diambil server ke server lalu diteruskan apa adanya.
    | Kegagalan dikembalikan sebagai pesan di halaman detail, bukan file PDF
    | berisi JSON error yang tidak bisa dibuka pembaca PDF.
    */
    /*
    | Foto bukti tarik barang. Sama seperti surat jalan: berkasnya di balik
    | endpoint ber-Authorization, jadi diambil server ke server lalu diteruskan.
    | Ditampilkan inline (bukan attachment) supaya bisa langsung dilihat di tab.
    */
    Route::get('/tindakan/{id}/bukti-tarik', function (int $id) {
        $response = \App\Support\Api::client()->get("/tindakan/usage/{$id}/bukti-tarik");

        $tipe = (string) $response->header('Content-Type');

        if ($response->failed() || ! str_starts_with(strtolower($tipe), 'image/')) {
            $pesan = $response->json('message') ?? 'Bukti tarik barang belum ada.';

            return redirect()->route('tindakan.detail', $id)->with('galat', $pesan);
        }

        return response($response->body(), 200, [
            'Content-Type' => $tipe,
            'Content-Disposition' => 'inline; filename="bukti-tarik-' . $id . '"',
        ]);
    })->whereNumber('id')->name('tindakan.bukti-tarik');

    Route::get('/tindakan/{id}/surat-jalan', function (int $id) {
        $response = \App\Support\Api::client()->get("/tindakan/{$id}/surat-jalan");

        $tipe = $response->header('Content-Type');

        if ($response->failed() || ! str_contains(strtolower($tipe), 'pdf')) {
            $pesan = $response->json('message') ?? 'Surat jalan belum bisa diunduh.';

            return redirect()->route('tindakan.detail', $id)->with('galat', $pesan);
        }

        return response($response->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="surat-jalan-' . $id . '.pdf"',
        ]);
    })->whereNumber('id')->name('tindakan.surat-jalan');
    Route::get('/sales-order', fn () => view('sales-order.index'))->name('sales-order');
    Route::get('/forecast', fn () => view('forecast.index'))->name('forecast');

    /*
    | Tabel pengisian qty. Dokumen forecast dibuat lebih dulu di /forecast,
    | karena server men-generate snapshot stok saat header dibuat -- tidak ada
    | endpoint untuk menyiapkan tabel tanpa dokumen.
    */
    Route::get('/forecast/{id}', fn (int $id) => view('forecast.produk', ['id' => $id]))
        ->whereNumber('id')
        ->name('forecast.produk');
    Route::get('/sph', fn () => view('sph.index'))->name('sph');
    Route::get('/scan', fn () => view('scan'))->name('scan');
    Route::get('/profil', fn () => view('profil'))->name('profil');
});
