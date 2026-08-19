<div>
  <div class="input-group mb-3">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari nama pengguna" aria-label="Cari kontak">
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
    </div>
  @endif

  @forelse ($baris as $b)
    <a href="{{ route('pesan.personal', $b['id']) }}" wire:navigate wire:key="kontak-{{ $b['id'] }}"
       class="ch-item text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2">
      <span class="ch-avatar">
        {{ \Illuminate\Support\Str::of($b['fullname'] ?? '?')->trim()->substr(0, 1)->upper() }}
      </span>

      <span class="min-width-0">
        <span class="fw-bold d-block text-truncate">{{ $b['fullname'] ?? $b['login'] }}</span>
        <span class="text-secondary small d-block text-truncate">{{ $b['login'] }}</span>
      </span>
    </a>
  @empty
    @if (! $galat)
      <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
        Tidak ada kontak yang cocok.
      </div>
    @endif
  @endforelse
</div>
