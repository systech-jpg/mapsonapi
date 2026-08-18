<div>
  <a href="{{ route('tindakan.buat') }}" wire:navigate class="btn btn-emas w-100 py-2 fw-semibold mb-3">
    <i class="bi bi-plus-lg me-1"></i> Buat Jadwal Tindakan
  </a>

  <div class="input-group mb-3">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari ref, RS, dokter, atau pasien" aria-label="Cari tindakan">
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  {{-- wire:loading bekerja selama request berlangsung; flag PHP biasa tidak
       akan pernah terlihat karena pengambilan datanya sinkron. --}}
  <div wire:loading wire:target="muat, muatLagi, cari" class="text-center text-secondary py-4">
    <div class="spinner-border spinner-border-sm" role="status"></div>
    <span class="ms-2">Memuat…</span>
  </div>

  <div wire:loading.remove wire:target="muat, muatLagi, cari">
    @forelse ($this->hasil as $item)
      @php $label = $this->label($item); @endphp

      <a href="{{ route('tindakan.detail', $item['id']) }}" wire:navigate
         class="d-block text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2"
         wire:key="td-{{ $item['id'] }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-width-0">
            <div class="fw-bold">{{ $item['ref'] ?? '-' }}</div>
            <div class="text-secondary small text-truncate">{{ $item['rs_name'] ?? '-' }}</div>
          </div>
          <span class="tk-status {{ \App\Support\StatusTindakan::warna($label) }} flex-shrink-0">{{ $label }}</span>
        </div>

        <div class="tk-meta mt-2">
          <span><i class="bi bi-person me-1"></i>{{ $item['pasien'] ?: '-' }}</span>
          <span><i class="bi bi-clipboard-pulse me-1"></i>{{ $item['dokter_name'] ?: '-' }}</span>
        </div>

        <div class="d-flex justify-content-between align-items-end mt-1">
          <span class="text-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ $item['tanggal'] ? \Illuminate\Support\Carbon::parse($item['tanggal'])->format('d M Y') : '—' }}
          </span>
          @if (! empty($item['ref_sj']))
            <span class="text-secondary small">SJ: {{ $item['ref_sj'] }}</span>
          @endif
        </div>
      </a>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
          @if (! empty($items))
            Tidak ada tindakan yang cocok dengan pencarian.
          @else
            Belum ada tindakan pada bulan ini.
          @endif
        </div>
      @endif
    @endforelse

    @if ($adaLagi)
      <button type="button" class="btn btn-outline-emas w-100 mt-1" wire:click="muatLagi"
              wire:loading.attr="disabled" wire:target="muatLagi">
        Muat lebih banyak
      </button>
    @endif
  </div>

  @if ($periode)
    <p class="text-secondary small text-center mt-3 mb-0">
      Periode {{ \Illuminate\Support\Carbon::parse($periode['start'])->format('d M') }}
      – {{ \Illuminate\Support\Carbon::parse($periode['end'])->format('d M Y') }}
      · {{ count($items) }} baris dimuat
    </p>
  @endif
</div>
