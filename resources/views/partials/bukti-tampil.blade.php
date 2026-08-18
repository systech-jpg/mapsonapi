{{--
  Kartu bukti yang sudah tersimpan. Gambarnya diambil lewat route web yang
  meneruskan dari API, karena browser tidak pernah memegang api_key.

  Parameter:
    $judul  judul kartu
    $rute   nama route web penyaji gambarnya
    $id     id tindakan
--}}
<div class="bg-white rounded-4 p-3 shadow-sm mt-3" wire:key="tampil-{{ $rute }}">
  <h3 class="h6 fw-bold mb-2">{{ $judul }}</h3>
  <a href="{{ route($rute, $id) }}" target="_blank" rel="noopener">
    <img src="{{ route($rute, $id) }}" alt="{{ $judul }}" class="tk-bukti">
  </a>
</div>
