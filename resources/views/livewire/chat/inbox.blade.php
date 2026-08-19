<div>
  <div class="input-group mb-3">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari nama atau grup" aria-label="Cari percakapan">
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  @forelse ($baris as $b)
    @php
      $tipe = $b['type'] ?? 'personal';
      $tautan = $tipe === 'group'
        ? route('pesan.grup', $b['id'])
        : route('pesan.personal', $b['id']);
      $belum = (int) ($b['unread_count'] ?? 0);
    @endphp

    <a href="{{ $tautan }}" wire:navigate wire:key="chat-{{ $tipe }}-{{ $b['id'] }}"
       class="ch-item text-decoration-none text-dark bg-white rounded-4 p-3 shadow-sm mb-2">
      <span class="ch-avatar {{ $tipe === 'group' ? 'grup' : '' }}">
        @if ($tipe === 'group')
          <i class="bi bi-people-fill"></i>
        @else
          {{ \Illuminate\Support\Str::of($b['name'] ?? '?')->trim()->substr(0, 1)->upper() }}
        @endif
      </span>

      <span class="min-width-0 flex-grow-1">
        <span class="d-flex justify-content-between align-items-baseline gap-2">
          <span class="fw-bold text-truncate">{{ $b['name'] ?? '-' }}</span>
          <span class="text-secondary flex-shrink-0" style="font-size: .72rem;">
            {{ \App\Support\WaktuChat::ringkas($b['last_message_time'] ?? null) }}
          </span>
        </span>

        <span class="d-flex justify-content-between align-items-center gap-2 mt-1">
          <span class="text-secondary small text-truncate">
            {{ $b['last_message'] ?: 'Belum ada pesan' }}
          </span>
          @if ($belum > 0)
            <span class="ch-badge flex-shrink-0">{{ $belum > 99 ? '99+' : $belum }}</span>
          @endif
        </span>
      </span>
    </a>
  @empty
    @if (! $galat)
      <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary">
        <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>
        @if (! empty($item))
          Tidak ada percakapan yang cocok dengan pencarian.
        @else
          Belum ada percakapan. Tekan tombol + untuk memulai.
        @endif
      </div>
    @endif
  @endforelse

  {{-- Baris terakhir harus bisa digulir melewati FAB, bukan tertutup olehnya. --}}
  <div class="fab-ruang"></div>
</div>
