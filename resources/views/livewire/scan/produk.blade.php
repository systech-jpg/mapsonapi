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
    {{-- Kalimatnya netral ("kode", bukan "produk"): lensa yang sama sekarang
         juga membaca kode QR login ERP. --}}
    <div wire:loading wire:target="terimaBarcode, scanManual, setujuiLogin, tolakLogin"
         class="text-center text-secondary py-2">
      <span class="spinner-border spinner-border-sm" role="status"></span>
      <span class="ms-2">Membaca kode…</span>
    </div>

    <div wire:loading.remove wire:target="terimaBarcode, scanManual, setujuiLogin, tolakLogin">
      @if ($login)
        {{-- Konfirmasi masuk ke ERP. IP dan peramban ditampilkan supaya petugas
             bisa mencocokkannya dengan komputer di depannya; kode QR yang
             ditempel orang lain hampir pasti datang dari alamat yang asing. --}}
        <div class="text-center">
          <i class="bi bi-display fs-1 text-secondary d-block"></i>
          <div class="fw-bold fs-5 mt-1">Masuk ke ERP?</div>
          <p class="text-secondary small mb-3">
            Ada komputer yang minta dibukakan ERP sebagai akun Anda.
          </p>
        </div>

        <dl class="tk-info mb-3">
          <dt>Alamat IP</dt>
          <dd>{{ $login['ip'] ?: '—' }}</dd>

          <dt>Peramban</dt>
          <dd>{{ $login['peramban'] ?? '—' }}</dd>

          <dt>Berlaku</dt>
          <dd>{{ $login['sisa_detik'] ?? 0 }} detik lagi</dd>
        </dl>

        <div class="alert alert-warning small py-2">
          <i class="bi bi-shield-exclamation me-1"></i>
          Kalau bukan Anda yang sedang membuka ERP, tekan <strong>Bukan saya</strong>.
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-emas w-50" wire:click="tolakLogin"
                  wire:loading.attr="disabled" wire:target="tolakLogin, setujuiLogin">
            Bukan saya
          </button>
          <button type="button" class="btn btn-emas w-50" wire:click="setujuiLogin"
                  wire:loading.attr="disabled" wire:target="tolakLogin, setujuiLogin">
            <i class="bi bi-check-lg me-1"></i> Setujui
          </button>
        </div>
      @elseif ($loginSelesai)
        <div class="text-center py-2">
          <i class="bi bi-check-circle-fill fs-1 text-secondary d-block mb-2"></i>
          <p class="mb-0">{{ $pesanLogin }}</p>
        </div>

        <button type="button" class="btn btn-emas w-100 mt-3" wire:click="ulangi">
          <i class="bi bi-arrow-repeat me-1"></i> Scan Lagi
        </button>
      @else
        {{-- Jalur produk: tidak berubah sama sekali dari sebelum ada login QR. --}}
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
          <p class="text-secondary text-center small mb-0">
            Arahkan kamera ke barcode produk, atau ke kode QR di halaman login ERP.
          </p>
        @endif
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
