{{--
  Halaman login berdiri sendiri, tidak memakai layouts.app, karena tab bar dan
  FAB Scan tidak boleh tampil sebelum pengguna masuk.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body style="padding-bottom: 0;">

  <header class="app-header">
    <h1>Masuk</h1>
    <p>Mapson</p>
  </header>

  <div class="px-3" style="margin-top: -3rem;">
    <div class="bg-white rounded-4 p-4 shadow-sm">

      @if (session('pesan'))
        <div class="alert alert-warning d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span>{{ session('pesan') }}</span>
        </div>
      @endif

      @error('username')
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-x-circle-fill"></i>
          <span>{{ $message }}</span>
        </div>
      @enderror

      <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
          <label for="username" class="form-label fw-semibold">Username</label>
          <div class="input-group">
            <span class="input-group-text bg-body-tertiary"><i class="bi bi-person"></i></span>
            <input type="text" id="username" name="username" value="{{ old('username') }}"
                   class="form-control @error('username') is-invalid @enderror"
                   autocomplete="username" autocapitalize="none" autofocus required>
          </div>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label fw-semibold">Kata sandi</label>
          <div class="input-group">
            <span class="input-group-text bg-body-tertiary"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="current-password" required>
          </div>
          @error('password')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        <button type="submit" class="btn w-100 fw-bold text-white py-2"
                style="background: var(--gold-500); border-radius: 999px;">
          Masuk
        </button>
      </form>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

  <script>
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
