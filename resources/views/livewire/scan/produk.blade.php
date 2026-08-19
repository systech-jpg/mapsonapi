<div>
  {{-- wire:ignore: setiap hasil scan membuat Livewire menggambar ulang
       komponen ini. Tanpa penanda itu penggabungan DOM boleh menyentuh elemen
       <video>, dan stream kamera yang sudah terpasang di srcObject bisa
       terlepas di tengah pemakaian. --}}
  <div class="scan-layar" wire:ignore>
    {{-- playsinline wajib: tanpa itu iOS memutar video kamera secara fullscreen
         dan menutupi seluruh halaman. muted membuat autoplay diizinkan. --}}
    <video id="scan-video" class="scan-video" playsinline muted autoplay></video>
    <div class="scan-bingkai"></div>

    <div id="scan-galat-kamera" class="scan-galat d-none">
      <i class="bi bi-camera-video-off fs-3 d-block mb-2"></i>
      <span class="small"></span>
    </div>
  </div>

  {{-- Keadaan kamera ditulis apa adanya. Layar kamera yang gelap bisa berarti
       banyak hal (belum HTTPS, izin ditolak, pustaka gagal dimuat), dan tanpa
       baris ini semuanya terlihat sama saja: kotak hitam.

       wire:ignore juga: yang mengisinya JavaScript kamera di halaman, sedangkan
       Livewire tidak tahu apa-apa soal isi terakhirnya dan akan
       mengembalikannya ke teks awal setiap kali komponen digambar ulang. --}}
  <p id="scan-keadaan" wire:ignore class="text-secondary small text-center mt-2 mb-0">Menyiapkan kamera…</p>

  <div class="bg-white rounded-4 p-3 shadow-sm mt-3">
    <div wire:loading wire:target="terimaBarcode, scanManual" class="text-center text-secondary py-2">
      <span class="spinner-border spinner-border-sm" role="status"></span>
      <span class="ms-2">Mencari produk…</span>
    </div>

    <div wire:loading.remove wire:target="terimaBarcode, scanManual">
      @if ($pesan)
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-2">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span class="small">{{ $pesan }}</span>
        </div>
      @endif

      @if ($hasil)
        <div class="fw-bold fs-5">{{ $hasil['judul'] }}</div>

        @if ($hasil['deskripsi'] !== '')
          <p class="text-secondary small scan-deskripsi mb-0 mt-1">{{ $hasil['deskripsi'] }}</p>
        @endif

        <hr class="my-3">

        <div class="d-flex justify-content-between align-items-center">
          <span class="text-secondary">Stok saat ini</span>
          <span class="scan-stok {{ $this->stokMinus() ? 'minus' : 'plus' }}">{{ $this->stokTampil() }}</span>
        </div>

        <button type="button" class="btn btn-emas w-100 mt-3" wire:click="ulangi">
          <i class="bi bi-arrow-repeat me-1"></i> Scan Lagi
        </button>
      @else
        <p class="text-secondary text-center small mb-0">Arahkan kamera ke barcode produk.</p>
      @endif
    </div>

    {{-- Ketik tangan: dipakai saat barcode rusak, atau saat kamera tidak bisa
         dipakai sama sekali (halaman dibuka lewat HTTP, izin ditolak). --}}
    <details class="mt-3">
      <summary class="text-secondary small">Ketik barcode manual</summary>

      <form wire:submit="scanManual" class="input-group mt-2">
        <input type="text" wire:model="barcodeManual" class="form-control"
               placeholder="Nomor barcode" aria-label="Barcode manual" inputmode="numeric">
        <button type="submit" class="btn btn-outline-emas" wire:loading.attr="disabled" wire:target="scanManual">
          Cari
        </button>
      </form>
    </details>
  </div>

  <div class="fab-ruang"></div>
</div>
