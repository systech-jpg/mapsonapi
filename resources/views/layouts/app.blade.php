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

  {{-- Penanda "sedang memuat" untuk seluruh aplikasi.
       Sengaja tidak menangkap ketukan (pointer-events: none di app.css): kalau
       JavaScript tersendat dan penanda ini tertinggal menyala, aplikasi tetap
       bisa dipakai, bukan terkunci di balik lapisan yang tidak mau hilang. --}}
  <div id="pemuat" class="pemuat" role="status" aria-live="polite" aria-hidden="true">
    <div class="pemuat-kotak">
      <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
      <span>Memuat…</span>
    </div>
  </div>

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

  {{--
    Penanda memuat bersama.

    data-navigate-once WAJIB: tanpa itu Livewire menjalankan ulang skrip ini
    setiap kali pindah halaman lewat wire:navigate, dan pendengarnya menumpuk.

    Kenapa ada waktu tampil MINIMUM, bukan tenggang sebelum tampil: permintaan
    di aplikasi ini selesai 150-300 ms. Penanda yang tampil lalu langsung
    hilang dalam tempo itu tidak sempat tertangkap mata -- yang terlihat cuma
    layar yang seolah tidak bereaksi. Jadi begitu menyala, penanda ini bertahan
    minimal 450 ms walau jawabannya sudah datang lebih dulu.
  --}}
  <script data-navigate-once>
  (function () {
    'use strict';

    var TAMPIL_MINIMAL = 450;   // ms
    var BATAS_AMAN = 15000;     // ms; penjaga supaya tidak pernah menyala abadi

    var berjalan = 0;
    var mulaiPada = 0;
    var pewaktuAman = null;

    // Elemennya dicari setiap kali, tidak disimpan: wire:navigate mengganti
    // seluruh isi <body>, sehingga acuan yang disimpan menunjuk elemen mati.
    function kotak() {
      return document.getElementById('pemuat');
    }

    function gambar(tampak) {
      var el = kotak();
      if (!el) return;
      el.classList.toggle('tampil', tampak);
      el.setAttribute('aria-hidden', tampak ? 'false' : 'true');
    }

    function mulai() {
      berjalan++;
      if (berjalan > 1) return;

      mulaiPada = Date.now();
      gambar(true);

      clearTimeout(pewaktuAman);
      pewaktuAman = setTimeout(function () {
        berjalan = 0;
        gambar(false);
      }, BATAS_AMAN);
    }

    function selesai() {
      berjalan--;
      if (berjalan > 0) return;
      berjalan = 0;

      clearTimeout(pewaktuAman);

      var sisa = TAMPIL_MINIMAL - (Date.now() - mulaiPada);
      setTimeout(function () {
        if (berjalan === 0) gambar(false);
      }, sisa > 0 ? sisa : 0);
    }

    // 1. Muat halaman pertama dan refresh.
    mulai();
    if (document.readyState === 'complete') {
      selesai();
    } else {
      window.addEventListener('load', selesai, { once: true });
    }

    // 2. Pindah halaman lewat wire:navigate. Selama pengambilan halaman baru,
    //    yang tergambar masih halaman lama -- tanpa penanda ini tidak ada
    //    tanda apa pun bahwa ketukan tadi diterima.
    document.addEventListener('livewire:navigate', mulai);
    document.addEventListener('livewire:navigated', function () {
      // Isi <body> sudah berganti, jadi hitungannya dinolkan, bukan dikurangi.
      berjalan = 1;
      selesai();
    });

    // 3. Setiap permintaan komponen Livewire: klik tombol, ketik di kotak cari,
    //    simpan formulir.
    //
    //    Cukup respond(), jangan ditambah fail(): di livewire.js handleFailure
    //    memanggil respond() lebih dulu baru fail(), jadi mendaftarkan keduanya
    //    membuat satu permintaan dihitung selesai dua kali -- dan penandanya
    //    padam walau permintaan lain masih berjalan.
    document.addEventListener('livewire:init', function () {
      window.Livewire.hook('commit', function (opsi) {
        mulai();
        opsi.respond(selesai);
      });
    });
  })();
  </script>

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

  {{-- Pendengar chat realtime. Diletakkan sebelum @stack karena isinya
       memakai @push, dan @push yang datang sesudah stack-nya digambar tidak
       akan pernah muncul. --}}
  @include('partials.pusher-chat')

  @stack('scripts')
</body>
</html>
