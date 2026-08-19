<div>
  <div class="input-group mb-3">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari nomor SPH, quotation, atau pelanggan" aria-label="Cari SPH">
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  {{-- Penanda memuat tidak lagi digambar di sini. Sekarang ada satu penanda
       bersama di layouts/app.blade.php yang menyala untuk SEMUA permintaan di
       semua halaman, termasuk halaman yang tidak punya blok seperti ini. --}}
  <div>
    @forelse ($items as $item)
      <a href="{{ route('sph.detail', $item['rowid']) }}" wire:navigate
         class="d-block text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2"
         wire:key="sph-{{ $item['rowid'] }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-width-0">
            <div class="fw-bold">{{ $item['ref'] ?? '-' }}</div>
            <div class="text-secondary small text-truncate">{{ $item['customer_name'] ?: '-' }}</div>
          </div>

          {{-- Warnanya lewat StatusTindakan supaya "Draft" di modul mana pun
               berwarna sama: abu untuk Draft, hijau untuk Validated. --}}
          <span class="tk-status {{ \App\Support\StatusTindakan::warna($item['status_label'] ?? '') }} flex-shrink-0">
            {{ $item['status_label'] ?? '-' }}
          </span>
        </div>

        <div class="tk-meta mt-2">
          <span><i class="bi bi-building me-1"></i>{{ $item['principal_name'] ?: '-' }}</span>
          <span><i class="bi bi-person-badge me-1"></i>{{ $item['sales_name'] ?: '-' }}</span>
        </div>

        <div class="d-flex justify-content-between align-items-end mt-1">
          <span class="text-secondary small">
            <i class="bi bi-calendar3 me-1"></i>
            {{ $item['date_sph'] ? \Illuminate\Support\Carbon::parse($item['date_sph'])->format('d M Y') : '—' }}
          </span>

          @if (! empty($item['ref_quotation']))
            <span class="text-secondary small">Quot: {{ $item['ref_quotation'] }}</span>
          @endif
        </div>
      </a>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
          @if ($cari !== '')
            Tidak ada SPH yang cocok dengan pencarian.
          @else
            Belum ada SPH. Tekan tombol Buat SPH untuk membuat yang pertama.
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
      {{ count($items) }} dari {{ $total }} dokumen
    </p>
  @endif

  <div class="fab-ruang"></div>
</div>
