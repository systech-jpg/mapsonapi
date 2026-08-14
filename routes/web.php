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
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
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
    Route::get('/sales-order', fn () => view('sales-order.index'))->name('sales-order');
    Route::get('/forecast', fn () => view('forecast.index'))->name('forecast');
    Route::get('/sph', fn () => view('sph.index'))->name('sph');
    Route::get('/scan', fn () => view('scan'))->name('scan');
    Route::get('/profil', fn () => view('profil'))->name('profil');
});
