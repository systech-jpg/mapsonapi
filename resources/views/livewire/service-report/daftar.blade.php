<div>
  <div class="input-group mb-2">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari nomor, customer, ruangan, atau no. SN" aria-label="Cari service report">
  </div>

  {{-- Saringan status memakai chip yang sama dengan saringan principal di
       stocktake, supaya bentuknya sudah dikenal petugas. --}}
  <div class="st-chips mb-3">
    @foreach (['' => 'Semua', '0' => 'Baru', '1' => 'Diproses', '2' => 'Selesai'] as $nilai => $label)
      <button type="button" class="st-chip {{ $status === (string) $nilai ? 'aktif' : '' }}"
              wire:click="saring('{{ $nilai }}')">{{ $label }}</button>
    @endforeach
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  <div>
    @forelse ($items as $item)
      <a href="{{ route('service-report.detail', $item['id']) }}" wire:navigate
         class="d-block text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2"
         wire:key="sr-{{ $item['id'] }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-width-0">
            <div class="fw-bold">{{ $item['no_report'] ?? '-' }}</div>
            <div class="text-secondary small text-truncate">{{ $item['customer_nama'] ?: '-' }}</div>
          </div>

          <span class="tk-status {{ \App\Livewire\ServiceReport\Daftar::warna((int) $item['status']) }} flex-shrink-0">
            {{ $item['status_label'] ?? '-' }}
          </span>
        </div>

        <div class="tk-meta mt-2">
          <span><i class="bi bi-wrench me-1"></i>{{ $item['jenis'] ?: '-' }}</span>
          <span><i class="bi bi-door-open me-1"></i>{{ $item['department'] ?: '-' }}</span>
        </div>

        @if (! empty($item['ringkasan']))
          <div class="small mt-1 text-truncate">{{ $item['ringkasan'] }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-end mt-1">
          <span class="text-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ $item['created_at'] ? \Illuminate\Support\Carbon::parse($item['created_at'])->format('d M Y H:i') : '—' }}
          </span>
          <span class="text-secondary small text-truncate ms-2">
            <i class="bi bi-person me-1"></i>{{ $item['contact_person'] ?: '-' }}
          </span>
        </div>
      </a>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
          @if ($cari !== '' || $status !== '')
            Tidak ada service report yang cocok.
          @else
            Belum ada service report masuk.
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

  @if ($total > 0)
    <p class="text-secondary small text-center mt-3 mb-0">
      {{ count($items) }} dari {{ $total }} laporan
    </p>
  @endif

  <div class="fab-ruang"></div>
</div>
