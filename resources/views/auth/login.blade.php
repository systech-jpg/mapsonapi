{{--
  Halaman login berdiri sendiri, tidak memakai layouts.app, karena tab bar dan
  FAB Scan tidak boleh tampil sebelum pengguna masuk.

  Susunannya mengikuti activity_login.xml di Android: logo besar di atas, dua
  kotak isian bergaya outlined, lalu satu tombol pil emas selebar layar.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  {{-- interactive-widget=resizes-content: saat keyboard muncul, viewport ikut
       menyusut sehingga tombol Masuk naik ke atas keyboard. Padanan
       NestedScrollView + fillViewport di layout Android. --}}
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#BC9E68">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Mapson">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">

  <title>Masuk — Mapson</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
</head>
<body style="padding-bottom: 0;">

  <div class="login-layar">
    <div class="login-kartu">
      {{-- Berkas yang sama persis dengan drawable/logo.png di Android, disalin
           ke public/pwa/ supaya kedua aplikasi memakai logo yang sama dan tidak
           bisa berbeda diam-diam saat salah satunya diperbarui. --}}
      <img src="{{ asset('pwa/logo.png') }}?v={{ filemtime(public_path('pwa/logo.png')) }}"
           alt="Mapson Arya Parahita" class="login-logo">

      <p class="login-sambutan">Masuk untuk melanjutkan</p>

      @if (session('pesan'))
        <div class="alert alert-warning d-flex align-items-center gap-2 rounded-4">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span class="small">{{ session('pesan') }}</span>
        </div>
      @endif

      @error('username')
        <div class="alert alert-danger d-flex align-items-center gap-2 rounded-4">
          <i class="bi bi-x-circle-fill"></i>
          <span class="small">{{ $message }}</span>
        </div>
      @enderror

      <form method="POST" action="{{ route('login') }}" id="form-masuk">
        @csrf

        {{-- placeholder=" " (satu spasi) WAJIB: label mengambang di app.css
             memakai :placeholder-shown untuk tahu kotaknya masih kosong. Tanpa
             placeholder, label tidak pernah turun kembali. --}}
        <div class="login-isian">
          <input type="text" id="username" name="username" value="{{ old('username') }}"
                 class="form-control @error('username') is-invalid @enderror"
                 placeholder=" " autocomplete="username" autocapitalize="none" autofocus required>
          <label for="username">Username</label>
        </div>

        <div class="login-isian punya-mata">
          <input type="password" id="password" name="password"
                 class="form-control @error('password') is-invalid @enderror"
                 placeholder=" " autocomplete="current-password" required>
          <label for="password">Kata sandi</label>

          <button type="button" class="login-mata" id="tombol-mata"
                  aria-label="Tampilkan kata sandi" aria-pressed="false">
            <i class="bi bi-eye" id="ikon-mata"></i>
          </button>
        </div>

        @error('password')
          <div class="text-danger small mb-2">{{ $message }}</div>
        @enderror

        <button type="submit" class="login-tombol mt-3" id="tombol-masuk">
          <span class="spinner-border spinner-border-sm d-none" id="putar-masuk" aria-hidden="true"></span>
          <span id="label-masuk">Masuk</span>
        </button>
      </form>
    </div>

    <p class="login-catatan">Mapson Field Service</p>
  </div>

  <script>
    // Lihat/sembunyikan kata sandi, padanan endIconMode="password_toggle" di
    // TextInputLayout Android.
    (() => {
      const isian = document.getElementById('password');
      const tombol = document.getElementById('tombol-mata');
      const ikon = document.getElementById('ikon-mata');

      tombol.addEventListener('click', () => {
        const tampak = isian.type === 'text';

        isian.type = tampak ? 'password' : 'text';
        ikon.className = tampak ? 'bi bi-eye' : 'bi bi-eye-slash';
        tombol.setAttribute('aria-pressed', tampak ? 'false' : 'true');
        tombol.setAttribute('aria-label', tampak ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');

        // Fokus dikembalikan supaya keyboard tidak menutup di tengah pengetikan.
        isian.focus();
      });
    })();

    // Penanda "sedang diproses". Login memanggil API Dolibarr dan bisa memakan
    // satu-dua detik; tanpa penanda, orang menekan tombolnya berkali-kali.
    (() => {
      const form = document.getElementById('form-masuk');
      const tombol = document.getElementById('tombol-masuk');

      form.addEventListener('submit', () => {
        document.getElementById('putar-masuk').classList.remove('d-none');
        document.getElementById('label-masuk').textContent = 'Memeriksa…';

        // setTimeout, bukan langsung: tombol submit yang di-disable di dalam
        // handler-nya sendiri membatalkan pengiriman form di sebagian browser.
        setTimeout(() => { tombol.disabled = true; }, 0);
      });
    })();

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }

    // Kebalikan dari kasus logout: setelah berhasil masuk lalu menekan Back,
    // halaman login bisa muncul lagi dari bfcache padahal sesi sudah aktif.
    window.addEventListener('pageshow', (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });
  </script>
</body>
</html>
