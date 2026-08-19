<?php

namespace App\Livewire\Scan;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Scan Produk: arahkan kamera ke barcode, lalu tampilkan ref, deskripsi, dan
 * stok terkini produk itu.
 *
 * Mengikuti ProductScanFragment + ProductScanViewModel di Android. Seperti di
 * sana, halaman ini sengaja BUKAN layar scan milik stocktake: keduanya
 * sama-sama membaca barcode, tapi stocktake MENULIS hasil hitungan ke
 * database sedangkan halaman ini hanya membaca.
 *
 * Pembacaan barcode dikerjakan di browser (ZXing), pencarian produknya tetap
 * lewat server: api_key tidak pernah boleh sampai ke JavaScript.
 */
class Produk extends Component
{
    /** Hasil scan terakhir: judul, deskripsi, stok. null berarti belum ada. */
    public ?array $hasil = null;

    public ?string $pesan = null;

    /**
     * Jalan keluar bila kamera tidak bisa dipakai — misalnya halaman dibuka
     * lewat HTTP biasa (browser hanya mengizinkan kamera di HTTPS) atau izin
     * kameranya ditolak. Tanpa ini halaman menjadi buntu total.
     */
    public string $barcodeManual = '';

    /**
     * Barcode dari kamera. Dikirim JavaScript halaman sebagai event Livewire,
     * bukan panggilan langsung ke method: skrip kameranya berjalan di luar
     * komponen supaya pratinjau kamera tetap hidup walau livewire.js gagal
     * dimuat (lihat komentar di resources/views/scan.blade.php).
     *
     * Penjagaan barcode berulang ada di sisi browser, sama seperti lastBarcode
     * di ProductScanViewModel: callback ZXing berbunyi berkali-kali per detik
     * selama barcode masih terbidik.
     */
    #[On('barcode-terbaca')]
    public function terimaBarcode(string $kode): void
    {
        $this->scan($kode);
    }

    public function scan(string $barcode): void
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return;
        }

        $this->pesan = null;

        try {
            $data = Api::post('/products/scan', ['barcode' => $barcode])['data'] ?? null;
        } catch (RequestException $e) {
            // Barcode tak dikenal dijawab 404 lengkap dengan kalimatnya sendiri.
            // Itu yang dibaca petugas, bukan "HTTP 404".
            $this->hasil = null;
            $this->pesan = $e->response->json('message') ?? 'Produk tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->hasil = null;
            $this->pesan = 'Gagal menghubungi server. Coba lagi.';

            return;
        }

        if (! $data) {
            $this->hasil = null;
            $this->pesan = 'Produk tidak ditemukan.';

            return;
        }

        $this->hasil = [
            'judul' => $data['judul'] ?? '-',
            'deskripsi' => $this->rapikanDeskripsi($data['deskripsi'] ?? ''),
            'stok' => (float) ($data['stok_saat_ini'] ?? 0),
        ];

        // Kamera dibekukan sampai user menekan "Scan Lagi". Kalau dibiarkan
        // membaca, barcode berikutnya yang kebetulan lewat di depan lensa akan
        // menimpa angka stok yang sedang dibaca user.
        $this->dispatch('produk-ketemu');
    }

    /** Barcode diketik tangan; jalurnya sama persis dengan hasil kamera. */
    public function scanManual(): void
    {
        $barcode = trim($this->barcodeManual);

        if ($barcode === '') {
            $this->pesan = 'Isi dulu barcode-nya.';

            return;
        }

        $this->scan($barcode);
    }

    /** Melepas kunci barcode terakhir supaya produk yang sama bisa discan ulang. */
    public function ulangi(): void
    {
        $this->hasil = null;
        $this->pesan = null;
        $this->barcodeManual = '';

        $this->dispatch('scan-lagi');
    }

    /**
     * Deskripsi produk kerap memuat kalimat yang sama dua kali. Yang dibuang di
     * sini hanya salinannya — sama seperti rapikanDeskripsi() di Android.
     *
     * Perapian HTML (entitas seperti "&times;") sudah dikerjakan
     * ProductController, jadi baris yang dibandingkan di sini dianggap bersih.
     */
    protected function rapikanDeskripsi(string $mentah): string
    {
        $baris = array_filter(array_map('trim', preg_split('/\R/', $mentah) ?: []), 'strlen');

        // array_unique mempertahankan kemunculan pertama, jadi urutan aslinya
        // tetap -- yang dibuang selalu salinan di bawahnya.
        return implode("\n", array_unique($baris));
    }

    /**
     * Angka stok seperti Money.qty di Android: nol di belakang koma dibuang
     * ("1.00" jadi "1", "2.50" jadi "2,5").
     */
    public function stokTampil(): string
    {
        $nilai = (float) ($this->hasil['stok'] ?? 0);

        if ($nilai == (int) $nilai) {
            return number_format($nilai, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',');
    }

    /** Stok minus adalah kondisi sah di gudang, bukan data rusak — ditandai merah. */
    public function stokMinus(): bool
    {
        return (float) ($this->hasil['stok'] ?? 0) < 0;
    }

    public function render()
    {
        return view('livewire.scan.produk');
    }
}
