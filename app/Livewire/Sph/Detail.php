<?php

namespace App\Livewire\Sph;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Detail satu SPH, disamakan dengan custom/sph/card.php mode tampil:
 * header, tabel Produk/Jasa, form Tambah Baris Baru, lalu tombol
 * CETAK PDF / VALIDATE / MODIFY (atau REOPEN bila sudah divalidasi).
 *
 * Semua tombol tulis hanya digambar saat status Draft, mengikuti penjagaan yang
 * sama di ERP. Server tetap memeriksa ulang — tombol yang tidak digambar masih
 * bisa dipanggil lewat request buatan sendiri.
 */
class Detail extends Component
{
    public int $sphId;

    public array $info = [];

    public array $lines = [];

    public ?string $galat = null;

    public ?string $pesan = null;

    // --- Form tambah baris ---

    public bool $sheetProduk = false;

    public string $cariProduk = '';

    public array $hasilProduk = [];

    public ?int $fk_product = null;

    public string $kodeProduk = '';

    public string $deskripsi = '';

    public string $qty = '1';

    public string $subprice = '';

    public string $tva_tx = '11';

    public string $discount_percent = '0';

    public function mount(int $id): void
    {
        $this->sphId = $id;
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $data = Api::get("/sph/{$this->sphId}")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Data SPH tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->info = $data['info'] ?? [];
        $this->lines = $data['lines'] ?? [];
    }

    /** Draft: satu-satunya keadaan yang boleh diubah, sama seperti di ERP. */
    public function draft(): bool
    {
        return (int) ($this->info['status'] ?? 0) === 0;
    }

    // --- Pemilih produk ---

    public function bukaProduk(): void
    {
        $this->sheetProduk = true;
        $this->cariProduk = '';

        $this->cariProdukSekarang();
    }

    public function tutupProduk(): void
    {
        $this->sheetProduk = false;
    }

    public function updatedCariProduk(): void
    {
        $this->cariProdukSekarang();
    }

    protected function cariProdukSekarang(): void
    {
        try {
            $this->hasilProduk = Api::get('/sph/products', ['search' => $this->cariProduk])['data'] ?? [];
        } catch (\Throwable $e) {
            $this->hasilProduk = [];
            $this->pesan = 'Gagal mencari produk. Periksa koneksi.';
        }
    }

    /**
     * Memilih produk mengisi deskripsi, harga, dan PPN sekaligus — pekerjaan
     * yang di ERP dilakukan ajax/get_product_details.php. Ketiganya tetap bisa
     * disunting: harga penawaran sering berbeda dari harga master.
     */
    public function pilihProduk(int $id): void
    {
        $produk = collect($this->hasilProduk)->firstWhere('rowid', $id);

        if (! $produk) {
            return;
        }

        $this->fk_product = $id;
        $this->kodeProduk = trim(($produk['ref'] ?? '') . ' — ' . ($produk['label'] ?? ''), ' —');
        $this->deskripsi = $produk['description'] !== '' ? $produk['description'] : (string) ($produk['label'] ?? '');
        $this->subprice = (string) (float) ($produk['price'] ?? 0);
        $this->tva_tx = (string) (float) ($produk['tva_tx'] ?? 0);
        $this->sheetProduk = false;
    }

    /** Hitungan Total baris, ditampilkan sebelum disimpan (rumus sama dengan server). */
    public function totalBarisBaru(): float
    {
        $qty = (float) str_replace(',', '.', $this->qty);
        $harga = (float) str_replace(',', '.', $this->subprice);
        $diskon = (float) str_replace(',', '.', $this->discount_percent);

        return $qty * $harga * (1 - ($diskon / 100));
    }

    public function tambahBaris(): void
    {
        if (! $this->draft()) {
            return;
        }

        $this->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'subprice' => ['required', 'numeric', 'min:0'],
            'tva_tx' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'qty.required' => 'Qty wajib diisi.',
            'qty.gt' => 'Qty harus lebih besar dari nol.',
            'subprice.required' => 'Unit price wajib diisi.',
            'discount_percent.max' => 'Diskon tidak boleh lebih dari 100%.',
        ]);

        try {
            Api::post("/sph/{$this->sphId}/lines", [
                'fk_product' => $this->fk_product,
                'description' => $this->deskripsi,
                'qty' => (float) str_replace(',', '.', $this->qty),
                'subprice' => (float) str_replace(',', '.', $this->subprice),
                'tva_tx' => (float) str_replace(',', '.', $this->tva_tx),
                'discount_percent' => (float) str_replace(',', '.', $this->discount_percent),
            ]);
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Baris gagal ditambahkan.';

            return;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Baris belum tersimpan.';

            return;
        }

        $this->kosongkanFormBaris();
        $this->pesan = 'Baris berhasil ditambahkan.';
        $this->muat();
    }

    protected function kosongkanFormBaris(): void
    {
        $this->fk_product = null;
        $this->kodeProduk = '';
        $this->deskripsi = '';
        $this->qty = '1';
        $this->subprice = '';
        $this->discount_percent = '0';
        // PPN dibiarkan pada nilai terakhir: satu SPH umumnya memakai tarif
        // yang sama untuk semua barisnya.
    }

    public function hapusBaris(int $lineId): void
    {
        if (! $this->draft()) {
            return;
        }

        try {
            Api::hapus("/sph/{$this->sphId}/lines/{$lineId}");
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Baris gagal dihapus.';
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Baris belum dihapus.';

            return;
        }

        $this->muat();
    }

    public function validasi(): void
    {
        if (! $this->draft()) {
            return;
        }

        $this->jalankan("/sph/{$this->sphId}/validate", 'SPH berhasil divalidasi.');
    }

    public function bukaKembali(): void
    {
        if ($this->draft()) {
            return;
        }

        $this->jalankan("/sph/{$this->sphId}/reopen", 'SPH dibuka kembali sebagai Draft.');
    }

    /** Bentuk kedua aksi status sama: POST tanpa isi, lalu tarik ulang data. */
    protected function jalankan(string $path, string $pesanSukses): void
    {
        try {
            Api::post($path);
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Aksi ditolak server.';
            $this->muat();

            return;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Coba lagi.';

            return;
        }

        $this->pesan = $pesanSukses;
        $this->muat();
    }

    /** Total dihitung dari baris, sama seperti yang dilakukan endpoint PDF. */
    public function totalHt(): float
    {
        return array_sum(array_map(fn ($l) => (float) ($l['total_ht'] ?? 0), $this->lines));
    }

    public function totalTtc(): float
    {
        return array_sum(array_map(fn ($l) => (float) ($l['total_ttc'] ?? 0), $this->lines));
    }

    public function render()
    {
        return view('livewire.sph.detail');
    }
}
