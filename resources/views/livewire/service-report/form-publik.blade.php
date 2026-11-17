<div>
  @if ($terkirim)
    <div class="sr-kartu text-center py-4">
      <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
      <div class="fw-bold fs-5 mt-2">Laporan terkirim</div>
      <div class="text-secondary">Nomor laporan Anda</div>
      <div class="fw-bold fs-4 my-2">{{ $terkirim }}</div>
      <p class="text-secondary small mb-3">
        Tim teknis PT. Mapson Arya Parahita akan menindaklanjuti laporan ini dan
        menghubungi contact person yang Anda cantumkan. Simpan nomor di atas untuk
        menanyakan perkembangannya.
      </p>
      <button type="button" class="btn btn-outline-emas" wire:click="laporLagi">Buat laporan lain</button>
    </div>
  @else
    {{-- wire:model sengaja deferred (tanpa .live): isiannya banyak, dan semuanya
         terkirim sekali saat tombol Kirim ditekan. --}}
    <form wire:submit="kirim" novalidate>
      @if ($galat)
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill"></i>
          <span class="small">{{ $galat }}</span>
        </div>
      @endif

      @if ($errors->any())
        <div class="alert alert-warning small">
          Periksa lagi isian yang ditandai merah.
        </div>
      @endif

      <div class="sr-kartu">
        <div class="sr-judul sr-wajib">Jenis pekerjaan</div>
        <div class="sr-jenis">
          @foreach ($pilihanJenis as $kode => $label)
            <label>
              <input type="checkbox" class="form-check-input m-0" value="{{ $kode }}" wire:model="jenis">
              <span>{{ $label }}</span>
            </label>
          @endforeach
        </div>
        @error('jenis') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">A. Customer</div>

        <label class="form-label small fw-semibold sr-wajib" for="f-nama">Name Customer</label>
        <input type="text" id="f-nama" class="form-control mb-1 @error('customer_nama') is-invalid @enderror"
               wire:model="customer_nama" maxlength="150" placeholder="Nama rumah sakit / instansi">
        @error('customer_nama') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

        <label class="form-label small fw-semibold mt-2" for="f-alamat">Address</label>
        <textarea id="f-alamat" class="form-control" rows="2" wire:model="customer_alamat" maxlength="500"></textarea>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">B. Room ID</div>

        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-dept">Department</label>
            <input type="text" id="f-dept" class="form-control" wire:model="department" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-pos">Position</label>
            <input type="text" id="f-pos" class="form-control" wire:model="position" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-ref">Customer Ref</label>
            <input type="text" id="f-ref" class="form-control" wire:model="customer_ref" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold sr-wajib" for="f-telp">Tlp No.</label>
            <input type="tel" id="f-telp" class="form-control @error('telepon') is-invalid @enderror"
                   wire:model="telepon" maxlength="50" inputmode="tel">
            @error('telepon') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold sr-wajib" for="f-cp">Contact Person</label>
            <input type="text" id="f-cp" class="form-control @error('contact_person') is-invalid @enderror"
                   wire:model="contact_person" maxlength="100">
            @error('contact_person') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-email">Email</label>
            <input type="email" id="f-email" class="form-control @error('email') is-invalid @enderror"
                   wire:model="email" maxlength="150" inputmode="email" autocapitalize="none">
            @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold" for="f-mulai">Date Service Begin</label>
            <input type="date" id="f-mulai" class="form-control" wire:model="tanggal_mulai">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold" for="f-selesai">Date End of Service</label>
            <input type="date" id="f-selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror"
                   wire:model="tanggal_selesai">
          </div>
          @error('tanggal_selesai') <div class="col-12 text-danger small">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">C. Product Survey</div>

        @foreach ($produk as $i => $p)
          <div class="border rounded-3 p-2 mb-2" wire:key="produk-{{ $i }}">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-semibold">Alat {{ $i + 1 }}</span>
              @if (count($produk) > 1)
                <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="hapusProduk({{ $i }})">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              @endif
            </div>
            <input type="text" class="form-control form-control-sm mb-2" placeholder="Description"
                   wire:model="produk.{{ $i }}.description" maxlength="200">
            <div class="row g-2">
              <div class="col-6">
                <input type="text" class="form-control form-control-sm" placeholder="Type"
                       wire:model="produk.{{ $i }}.type" maxlength="100">
              </div>
              <div class="col-6">
                <input type="text" class="form-control form-control-sm" placeholder="NIE (Unit)"
                       wire:model="produk.{{ $i }}.nie" maxlength="100">
              </div>
              <div class="col-6">
                <input type="text" class="form-control form-control-sm" placeholder="No. SN"
                       wire:model="produk.{{ $i }}.sn" maxlength="100">
              </div>
              <div class="col-6">
                <input type="text" class="form-control form-control-sm" placeholder="S.W Version"
                       wire:model="produk.{{ $i }}.sw_version" maxlength="100">
              </div>
            </div>
          </div>
        @endforeach

        <button type="button" class="btn btn-sm btn-outline-emas" wire:click="tambahProduk">
          <i class="bi bi-plus-lg"></i> Tambah alat
        </button>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">D. Problem Identification</div>

        @foreach ($pemeriksaan as $i => $b)
          <div class="border rounded-3 p-2 mb-2" wire:key="periksa-{{ $i }}">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-semibold {{ $i === 0 ? 'sr-wajib' : '' }}">No. {{ $i + 1 }}</span>
              @if (count($pemeriksaan) > 1)
                <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="hapusPemeriksaan({{ $i }})">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              @endif
            </div>
            <textarea class="form-control form-control-sm mb-2 @error('pemeriksaan.'.$i.'.tahap') is-invalid @enderror"
                      rows="3" placeholder="Tahap pemeriksaan / keluhan yang ditemukan"
                      wire:model="pemeriksaan.{{ $i }}.tahap" maxlength="2000"></textarea>
            @error('pemeriksaan.'.$i.'.tahap') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <textarea class="form-control form-control-sm" rows="2" placeholder="Solusi / rekomendasi (boleh dikosongkan)"
                      wire:model="pemeriksaan.{{ $i }}.solusi" maxlength="2000"></textarea>
          </div>
        @endforeach

        <button type="button" class="btn btn-sm btn-outline-emas" wire:click="tambahPemeriksaan">
          <i class="bi bi-plus-lg"></i> Tambah baris
        </button>
      </div>

      <div class="sr-kartu">
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-eng">Engineering PT. Mapson Arya Parahita — Name</label>
            <input type="text" id="f-eng" class="form-control" wire:model="nama_engineer" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-cust">Customer — Name</label>
            <input type="text" id="f-cust" class="form-control" wire:model="nama_customer" maxlength="100">
          </div>
        </div>
      </div>

      {{-- Isian jebakan: tidak terlihat dan tidak bisa dijangkau Tab, jadi
           hanya robot yang mengisinya. --}}
      <div class="sr-jebakan" aria-hidden="true">
        <label for="f-situs">Situs</label>
        <input type="text" id="f-situs" wire:model="situs" tabindex="-1" autocomplete="off">
      </div>

      <button type="submit" class="btn btn-emas w-100 py-2 mb-4" wire:loading.attr="disabled" wire:target="kirim">
        <span wire:loading wire:target="kirim" class="spinner-border spinner-border-sm align-middle me-2" aria-hidden="true"></span>
        Kirim laporan
      </button>
    </form>
  @endif
</div>
