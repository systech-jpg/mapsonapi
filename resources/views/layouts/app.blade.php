<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- PWA --}}
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#BC9E68">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Mapson">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <title>@yield('title', 'Mapson')</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
  {{-- Ditandai versi berkasnya. Tanpa ini URL-nya tidak pernah berubah, dan
       browser -- yang tidak menerima header Cache-Control dari Apache -- boleh
       menyajikan salinan lama tanpa menghubungi server, sehingga perubahan
       tampilan tidak pernah terlihat di perangkat yang sudah pernah membuka. --}}
  <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
  @livewireStyles
</head>
{{-- Halaman kerja yang penuh isian (mis. tabel Forecast) menyatakan
     @section('tanpa-menu') supaya tab bar dan FAB Scan tidak ikut digambar.
     Keduanya menempati dasar layar dan bertabrakan dengan bilah aksi halaman,
     sekaligus memakan ruang yang justru dibutuhkan daftarnya. --}}
<body class="@hasSection('tanpa-menu') tanpa-menu @endif">

  <main>
    @yield('content')
  </main>

  @sectionMissing('tanpa-menu')
    {{-- Tab bar: slot tengah dikosongkan supaya tidak tertimpa FAB Scan. --}}
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

    {{-- Tanpa wire:navigate: halaman scan perlu inisialisasi kamera dari awal. --}}
    <a href="{{ route('scan') }}" class="fab-scan" aria-label="Pindai kode">
      <i class="bi bi-camera-fill"></i>
    </a>
    <span class="fab-label">Scan</span>
  @endif

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  @livewireScripts

  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }

    // Header no-store saja tidak cukup: browser masih boleh mengembalikan
    // halaman ini dari bfcache saat tombol Back ditekan, tanpa menghubungi
    // server — sehingga dashboard tetap terlihat walau sudah logout.
    // event.persisted menandai pemulihan dari bfcache; muat ulang agar
    // middleware api.auth yang memutuskan, bukan cache.
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>

  @stack('scripts')
</body>
</html>
