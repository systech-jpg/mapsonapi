@extends('layouts.app')

@section('title', 'Hitung Stocktake')

{{-- Halaman kerja penuh isian: tab bar dan FAB Scan tidak digambar supaya
     tombol Simpan yang menempel di dasar layar tidak berebut tempat. --}}
@section('tanpa-menu', 'ya')

@section('content')
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('stocktake') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke daftar stocktake">
      <i class="bi bi-arrow-left"></i>
    </a>

    <h1>Hitung Fisik</h1>
  </header>

  <div class="px-3 st-halaman" style="margin-top: -2.25rem;">
    @livewire('stocktake.hitung', ['id' => $id])
  </div>
@endsection

@push('scripts')
{{--
  Dua pekerjaan, keduanya sengaja di halaman dan bukan di dalam @script komponen
  (alasan yang sama seperti di resources/views/scan.blade.php: blok @script baru
  berjalan bila livewire.js berhasil dimuat).

  1. Menjumlahkan Rak + Tray + Container menjadi Total. Dikerjakan browser tanpa
     memanggil server sama sekali — satu dokumen berisi 300-an baris, dan
     wire:model.live di tiap kotak berarti ratusan permintaan hanya untuk
     menampilkan angka yang bisa dihitung di tempat. Ini cerminan calcQty() di
     custom/stocktake/card.php.

  2. Kamera barcode untuk lembar Scan.
--}}
<script>
(function () {
  'use strict';

  /*
    wire:navigate tidak memuat ulang halaman, jadi konteks JavaScript-nya awet
    dan skrip ini dijalankan LAGI setiap kali halaman hitung dibuka kembali.
    Tanpa penjaga di bawah, tiap kunjungan menambah satu pendengar 'input',
    satu 'livewire:navigated', dan satu hook 'morphed' -- dan hook 'morphed'
    berbunyi untuk SEMUA komponen di SEMUA halaman, bukan cuma halaman ini.
    Setelah beberapa kali bolak-balik, satu ketukan tombol di modul mana pun
    menyeret belasan penjumlahan yang tidak ada gunanya.

    Aman keluar lebih awal: seluruh pendengar dipasang di document dan di objek
    Livewire, keduanya bertahan melewati perpindahan halaman, dan
    'livewire:navigated' yang sudah terpasang tetap menghitung ulang Total
    untuk halaman yang baru digambar.
  */
  if (window.__stocktakeTerpasang) return;
  window.__stocktakeTerpasang = true;

  /* ------------------------------------------------------------------
   | 1. Total per baris
   * ------------------------------------------------------------------ */

  function rapikan(n) {
    return (Math.round(n * 10000) / 10000).toString();
  }

  function hitungBaris(baris) {
    if (!baris) return;

    var total = 0;
    baris.querySelectorAll('.st-in').forEach(function (kotak) {
      var nilai = parseFloat(String(kotak.value).replace(',', '.'));
      if (!isNaN(nilai)) total += nilai;
    });

    var kotakTotal = baris.querySelector('[data-st-total]');
    if (kotakTotal) kotakTotal.textContent = rapikan(total);

    // Selisih hanya digambar untuk peran yang boleh melihat angka sistem;
    // untuk petugas hitung elemennya memang tidak ada.
    var kotakSelisih = baris.querySelector('[data-st-selisih]');
    if (kotakSelisih) {
      var teori = parseFloat(kotakSelisih.getAttribute('data-teori'));
      if (!isNaN(teori)) {
        var beda = total - teori;
        kotakSelisih.textContent = (beda > 0 ? '+' : '') + rapikan(beda);
        kotakSelisih.classList.toggle('plus', beda > 0);
        kotakSelisih.classList.toggle('minus', beda < 0);
      }
    }
  }

  function hitungSemua() {
    document.querySelectorAll('[data-st-baris]').forEach(hitungBaris);
  }

  // Delegasi di document, bukan pemasangan per kotak: Livewire menggambar ulang
  // baris setiap kali penyaring berubah, dan pendengar yang dipasang satu-satu
  // ikut hilang bersama elemen lamanya.
  document.addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('st-in')) {
      hitungBaris(e.target.closest('[data-st-baris]'));
    }
  });

  /* ------------------------------------------------------------------
   | 2. Kamera barcode
   * ------------------------------------------------------------------ */

  var SUMBER_ZXING = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';

  var pembaca = null;
  var jalan = false;
  var sibuk = false;

  function galat(kalimat) {
    var kotak = document.getElementById('st-scan-galat');
    if (kotak) {
      kotak.querySelector('span').textContent = kalimat;
      kotak.classList.remove('d-none');
    }
    console.error('[stocktake]', kalimat);
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

  function mulaiKamera() {
    var video = document.getElementById('st-scan-video');
    if (!video || jalan) return;

    // Kamera hanya diberikan browser di konteks aman (HTTPS atau localhost).
    // http://mapsonapi.test TIDAK termasuk, dan tanpa kalimat ini yang terlihat
    // cuma kotak hitam yang menyerupai aplikasi rusak.
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      galat(
        'Kamera tidak bisa dinyalakan karena halaman ini dibuka lewat ' +
        location.protocol + '//' + location.host +
        ' — browser hanya mengizinkan kamera di alamat HTTPS. Ketik saja kodenya di kotak pencarian.'
      );
      return;
    }

    muatZXing().then(function () {
      pembaca = new ZXing.BrowserMultiFormatReader();
      jalan = true;

      return pembaca.decodeFromConstraints(
        { video: { facingMode: { ideal: 'environment' } } },
        video,
        function (hasil) {
          if (!hasil || sibuk) return;

          var kode = hasil.getText();
          if (!kode) return;

          // Satu barcode cukup sekali: lembarnya langsung ditutup oleh
          // komponen, jadi tidak perlu penjagaan barcode berulang seperti di
          // halaman Scan Produk.
          sibuk = true;

          if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch('barcode-terbaca', { kode: kode });
          } else {
            galat('Barcode terbaca (' + kode + ') tapi mesin halaman belum siap. Ketik kodenya di kotak pencarian.');
            sibuk = false;
          }
        }
      );
    }).catch(function (e) {
      jalan = false;

      if (e && e.message === 'gagal memuat') {
        galat('Pustaka pembaca barcode gagal dimuat dari internet. Periksa koneksi perangkat.');
        return;
      }

      galat(
        (e && e.name === 'NotAllowedError')
          ? 'Izin kamera ditolak. Buka pengaturan situs di browser, izinkan Kamera, lalu muat ulang halaman ini.'
          : 'Kamera tidak bisa dibuka (' + ((e && e.name) || 'sebab tidak dikenal') + ').'
      );
    });
  }

  function hentikanKamera() {
    if (pembaca) pembaca.reset();
    pembaca = null;
    jalan = false;
    sibuk = false;
  }

  function pasangPendengar() {
    if (!window.Livewire || typeof window.Livewire.on !== 'function') return false;

    // Elemen <video> baru ada setelah Livewire menggambar lembarnya, jadi
    // kameranya dinyalakan satu putaran setelah event, bukan langsung.
    window.Livewire.on('scan-dibuka', function () {
      setTimeout(mulaiKamera, 50);
    });

    window.Livewire.on('scan-ditutup', hentikanKamera);

    // Setelah Livewire menggambar ulang, kotak isian kembali memakai nilai dari
    // server. Total yang tadi dihitung browser ikut mundur kalau tidak dihitung
    // ulang di sini.
    if (typeof window.Livewire.hook === 'function') {
      window.Livewire.hook('morphed', hitungSemua);
    }

    return true;
  }

  if (!pasangPendengar()) {
    document.addEventListener('livewire:initialized', pasangPendengar, { once: true });
  }

  // Kamera wajib dilepas saat pindah halaman; tanpa ini lampu kamera tetap
  // menyala dan perangkat menahan streamnya.
  document.addEventListener('livewire:navigating', hentikanKamera, { once: true });

  /*
    Total dihitung tiga kali pada pemuatan pertama, dan itu memang disengaja.

    Kotak isian sudah membawa value= dari server, jadi hitungan pertama di bawah
    biasanya sudah benar. Tapi Livewire menimpa isi kotak dengan datanya sendiri
    sesudah livewire.js selesai dimuat — dan kalau penjumlahan tidak diulang
    setelah itu, seluruh kolom Total tergambar 0 walau kotaknya berisi angka.
    Itu persis bug yang terlihat di layar pertama kali halaman ini dipakai.
  */
  document.addEventListener('livewire:initialized', hitungSemua);
  document.addEventListener('livewire:navigated', hitungSemua);

  hitungSemua();
})();
</script>
@endpush
