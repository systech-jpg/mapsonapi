{{--
  Kartu unggah bukti foto, dipakai ketiga tahap: pickup, barang sampai, dan
  tarik barang. Bentuknya sama persis supaya petugas tidak perlu belajar ulang
  di tiap tahap.

  Dulu ada tahap keempat (serah terima dokumen); tahap itu sudah dihapus dari
  halaman usage ERP, jadi ikut dihapus di sini.

  Tanpa capture="environment": dengan atribut itu ponsel langsung membuka kamera
  dan galeri tidak bisa dipilih, padahal fotonya sering sudah diambil lebih dulu.

  Parameter:
    $judul       judul kartu
    $keterangan  kalimat penjelas di bawah judul
    $properti    nama properti Livewire penampung berkas
    $aksi        nama method Livewire yang dipanggil tombol
    $tombol      teks tombol
    $ikon        kelas ikon bootstrap-icons untuk tombol

  wire:key wajib: keempat tahap memakai partial ini, jadi kartunya kembar
  persis. Tanpa kunci, penggabungan DOM Livewire bisa menyangka kartu tahap
  berikutnya adalah kartu tahap ini yang sekadar berganti isi, lalu menyisakan
  input berkas milik tahap sebelumnya.
--}}
<div class="bg-white rounded-4 p-3 shadow-sm mt-3" wire:key="unggah-{{ $properti }}">
  <h3 class="h6 fw-bold mb-1">{{ $judul }}</h3>
  <p class="text-secondary small mb-2">{{ $keterangan }}</p>

  <input type="file" accept="image/*" wire:model="{{ $properti }}"
         class="form-control @error($properti) is-invalid @enderror"
         aria-label="Foto {{ $judul }}">

  @error($properti) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

  <div wire:loading wire:target="{{ $properti }}" class="text-secondary small mt-2">
    <span class="spinner-border spinner-border-sm" role="status"></span>
    <span class="ms-1">Mengunggah foto…</span>
  </div>

  @if ($this->{$properti})
    <div wire:loading.remove wire:target="{{ $properti }}" class="mt-2">
      <img src="{{ $this->{$properti}->temporaryUrl() }}" alt="Pratinjau {{ $judul }}" class="tk-bukti">
    </div>
  @endif

  {{-- Dikunci selama foto belum dipilih: tanpa bukti, server pasti menolak
       dengan 422 dan tidak ada yang berubah. --}}
  <button type="button" class="btn btn-emas w-100 mt-2"
          wire:click="{{ $aksi }}"
          @disabled(! $this->{$properti})
          wire:loading.attr="disabled" wire:target="{{ $aksi }}, {{ $properti }}">
    <span wire:loading.remove wire:target="{{ $aksi }}">
      <i class="bi {{ $ikon }} me-1"></i> {{ $tombol }}
    </span>
    <span wire:loading wire:target="{{ $aksi }}">
      <span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim…
    </span>
  </button>
</div>
