<div>
  <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
    <span class="badge text-bg-light fs-6 fw-bold">{{ $ref }}</span>

    @if ($terkunci)
      <span class="badge text-bg-success"><i class="bi bi-lock-fill me-1"></i>Final</span>
    @else
      <span class="badge text-bg-warning">Draft</span>
    @endif
  </div>

  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
    </div>
  @endif

  @if ($pesan)
    <div class="alert alert-info py-2 small mb-2" wire:key="pesan-{{ $pesan }}">
      <i class="bi bi-info-circle-fill me-1"></i>{{ $pesan }}
    </div>
  @endif

  @if ($terkunci)
    <div class="alert alert-secondary py-2 small">
      Dokumen sudah divalidasi menjadi Final dan tidak dapat diubah lagi.
    </div>
  @endif

  <div class="input-group mb-2">
    <span class="input-group-text bg-white border-end-0"><i class="bi bi-funnel text-secondary"></i></span>
    <input type="search" wire:model.live.debounce.300ms="cari" class="form-control border-start-0 ps-0"
           placeholder="Saring kode / nama produk" aria-label="Saring produk">
  </div>

  @unless ($terkunci)
    <div class="d-flex gap-2 mb-2">
      <button type="button" class="btn btn-outline-emas btn-sm flex-fill" wire:click="bukaDialog">
        <i class="bi bi-plus-lg me-1"></i>Tambah Produk
      </button>

      <button type="button" class="btn btn-outline-emas btn-sm flex-fill"
              wire:click="isiRekomendasi"
              wire:confirm="Semua kolom Forecast akan diisi sesuai angka rekomendasi. Angka yang sudah Anda ketik akan tertimpa. Lanjutkan?">
        <i class="bi bi-magic me-1"></i>Isi Rekom.
      </button>
    </div>
  @endunless

  <div class="bg-white rounded-4 shadow-sm overflow-hidden">
    <div class="fc-grid fc-head">
      <span>Produk</span>
      <span>Buffer</span>
      <span>Saldo</span>
      <span>Rekom.</span>
      <span>Forecast</span>
    </div>

    @forelse ($this->hasil as $baris)
      @php
        $id = (int) $baris['product_id'];
        $rekom = (int) ($baris['rekomendasi_butuh'] ?? 0);
      @endphp

      <div class="fc-grid fc-row" wire:key="fc-{{ $id }}">
        <div class="min-width-0">
          <div class="fc-kode text-truncate">{{ $baris['product_kode'] ?? '-' }}</div>
          <div class="fc-nama">{{ $baris['product_name'] ?? '-' }}</div>
        </div>

        <span class="fc-angka">{{ $baris['buffer'] ?? 0 }}</span>
        <span class="fc-angka">{{ $baris['saldo_akhir'] ?? 0 }}</span>
        <span class="fc-angka fc-rekom {{ $rekom === 0 ? 'nol' : '' }}">{{ $rekom }}</span>

        <input type="number" min="0" inputmode="numeric"
               class="form-control form-control-sm fc-qty"
               wire:model="qty.{{ $id }}"
               @disabled($terkunci)
               aria-label="Qty forecast {{ $baris['product_kode'] ?? '' }}">
      </div>
    @empty
      <div class="p-4 text-center text-secondary small">
        @if (! empty($produk))
          Produk tidak ditemukan.
        @elseif (! $galat)
          Tidak ada produk untuk principal ini.
        @endif
      </div>
    @endforelse
  </div>

  @unless ($terkunci)
    <div class="fc-aksi mt-2">
      <button type="button" class="btn btn-outline-emas flex-fill"
              wire:click="simpan(false)"
              wire:loading.attr="disabled" wire:target="simpan">
        Simpan Draft
      </button>

      <button type="button" class="btn btn-emas flex-fill"
              wire:click="simpan(true)"
              wire:confirm="Dokumen akan langsung divalidasi menjadi Final dan tidak dapat diubah lagi. Lanjutkan?"
              wire:loading.attr="disabled" wire:target="simpan">
        Submit Final
      </button>
    </div>

    <div class="fc-ruang"></div>
  @endunless

  {{-- Dialog tambah produk.
       Sengaja tidak memakai modal bawaan Bootstrap: modal itu memindahkan dan
       melepas elemen lewat JS-nya sendiri, sementara Livewire menggambar ulang
       DOM yang sama, sehingga backdrop mudah tertinggal saat isinya berubah. --}}
  @if ($dialogTambah)
    <div class="sheet-backdrop" wire:click="tutupDialog"></div>

    <div class="sheet" role="dialog" aria-modal="true" aria-label="Tambah produk">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 fw-bold mb-0">Tambah Produk</h2>
        <button type="button" class="btn-close" wire:click="tutupDialog" aria-label="Tutup"></button>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
        {{-- Ditunda 350 ms supaya tidak menembak API tiap huruf yang diketik. --}}
        <input type="search" wire:model.live.debounce.350ms="cariTambah" class="form-control border-start-0 ps-0"
               placeholder="Cari kode atau nama produk" aria-label="Cari produk">
      </div>

      <div wire:loading wire:target="cariTambah" class="text-center text-secondary py-3">
        <span class="spinner-border spinner-border-sm" role="status"></span>
        <span class="ms-2 small">Mencari…</span>
      </div>

      <div class="sheet-hasil" wire:loading.remove wire:target="cariTambah">
        @forelse ($hasilTambah as $item)
          <button type="button" class="sheet-item" wire:key="cari-{{ $item['id'] }}"
                  wire:click="tambahProduk({{ (int) $item['id'] }})"
                  wire:loading.attr="disabled" wire:target="tambahProduk">
            <span class="fc-kode">{{ $item['ref'] ?? '-' }}</span>
            <span class="fc-nama">{{ $item['label'] ?? '-' }}</span>
          </button>
        @empty
          <p class="text-secondary small text-center py-3 mb-0">{{ $pesanTambah }}</p>
        @endforelse
      </div>

      @if ($pesanTambah && ! empty($hasilTambah))
        <p class="text-secondary small mt-2 mb-0">{{ $pesanTambah }}</p>
      @endif

      <button type="button" class="btn btn-light w-100 mt-3" wire:click="tutupDialog">Tutup</button>
    </div>
  @endif
</div>
