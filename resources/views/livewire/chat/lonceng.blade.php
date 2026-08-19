{{-- Tombol Pesan di beranda berikut angka belum dibaca. Tombolnya ikut di
     dalam komponen (bukan cuma angkanya) supaya badge dan tombol tidak bisa
     terpisah saat Livewire menukar potongan DOM. --}}
<a href="{{ route('pesan') }}" wire:navigate class="header-btn position-relative" aria-label="Pesan">
  <i class="bi bi-chat-fill"></i>

  @if ($jumlah > 0)
    <span class="ch-badge ch-badge-pojok">{{ $jumlah > 99 ? '99+' : $jumlah }}</span>
  @endif
</a>
