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

  <div class="bg-white rounded-4 p-3 shadow-sm mt-3">
    <div wire:loading wire:target="scan, scanManual" class="text-center text-secondary py-2">
      <span class="spinner-border spinner-border-sm" role="status"></span>
      <span class="ms-2">Mencari produk…</span>
    </div>

    <div wire:loading.remove wire:target="scan, scanManual">
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

@script
<script>
  // ZXing: pembaca barcode yang sama dengan yang dipakai aplikasi Android
  // (journeyapps/zxing-android). Dimuat dari CDN saat halaman dibuka, bukan di
  // layout, supaya halaman lain tidak ikut menanggung 330 KB.
  const SUMBER_ZXING = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';

  const video = document.getElementById('scan-video');
  const kotakGalat = document.getElementById('scan-galat-kamera');

  let pembaca = null;
  let jalan = false;

  // Cerminan lastBarcode di ProductScanViewModel: callback ZXing berbunyi tiap
  // frame selama barcode masih terbidik, jadi tanpa penjagaan ini satu kali
  // mengarahkan kamera berarti puluhan request untuk barcode yang sama.
  //
  // Sengaja TIDAK dibersihkan saat pencarian gagal: kalau dibersihkan, barcode
  // yang tidak terdaftar langsung ditembakkan ulang oleh frame berikutnya dan
  // berputar tanpa henti. Yang melepasnya cuma tombol "Scan Lagi".
  let terakhir = null;
  let sibuk = false;
  let bekukan = false;

  function tampilkanGalat(kalimat) {
    kotakGalat.querySelector('span').textContent = kalimat;
    kotakGalat.classList.remove('d-none');
  }

  function muatZXing() {
    if (window.ZXing) return Promise.resolve();

    return new Promise((resolve, reject) => {
      const tag = document.createElement('script');
      tag.src = SUMBER_ZXING;
      tag.onload = resolve;
      tag.onerror = () => reject(new Error('gagal memuat'));
      document.head.appendChild(tag);
    });
  }

  async function mulai() {
    if (jalan || bekukan) return;

    // getUserMedia hanya ada di konteks aman. Di http:// biasa ia tidak
    // terdefinisi sama sekali, dan tanpa pesan ini layarnya cuma hitam.
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      tampilkanGalat('Kamera hanya bisa dipakai lewat HTTPS. Buka aplikasi ini dengan alamat https, lalu coba lagi. Sementara itu barcode bisa diketik manual di bawah.');
      return;
    }

    try {
      await muatZXing();
    } catch (e) {
      tampilkanGalat('Pustaka pembaca barcode gagal dimuat. Periksa koneksi internet perangkat.');
      return;
    }

    pembaca = new ZXing.BrowserMultiFormatReader();
    jalan = true;

    try {
      // facingMode environment: kamera belakang. "ideal", bukan "exact",
      // supaya perangkat yang cuma punya kamera depan tetap jalan.
      await pembaca.decodeFromConstraints(
        { video: { facingMode: { ideal: 'environment' } } },
        video,
        (hasil) => {
          // Frame tanpa barcode memanggil callback ini dengan hasil kosong;
          // itu keadaan normal, bukan kesalahan yang perlu dilaporkan.
          if (!hasil || sibuk || bekukan) return;

          const kode = hasil.getText();
          if (!kode || kode === terakhir) return;

          terakhir = kode;
          sibuk = true;

          Promise.resolve($wire.scan(kode)).finally(() => { sibuk = false; });
        }
      );
    } catch (e) {
      jalan = false;
      tampilkanGalat('Kamera tidak bisa dibuka. Pastikan izin kamera diberikan dan tidak sedang dipakai aplikasi lain.');
    }
  }

  function berhenti() {
    if (pembaca) pembaca.reset();
    jalan = false;
  }

  $wire.on('produk-ketemu', () => { bekukan = true; });

  $wire.on('scan-lagi', () => {
    bekukan = false;
    terakhir = null;
    mulai();
  });

  // Cerminan onPause/onResume: kembali dari background dengan kartu produk
  // terbuka tidak boleh diam-diam menimpanya dengan apa pun di depan lensa.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) berhenti();
    else mulai();
  });

  // Kamera harus dilepas saat pindah halaman; tanpa ini lampu kamera tetap
  // menyala dan perangkat menahan streamnya.
  document.addEventListener('livewire:navigating', berhenti, { once: true });

  mulai();
</script>
@endscript
