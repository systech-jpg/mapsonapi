@extends('layouts.app')

@section('title', 'Scan Produk')

{{-- Tab bar dan FAB Scan tidak digambar: layar ini isinya pratinjau kamera
     yang butuh tinggi penuh, dan tombol Scan di dasar layar tidak ada gunanya
     di halaman Scan itu sendiri. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('home') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke beranda">
      <i class="bi bi-arrow-left"></i>
    </a>

    <h1>Scan Produk</h1>
  </header>

  <div class="px-3" style="margin-top: -2.25rem;">
    @livewire('scan.produk')
  </div>
@endsection

@push('scripts')
{{--
  Kamera sengaja dijalankan dari halaman, BUKAN dari @script di dalam komponen.

  Blok @script dieksekusi lewat mekanisme "effect" Livewire: isinya dititipkan
  di payload komponen dan baru dijalankan oleh livewire.js. Kalau berkas itu
  gagal dimuat -- kejadian biasa lewat tunnel gratis yang menyisipkan halaman
  interstitial -- skripnya tidak pernah berjalan sama sekali dan yang tersisa
  cuma kotak hitam tanpa keterangan.

  Ditaruh di @stack('scripts') seperti ini, pratinjau kamera tetap menyala walau
  Livewire mati total; yang hilang hanya pencarian produknya, dan itu pun masih
  bisa lewat isian barcode manual.
--}}
<script>
(function () {
  'use strict';

  // ZXing: pembaca barcode yang sama dengan yang dipakai aplikasi Android
  // (journeyapps/zxing-android), versi JavaScript-nya.
  var SUMBER_ZXING = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';

  var video = document.getElementById('scan-video');
  var kotakGalat = document.getElementById('scan-galat-kamera');
  var barisKeadaan = document.getElementById('scan-keadaan');

  // Halaman lain memakai layout yang sama; tanpa penjagaan ini skripnya ikut
  // jalan di sana dan mengeluh soal elemen yang memang tidak ada.
  if (!video) return;

  var pembaca = null;
  var jalan = false;

  // Cerminan lastBarcode di ProductScanViewModel: callback ZXing berbunyi tiap
  // frame selama barcode masih terbidik, jadi tanpa penjagaan ini satu kali
  // mengarahkan kamera berarti puluhan request untuk barcode yang sama.
  //
  // Sengaja TIDAK dibersihkan saat pencarian gagal: kalau dibersihkan, barcode
  // yang tidak terdaftar langsung ditembakkan ulang oleh frame berikutnya dan
  // berputar tanpa henti. Yang melepasnya cuma tombol "Scan Lagi".
  var terakhir = null;
  var sibuk = false;
  var bekukan = false;

  function keadaan(kalimat) {
    if (barisKeadaan) barisKeadaan.textContent = kalimat;
    console.log('[scan]', kalimat);
  }

  function tampilkanGalat(kalimat) {
    if (kotakGalat) {
      kotakGalat.querySelector('span').textContent = kalimat;
      kotakGalat.classList.remove('d-none');
    }
    if (barisKeadaan) barisKeadaan.textContent = kalimat;
    console.error('[scan]', kalimat);
  }

  function muatZXing() {
    if (window.ZXing) return Promise.resolve();

    return new Promise(function (resolve, reject) {
      var tag = document.createElement('script');
      tag.src = SUMBER_ZXING;
      tag.onload = resolve;
      tag.onerror = function () { reject(new Error('gagal memuat')); };
      document.head.appendChild(tag);
    });
  }

  /** Barcode diserahkan ke komponen Livewire; api_key tidak pernah ke browser. */
  function kirim(kode) {
    if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
      sibuk = true;
      window.Livewire.dispatch('barcode-terbaca', { kode: kode });

      // Livewire tidak mengembalikan janji lewat dispatch, jadi kuncinya
      // dilepas setelah jeda pendek -- cukup untuk mencegah tembakan beruntun
      // dari frame-frame berikutnya.
      setTimeout(function () { sibuk = false; }, 1200);
      return;
    }

    tampilkanGalat('Barcode terbaca (' + kode + ') tapi mesin halaman belum siap. Ketik barcode itu di isian manual di bawah.');
  }

  function mulai() {
    if (jalan || bekukan) return;

    // Kamera hanya diberikan browser di "konteks aman": HTTPS, atau localhost.
    // Alamat seperti http://mapsonapi.test TIDAK termasuk -- di situ
    // navigator.mediaDevices bahkan tidak ada, dan tanpa penjelasan ini
    // layarnya cuma kotak hitam yang terlihat seperti aplikasi rusak.
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      tampilkanGalat(
        'Browser menolak menyalakan kamera karena halaman ini dibuka lewat ' +
        location.protocol + '//' + location.host +
        ' — kamera hanya diizinkan di alamat HTTPS (atau localhost). ' +
        'Sementara itu barcode bisa diketik manual di bawah.'
      );
      return;
    }

    keadaan('Memuat pembaca barcode…');

    muatZXing().then(function () {
      keadaan('Meminta izin kamera…');

      pembaca = new ZXing.BrowserMultiFormatReader();
      jalan = true;

      // facingMode environment: kamera belakang. "ideal", bukan "exact",
      // supaya perangkat yang cuma punya kamera depan tetap jalan.
      return pembaca.decodeFromConstraints(
        { video: { facingMode: { ideal: 'environment' } } },
        video,
        function (hasil) {
          // Frame tanpa barcode memanggil callback ini dengan hasil kosong;
          // itu keadaan normal, bukan kesalahan yang perlu dilaporkan.
          if (!hasil || sibuk || bekukan) return;

          var kode = hasil.getText();
          if (!kode || kode === terakhir) return;

          terakhir = kode;
          kirim(kode);
        }
      );
    }).then(function () {
      keadaan('Kamera aktif. Arahkan ke barcode produk.');
    }).catch(function (e) {
      jalan = false;

      if (e && e.message === 'gagal memuat') {
        tampilkanGalat('Pustaka pembaca barcode gagal dimuat dari internet. Periksa koneksi perangkat.');
        return;
      }

      // Nama kesalahannya disebut apa adanya: NotAllowedError (izin ditolak)
      // dan NotFoundError (tidak ada kamera) menuntut tindakan yang berbeda,
      // dan satu kalimat umum membuat keduanya tidak bisa dibedakan.
      var sebab = (e && e.name === 'NotAllowedError')
        ? 'Izin kamera ditolak. Buka pengaturan situs di browser, izinkan Kamera, lalu muat ulang halaman ini.'
        : (e && e.name === 'NotFoundError')
          ? 'Tidak ada kamera yang terbaca di perangkat ini.'
          : 'Kamera tidak bisa dibuka (' + ((e && e.name) || (e && e.message) || 'sebab tidak dikenal') + '). Pastikan tidak sedang dipakai aplikasi lain.';

      tampilkanGalat(sebab);
    });
  }

  function berhenti() {
    if (pembaca) pembaca.reset();
    jalan = false;
  }

  function pasangPendengar() {
    if (!window.Livewire || typeof window.Livewire.on !== 'function') return false;

    // Hasil dibekukan sampai user menekan "Scan Lagi" -- alasan yang sama
    // dengan barcodeScanner.pause() di Android: barcode lain yang kebetulan
    // lewat di depan lensa tidak boleh menimpa angka stok yang sedang dibaca.
    window.Livewire.on('produk-ketemu', function () { bekukan = true; });

    window.Livewire.on('scan-lagi', function () {
      bekukan = false;
      terakhir = null;
      sibuk = false;
      mulai();
    });

    return true;
  }

  if (!pasangPendengar()) {
    document.addEventListener('livewire:initialized', pasangPendengar, { once: true });
  }

  // Cerminan onPause/onResume: kembali dari background dengan kartu produk
  // terbuka tidak boleh diam-diam menimpanya dengan apa pun di depan lensa.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) berhenti();
    else mulai();
  });

  // Kamera harus dilepas saat pindah halaman; tanpa ini lampu kamera tetap
  // menyala dan perangkat menahan streamnya.
  document.addEventListener('livewire:navigating', berhenti, { once: true });

  mulai();
})();
</script>
@endpush
