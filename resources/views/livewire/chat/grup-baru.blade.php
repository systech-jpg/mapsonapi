<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
    </div>
  @endif

  <div class="bg-white rounded-4 p-3 shadow-sm mb-3">
    <label for="nama-grup" class="form-label fw-semibold">Nama grup</label>
    <input type="text" id="nama-grup" wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
           placeholder="Contoh: Tim Gudang Jakarta" maxlength="255">
    @error('nama') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="fw-semibold">Anggota</span>
    <span class="text-secondary small">{{ count($anggota) }} dipilih</span>
  </div>

  @error('anggota') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

  <div class="input-group mb-2">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari nama pengguna" aria-label="Cari kontak">
  </div>

  <div class="bg-white rounded-4 shadow-sm overflow-hidden mb-3">
    @forelse ($baris as $b)
      {{-- Label membungkus checkbox supaya seluruh baris bisa diketuk; di layar
           sentuh, kotak centang setinggi 16 px terlalu kecil untuk jempol. --}}
      <label class="ch-pilih" wire:key="anggota-{{ $b['id'] }}">
        <input type="checkbox" class="form-check-input m-0 flex-shrink-0"
               wire:model.live="anggota" value="{{ $b['id'] }}">
        <span class="ch-avatar kecil">
          {{ \Illuminate\Support\Str::of($b['fullname'] ?? '?')->trim()->substr(0, 1)->upper() }}
        </span>
        <span class="min-width-0">
          <span class="fw-semibold d-block text-truncate">{{ $b['fullname'] ?? $b['login'] }}</span>
          <span class="text-secondary small d-block text-truncate">{{ $b['login'] }}</span>
        </span>
      </label>
    @empty
      <div class="p-4 text-center text-secondary">Tidak ada kontak yang cocok.</div>
    @endforelse
  </div>

  <div class="st-aksi">
    <button type="button" class="btn btn-emas w-100" wire:click="simpan"
            wire:loading.attr="disabled" wire:target="simpan">
      <i class="bi bi-people-fill me-1"></i> Buat grup
    </button>
  </div>
</div>
