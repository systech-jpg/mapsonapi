# Panduan PWA — Bagian 2: Autentikasi via API & Desain UI

Dokumen ini melengkapi `PWA-PLAN.md`. Bagian A **menggantikan** STEP 9 dan STEP 10 di dokumen pertama. Bagian B adalah spesifikasi tampilan.

## Instruksi untuk Claude Code

> Kerjakan berurutan, berhenti dan laporkan setiap selesai satu STEP. Jangan mengubah endpoint yang ada di `routes/api.php` — endpoint itu dipakai aplikasi Android dan harus tetap berfungsi identik.

---

# BAGIAN A — Semua data lewat API

## Jawaban singkat atas pertanyaan arsitektur

Semua request bisa lewat API. Tapi perlu dipisahkan dua hal yang sering tertukar:

- **Di sisi API**, autentikasi tetap dibutuhkan. Kalau endpoint `/api/login` yang sekarang dipakai Android sudah mengeluarkan token (Sanctum, JWT, atau token buatan sendiri), biarkan apa adanya. Tidak ada yang perlu diubah.
- **Di sisi web/PWA**, yang tidak dibutuhkan adalah *guard session* Laravel dan Sanctum SPA mode. Frontend cukup mengirim `Authorization: Bearer <token>` ke API yang sama persis dengan yang dipakai Android.

**Keputusan penting: token disimpan di session server, bukan di `localStorage`.**

Alasannya: token di `localStorage` bisa dicuri lewat satu celah XSS saja, dan token API Anda biasanya berumur panjang. Dengan menyimpannya di session PHP, token tidak pernah menyentuh JavaScript, dan Livewire tetap bisa dipakai sehingga Anda tidak perlu menulis JavaScript untuk mengambil data.

Alur yang dipakai:

```
Browser → (form login, session cookie) → Laravel Web
                                              ↓ Bearer token dari session
                                         API Laravel  →  Database
```

## Catatan bila frontend berada di project yang sama dengan API

Kalau Blade dan `routes/api.php` ada di satu aplikasi Laravel, jangan lakukan HTTP request ke domain sendiri — itu menambah satu round trip penuh (TCP + TLS + boot Laravel kedua kali) untuk setiap hit. Cukup panggil Service/Action class yang sama dengan yang dipakai controller API.

Yang tetap berlaku dalam kasus itu: struktur di bawah tetap dipakai, hanya isi `App\Support\Api` yang diganti menjadi pemanggilan service internal. Sisanya tidak berubah.

Instruksi di bawah ditulis untuk skenario frontend memanggil API lewat HTTP.

---

## STEP A1 — Konfigurasi base URL API

Di `.env`:

```env
API_BASE_URL=https://domain-api-anda.com/api
```

Di `config/services.php`:

```php
'backend' => [
    'url' => env('API_BASE_URL'),
],
```

---

## STEP A2 — Helper API client

Buat `app/Support/Api.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Api
{
    public static function client(): PendingRequest
    {
        $request = Http::baseUrl(config('services.backend.url'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200);

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
        $response = self::client()->get($path, $query);

        if ($response->status() === 401) {
            session()->flush();
            abort(redirect()->route('login')->with('pesan', 'Sesi berakhir, silakan masuk lagi.'));
        }

        $response->throw();

        return $response->json() ?? [];
    }

    public static function post(string $path, array $data = []): array
    {
        $response = self::client()->post($path, $data);

        if ($response->status() === 401) {
            session()->flush();
            abort(redirect()->route('login'));
        }

        $response->throw();

        return $response->json() ?? [];
    }
}
```

---

## STEP A3 — Middleware pengganti `auth`

Buat `app/Http/Middleware/EnsureApiToken.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiToken
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('api_token')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
```

Daftarkan aliasnya di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'api.auth' => \App\Http\Middleware\EnsureApiToken::class,
    ]);
})
```

Model `User` lokal, guard `web`, dan tabel `users` tidak dipakai sama sekali di frontend ini.

---

## STEP A4 — Controller login

Buat `app/Http/Controllers/AuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Support\Api;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $kredensial = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $response = Api::client()->post('/login', $kredensial);

        if ($response->failed()) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $data = $response->json();

        $request->session()->regenerate();
        $request->session()->put([
            'api_token' => $data['token'],
            'api_user'  => $data['user'],
        ]);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Api::client()->post('/logout');

        $request->session()->flush();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

**Sesuaikan nama field** (`token`, `user`) dengan bentuk response `/api/login` yang sebenarnya. Periksa dulu dengan:

```bash
curl -X POST https://domain-api-anda.com/api/login \
  -H "Accept: application/json" \
  -d "username=admin.mapson&password=..."
```

---

## STEP A5 — Route web

Di `routes/web.php`:

```php
use App\Http\Controllers\AuthController;
use App\Support\Api;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('api.auth')->group(function () {
    Route::get('/', fn () => view('home'))->name('home');
    Route::get('/stocktake', fn () => view('stocktake.index'))->name('stocktake');
    Route::get('/tindakan', fn () => view('tindakan.index'))->name('tindakan');
    Route::get('/sales-order', fn () => view('sales-order.index'))->name('sales-order');
    Route::get('/forecast', fn () => view('forecast.index'))->name('forecast');
    Route::get('/sph', fn () => view('sph.index'))->name('sph');
    Route::get('/scan', fn () => view('scan'))->name('scan');
    Route::get('/profil', fn () => view('profil'))->name('profil');
});
```

---

## STEP A6 — Mengambil data di komponen Livewire

Contoh `app/Livewire/SalesOrder/Daftar.php`:

```php
<?php

namespace App\Livewire\SalesOrder;

use App\Support\Api;
use Livewire\Component;

class Daftar extends Component
{
    public string $cari = '';
    public array $items = [];
    public bool $memuat = true;

    public function mount(): void
    {
        $this->ambilData();
    }

    public function updatedCari(): void
    {
        $this->ambilData();
    }

    public function ambilData(): void
    {
        $this->memuat = true;
        $this->items = Api::get('/sales-orders', ['q' => $this->cari])['data'] ?? [];
        $this->memuat = false;
    }

    public function render()
    {
        return view('livewire.sales-order.daftar');
    }
}
```

Token diambil otomatis dari session oleh `Api::client()`. Tidak ada satu baris JavaScript pun di sini.

---

## STEP A7 — Push subscription diteruskan ke API

Ini **menggantikan STEP 9** di dokumen pertama. Karena tidak ada model `User` lokal, route web hanya bertindak sebagai perantara:

```php
Route::middleware('api.auth')->group(function () {
    Route::post('/push/subscribe', function (Request $request) {
        Api::post('/push/subscriptions', $request->only(['endpoint', 'keys', 'contentEncoding']));
        return response()->json(['success' => true]);
    })->name('push.subscribe');
});
```

Di sisi **API**, tambahkan endpoint yang menyimpannya (di sinilah paket `laravel-notification-channels/webpush` dan trait `HasPushSubscriptions` dipasang, sesuai STEP 1–2 dan STEP 12 dokumen pertama):

```php
Route::middleware('auth:sanctum')->post('/push/subscriptions', function (Request $request) {
    $request->user()->updatePushSubscription(
        $request->input('endpoint'),
        $request->input('keys.p256dh'),
        $request->input('keys.auth'),
        $request->input('contentEncoding', 'aesgcm')
    );

    return response()->json(['success' => true]);
});
```

JavaScript di STEP 10 dokumen pertama tidak berubah — ia tetap POST ke `/push/subscribe` dengan CSRF token, bukan bearer token.

---

# BAGIAN B — Desain UI

Meniru tampilan aplikasi Android yang sudah jadi.

## STEP B1 — Design token

Buat `public/css/app.css` (menimpa versi di STEP 7 dokumen pertama):

```css
:root {
  --gold-400: #CBAF87;
  --gold-500: #BC9E68;
  --gold-050: #F7EFDD;
  --bg:       #F8F3F6;
  --surface:  #FFFFFF;
  --ink:      #111111;
  --muted:    #8E8E93;
  --r-card:   22px;
  --r-header: 32px;
}

body {
  background: var(--bg);
  -webkit-tap-highlight-color: transparent;
  overscroll-behavior-y: none;
  padding-bottom: 6.5rem;
}

input, select, textarea { font-size: 16px; }

/* ---------- Header ---------- */
.app-header {
  background: linear-gradient(160deg, var(--gold-400) 0%, var(--gold-500) 100%);
  border-bottom-left-radius:  var(--r-header);
  border-bottom-right-radius: var(--r-header);
  padding: calc(1.75rem + env(safe-area-inset-top)) 1.5rem 4.5rem;
  color: #fff;
}

.app-header h1 { font-size: 2rem;   font-weight: 800; line-height: 1.05; margin: 0; }
.app-header p  { font-size: 1.5rem; font-weight: 800; margin: 0; opacity: .95; }

.header-btn {
  width: 48px; height: 48px;
  display: grid; place-items: center;
  border-radius: 999px;
  background: rgba(255, 255, 255, .28);
  color: #fff; font-size: 1.25rem;
  text-decoration: none;
}

/* ---------- Grid menu ---------- */
.menu-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  padding: 0 1rem;
  margin-top: -3rem;
}

.menu-card {
  background: var(--surface);
  border-radius: var(--r-card);
  aspect-ratio: 1 / .92;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: .85rem;
  color: var(--ink); text-decoration: none;
  box-shadow: 0 6px 18px rgba(17, 17, 17, .07);
  transition: transform .12s ease;
}

.menu-card i    { font-size: 3rem; line-height: 1; }
.menu-card span { font-weight: 700; font-size: 1.05rem; }
.menu-card:active { transform: scale(.97); }

/* ---------- Tab bar ---------- */
.tabbar {
  position: fixed; left: 0; right: 0; bottom: 0; z-index: 20;
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  background: var(--surface);
  border-top-left-radius: 24px; border-top-right-radius: 24px;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, .06);
  padding: .6rem 0 calc(.6rem + env(safe-area-inset-bottom));
}

.tabbar a {
  display: flex; flex-direction: column; align-items: center; gap: .2rem;
  font-size: .8rem; color: var(--muted); text-decoration: none;
}

.tabbar a i { font-size: 1.25rem; padding: .3rem 1.1rem; border-radius: 999px; }
.tabbar a.active      { color: var(--gold-500); font-weight: 700; }
.tabbar a.active i    { background: var(--gold-050); }
.tabbar .slot-tengah  { visibility: hidden; }

.fab-scan {
  position: fixed; left: 50%; z-index: 21;
  bottom: calc(2.9rem + env(safe-area-inset-bottom));
  transform: translateX(-50%);
  width: 66px; height: 66px; border-radius: 999px;
  background: var(--gold-500); border: 4px solid var(--surface);
  color: #fff; font-size: 1.5rem;
  display: grid; place-items: center; text-decoration: none;
  box-shadow: 0 6px 16px rgba(188, 158, 104, .45);
}

.fab-label {
  position: fixed; left: 50%; transform: translateX(-50%); z-index: 21;
  bottom: calc(.75rem + env(safe-area-inset-bottom));
  font-size: .8rem; color: var(--muted);
}

@media (prefers-reduced-motion: reduce) {
  .menu-card { transition: none; }
}
```

## STEP B2 — Tambahkan Bootstrap Icons

Di `<head>` layout, setelah CSS Bootstrap:

```html
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
```

Semua ikon di desain ini memakai font icon — tidak perlu file gambar.

## STEP B3 — Tab bar di layout

Ganti blok `<nav>` di `layouts/app.blade.php`:

```blade
<nav class="tabbar">
  <a href="{{ route('home') }}" wire:navigate class="{{ request()->routeIs('home') ? 'active' : '' }}">
    <i class="bi bi-house-door-fill"></i>
    <span>Home</span>
  </a>

  <span class="slot-tengah"></span>

  <a href="{{ route('profil') }}" wire:navigate class="{{ request()->routeIs('profil') ? 'active' : '' }}">
    <i class="bi bi-person-fill"></i>
    <span>Profile</span>
  </a>
</nav>

<a href="{{ route('scan') }}" class="fab-scan" aria-label="Pindai kode">
  <i class="bi bi-camera-fill"></i>
</a>
<span class="fab-label">Scan</span>
```

Atur juga `theme_color` di `manifest.webmanifest` dan meta `theme-color` di layout menjadi `#BC9E68` agar status bar ikut berwarna emas saat dibuka dari home screen.

## STEP B4 — Halaman home

Buat `resources/views/home.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
  <header class="app-header">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h1>Selamat Datang</h1>
        <p>{{ session('api_user.username') }}</p>
      </div>
      <a href="{{ route('pesan') }}" class="header-btn" aria-label="Pesan">
        <i class="bi bi-chat-fill"></i>
      </a>
    </div>
  </header>

  <div class="menu-grid">
    @foreach ($menu as $item)
      <a href="{{ route($item['route']) }}" wire:navigate class="menu-card">
        <i class="bi {{ $item['icon'] }}"></i>
        <span>{{ $item['label'] }}</span>
      </a>
    @endforeach
  </div>

  @include('partials.ios-install-banner')
@endsection
```

Sediakan `$menu` dari controller. Idealnya daftar ini datang dari API agar hak akses per role konsisten dengan aplikasi Android:

```php
Route::get('/', function () {
    $menu = [
        ['label' => 'Stocktake',   'route' => 'stocktake',   'icon' => 'bi-list-ul'],
        ['label' => 'Tindakan',    'route' => 'tindakan',    'icon' => 'bi-file-earmark-plus-fill'],
        ['label' => 'Sales Order', 'route' => 'sales-order', 'icon' => 'bi-file-earmark-text-fill'],
        ['label' => 'Forecast',    'route' => 'forecast',    'icon' => 'bi-file-earmark-plus-fill'],
        ['label' => 'SPH',         'route' => 'sph',         'icon' => 'bi-file-earmark-plus-fill'],
    ];

    return view('home', compact('menu'));
})->name('home');
```

## STEP B5 — Halaman login

Buat `resources/views/auth/login.blade.php` dengan header emas yang sama (tanpa tab bar), form username dan password Bootstrap, serta tampilkan `$errors->first('username')` di atas form.

---

# Checklist penerimaan

1. Login dengan akun yang sama seperti di aplikasi Android berhasil, dan `session('api_token')` terisi.
2. Buka DevTools → Application → Local Storage: **tidak ada token di sana**.
3. Halaman Sales Order menampilkan data yang identik dengan aplikasi Android.
4. Hapus `api_token` dari session lalu muat ulang: otomatis dilempar ke halaman login.
5. Tampilan home di iPhone: header emas menyentuh notch, kartu tidak terpotong, FAB Scan tidak tertutup home indicator.
6. Endpoint `routes/api.php` yang dipakai Android diuji ulang — semua masih normal.
