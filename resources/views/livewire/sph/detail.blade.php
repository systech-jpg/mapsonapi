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

  @if ($info)
    <div class="bg-white rounded-4 p-3 shadow-sm mb-2">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div class="min-width-0">
          <div class="fw-bold">{{ $info['ref'] ?? '-' }}</div>
          <div class="text-secondary small">{{ $info['customer_name'] ?: '-' }}</div>
        </div>

        <span class="tk-status {{ \App\Support\StatusTindakan::warna($info['status_label'] ?? '') }} flex-shrink-0">
          {{ $info['status_label'] ?? '-' }}
        </span>
      </div>

      <dl class="tk-info mb-0">
        <dt>Nomor Quotation</dt><dd>{{ $info['ref_quotation'] ?: '-' }}</dd>
        <dt>Principal</dt><dd>{{ $info['principal_name'] ?: '-' }}</dd>
        <dt>Sales</dt><dd>{{ $info['sales_name'] ?: '-' }}</dd>

        <dt>Tanggal SPH</dt>
        <dd>{{ ! empty($info['date_sph']) ? \Illuminate\Support\Carbon::parse($info['date_sph'])->format('d M Y') : '-' }}</dd>

        <dt>Valid until</dt>
        <dd>{{ ! empty($info['date_valid']) ? \Illuminate\Support\Carbon::parse($info['date_valid'])->format('d M Y') : '-' }}</dd>

        <dt>Dibuat oleh</dt><dd>{{ $info['author_name'] ?: '-' }}</dd>

        @if (! empty($info['note']))
          <dt>Note</dt><dd>{{ $info['note'] }}</dd>
        @endif
      </dl>
    </div>

    <h2 class="h6 fw-bold px-1 mt-3 mb-2">Produk/Jasa</h2>

    @forelse ($lines as $line)
      <div class="bg-white rounded-4 p-3 shadow-sm mb-2" wire:key="line-{{ $line['rowid'] }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-width-0">
            <div class="fc-kode">{{ $line['product_ref'] ?: '-' }}</div>
            <div class="fw-semibold">{{ $line['description'] ?: '-' }}</div>
          </div>

          @if ($this->draft())
            <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0"
                    wire:click="hapusBaris({{ (int) $line['rowid'] }})"
                    wire:confirm="Hapus baris ini dari SPH?"
                    wire:loading.attr="disabled" wire:target="hapusBaris">
              <i class="bi bi-trash"></i>
            </button>
          @endif
        </div>

        <div class="tk-meta mt-2">
          <span>Qty {{ rtrim(rtrim(number_format((float) $line['qty'], 2, ',', '.'), '0'), ',') }}</span>
          <span>@ Rp {{ number_format((float) $line['subprice'], 0, ',', '.') }}</span>
          <span>PPN {{ rtrim(rtrim(number_format((float) $line['tva_tx'], 2, ',', '.'), '0'), ',') }}%</span>
          @if ((float) $line['discount_percent'] > 0)
            <span>Disc {{ rtrim(rtrim(number_format((float) $line['discount_percent'], 2, ',', '.'), '0'), ',') }}%</span>
          @endif
        </div>

        <div class="d-flex justify-content-between align-items-end mt-1">
          <span class="text-secondary small">Total HT</span>
          <span class="fw-bold">Rp {{ number_format((float) $line['total_ht'], 0, ',', '.') }}</span>
        </div>
      </div>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary small">
          Belum ada baris yang ditambahkan.
        </div>
      @endif
    @endforelse

    @if (! empty($lines))
      <div class="bg-white rounded-4 p-3 shadow-sm mt-2">
        <div class="d-flex justify-content-between">
          <span class="text-secondary">Total sebelum PPN</span>
          <span class="fw-semibold">Rp {{ number_format($this->totalHt(), 0, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between mt-1">
          <span class="text-secondary">Total setelah PPN</span>
          <span class="fw-bold">Rp {{ number_format($this->totalTtc(), 0, ',', '.') }}</span>
        </div>
      </div>
    @endif

    {{-- Form tambah baris hanya saat Draft, penjagaan yang sama dengan
         card.php: begitu divalidasi, isinya tidak boleh berubah lagi. --}}
    @if ($this->draft())
      <div class="bg-white rounded-4 p-3 shadow-sm mt-3">
        <h3 class="h6 fw-bold mb-2">Tambah Baris Baru</h3>

        <div class="mb-2">
          <label class="form-label small text-secondary">Produk</label>
          <button type="button" class="form-select text-start" wire:click="bukaProduk">
            {{ $kodeProduk ?: 'Pilih produk…' }}
          </button>
        </div>

        <div class="mb-2">
          <label class="form-label small text-secondary" for="deskripsi">Deskripsi penawaran</label>
          <textarea id="deskripsi" wire:model="deskripsi" rows="2" class="form-control"
                    placeholder="Otomatis dari produk, bisa diubah"></textarea>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <label class="form-label small text-secondary" for="qty">Qty</label>
            <input type="text" inputmode="decimal" id="qty" wire:model.live.debounce.400ms="qty"
                   class="form-control @error('qty') is-invalid @enderror">
            @error('qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-6">
            <label class="form-label small text-secondary" for="subprice">Unit price (excl.)</label>
            <input type="text" inputmode="decimal" id="subprice" wire:model.live.debounce.400ms="subprice"
                   class="form-control @error('subprice') is-invalid @enderror">
            @error('subprice') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-6">
            <label class="form-label small text-secondary" for="tva_tx">PPN (%)</label>
            <input type="text" inputmode="decimal" id="tva_tx" wire:model="tva_tx"
                   class="form-control @error('tva_tx') is-invalid @enderror">
            @error('tva_tx') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-6">
            <label class="form-label small text-secondary" for="discount_percent">Disc. (%)</label>
            <input type="text" inputmode="decimal" id="discount_percent" wire:model.live.debounce.400ms="discount_percent"
                   class="form-control @error('discount_percent') is-invalid @enderror">
            @error('discount_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
        </div>

        {{-- Total dihitung ulang di server saat disimpan; yang ini pratinjau,
             memakai rumus yang sama supaya angkanya tidak pernah berbeda. --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
          <span class="text-secondary small">Total HT baris ini</span>
          <span class="fw-bold">Rp {{ number_format($this->totalBarisBaru(), 0, ',', '.') }}</span>
        </div>

        <button type="button" class="btn btn-emas w-100 mt-2" wire:click="tambahBaris"
                wire:loading.attr="disabled" wire:target="tambahBaris">
          <span wire:loading.remove wire:target="tambahBaris"><i class="bi bi-plus-lg me-1"></i> Tambah Baris</span>
          <span wire:loading wire:target="tambahBaris">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan…
          </span>
        </button>
      </div>
    @endif

    {{-- Bilah aksi, isinya sama dengan deretan tombol di bawah card ERP. --}}
    <div class="fc-aksi mt-3">
      <a href="{{ route('sph.pdf', $sphId) }}" class="btn btn-outline-emas flex-fill">
        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
      </a>

      @if ($this->draft())
        <a href="{{ route('sph.ubah', $sphId) }}" wire:navigate class="btn btn-outline-emas flex-fill">
          <i class="bi bi-pencil me-1"></i> Ubah
        </a>

        <button type="button" class="btn btn-emas flex-fill"
                wire:click="validasi"
                wire:confirm="Nomor resmi akan terbit dan isi SPH tidak bisa diubah lagi. Lanjutkan?"
                wire:loading.attr="disabled" wire:target="validasi">
          <i class="bi bi-check2-circle me-1"></i> Validasi
        </button>
      @else
        <button type="button" class="btn btn-outline-emas flex-fill"
                wire:click="bukaKembali"
                wire:confirm="Buka kembali SPH ini menjadi Draft?"
                wire:loading.attr="disabled" wire:target="bukaKembali">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Buka Lagi
        </button>
      @endif
    </div>

    <div class="fc-ruang"></div>
  @endif

  {{-- Lembar pilih produk. Memakai pola .sheet, bukan modal Bootstrap. --}}
  @if ($sheetProduk)
    <div class="sheet-backdrop" wire:click="tutupProduk"></div>

    <div class="sheet" role="dialog" aria-modal="true" aria-label="Pilih produk">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 fw-bold mb-0">Pilih Produk</h2>
        <button type="button" class="btn-close" wire:click="tutupProduk" aria-label="Tutup"></button>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
        <input type="search" wire:model.live.debounce.350ms="cariProduk" class="form-control border-start-0 ps-0"
               placeholder="Cari kode atau nama produk" aria-label="Cari produk">
      </div>

      <div wire:loading wire:target="cariProduk" class="text-center text-secondary py-3">
        <span class="spinner-border spinner-border-sm" role="status"></span>
        <span class="ms-2 small">Mencari…</span>
      </div>

      <div class="sheet-hasil" wire:loading.remove wire:target="cariProduk">
        @forelse ($hasilProduk as $p)
          <button type="button" class="sheet-item" wire:key="prod-{{ $p['rowid'] }}"
                  wire:click="pilihProduk({{ (int) $p['rowid'] }})">
            <span class="fc-kode">{{ $p['ref'] }}</span>
            <span class="fc-nama">{{ $p['label'] }}</span>
          </button>
        @empty
          <p class="text-secondary small text-center py-3 mb-0">
            Tidak ada produk yang cocok. Coba kata kunci lain.
          </p>
        @endforelse
      </div>
    </div>
  @endif
</div>
