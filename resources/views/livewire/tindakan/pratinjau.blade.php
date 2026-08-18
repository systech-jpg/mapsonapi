<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  @if ($pesan)
    <div class="alert alert-info py-2 small" wire:key="pesan-{{ md5($pesan) }}">
      <i class="bi bi-info-circle-fill me-1"></i>{{ $pesan }}
    </div>
  @endif

  @if ($usage)
    @php $label = $usage['status_label'] ?? '-'; @endphp

    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
      <div class="min-width-0">
        <div class="fw-bold">{{ $usage['ref'] ?? '-' }}</div>
        <div class="text-secondary small text-truncate">
          {{ $usage['tindakan_ref'] ?? '-' }} · {{ $usage['rs_name'] ?? '-' }}
        </div>
      </div>
      <span class="tk-status {{ \App\Support\StatusTindakan::warna($label) }} flex-shrink-0">{{ $label }}</span>
    </div>

    @if ($this->terkunci())
      <div class="alert alert-secondary py-2 small">
        Laporan sudah divalidasi dan tidak dapat diubah lagi dari aplikasi.
      </div>
    @elseif ($this->barisSalah())
      <div class="alert alert-danger py-2 small">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        Qty pakai melebihi qty kirim pada
        {{ implode(', ', array_column($this->barisSalah(), 'ref')) }}.
        Betulkan lewat tombol Perbaiki — laporan belum bisa divalidasi.
      </div>
    @else
      <div class="alert alert-warning py-2 small">
        Periksa angkanya sekali lagi. Setelah divalidasi, laporan ini terkunci —
        perubahan hanya bisa dilakukan lewat ERP.
      </div>
    @endif
  @endif

  <div class="bg-white rounded-4 shadow-sm overflow-hidden">
    <div class="tk-grid tk-head">
      <span>Produk</span>
      <span>Kirim</span>
      <span>Pakai</span>
      <span>Kembali</span>
    </div>

    @forelse ($baris as $b)
      @php $salah = $b['pakai'] > $b['kirim'] || $b['pakai'] < 0; @endphp

      <div class="tk-grid tk-row {{ $salah ? 'tk-salah' : '' }}" wire:key="pv-{{ $loop->index }}">
        <div class="min-width-0">
          <div class="fc-kode text-truncate">{{ $b['ref'] }}</div>
          <div class="fc-nama">{{ $b['nama'] }}</div>
        </div>

        <span class="tk-angka">{{ $b['kirim'] }}</span>
        <span class="tk-angka fw-bold">{{ $b['pakai'] }}</span>
        <span class="tk-angka">{{ $b['kembali'] }}</span>
      </div>
    @empty
      <div class="p-4 text-center text-secondary small">
        @if (! $galat)
          Belum ada baris barang di laporan pemakaian ini.
        @endif
      </div>
    @endforelse
  </div>

  @if ($usage)
    <div class="fc-aksi mt-2">
      @if ($this->terkunci())
        <a href="{{ route('tindakan.detail', $tindakanId) }}" wire:navigate class="btn btn-outline-emas flex-fill">
          Kembali
        </a>

        <a href="{{ route('tindakan.surat-jalan', $tindakanId) }}" class="btn btn-emas flex-fill">
          <i class="bi bi-file-earmark-pdf me-1"></i> Surat Jalan
        </a>
      @else
        <a href="{{ route('tindakan.detail', $tindakanId) }}" wire:navigate class="btn btn-outline-emas flex-fill">
          <i class="bi bi-pencil me-1"></i> Perbaiki
        </a>

        <button type="button" class="btn btn-emas flex-fill"
                wire:click="validasi"
                wire:confirm="Laporan pemakaian akan divalidasi dan tidak bisa diubah lagi. Lanjutkan?"
                @disabled(! $this->bisaValidasi())
                wire:loading.attr="disabled" wire:target="validasi">
          <span wire:loading.remove wire:target="validasi"><i class="bi bi-check2-circle me-1"></i> Validasi</span>
          <span wire:loading wire:target="validasi">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Memvalidasi…
          </span>
        </button>
      @endif
    </div>

    <div class="fc-ruang"></div>
  @endif
</div>
