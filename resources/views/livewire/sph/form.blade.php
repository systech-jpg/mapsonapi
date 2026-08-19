<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
    </div>
  @endif

  <div class="bg-white rounded-4 p-3 shadow-sm">
    {{-- Ref hanya ditampilkan, tidak bisa diubah: nomornya diterbitkan server
         saat disimpan. Yang tampil di sini nomor calon berikutnya, sama seperti
         baris Ref di form ERP. --}}
    <dl class="tk-info mb-3">
      <dt>Ref</dt>
      <dd class="fw-bold">{{ $refBerikut ?: '—' }}</dd>
    </dl>

    <div class="mb-3">
      <label class="form-label small text-secondary" for="ref_quotation">Nomor Quotation</label>
      <input type="text" id="ref_quotation" wire:model="ref_quotation" maxlength="50"
             class="form-control @error('ref_quotation') is-invalid @enderror"
             placeholder="Nomor dari principal">
      @error('ref_quotation') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small text-secondary" for="fk_principal">Principal</label>
      <select id="fk_principal" wire:model="fk_principal" class="form-select">
        <option value="">-- Pilih Principal --</option>
        @foreach ($principals as $p)
          <option value="{{ $p['rowid'] }}">{{ $p['nom'] }}</option>
        @endforeach
      </select>
    </div>

    {{-- Pelanggan wajib, sama seperti fieldrequired di ERP. Dipilih lewat
         lembar cari, bukan dropdown panjang berisi ratusan societe. --}}
    <div class="mb-3">
      <label class="form-label small text-secondary">Pelanggan <span class="text-danger">*</span></label>

      <button type="button" class="form-select text-start @error('fk_soc') is-invalid @enderror"
              wire:click="bukaPelanggan">
        {{ $namaPelanggan ?: 'Pilih pelanggan…' }}
      </button>

      @error('fk_soc') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small text-secondary" for="sales_name">Sales</label>
      <select id="sales_name" wire:model="sales_name" class="form-select">
        @foreach ($sales as $nama)
          <option value="{{ $nama }}">{{ $nama }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label small text-secondary" for="date_sph">Tanggal SPH <span class="text-danger">*</span></label>
      <input type="date" id="date_sph" wire:model="date_sph"
             class="form-control @error('date_sph') is-invalid @enderror">
      @error('date_sph') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
      <label class="form-label small text-secondary" for="date_valid">Valid until</label>
      <input type="date" id="date_valid" wire:model="date_valid"
             class="form-control @error('date_valid') is-invalid @enderror">
      @error('date_valid') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-0">
      <label class="form-label small text-secondary" for="note">Note</label>
      <textarea id="note" wire:model="note" rows="3" class="form-control"></textarea>
    </div>
  </div>

  <div class="fc-aksi mt-2">
    <a href="{{ $sphId ? route('sph.detail', $sphId) : route('sph') }}" wire:navigate
       class="btn btn-outline-emas flex-fill">Batal</a>

    <button type="button" class="btn btn-emas flex-fill" wire:click="simpan"
            wire:loading.attr="disabled" wire:target="simpan">
      <span wire:loading.remove wire:target="simpan"><i class="bi bi-save me-1"></i> Simpan</span>
      <span wire:loading wire:target="simpan">
        <span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan…
      </span>
    </button>
  </div>

  <div class="fc-ruang"></div>

  {{-- Lembar pilih pelanggan. Memakai pola .sheet, bukan modal Bootstrap:
       modal bawaan melepas elemen lewat JS-nya sendiri sementara Livewire
       menggambar ulang DOM yang sama, sehingga backdrop mudah tertinggal. --}}
  @if ($sheetPelanggan)
    <div class="sheet-backdrop" wire:click="tutupPelanggan"></div>

    <div class="sheet" role="dialog" aria-modal="true" aria-label="Pilih pelanggan">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 fw-bold mb-0">Pilih Pelanggan</h2>
        <button type="button" class="btn-close" wire:click="tutupPelanggan" aria-label="Tutup"></button>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
        {{-- Ditunda 350 ms supaya tidak menembak API tiap huruf yang diketik. --}}
        <input type="search" wire:model.live.debounce.350ms="cariPelanggan"
               class="form-control border-start-0 ps-0"
               placeholder="Cari nama atau kota" aria-label="Cari pelanggan">
      </div>

      <div wire:loading wire:target="cariPelanggan" class="text-center text-secondary py-3">
        <span class="spinner-border spinner-border-sm" role="status"></span>
        <span class="ms-2 small">Mencari…</span>
      </div>

      <div class="sheet-hasil" wire:loading.remove wire:target="cariPelanggan">
        @forelse ($hasilPelanggan as $c)
          <button type="button" class="sheet-item" wire:key="cust-{{ $c['rowid'] }}"
                  wire:click="pilihPelanggan({{ (int) $c['rowid'] }}, @js($c['nom']))">
            <span class="fc-nama fw-semibold text-dark">{{ $c['nom'] }}</span>
            <span class="fc-kode">{{ $c['town'] ?: '—' }}</span>
          </button>
        @empty
          <p class="text-secondary small text-center py-3 mb-0">
            Tidak ada pelanggan yang cocok. Coba kata kunci lain.
          </p>
        @endforelse
      </div>
    </div>
  @endif
</div>
