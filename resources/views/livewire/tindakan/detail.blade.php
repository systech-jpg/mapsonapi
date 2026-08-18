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
    @php $label = $this->labelStatus(); @endphp

    <div class="bg-white rounded-4 p-3 shadow-sm mb-2">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div class="min-width-0">
          <div class="fw-bold">{{ $info['ref'] ?? '-' }}</div>
          <div class="text-secondary small">{{ $info['rs_name'] ?? '-' }}</div>
        </div>
        <span class="tk-status {{ \App\Support\StatusTindakan::warna($label) }} flex-shrink-0">{{ $label }}</span>
      </div>

      <dl class="tk-info mb-0">
        <dt>Pasien</dt>
        <dd>
          {{ $info['pasien'] ?: '-' }}
          @if (! empty($info['pasien_dob']))
            <span class="text-secondary">({{ \Illuminate\Support\Carbon::parse($info['pasien_dob'])->format('d M Y') }})</span>
          @endif
        </dd>

        <dt>Dokter</dt><dd>{{ $info['dokter_name'] ?: '-' }}</dd>

        <dt>Jadwal</dt>
        <dd>
          {{ ! empty($info['tanggal']) ? \Illuminate\Support\Carbon::parse($info['tanggal'])->format('d M Y') : '-' }}
          {{ $info['waktu'] ?? '' }}
        </dd>

        <dt>TS / PIC</dt><dd>{{ trim(($info['ts_firstname'] ?? '') . ' ' . ($info['ts_lastname'] ?? '')) ?: '-' }}</dd>
        <dt>Jenis</dt><dd>{{ $info['jenis_tindakan'] ?: '-' }}</dd>
        <dt>Pesanan</dt><dd>{{ $info['rencana_alat'] ?: '-' }}</dd>

        @if (! empty($info['diagnosa']))
          <dt>Catatan</dt><dd>{{ $info['diagnosa'] }}</dd>
        @endif

        @if (! empty($info['ref_sj']))
          <dt>Surat jalan</dt><dd>{{ $info['ref_sj'] }}</dd>
        @endif

        @if ($usage)
          <dt>Laporan</dt><dd>{{ $usage['ref'] ?? '-' }} · {{ $usage['status_label'] ?? '-' }}</dd>
        @endif
      </dl>
    </div>

    {{-- Jadwal Draft belum bisa diapa-apakan selain divalidasi. Kalau tombolnya
         tidak digambar, sebabnya ditulis di sini supaya petugas tahu harus
         menunggu siapa — bukan mengira halamannya rusak. --}}
    @if ($this->jadwalDraft() && ! $this->bisaValidasiJadwal())
      <div class="alert alert-secondary py-2 small">
        Jadwal ini masih Draft. Validasi dilakukan oleh TS yang ditugaskan
        ({{ trim(($info['ts_firstname'] ?? '') . ' ' . ($info['ts_lastname'] ?? '')) ?: 'belum diisi' }}).
      </div>
    @endif

    <h2 class="h6 fw-bold px-1 mt-3 mb-2">
      {{ $this->sisiTs() ? 'Laporan Pemakaian / Daftar Alat' : 'Daftar Paket & Implant' }}
    </h2>

    @if ($this->bisaIsi())
      <p class="text-secondary small px-1 mb-2">
        Isi kolom <strong>Pakai</strong>. Kolom Kembali dihitung sendiri
        (Kirim − Pakai). Angka baru tersimpan setelah tombol Simpan ditekan.
      </p>
    @endif

    @forelse ($seksi as $s)
      {{-- <details> dipakai sebagai accordion supaya tidak perlu JavaScript
           Bootstrap yang bertabrakan dengan penggambaran ulang Livewire. --}}
      <details class="tk-seksi bg-white rounded-4 shadow-sm mb-2" open wire:key="seksi-{{ $loop->index }}">
        <summary class="tk-seksi-judul">
          <i class="bi bi-chevron-right"></i>
          <span>{{ $s['judul'] }}</span>
        </summary>

        @foreach ($s['kits'] as $kit)
          <div class="tk-kit">
            <span class="min-width-0">{{ $kit['ref'] }} — {{ $kit['label'] }}</span>
            <span class="flex-shrink-0">Qty {{ $kit['qty'] }}</span>
          </div>

          @if ($kit['note'])
            <p class="tk-note">{{ $kit['note'] }}</p>
          @endif

          <div class="tk-grid tk-head">
            <span>Produk</span>
            <span>Kirim</span>
            <span>Pakai</span>
            <span>Kembali</span>
          </div>

          @forelse ($kit['baris'] as $baris)
            @php
              // Baris cacat bisa datang dari data lama atau dari perubahan di
              // ERP, jadi ditandai sejak digambar — bukan hanya saat diketik.
              $nilai = (int) ($qty[$baris['id']] ?? $baris['pakai']);
              $salah = $nilai > $baris['kirim'] || $nilai < 0;
            @endphp

            <div class="tk-grid tk-row {{ $salah ? 'tk-salah' : '' }}"
                 wire:key="baris-{{ $loop->parent->index }}-{{ $baris['id'] ?? $loop->index }}">
              <div class="min-width-0">
                <div class="fc-kode text-truncate">{{ $baris['ref'] }}</div>
                <div class="fc-nama">{{ $baris['nama'] }}</div>
              </div>

              <span class="tk-angka">{{ $baris['kirim'] }}</span>

              @if ($this->bisaIsi() && $baris['id'])
                {{-- Hitungan Kembali dikerjakan di browser: memakai wire:model.live
                     berarti satu request per ketikan untuk angka yang tidak perlu
                     dikirim ke server sampai tombol Simpan ditekan. --}}
                <input type="number" min="0" max="{{ $baris['kirim'] }}" inputmode="numeric"
                       class="form-control form-control-sm fc-qty"
                       wire:model="qty.{{ $baris['id'] }}"
                       oninput="tkHitungKembali(this, {{ $baris['kirim'] }})"
                       aria-label="Qty terpakai {{ $baris['ref'] }}">
              @else
                <span class="tk-angka">{{ $baris['pakai'] }}</span>
              @endif

              <span class="tk-angka" data-kembali>{{ $baris['kembali'] }}</span>
            </div>
          @empty
            <p class="tk-note mb-0">Kit ini belum punya rincian barang.</p>
          @endforelse
        @endforeach
      </details>
    @empty
      @if (! $galat)
        <div class="bg-white rounded-4 p-4 shadow-sm text-center text-secondary small">
          Belum ada Paket Tray maupun Set Implant untuk tindakan ini.
        </div>
      @endif
    @endforelse

    {{-- Form bukti tarik barang, mengikuti "Upload Bukti Tarik Barang" di
         halaman usage ERP. Tanpa capture="environment": dengan atribut itu
         ponsel langsung membuka kamera dan galeri tidak bisa dipilih, padahal
         foto sering sudah diambil lebih dulu di gudang. --}}
    @if ($this->bisaTarikBarang())
      <div class="bg-white rounded-4 p-3 shadow-sm mt-3">
        <h3 class="h6 fw-bold mb-1">Bukti Tarik Barang</h3>
        <p class="text-secondary small mb-2">
          Wajib. Ambil foto langsung dengan kamera, atau pilih dari galeri.
        </p>

        <input type="file" accept="image/*" wire:model="bukti"
               class="form-control @error('bukti') is-invalid @enderror"
               aria-label="Foto bukti tarik barang">

        @error('bukti') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

        <div wire:loading wire:target="bukti" class="text-secondary small mt-2">
          <span class="spinner-border spinner-border-sm" role="status"></span>
          <span class="ms-1">Mengunggah foto…</span>
        </div>

        @if ($bukti)
          <div wire:loading.remove wire:target="bukti" class="mt-2">
            <img src="{{ $bukti->temporaryUrl() }}" alt="Pratinjau bukti tarik barang" class="tk-bukti">
          </div>
        @endif
      </div>
    @elseif ($this->adaBukti())
      <div class="bg-white rounded-4 p-3 shadow-sm mt-3">
        <h3 class="h6 fw-bold mb-2">Bukti Tarik Barang</h3>
        <a href="{{ route('tindakan.bukti-tarik', $tindakanId) }}" target="_blank" rel="noopener">
          <img src="{{ route('tindakan.bukti-tarik', $tindakanId) }}" alt="Bukti tarik barang" class="tk-bukti">
        </a>
      </div>
    @endif

    {{-- Bilah aksi: isinya mengikuti peran dan status, sama seperti di Android. --}}
    @php
      $adaAksi = $this->bisaIsi() || $this->bisaValidasiJadwal() || $this->usageTerkunci()
                 || $this->bisaKonfirmasiSampai() || $this->bisaTarikBarang();
    @endphp

    @if ($adaAksi)
      <div class="fc-aksi mt-2">
        @if ($this->sisiTs())
          @if ($this->bisaValidasiJadwal())
            <a href="{{ route('tindakan.ubah', $tindakanId) }}" wire:navigate class="btn btn-outline-emas flex-fill">
              <i class="bi bi-pencil me-1"></i> Ubah
            </a>

            <button type="button" class="btn btn-emas flex-fill"
                    wire:click="validasiJadwal"
                    wire:confirm="Jadwal akan divalidasi dan nomor referensi resmi terbit. Lanjutkan?"
                    wire:loading.attr="disabled" wire:target="validasiJadwal">
              <i class="bi bi-check2-circle me-1"></i> Validasi
            </button>
          @elseif ($this->bisaIsi())
            <button type="button" class="btn btn-emas flex-fill"
                    wire:click="simpanDraft"
                    wire:loading.attr="disabled" wire:target="simpanDraft">
              <span wire:loading.remove wire:target="simpanDraft"><i class="bi bi-save me-1"></i> Simpan &amp; Pratinjau</span>
              <span wire:loading wire:target="simpanDraft">
                <span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan…
              </span>
            </button>
          @elseif ($this->usageTerkunci())
            <a href="{{ route('tindakan.pratinjau', $tindakanId) }}" wire:navigate class="btn btn-outline-emas flex-fill">
              <i class="bi bi-list-check me-1"></i> Lihat Laporan
            </a>

            <a href="{{ route('tindakan.surat-jalan', $tindakanId) }}" class="btn btn-emas flex-fill">
              <i class="bi bi-file-earmark-pdf me-1"></i> Surat Jalan
            </a>
          @endif
        @else
          @if ($this->bisaKonfirmasiSampai())
            <button type="button" class="btn btn-emas flex-fill"
                    wire:click="konfirmasiSampai"
                    wire:confirm="Konfirmasi bahwa barang sudah sampai di rumah sakit?"
                    wire:loading.attr="disabled" wire:target="konfirmasiSampai">
              <i class="bi bi-box-seam me-1"></i> Barang Sampai
            </button>
          @endif

          @if ($this->bisaTarikBarang())
            {{-- Dikunci selama foto belum dipilih: tanpa bukti, server pasti
                 menolak dengan 422 dan status dokumen tidak berubah. --}}
            <button type="button" class="btn btn-emas flex-fill"
                    wire:click="tarikBarang"
                    wire:confirm="Tarik barang untuk laporan pemakaian ini?"
                    @disabled(! $bukti)
                    wire:loading.attr="disabled" wire:target="tarikBarang, bukti">
              <span wire:loading.remove wire:target="tarikBarang">
                <i class="bi bi-arrow-down-square me-1"></i> Tarik Barang
              </span>
              <span wire:loading wire:target="tarikBarang">
                <span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim…
              </span>
            </button>
          @endif

          @if ($this->usageTerkunci())
            <a href="{{ route('tindakan.surat-jalan', $tindakanId) }}" class="btn btn-outline-emas flex-fill">
              <i class="bi bi-file-earmark-pdf me-1"></i> Surat Jalan
            </a>
          @endif
        @endif
      </div>

      <div class="fc-ruang"></div>
    @endif
  @endif
</div>
