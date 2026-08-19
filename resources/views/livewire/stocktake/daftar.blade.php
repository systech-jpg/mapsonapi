<div>
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
      @php
        $total = (int) ($item['total_baris'] ?? 0);
        $terisi = (int) ($item['baris_terisi'] ?? 0);
        $persen = $total > 0 ? round($terisi / $total * 100) : 0;
      @endphp

      <a href="{{ route('stocktake.hitung', $item['rowid']) }}" wire:navigate
         class="d-block text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2"
         wire:key="stk-{{ $item['rowid'] }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-width-0">
            <div class="fw-bold">{{ $item['ref'] ?? '-' }}</div>
            <div class="text-secondary small text-truncate">{{ $item['label'] ?: '-' }}</div>
          </div>

          <span class="tk-status {{ \App\Support\StatusTindakan::warna($item['status_label'] ?? '') }} flex-shrink-0">
            {{ $item['status_label'] ?? '-' }}
          </span>
        </div>

        <div class="tk-meta mt-2">
          <span><i class="bi bi-building me-1"></i>{{ $item['warehouse_name'] ?: '-' }}</span>
          <span><i class="bi bi-calendar3 me-1"></i>{{ $item['periode'] ?? '-' }}</span>
        </div>

        <div class="st-progres mt-2">
          <div class="st-progres-isi" style="width: {{ $persen }}%"></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-1">
          <span class="text-secondary small">{{ $terisi }} dari {{ $total }} barang terhitung</span>

          @if ($item['boleh_isi'] ?? false)
            <span class="small fw-bold text-success"><i class="bi bi-pencil-square me-1"></i>Bisa diisi</span>
          @else
            <span class="small text-secondary"><i class="bi bi-lock-fill me-1"></i>Terkunci</span>
          @endif
        </div>
      </a>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
          Belum ada dokumen stocktake. Dokumennya dibuat di ERP, lalu muncul di sini.
        </div>
      @endif
    @endforelse
  </div>

  <div class="fab-ruang"></div>
</div>
