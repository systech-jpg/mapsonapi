{{--
  Formulir Service Report untuk pelapor TANPA akun, dibuka dari kode QR.

  Berdiri sendiri, tidak memakai layouts.app: layout itu menggambar tab bar,
  FAB Scan, dan pendengar chat yang membaca session petugas — tak satu pun
  berlaku untuk pelapor yang belum masuk.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#BC9E68">
  <meta name="robots" content="noindex">

  <title>Service Report — Mapson Arya Parahita</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">

  {{-- Gaya khusus halaman ini ditulis di sini, bukan di app.css, karena
       tidak dipakai halaman lain. --}}
  <style>
    body { padding-bottom: 0; }
    .sr-kepala { background: linear-gradient(135deg, #C9AD80, #BC9E68); color: #fff; padding: 1.25rem 1rem 3.5rem; }
    .sr-kepala img { height: 44px; background: #fff; border-radius: .6rem; padding: .25rem .4rem; }
    .sr-kepala h1 { font-size: 1.35rem; font-weight: 800; margin: .75rem 0 0; }
    .sr-kepala p { margin: 0; opacity: .95; font-size: .85rem; }
    .sr-isi { margin-top: -2.5rem; max-width: 720px; }
    .sr-kartu { background: #fff; border-radius: 1rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); padding: 1rem; margin-bottom: 1rem; }
    .sr-judul { font-weight: 800; margin-bottom: .75rem; }
    .sr-jenis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .sr-jenis label { border: 1px solid #E2D7C3; border-radius: .75rem; padding: .6rem .75rem; display: flex; gap: .5rem; align-items: center; font-weight: 600; }
    .sr-jenis input:checked + span { color: #8A6A2F; }
    .sr-wajib::after { content: " *"; color: #dc3545; }
    .sr-jebakan { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
    @media (min-width: 576px) { .sr-jenis { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
  </style>
  @livewireStyles
</head>
<body>
  <header class="sr-kepala">
    <img src="{{ asset('pwa/logo.png') }}?v={{ filemtime(public_path('pwa/logo.png')) }}" alt="Mapson Arya Parahita">
    <h1>Formulir Service Report / Maintenance Alat</h1>
    <p>PT. Mapson Arya Parahita · Form No. 007/01/TKS-MAP/F-03</p>
  </header>

  <main class="px-3 mx-auto sr-isi">
    @livewire('service-report.form-publik')
  </main>

  @livewireScripts
</body>
</html>
