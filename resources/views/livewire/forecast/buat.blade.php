<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span>{{ $galat }}</span>
    </div>
  @endif

  <form wire:submit="buat" class="bg-white rounded-4 p-3 shadow-sm">
    <div class="mb-3">
      <label for="fc-principal" class="form-label fw-semibold">Principal</label>
      <select id="fc-principal" wire:model="principal" class="form-select @error('principal') is-invalid @enderror">
        <option value="">— Pilih principal —</option>
        @foreach ($principals as $p)
          <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
        @endforeach
      </select>
      @error('principal') <div class="invalid-feedback">{{ $message }}</div> @enderror

      @if (empty($principals) && ! $galat)
        <div class="form-text">Daftar principal kosong.</div>
      @endif
    </div>

    <div class="mb-3">
      <label for="fc-bulan" class="form-label fw-semibold">Bulan forecast</label>
      <select id="fc-bulan" wire:model="bulan" class="form-select @error('bulan') is-invalid @enderror">
        @foreach ($namaBulan as $nomor => $nama)
          <option value="{{ $nomor }}">{{ $nama }}</option>
        @endforeach
      </select>
      @error('bulan') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-4">
      <label for="fc-tanggal" class="form-label fw-semibold">Tanggal forecast</label>
      <input type="date" id="fc-tanggal" wire:model="tanggal"
             class="form-control @error('tanggal') is-invalid @enderror">
      @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
      <div class="form-text">Menentukan nomor dokumen (FC/tahun/bulan/urut).</div>
    </div>

    {{-- Dokumen sudah terbentuk di server begitu request terkirim, jadi tombol
         dikunci selama proses supaya tap kedua tidak membuat dokumen ganda. --}}
    <button type="submit" class="btn btn-emas w-100 py-2 fw-semibold"
            wire:loading.attr="disabled" wire:target="buat">
      <span wire:loading.remove wire:target="buat">
        <i class="bi bi-arrow-right-circle me-1"></i> Lanjut
      </span>
      <span wire:loading wire:target="buat">
        <span class="spinner-border spinner-border-sm me-1" role="status"></span> Membuat dokumen…
      </span>
    </button>
  </form>

  <p class="text-secondary small mt-3 mb-0 px-1">
    Setelah dokumen dibuat, snapshot stok ditarik otomatis dan Anda langsung
    masuk ke tabel pengisian qty.
  </p>
</div>
