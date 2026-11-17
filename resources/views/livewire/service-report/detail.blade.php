<div>
  @if ($galat)
    <div class="alert alert-danger d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-octagon-fill"></i>
      <span class="small">{{ $galat }}</span>
      @if (empty($info))
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
      @endif
    </div>
  @endif

  @if ($pesan)
    <div class="alert alert-success d-flex align-items-center gap-2">
      <i class="bi bi-check-circle-fill"></i>
      <span class="small">{{ $pesan }}</span>
    </div>
  @endif

  @if (! empty($info))
    @php
      $tgl = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('d M Y') : '—';
    @endphp

    <div class="bg-white rounded-4 p-3 shadow-sm mb-3">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="min-width-0">
          <div class="fw-bold fs-5">{{ $info['no_report'] ?? '-' }}</div>
          <div class="text-secondary small">{{ $info['jenis_label'] ?: '-' }}</div>
        </div>
        <span class="tk-status {{ \App\Livewire\ServiceReport\Daftar::warna((int) $info['status']) }} flex-shrink-0">
          {{ $info['status_label'] }}
        </span>
      </div>
      <div class="text-secondary small mt-2">
        <i class="bi bi-clock me-1"></i>Masuk
        {{ \Illuminate\Support\Carbon::parse($info['created_at'])->format('d M Y H:i') }}
      </div>

      <a href="{{ route('service-report.pdf', $info['id']) }}" class="btn btn-outline-emas w-100 mt-3" target="_blank" rel="noopener">
        <i class="bi bi-file-earmark-pdf me-1"></i> Lihat PDF formulir
      </a>
    </div>

    <div class="bg-white rounded-4 p-3 shadow-sm mb-3">
      <div class="fw-bold mb-2">A. Customer</div>
      <dl class="tk-info">
        <dt>Nama</dt><dd>{{ $info['customer_nama'] }}</dd>
        <dt>Alamat</dt><dd>{{ $info['customer_alamat'] ?: '—' }}</dd>
      </dl>

      <div class="fw-bold mb-2 mt-3">B. Room ID</div>
      <dl class="tk-info">
        <dt>Department</dt><dd>{{ $info['department'] ?: '—' }}</dd>
        <dt>Position</dt><dd>{{ $info['position'] ?: '—' }}</dd>
        <dt>Customer Ref</dt><dd>{{ $info['customer_ref'] ?: '—' }}</dd>
        <dt>Contact Person</dt><dd>{{ $info['contact_person'] ?: '—' }}</dd>
        <dt>Telepon</dt>
        <dd>
          @if ($info['telepon'])
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $info['telepon']) }}">{{ $info['telepon'] }}</a>
          @else
            —
          @endif
        </dd>
        <dt>Email</dt>
        <dd>
          @if ($info['email'])
            <a href="mailto:{{ $info['email'] }}">{{ $info['email'] }}</a>
          @else
            —
          @endif
        </dd>
        <dt>Nama customer (TTD)</dt><dd>{{ $info['nama_customer'] ?: '—' }}</dd>
      </dl>
    </div>

    <div class="bg-white rounded-4 p-3 shadow-sm mb-3">
      <div class="fw-bold mb-2">C. Product Survey</div>
      @forelse ($info['produk'] as $i => $p)
        <div class="{{ $i > 0 ? 'border-top pt-2 mt-2' : '' }}" wire:key="produk-{{ $i }}">
          <div class="fw-semibold small">{{ $i + 1 }}. {{ $p['description'] ?: '—' }}</div>
          <div class="text-secondary small">
            Type: {{ $p['type'] ?: '—' }} · NIE: {{ $p['nie'] ?: '—' }}<br>
            SN: {{ $p['sn'] ?: '—' }} · S/W: {{ $p['sw_version'] ?: '—' }}
          </div>
        </div>
      @empty
        <div class="text-secondary small">Tidak ada alat yang dicantumkan.</div>
      @endforelse
    </div>

    {{-- Isian tindak lanjut. wire:model sengaja deferred (tanpa .live):
         terkirim sekali saat Simpan ditekan. --}}
    <form wire:submit="simpan" class="bg-white rounded-4 p-3 shadow-sm mb-3">
      <div class="fw-bold mb-2">Tindak Lanjut</div>

      <label class="form-label small fw-semibold" for="sr-status">Status</label>
      <select id="sr-status" class="form-select mb-3" wire:model="status">
        @foreach ($pilihanStatus as $nilai => $label)
          <option value="{{ $nilai }}">{{ $label }}</option>
        @endforeach
      </select>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label small fw-semibold" for="sr-mulai">Date Service Begin</label>
          <input type="date" id="sr-mulai" class="form-control" wire:model="tanggal_mulai">
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold" for="sr-selesai">Date End of Service</label>
          <input type="date" id="sr-selesai" class="form-control" wire:model="tanggal_selesai">
        </div>
      </div>

      <div class="fw-semibold small mb-2">D. Problem Identification</div>
      @foreach ($pemeriksaan as $i => $baris)
        <div class="border rounded-3 p-2 mb-2" wire:key="periksa-{{ $i }}">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold">No. {{ $i + 1 }}</span>
            @if (count($pemeriksaan) > 1)
              <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="hapusPemeriksaan({{ $i }})">
                <i class="bi bi-trash"></i> Hapus
              </button>
            @endif
          </div>
          <textarea class="form-control form-control-sm mb-2" rows="2" placeholder="Tahap pemeriksaan"
                    wire:model="pemeriksaan.{{ $i }}.tahap"></textarea>
          <textarea class="form-control form-control-sm" rows="2" placeholder="Solusi / rekomendasi"
                    wire:model="pemeriksaan.{{ $i }}.solusi"></textarea>
        </div>
      @endforeach
      <button type="button" class="btn btn-sm btn-outline-emas mb-3" wire:click="tambahPemeriksaan">
        <i class="bi bi-plus-lg"></i> Tambah baris
      </button>

      <label class="form-label small fw-semibold d-block" for="sr-engineer">Nama engineer</label>
      <input type="text" id="sr-engineer" class="form-control mb-3" wire:model="nama_engineer" maxlength="100">

      <label class="form-label small fw-semibold" for="sr-catatan">Catatan tindak lanjut</label>
      <textarea id="sr-catatan" class="form-control mb-2" rows="3" wire:model="catatan"
                placeholder="Mis. jadwal kunjungan, suku cadang yang dipesan"></textarea>

      @if (! empty($info['tanggal_tindak_lanjut']))
        <div class="text-secondary small mb-2">
          Terakhir diperbarui {{ \Illuminate\Support\Carbon::parse($info['tanggal_tindak_lanjut'])->format('d M Y H:i') }}
          oleh {{ $info['user_tindak_lanjut'] ?: '—' }}
        </div>
      @endif

      <button type="submit" class="btn btn-emas w-100" wire:loading.attr="disabled" wire:target="simpan">
        Simpan tindak lanjut
      </button>
    </form>
  @endif
</div>
