<div>
  {{-- Header ada di dalam komponen, bukan di halaman pembungkus, karena
       judulnya (nama lawan bicara / nama grup) baru diketahui setelah komponen
       memanggil API. --}}
  <header class="app-header ringkas d-flex align-items-center gap-3">
    <a href="{{ route('pesan') }}" wire:navigate class="header-btn flex-shrink-0" aria-label="Kembali ke daftar pesan">
      <i class="bi bi-arrow-left"></i>
    </a>

    <div class="min-width-0">
      <h1 class="text-truncate">{{ $judul }}</h1>
      @if ($tipe === 'group')
        <div class="small" style="opacity: .9;">Grup</div>
      @endif
    </div>
  </header>

  <div class="px-3 pt-3">
    @if ($galat)
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span class="small">{{ $galat }}</span>
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0" wire:click="muat">Muat ulang</button>
      </div>
    @endif

    <div class="ch-percakapan">
      @php $tanggalTerakhir = null; @endphp

      @forelse ($pesan as $p)
        @php
          $milikSaya = (int) ($p['sender_id'] ?? 0) === $sayaId;
          $tanggal = \App\Support\WaktuChat::tanggal($p['created_at'] ?? null);
          $lampiranPesan = $p['attachments'] ?? [];
        @endphp

        @if ($tanggal !== $tanggalTerakhir)
          <div class="ch-pemisah"><span>{{ $tanggal }}</span></div>
          @php $tanggalTerakhir = $tanggal; @endphp
        @endif

        <div class="ch-baris {{ $milikSaya ? 'saya' : '' }}" wire:key="pesan-{{ $p['id'] }}">
          <div class="ch-gelembung {{ $milikSaya ? 'saya' : '' }}">
            {{-- Nama pengirim hanya di grup, dan hanya untuk pesan orang lain:
                 di chat personal namanya sudah ada di header, dan pesan sendiri
                 tidak perlu diberi label. --}}
            @if ($tipe === 'group' && ! $milikSaya)
              <div class="ch-nama">{{ $p['sender_name'] ?? 'Tanpa nama' }}</div>
            @endif

            @foreach ($lampiranPesan as $l)
              @php $tautan = $this->tautanBerkas($l); @endphp

              @if (($l['file_type'] ?? '') === 'image')
                <a href="{{ $tautan }}" target="_blank" rel="noopener">
                  <img src="{{ $tautan }}" alt="{{ $l['file_name'] ?? 'Lampiran' }}" class="ch-gambar" loading="lazy">
                </a>
              @else
                <a href="{{ $tautan }}" target="_blank" rel="noopener" class="ch-berkas">
                  <i class="bi bi-paperclip"></i>
                  <span class="text-truncate">{{ $l['file_name'] ?? 'Berkas' }}</span>
                </a>
              @endif
            @endforeach

            @if (filled($p['message'] ?? null))
              <div class="ch-teks">{{ $p['message'] }}</div>
            @endif

            <div class="ch-jam">{{ \App\Support\WaktuChat::jam($p['created_at'] ?? null) }}</div>
          </div>
        </div>
      @empty
        @if (! $galat)
          <div class="text-center text-secondary py-5">
            <i class="bi bi-chat-dots fs-2 d-block mb-2"></i>
            Belum ada pesan. Mulai percakapan di bawah.
          </div>
        @endif
      @endforelse
    </div>

    <form wire:submit="kirim" class="ch-komposer">
      @if (! empty($lampiran))
        <div class="ch-antre">
          @foreach ($lampiran as $i => $berkas)
            <span class="ch-antre-item" wire:key="lampiran-{{ $i }}">
              <i class="bi bi-paperclip"></i>
              <span class="text-truncate">{{ $berkas->getClientOriginalName() }}</span>
              <button type="button" class="btn-buang" wire:click="buangLampiran({{ $i }})" aria-label="Buang berkas">
                <i class="bi bi-x-lg"></i>
              </button>
            </span>
          @endforeach
        </div>
      @endif

      @error('lampiran.*') <div class="text-danger small mb-1">{{ $message }}</div> @enderror

      <div class="ch-komposer-baris">
        {{-- Input berkas disembunyikan di balik label: tombol bawaan browser
             tidak bisa diberi bentuk, dan lebarnya berubah-ubah menurut bahasa
             perangkat sehingga merusak susunan baris ini. --}}
        <label class="ch-tombol" title="Lampirkan berkas">
          <i class="bi bi-paperclip"></i>
          <input type="file" wire:model="lampiran" multiple class="d-none">
        </label>

        {{-- wire:model dibiarkan deferred (tanpa .live): isinya cuma dibutuhkan
             saat tombol kirim ditekan, dan .live berarti satu permintaan ke
             server untuk setiap huruf yang diketik. --}}
        <input type="text" wire:model="teks" class="form-control ch-isian"
               placeholder="Tulis pesan…" aria-label="Isi pesan" autocomplete="off">

        <button type="submit" class="ch-tombol kirim" wire:loading.attr="disabled" wire:target="kirim,lampiran"
                aria-label="Kirim pesan">
          <i class="bi bi-send-fill"></i>
        </button>
      </div>

      <div class="text-secondary small mt-1" wire:loading wire:target="lampiran">
        Mengunggah berkas…
      </div>
    </form>
  </div>
</div>

@script
<script>
  // Percakapan dibaca dari bawah: yang perlu terlihat begitu halaman terbuka
  // adalah pesan TERAKHIR, bukan yang paling lama.
  const keBawah = () => window.scrollTo({ top: document.body.scrollHeight });

  // requestAnimationFrame, bukan langsung: saat baris ini berjalan, gambar
  // lampiran belum punya tinggi sehingga scrollHeight masih terlalu pendek.
  requestAnimationFrame(keBawah);

  $wire.on('gulir-ke-bawah', () => requestAnimationFrame(keBawah));
</script>
@endscript
