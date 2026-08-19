<div>
  <div class="d-flex gap-2 mb-3">
    <div class="input-group">
      <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
      <input type="date" wire:model.live="tanggal" class="form-control" aria-label="Tanggal order">
    </div>
  </div>

  <div class="input-group mb-3">
    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control"
           placeholder="Cari ref atau pelanggan" aria-label="Cari">
  </div>

  {{-- Penanda memuat tidak lagi digambar di sini. Sekarang ada satu penanda
       bersama di layouts/app.blade.php yang menyala untuk SEMUA permintaan di
       semua halaman, termasuk halaman yang tidak punya blok seperti ini. --}}
  <div>
    @if ($galat)
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>{{ $galat }}</span>
      </div>
    @elseif (empty($this->hasil))
      <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
        Tidak ada sales order pada tanggal ini.
      </div>
    @else
      <div class="d-flex flex-column gap-2">
        @foreach ($this->hasil as $item)
          <div class="bg-white rounded-4 p-3 shadow-sm">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="min-width-0">
                <div class="fw-bold">{{ $item['ref'] ?? '-' }}</div>
                <div class="text-secondary small text-truncate">{{ $item['third_party'] ?? '-' }}</div>
              </div>
              <span class="badge text-bg-light flex-shrink-0">{{ $item['status_label'] ?? '-' }}</span>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-2">
              <span class="text-secondary small">
                {{ $item['planned_delivery_date'] ? \Illuminate\Support\Carbon::parse($item['planned_delivery_date'])->format('d M Y') : '—' }}
              </span>
              <span class="fw-semibold">
                Rp {{ number_format((float) ($item['amount_excl_tax'] ?? 0), 0, ',', '.') }}
              </span>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
