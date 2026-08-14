# Panduan Build PWA — Laravel 12 + Blade + Bootstrap 5 + Livewire 3

Dokumen ini ditulis untuk dieksekusi bertahap oleh Claude Code di dalam project Laravel 12 yang sudah ada.

## Instruksi untuk Claude Code

> Kerjakan langkah-langkah di bawah ini **satu per satu, berurutan**. Setelah menyelesaikan setiap STEP, berhenti dan laporkan file apa saja yang dibuat/diubah, lalu tunggu konfirmasi sebelum lanjut ke STEP berikutnya. Jangan mengubah route atau controller yang ada di `routes/api.php` — route tersebut dipakai aplikasi Android dan harus tetap berfungsi. Semua penambahan frontend web masuk ke `routes/web.php` untuk fungsi,class,method gunakan bahasa inggris.

---

## STEP 0 — Persiapan & pemeriksaan awal

1. Buat branch baru: `git checkout -b feat/pwa-frontend`
2. Pastikan versi: `php artisan --version` (harus Laravel 12.x) dan `php -v` (8.2+).
3. Konfirmasi domain sudah HTTPS dengan sertifikat valid. **Service worker tidak akan jalan di HTTP**, kecuali `localhost`.
4. Cek isi `routes/api.php` dan catat endpoint yang sudah ada — jangan diubah.
5. Cek apakah `App\Models\User` sudah memakai trait `Notifiable`.

---

## STEP 1 — Install dependency

```bash
composer require livewire/livewire
composer require laravel-notification-channels/webpush
```

Publish dan jalankan migration untuk tabel push subscription:

```bash
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="NotificationChannels\WebPush\WebPushServiceProvider" --tag="config"
php artisan migrate
```

Generate VAPID key (kunci untuk Web Push):

```bash
php artisan webpush:vapid
```

Perintah ini menambahkan `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` ke `.env`. **Jangan commit `.env`.** Tambahkan juga ke `.env.example` sebagai placeholder kosong.

Tambahkan di `.env`:

```env
VAPID_SUBJECT=mailto:admin@domain-anda.com
```

Bootstrap 5 dan pusher-js dipakai lewat CDN — tidak perlu npm atau Vite sama sekali.

---

## STEP 2 — Trait push di model User

Di `app/Models/User.php`:

```php
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use Notifiable, HasPushSubscriptions;
    // ...
}
```

---

## STEP 3 — Icon aplikasi

Buat folder `public/icons/` dan siapkan file berikut (dari satu logo sumber, format PNG, background solid — bukan transparan, karena iOS tidak menambahkan background sendiri):

| File | Ukuran | Keperluan |
|---|---|---|
| `icon-192.png` | 192x192 | manifest, notifikasi |
| `icon-512.png` | 512x512 | splash screen Android |
| `icon-maskable-512.png` | 512x512 | icon adaptif Android (beri padding ±10% di tiap sisi) |
| `apple-touch-icon.png` | 180x180 | icon home screen iPhone |
| `badge-72.png` | 72x72 | badge monokrom notifikasi Android |

Letakkan `apple-touch-icon.png` di `public/` (root), bukan di dalam `icons/`, agar iOS menemukannya secara default.

---

## STEP 4 — Manifest

Buat `public/manifest.webmanifest`:

```json
{
  "name": "Nama Lengkap Aplikasi",
  "short_name": "NamaApp",
  "description": "Deskripsi singkat aplikasi.",
  "start_url": "/?source=pwa",
  "scope": "/",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#ffffff",
  "theme_color": "#0d6efd",
  "lang": "id",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

Ganti `theme_color` dengan warna brand — nilai ini yang mewarnai status bar saat aplikasi dibuka dari home screen.

---

## STEP 5 — Halaman offline

Buat `public/offline.html` — HTML statis murni, tanpa Blade, tanpa dependency eksternal (halaman ini muncul justru saat tidak ada koneksi, jadi CDN pun tidak akan termuat):

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tidak ada koneksi</title>
  <style>
    body { font-family: system-ui, sans-serif; display: grid; place-items: center;
           min-height: 100vh; margin: 0; padding: 2rem; text-align: center; color: #212529; }
    h1 { font-size: 1.25rem; margin-bottom: .5rem; }
    p  { color: #6c757d; margin-bottom: 1.5rem; }
    button { padding: .625rem 1.25rem; border: 0; border-radius: .5rem;
             background: #0d6efd; color: #fff; font-size: 1rem; }
  </style>
</head>
<body>
  <div>
    <h1>Tidak ada koneksi internet</h1>
    <p>Periksa jaringan Anda, lalu muat ulang halaman.</p>
    <button onclick="location.reload()">Muat ulang</button>
  </div>
</body>
</html>
```

---

## STEP 6 — Service worker

Buat `public/sw.js`. File ini **wajib berada di root** (`/sw.js`) agar scope-nya mencakup seluruh situs.

```js
const CACHE_VERSION = 'v1';
const CACHE_NAME = `app-shell-${CACHE_VERSION}`;

const PRECACHE = [
  '/offline.html',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  if (new URL(req.url).origin !== self.location.origin) return;

  // Halaman: network-first, fallback ke halaman offline
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Aset statis: cache-first
  event.respondWith(caches.match(req).then((cached) => cached || fetch(req)));
});

self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data.json();
  } catch (e) {
    payload = { title: 'Notifikasi', body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(payload.title || 'Notifikasi', {
      body: payload.body || '',
      icon: payload.icon || '/icons/icon-192.png',
      badge: '/badge-72.png',
      tag: payload.tag || undefined,
      data: { url: (payload.data && payload.data.url) || '/' },
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
      for (const win of wins) {
        if (win.url.includes(target) && 'focus' in win) return win.focus();
      }
      return clients.openWindow(target);
    })
  );
});
```

**Penting:** setiap kali deploy dengan perubahan aset, naikkan `CACHE_VERSION` (`v1` → `v2`). Kalau lupa, pengguna akan terus melihat versi lama.

---

## STEP 7 — Layout Blade

Buat `resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- PWA --}}
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#0d6efd">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="NamaApp">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <title>@yield('title', 'NamaApp')</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  @livewireStyles
</head>
<body class="bg-body-tertiary">

  <main class="pb-5 mb-4">
    @yield('content')
  </main>

  {{-- Bottom navigation --}}
  <nav class="bottom-nav border-top bg-body fixed-bottom">
    <div class="d-flex justify-content-around">
      <a href="/" class="nav-item-btn {{ request()->is('/') ? 'active' : '' }}">Beranda</a>
      <a href="/pesanan" class="nav-item-btn {{ request()->is('pesanan*') ? 'active' : '' }}">Pesanan</a>
      <a href="/akun" class="nav-item-btn {{ request()->is('akun*') ? 'active' : '' }}">Akun</a>
    </div>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @livewireScripts

  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
  </script>

  @stack('scripts')
</body>
</html>
```

Buat `public/css/app.css` untuk penyesuaian khas mobile:

```css
body {
  -webkit-tap-highlight-color: transparent;
  overscroll-behavior-y: none;
}

/* Hindari tertutup home indicator iPhone */
.bottom-nav {
  padding-top: .5rem;
  padding-bottom: calc(.5rem + env(safe-area-inset-bottom));
}

.nav-item-btn {
  flex: 1;
  text-align: center;
  font-size: .8125rem;
  text-decoration: none;
  color: #6c757d;
}

.nav-item-btn.active { color: #0d6efd; font-weight: 600; }

/* Cegah zoom otomatis Safari saat fokus ke input */
input, select, textarea { font-size: 16px; }
```

Catatan: cek versi Bootstrap terbaru di jsDelivr sebelum menyalin URL CDN di atas.

---

## STEP 8 — Navigasi terasa seperti aplikasi

Tanpa ini, tiap klik akan memicu full reload dan terlihat jelas seperti website.

Tambahkan `wire:navigate` pada semua link internal:

```blade
<a href="/pesanan" wire:navigate class="nav-item-btn">Pesanan</a>
```

Livewire akan mengambil halaman berikutnya lewat AJAX dan menukar isi `<body>` tanpa reload. Tidak perlu menulis JavaScript sama sekali.

Untuk halaman yang butuh interaktivitas (form, filter, tabel), buat komponen Livewire:

```bash
php artisan make:livewire Pesanan/DaftarPesanan
```

---

## STEP 9 — Endpoint penyimpanan push subscription

Tambahkan di `routes/web.php`:

```php
use Illuminate\Http\Request;

Route::middleware('auth')->group(function () {
    Route::post('/push/subscribe', function (Request $request) {
        $request->validate([
            'endpoint'    => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        $request->user()->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
            $request->input('contentEncoding', 'aesgcm')
        );

        return response()->json(['success' => true]);
    })->name('push.subscribe');

    Route::delete('/push/unsubscribe', function (Request $request) {
        $request->user()->deletePushSubscription($request->input('endpoint'));
        return response()->json(['success' => true]);
    })->name('push.unsubscribe');
});
```

---

## STEP 10 — Tombol aktivasi notifikasi

Buat partial `resources/views/partials/push-toggle.blade.php`:

```blade
<button id="btn-aktifkan-notifikasi" class="btn btn-primary d-none">
  Aktifkan notifikasi
</button>

@push('scripts')
<script>
const VAPID_PUBLIC_KEY = @json(config('webpush.vapid.public_key'));

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
  || window.navigator.standalone === true;

const btn = document.getElementById('btn-aktifkan-notifikasi');

// Di iOS, push hanya tersedia setelah aplikasi dipasang ke home screen.
if ('serviceWorker' in navigator && 'PushManager' in window && (!isIos || isStandalone)) {
  if (Notification.permission !== 'granted') {
    btn.classList.remove('d-none');
  }
}

btn.addEventListener('click', async () => {
  btn.disabled = true;
  try {
    // Wajib dipanggil dari dalam handler klik, bukan saat halaman dimuat.
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      btn.disabled = false;
      return;
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
    });

    const payload = subscription.toJSON();
    payload.contentEncoding = (PushManager.supportedContentEncodings || ['aesgcm'])[0];

    await fetch('{{ route('push.subscribe') }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify(payload),
    });

    btn.classList.add('d-none');
  } catch (e) {
    console.error(e);
    btn.disabled = false;
  }
});
</script>
@endpush
```

---

## STEP 11 — Banner panduan install untuk iPhone

Safari tidak punya event `beforeinstallprompt`, jadi pengguna iPhone harus diarahkan secara manual. Tanpa langkah ini, notifikasi tidak akan pernah aktif di iOS.

Buat `resources/views/partials/ios-install-banner.blade.php`:

```blade
<div id="ios-install-banner" class="alert alert-info d-none m-3">
  <strong>Pasang aplikasi ini</strong>
  <p class="mb-0 small">
    Ketuk tombol Bagikan di bawah layar Safari, lalu pilih
    <strong>Add to Home Screen</strong>. Notifikasi hanya aktif setelah aplikasi dipasang.
  </p>
</div>

@push('scripts')
<script>
(() => {
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
  const sudahDitutup = localStorage.getItem('ios-banner-ditutup') === '1';

  if (isIos && !isStandalone && !sudahDitutup) {
    document.getElementById('ios-install-banner').classList.remove('d-none');
  }
})();
</script>
@endpush
```

Sertakan partial ini di halaman utama, di bawah header.

---

## STEP 12 — Notification class

```bash
php artisan make:notification PesananBaru
```

Isi `app/Notifications/PesananBaru.php`:

```php
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PesananBaru extends Notification
{
    public function __construct(public $pesanan) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Pesanan baru masuk')
            ->body("Pesanan #{$this->pesanan->kode} menunggu konfirmasi.")
            ->icon('/icons/icon-192.png')
            ->tag("pesanan-{$this->pesanan->id}")
            ->data(['url' => "/pesanan/{$this->pesanan->id}"]);
    }
}
```

Cara memicu: `$user->notify(new PesananBaru($pesanan));`

Uji lewat tinker:

```bash
php artisan tinker
>>> App\Models\User::find(1)->notify(new App\Notifications\PesananBaru(App\Models\Pesanan::first()));
```

---

## STEP 13 — Realtime dengan Pusher Channels

Aktifkan broadcasting (Laravel 11+ tidak menyertakannya secara default):

```bash
php artisan install:broadcasting
```

Pastikan `.env` berisi kredensial Pusher yang sudah dipakai aplikasi Android:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
```

Tambahkan di layout (dalam `@push('scripts')` pada halaman yang butuh realtime saja):

```blade
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script>
const pusher = new Pusher(@json(config('broadcasting.connections.pusher.key')), {
  cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
  authEndpoint: '/broadcasting/auth',
  auth: {
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
  },
});

const channel = pusher.subscribe('private-pesanan.{{ auth()->id() }}');

channel.bind('pesanan.baru', (data) => {
  Livewire.dispatch('pesanan-baru', { pesanan: data });
});
</script>
```

Di komponen Livewire, tangkap event tersebut:

```php
#[On('pesanan-baru')]
public function refreshDaftar() { /* ... */ }
```

Cek versi terbaru pusher-js sebelum menyalin URL CDN di atas.

---

## STEP 14 — Konfigurasi server

Pastikan `sw.js` tidak di-cache lama oleh browser. Tambahkan di konfigurasi Nginx:

```nginx
location = /sw.js {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    expires off;
}

location = /manifest.webmanifest {
    add_header Cache-Control "no-cache";
    types { application/manifest+json webmanifest; }
}
```

Reload: `sudo nginx -t && sudo systemctl reload nginx`

---

## STEP 15 — Checklist pengujian

Uji berurutan, jangan dilompati:

1. **Desktop Chrome** — buka DevTools → Application → Manifest: tidak ada error. Service Worker: status *activated*.
2. **Lighthouse** — jalankan audit kategori PWA, targetkan semua item hijau.
3. **Mode offline** — DevTools → Network → Offline, lalu reload: harus muncul `offline.html`.
4. **Android Chrome** — muncul prompt *Install app*, buka dari home screen, cek status bar berwarna `theme_color`.
5. **iPhone Safari (perangkat asli, bukan simulator)** —
   - Buka situs, banner panduan install muncul.
   - Share → Add to Home Screen, lalu buka dari home screen (bukan dari Safari).
   - Tombol "Aktifkan notifikasi" baru muncul di sini. Ketuk, izinkan.
   - Kirim notifikasi uji lewat tinker, pastikan muncul di lock screen.
   - Ketuk notifikasi, pastikan membuka halaman yang benar.
6. **Regresi Android app** — panggil beberapa endpoint di `routes/api.php` untuk memastikan tidak ada yang rusak.

Untuk debug di iPhone: sambungkan ke Mac, aktifkan Settings → Safari → Advanced → Web Inspector, lalu buka Safari di Mac → menu Develop.

---

## Jebakan yang paling sering terjadi

| Gejala | Penyebab |
|---|---|
| Dialog izin notifikasi tidak muncul di Safari | `Notification.requestPermission()` dipanggil di luar handler klik |
| Push tidak jalan di iPhone padahal jalan di Android | Situs dibuka lewat tab Safari, belum di-*Add to Home Screen* |
| Perubahan tidak muncul setelah deploy | `CACHE_VERSION` di `sw.js` tidak dinaikkan |
| Service worker tidak teregistrasi | Situs diakses via HTTP, atau `sw.js` tidak berada di root |
| Icon home screen iPhone tampil berlatar hitam | PNG icon punya background transparan |
| Halaman "melompat" saat fokus ke input | `font-size` input di bawah 16px |
