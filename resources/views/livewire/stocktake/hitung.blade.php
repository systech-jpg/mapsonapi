<div>
  @php
    $bolehIsi = (bool) ($dokumen['boleh_isi'] ?? false);
    $totalBaris = (int) ($ringkasan['total_baris'] ?? 0);
    $terisi = (int) ($ringkasan['terisi'] ?? 0);
    $persen = $totalBaris > 0 ? round($terisi / $totalBaris * 100) : 0;
  @endphp

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

  {{-- Ringkasan dokumen --}}
  <div class="bg-white rounded-4 p-3 shadow-sm mb-2">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div class="min-width-0">
        <div class="fw-bold">{{ $dokumen['ref'] ?? '-' }}</div>
        <div class="text-secondary small text-truncate">{{ $dokumen['label'] ?: '-' }}</div>
      </div>

      <span class="tk-status {{ \App\Support\StatusTindakan::warna($dokumen['status_label'] ?? '') }} flex-shrink-0">
        {{ $dokumen['status_label'] ?? '-' }}
      </span>
    </div>

    <div class="st-progres mt-2">
      <div class="st-progres-isi" style="width: {{ $persen }}%"></div>
    </div>

    <div class="tk-meta mt-2">
      <span><i class="bi bi-check2-square me-1"></i>{{ $terisi }} dari {{ $totalBaris }} terhitung</span>
      <span><i class="bi bi-box-seam me-1"></i>Fisik {{ rtrim(rtrim(number_format((float) ($ringkasan['total_fisik'] ?? 0), 2, ',', '.'), '0'), ',') }}</span>
    </div>

    <div class="tk-meta mt-1">
      <span><i class="bi bi-cpu me-1"></i>Sistem {{ rtrim(rtrim(number_format((float) ($ringkasan['total_teori'] ?? 0), 2, ',', '.'), '0'), ',') }}</span>
      <span><i class="bi bi-exclamation-diamond me-1"></i>{{ $ringkasan['baris_selisih'] ?? 0 }} baris berselisih</span>
    </div>
  </div>

  @unless ($bolehIsi)
    <div class="alert alert-secondary py-2 small">
      <i class="bi bi-lock-fill me-1"></i>
      Dokumen sudah {{ $dokumen['status_label'] ?? 'terkunci' }} di ERP, jadi angkanya hanya bisa dilihat.
    </div>
  @endunless

  {{-- Penyaring principal. Digulir mendatar, bukan dropdown: jumlahnya sedikit
       dan chip lebih cepat diketuk sambil memegang barang. --}}
  @if (count($principals) > 1)
    <div class="st-chips mb-2">
      <button type="button" class="st-chip {{ $principal === '' ? 'aktif' : '' }}" wire:click="pilihPrincipal('')">
        Semua ({{ $totalBaris }})
      </button>

      @foreach ($principals as $p)
        <button type="button" wire:key="prin-{{ $p['id'] }}"
                class="st-chip {{ $principal === (string) $p['id'] ? 'aktif' : '' }}"
                wire:click="pilihPrincipal('{{ $p['id'] }}')">
          {{ $p['nama'] }} ({{ $p['terisi'] }}/{{ $p['total'] }})
        </button>
      @endforeach
    </div>
  @endif

  <div class="input-group mb-2">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Cari kode, nama, atau barcode" aria-label="Cari barang">
    <button type="button" class="btn btn-outline-emas" wire:click="bukaScan" aria-label="Scan barcode">
      <i class="bi bi-upc-scan"></i>
    </button>
  </div>

  <div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" role="switch" id="st-hanya-belum" wire:model.live="hanyaBelum">
    <label class="form-check-label small text-secondary" for="st-hanya-belum">Tampilkan hanya yang belum dihitung</label>
  </div>

  <div class="bg-white rounded-4 shadow-sm overflow-hidden">
    <div class="st-grid st-head">
      <span>Barang</span>
      <span>Rak</span>
      <span>Tray</span>
      <span>Container</span>
      <span>Total</span>
    </div>

    @forelse ($baris as $b)
      @php $detId = (int) $b['det_id']; @endphp

      <div class="st-baris" wire:key="st-{{ $detId }}" data-st-baris="{{ $detId }}">
        <div class="st-grid st-row">
          <div class="min-width-0">
            <div class="st-kode text-truncate">{{ $b['ref'] ?? '-' }}</div>
            <div class="st-nama">{{ $b['label'] ?? '-' }}</div>

            <div class="st-sub">
              <span class="text-truncate">{{ $b['principal_name'] ?? '-' }}</span>

              {{-- Angka sistem dan selisihnya ditaruh di baris keterangan, bukan
                   jadi kolom tersendiri: lima kolom di atas sudah pas-pasan di
                   layar ponsel, dan angka ini dibaca, bukan diketik. --}}
              <span class="flex-shrink-0">
                Sistem {{ rtrim(rtrim(number_format((float) ($b['qty_theoretical'] ?? 0), 2, ',', '.'), '0'), ',') }}
                ·
                <b data-st-selisih data-teori="{{ (float) ($b['qty_theoretical'] ?? 0) }}"
                   class="{{ ($b['selisih'] ?? 0) > 0 ? 'plus' : (($b['selisih'] ?? 0) < 0 ? 'minus' : '') }}">
                  {{ ($b['selisih'] ?? 0) > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) ($b['selisih'] ?? 0), 2, ',', '.'), '0'), ',') }}
                </b>
              </span>

              <button type="button" class="st-catatan-tombol" wire:click="bukaCatatan({{ $detId }})"
                      aria-label="Catatan untuk {{ $b['ref'] ?? '' }}">
                <i class="bi {{ ($isian[$detId]['catatan'] ?? '') !== '' ? 'bi-chat-left-text-fill' : 'bi-chat-left-text' }}"></i>
              </button>
            </div>
          </div>

          {{-- value= ditulis sendiri, tidak dibiarkan diisi Livewire.
               Livewire memasang isi kotak lewat JavaScript SETELAH halaman
               selesai dibaca, sedangkan penjumlah Total berjalan lebih dulu —
               akibatnya semua Total tergambar 0 walau kotaknya berisi angka. --}}
          <input type="text" inputmode="decimal" class="form-control form-control-sm st-qty st-in"
                 wire:model="isian.{{ $detId }}.rak" value="{{ $isian[$detId]['rak'] ?? '' }}"
                 @disabled(! $bolehIsi) aria-label="Rak {{ $b['ref'] ?? '' }}">

          <input type="text" inputmode="decimal" class="form-control form-control-sm st-qty st-in"
                 wire:model="isian.{{ $detId }}.tray" value="{{ $isian[$detId]['tray'] ?? '' }}"
                 @disabled(! $bolehIsi) aria-label="Tray {{ $b['ref'] ?? '' }}">

          <input type="text" inputmode="decimal" class="form-control form-control-sm st-qty st-in"
                 wire:model="isian.{{ $detId }}.container" value="{{ $isian[$detId]['container'] ?? '' }}"
                 @disabled(! $bolehIsi) aria-label="Container {{ $b['ref'] ?? '' }}">

          {{-- Diisi ulang oleh JavaScript setiap ketikan; nilai awal dari server
               supaya angkanya sudah benar walau JS gagal jalan. --}}
          <span class="st-total" data-st-total>{{ rtrim(rtrim(number_format((float) ($b['qty_physical'] ?? 0), 2, '.', ''), '0'), '.') ?: '0' }}</span>
        </div>

        @if ($catatanTerbuka[$detId] ?? false)
          <div class="st-catatan">
            <input type="text" class="form-control form-control-sm"
                   wire:model="isian.{{ $detId }}.catatan" value="{{ $isian[$detId]['catatan'] ?? '' }}"
                   @disabled(! $bolehIsi)
                   maxlength="255" placeholder="Catatan, mis. barang rusak / beda lokasi"
                   aria-label="Catatan {{ $b['ref'] ?? '' }}">
          </div>
        @endif
      </div>
    @empty
      <div class="p-4 text-center text-secondary small">
        @if ($cari !== '' || $hanyaBelum || $principal !== '')
          Tidak ada barang yang cocok dengan saringan ini.
        @elseif (! $galat)
          Dokumen ini belum berisi barang. Tekan "Tarik Data" di ERP lebih dulu.
        @endif
      </div>
    @endforelse
  </div>

  @if ($adaLagi)
    <button type="button" class="btn btn-outline-emas w-100 mt-2" wire:click="muatLagi"
            wire:loading.attr="disabled" wire:target="muatLagi">
      Muat lebih banyak
    </button>
  @endif

  @if ($total > 0)
    <p class="text-secondary small text-center mt-2 mb-0">
      {{ count($baris) }} dari {{ $total }} barang
    </p>
  @endif

  @if ($bolehIsi)
    <div class="st-aksi mt-2">
      <button type="button" class="btn btn-emas flex-fill" wire:click="simpan"
              wire:loading.attr="disabled" wire:target="simpan">
        <span wire:loading.remove wire:target="simpan">
          <i class="bi bi-save me-1"></i>Simpan
          @if ($this->belumDisimpan > 0)
            ({{ $this->belumDisimpan }} baris)
          @endif
        </span>
        <span wire:loading wire:target="simpan">Menyimpan…</span>
      </button>
    </div>

    <div class="fc-ruang"></div>
  @endif

  {{-- Lembar scan. Pola .sheet, bukan modal Bootstrap: modal memindahkan
       elemennya sendiri lewat JS dan berebut DOM dengan Livewire. --}}
  @if ($dialogScan)
    <div class="sheet-backdrop" wire:click="tutupScan"></div>

    <div class="sheet" role="dialog" aria-modal="true" aria-label="Scan barcode">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <strong>Scan barcode</strong>
        <button type="button" class="btn-close" wire:click="tutupScan" aria-label="Tutup"></button>
      </div>

      <div class="scan-layar mb-2">
        <video id="st-scan-video" class="scan-video" playsinline muted></video>
        <div class="scan-bingkai"></div>
      </div>

      <div id="st-scan-galat" class="scan-galat d-none">
        <i class="bi bi-exclamation-triangle-fill me-1"></i><span></span>
      </div>

      <p class="text-secondary small mb-0">
        Barcode yang terbaca dimasukkan ke kotak pencarian, lalu barangnya
        langsung bisa diisi di daftar.
      </p>
    </div>
  @endif
</div>
