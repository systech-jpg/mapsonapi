<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-start gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
    </div>
  @endif

  @if ($langkah === 'isian')
    <form wire:submit="lanjut" class="bg-white rounded-4 p-3 shadow-sm">
      <div class="row g-2 mb-3">
        <div class="col-7">
          <label for="tk-tanggal" class="form-label fw-semibold">Tanggal operasi <span class="text-danger">*</span></label>
          <input type="date" id="tk-tanggal" wire:model="tanggal"
                 class="form-control @error('tanggal') is-invalid @enderror">
          @error('tanggal') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-5">
          <label for="tk-waktu" class="form-label fw-semibold">Jam</label>
          <input type="time" id="tk-waktu" wire:model="waktu"
                 class="form-control @error('waktu') is-invalid @enderror">
          @error('waktu') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Rumah sakit / mitra <span class="text-danger">*</span></label>
        {{-- Tombol, bukan input: nilainya hanya boleh datang dari hasil
             pencarian supaya id-nya pasti ikut terisi. --}}
        <button type="button" class="tk-pilih @error('rsId') is-invalid @enderror" wire:click="bukaDialog('rs')">
          <span class="{{ $rsNama ? '' : 'text-secondary' }}">{{ $rsNama ?: 'Pilih rumah sakit' }}</span>
          <i class="bi bi-search"></i>
        </button>
        @error('rsId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Dokter operator <span class="text-danger">*</span></label>
        <button type="button" class="tk-pilih" wire:click="bukaDialog('dokter')">
          <span class="{{ $dokterNama ? '' : 'text-secondary' }}">{{ $dokterNama ?: 'Pilih dokter' }}</span>
          <i class="bi bi-search"></i>
        </button>
        @error('dokterId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="mb-3">
        <label for="tk-ts" class="form-label fw-semibold">TS / PIC lapangan <span class="text-danger">*</span></label>
        <select id="tk-ts" wire:model="tsId" class="form-select @error('tsId') is-invalid @enderror">
          <option value="">— Pilih TS —</option>
          @foreach ($tsList as $ts)
            <option value="{{ $ts['id'] }}">{{ $ts['label'] }}</option>
          @endforeach
        </select>
        @error('tsId') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="mb-3">
        <label for="tk-jenis" class="form-label fw-semibold">Jenis tindakan</label>
        <input type="text" id="tk-jenis" wire:model="jenisTindakan" class="form-control"
               placeholder="Contoh: ORIF Femur">
      </div>

      <div class="row g-2 mb-3">
        <div class="col-7">
          <label for="tk-pasien" class="form-label fw-semibold">Nama pasien <span class="text-danger">*</span></label>
          <input type="text" id="tk-pasien" wire:model="pasien"
                 class="form-control @error('pasien') is-invalid @enderror">
          @error('pasien') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-5">
          <label for="tk-dob" class="form-label fw-semibold">Tgl lahir</label>
          <input type="date" id="tk-dob" wire:model="pasienDob"
                 class="form-control @error('pasienDob') is-invalid @enderror">
          @error('pasienDob') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="mb-3">
        <label for="tk-alat" class="form-label fw-semibold">Pesanan / alat <span class="text-danger">*</span></label>
        <textarea id="tk-alat" wire:model="rencanaAlat" rows="3"
                  class="form-control @error('rencanaAlat') is-invalid @enderror"
                  placeholder="Tulis paket tray dan set implant yang diminta"></textarea>
        @error('rencanaAlat') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="mb-4">
        <label for="tk-catatan" class="form-label fw-semibold">Catatan lain</label>
        <textarea id="tk-catatan" wire:model="diagnosa" rows="2" class="form-control"></textarea>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ $tindakanId ? route('tindakan.detail', $tindakanId) : route('tindakan') }}" wire:navigate
           class="btn btn-light flex-fill py-2">Batal</a>

        <button type="submit" class="btn btn-emas flex-fill py-2 fw-semibold">
          <i class="bi bi-arrow-right-circle me-1"></i> Lanjut
        </button>
      </div>
    </form>

    <p class="text-secondary small mt-3 mb-0 px-1">
      Isian bertanda <span class="text-danger">*</span> wajib. Setelah Lanjut, data
      ditampilkan sekali lagi untuk diperiksa sebelum benar-benar disimpan.
    </p>
  @else
    <div class="bg-white rounded-4 p-3 shadow-sm">
      <h2 class="h6 fw-bold mb-3">Periksa data jadwal</h2>

      <dl class="tk-info mb-0">
        <dt>Rumah sakit</dt><dd>{{ $rsNama ?: '-' }}</dd>
        <dt>Dokter</dt><dd>{{ $dokterNama ?: '-' }}</dd>
        <dt>TS / PIC</dt><dd>{{ $this->namaTs() }}</dd>
        <dt>Tanggal</dt><dd>{{ $tanggal ? \Illuminate\Support\Carbon::parse($tanggal)->format('d M Y') : '-' }} {{ $waktu }}</dd>
        <dt>Jenis tindakan</dt><dd>{{ $jenisTindakan ?: '-' }}</dd>
        <dt>Pasien</dt><dd>{{ $pasien ?: '-' }}</dd>
        <dt>Tgl lahir</dt><dd>{{ $pasienDob ? \Illuminate\Support\Carbon::parse($pasienDob)->format('d M Y') : '-' }}</dd>
        <dt>Pesanan / alat</dt><dd>{{ $rencanaAlat ?: '-' }}</dd>
        <dt>Catatan lain</dt><dd>{{ $diagnosa ?: '-' }}</dd>
      </dl>
    </div>

    <div class="d-flex gap-2 mt-3">
      <button type="button" class="btn btn-light flex-fill py-2" wire:click="kembaliIsian">
        <i class="bi bi-pencil me-1"></i> Ubah
      </button>

      {{-- Dokumen terbentuk di server begitu request terkirim, jadi tombol
           dikunci selama proses supaya klik kedua tidak membuat jadwal ganda. --}}
      <button type="button" class="btn btn-emas flex-fill py-2 fw-semibold"
              wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
        <span wire:loading.remove wire:target="simpan">
          <i class="bi bi-check-lg me-1"></i> {{ $tindakanId ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
        </span>
        <span wire:loading wire:target="simpan">
          <span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan…
        </span>
      </button>
    </div>

    <p class="text-secondary small mt-3 mb-0 px-1">
      Jadwal tersimpan sebagai Draft. Validasi dilakukan di halaman detail,
      setelah itu nomor referensi resmi terbit.
    </p>
  @endif

  {{-- Dialog pencarian. Sengaja tidak memakai modal bawaan Bootstrap: modal itu
       memindahkan dan melepas elemen lewat JS-nya sendiri, sementara Livewire
       menggambar ulang DOM yang sama, sehingga backdrop mudah tertinggal. --}}
  @if ($dialog)
    <div class="sheet-backdrop" wire:click="tutupDialog"></div>

    <div class="sheet" role="dialog" aria-modal="true"
         aria-label="{{ $dialog === 'dokter' ? 'Cari dokter' : 'Cari rumah sakit' }}">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h6 fw-bold mb-0">{{ $dialog === 'dokter' ? 'Pilih Dokter' : 'Pilih Rumah Sakit' }}</h2>
        <button type="button" class="btn-close" wire:click="tutupDialog" aria-label="Tutup"></button>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
        {{-- Ditunda 350 ms supaya tidak menembak API tiap huruf yang diketik. --}}
        <input type="search" wire:model.live.debounce.350ms="cariDialog" class="form-control border-start-0 ps-0"
               placeholder="{{ $dialog === 'dokter' ? 'Nama dokter' : 'Nama atau kode RS' }}" aria-label="Kata kunci">
      </div>

      <div wire:loading wire:target="cariDialog" class="text-center text-secondary py-3">
        <span class="spinner-border spinner-border-sm" role="status"></span>
        <span class="ms-2 small">Mencari…</span>
      </div>

      <div class="sheet-hasil" wire:loading.remove wire:target="cariDialog">
        @forelse ($hasilDialog as $item)
          <button type="button" class="sheet-item" wire:key="pilih-{{ $item['id'] }}"
                  wire:click="pilih({{ (int) $item['id'] }}, @js($item['label'] ?? '-'))">
            <span class="fc-kode">{{ $item['label'] ?? '-' }}</span>
            @if ($dialog !== 'dokter' && ! empty($item['code_client']))
              <span class="fc-nama">{{ $item['code_client'] }}</span>
            @endif
          </button>
        @empty
          <p class="text-secondary small text-center py-3 mb-0">{{ $pesanDialog }}</p>
        @endforelse
      </div>

      <button type="button" class="btn btn-light w-100 mt-3" wire:click="tutupDialog">Tutup</button>
    </div>
  @endif
</div>
