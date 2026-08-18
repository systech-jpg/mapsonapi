<?php

namespace App\Livewire\Forecast;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tabel input Forecast. Snapshot (buffer, saldo akhir, rekomendasi) sudah
 * digenerate server saat header dibuat, jadi di sini user tinggal mengisi
 * kolom qty lalu menyimpannya sebagai Draft atau langsung Final.
 */
class Produk extends Component
{
    public int $forecastId;

    public string $ref = '-';

    /** Dokumen berstatus Final tidak bisa diubah lagi, semua input dikunci. */
    public bool $terkunci = false;

    /** Baris tampilan dari server: kode, nama, buffer, saldo, rekomendasi. */
    public array $produk = [];

    /**
     * Qty yang diisi user, dikunci ke product_id dan bukan ke posisi baris.
     * Dengan begitu angka yang sudah diketik tetap utuh walau barisnya sedang
     * tersembunyi oleh kotak saring.
     */
    public array $qty = [];

    /**
     * Qty saat pertama dimuat dari server. Dipakai untuk mendeteksi baris yang
     * tadinya berisi lalu dikosongkan user, supaya baris itu tetap ikut
     * terkirim (qty 0) dan angkanya benar-benar terhapus di server.
     */
    public array $qtyAwal = [];

    public string $cari = '';

    public ?string $galat = null;

    public ?string $pesan = null;

    // --- Dialog tambah produk ---

    public bool $dialogTambah = false;

    public string $cariTambah = '';

    public array $hasilTambah = [];

    public ?string $pesanTambah = null;

    public function mount(int $id): void
    {
        $this->forecastId = $id;

        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $data = Api::get("/forecast/{$this->forecastId}/products")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal memuat produk forecast.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Periksa koneksi lalu coba lagi.';

            return;
        }

        $this->ref = $data['forecast']['ref'] ?? '-';
        $this->terkunci = (bool) ($data['forecast']['is_validated'] ?? false);
        $this->produk = $data['products'] ?? [];

        $this->qty = [];
        $this->qtyAwal = [];

        foreach ($this->produk as $baris) {
            $id = (int) $baris['product_id'];
            $this->qty[$id] = (int) ($baris['qty_forecast'] ?? 0);
            $this->qtyAwal[$id] = $this->qty[$id];
        }
    }

    /**
     * Saringan di layar utama hanya menyaring baris yang sudah dimuat, berbeda
     * dengan pencarian di dialog tambah yang dilayani server.
     */
    #[Computed]
    public function hasil(): array
    {
        $kata = trim($this->cari);

        if ($kata === '') {
            return $this->produk;
        }

        return array_values(array_filter($this->produk, function ($baris) use ($kata) {
            $target = ($baris['product_kode'] ?? '') . ' ' . ($baris['product_name'] ?? '');

            return stripos($target, $kata) !== false;
        }));
    }

    public function isiRekomendasi(): void
    {
        if ($this->terkunci) {
            return;
        }

        foreach ($this->produk as $baris) {
            $this->qty[(int) $baris['product_id']] = (int) ($baris['rekomendasi_butuh'] ?? 0);
        }

        $this->pesan = 'Kolom forecast diisi sesuai rekomendasi. Belum tersimpan.';
    }

    // --- Dialog tambah produk ---

    public function bukaDialog(): void
    {
        $this->dialogTambah = true;
        $this->cariTambah = '';
        $this->hasilTambah = [];
        $this->pesanTambah = 'Ketik minimal 2 huruf untuk mencari produk.';
    }

    public function tutupDialog(): void
    {
        $this->dialogTambah = false;
        $this->cariTambah = '';
        $this->hasilTambah = [];
        $this->pesanTambah = null;
    }

    public function updatedCariTambah(): void
    {
        $kata = trim($this->cariTambah);

        if (mb_strlen($kata) < 2) {
            $this->hasilTambah = [];
            $this->pesanTambah = 'Ketik minimal 2 huruf untuk mencari produk.';

            return;
        }

        try {
            $this->hasilTambah = Api::get("/forecast/{$this->forecastId}/search-products", ['q' => $kata])['data'] ?? [];
        } catch (\Throwable $e) {
            $this->hasilTambah = [];
            $this->pesanTambah = 'Pencarian gagal. Coba lagi.';

            return;
        }

        $this->pesanTambah = empty($this->hasilTambah)
            ? 'Produk tidak ditemukan, atau sudah ada di daftar forecast.'
            : null;
    }

    public function tambahProduk(int $productId): void
    {
        if ($this->terkunci) {
            return;
        }

        try {
            $baris = Api::post("/forecast/{$this->forecastId}/add-product", ['product_id' => $productId])['data'] ?? null;
        } catch (RequestException $e) {
            $this->pesanTambah = $e->response->json('message') ?? 'Gagal menambahkan produk.';

            return;
        } catch (\Throwable $e) {
            $this->pesanTambah = 'Gagal menghubungi server.';

            return;
        }

        if (! $baris) {
            $this->pesanTambah = 'Server tidak mengembalikan data produk.';

            return;
        }

        // Respons add-product berisi baris yang sudah lengkap, jadi cukup
        // ditempel ke bawah tabel tanpa memuat ulang seluruh daftar.
        $this->produk[] = $baris;

        $id = (int) $baris['product_id'];
        $this->qty[$id] = (int) ($baris['qty_forecast'] ?? 0);
        $this->qtyAwal[$id] = $this->qty[$id];

        // Saringan dibersihkan supaya baris baru pasti terlihat saat dialog
        // ditutup. Dialog sendiri dibiarkan terbuka untuk penambahan berikutnya.
        $this->cari = '';
        $this->cariTambah = '';
        $this->hasilTambah = [];
        $this->pesanTambah = 'Ditambahkan: ' . ($baris['product_kode'] ?? '-') . '. Cari lagi bila perlu.';
    }

    // --- Simpan ---

    /**
     * Baris ber-qty 0 yang memang kosong sejak awal tidak perlu dikirim, tapi
     * baris yang tadinya berisi lalu dikosongkan tetap harus ikut supaya
     * angkanya benar-benar terhapus di server.
     */
    protected function kumpulkanItem(): array
    {
        $items = [];

        foreach ($this->produk as $baris) {
            $id = (int) $baris['product_id'];
            $nilai = (int) ($this->qty[$id] ?? 0);

            if ($nilai > 0 || ($this->qtyAwal[$id] ?? 0) > 0) {
                $items[] = ['product_id' => $id, 'qty' => $nilai];
            }
        }

        return $items;
    }

    public function simpan(bool $final = false)
    {
        if ($this->terkunci) {
            return null;
        }

        $items = $this->kumpulkanItem();

        // Endpoint menolak items kosong dengan 422. Dijaga di sini supaya user
        // mendapat kalimat yang jelas, bukan galat validasi mentah.
        if (empty($items)) {
            $this->pesan = 'Belum ada qty yang diisi.';

            return null;
        }

        if ($final && ! collect($items)->contains(fn ($item) => $item['qty'] > 0)) {
            $this->pesan = 'Isi minimal satu qty forecast sebelum submit final.';

            return null;
        }

        try {
            Api::post("/forecast/{$this->forecastId}/save", [
                'validate' => $final,
                'items' => $items,
            ]);
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Gagal menyimpan forecast.';

            return null;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Angka Anda belum tersimpan.';

            return null;
        }

        if ($final) {
            // Dokumen sudah Final, tidak ada lagi yang bisa dikerjakan di sini
            // maupun di form pembuatnya, jadi keduanya ditutup.
            session()->flash('pesan', "Forecast {$this->ref} telah divalidasi menjadi Final.");

            return $this->redirect(route('forecast'), navigate: true);
        }

        // Draft: nilai awal disegarkan supaya perubahan berikutnya dihitung
        // dari kondisi yang sudah tersimpan.
        $this->qtyAwal = $this->qty;
        $this->pesan = 'Draft tersimpan.';

        return null;
    }

    public function render()
    {
        return view('livewire.forecast.produk');
    }
}
