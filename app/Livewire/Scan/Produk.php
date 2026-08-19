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
    /**
     * Awalan isi kode QR login ERP.
     *
     * Inilah yang membuat satu layar kamera bisa melayani dua hal. Barcode
     * produk tidak pernah berbentuk seperti ini — ia berupa angka atau kode
     * ref — jadi awalan ini cukup untuk memilah tanpa perlu bertanya ke server
     * lebih dulu. Nilainya HARUS sama persis dengan yang ditulis
     * custom/qrlogin/api.php di project mapsonerp.
     */
    public const AWALAN_QR_LOGIN = 'MAPSONLOGIN:';

    /** Hasil scan terakhir: judul, deskripsi, stok. null berarti belum ada. */
    public ?array $hasil = null;

    public ?string $pesan = null;

    /**
     * Permintaan login ERP yang sedang menunggu jawaban petugas.
     * Berisi token, ip, peramban, sisa_detik.
     */
    public ?array $login = null;

    /** Kalimat hasil dari alur login QR (berhasil maupun ditolak server). */
    public ?string $pesanLogin = null;

    /** true setelah disetujui atau ditolak — kartunya berganti jadi ringkasan. */
    public bool $loginSelesai = false;

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

        // Percabangan "kamera pintar": satu lensa, dua tujuan. Kode QR login ERP
        // dikenali dari awalannya dan tidak pernah dicari sebagai produk —
        // kalau dicari, jawabannya pasti 404 dan petugas melihat "Produk tidak
        // ditemukan" untuk kode yang sebenarnya baik-baik saja.
        if (str_starts_with($barcode, self::AWALAN_QR_LOGIN)) {
            $this->bacaQrLogin(substr($barcode, strlen(self::AWALAN_QR_LOGIN)));

            return;
        }

        $this->pesan = null;
        $this->login = null;
        $this->pesanLogin = null;

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

        $this->login = null;
        $this->pesanLogin = null;
        $this->loginSelesai = false;

        $this->dispatch('scan-lagi');
    }

    /* =========================================================
       Login ERP lewat kode QR
       ========================================================= */

    /**
     * Mengambil keterangan permintaan login, lalu menahannya di layar sampai
     * petugas menjawab.
     *
     * Persetujuannya SENGAJA tidak otomatis. Kalau memindai saja sudah cukup
     * untuk masuk, siapa pun bisa menempelkan kode QR miliknya di gudang: satu
     * petugas memindainya karena mengira itu barcode barang, dan penyerang
     * mendapat sesi ERP atas nama petugas itu. Layar konfirmasi menyebutkan IP
     * dan peramban peminta, supaya yang diketuk adalah keputusan, bukan refleks.
     */
    protected function bacaQrLogin(string $token): void
    {
        $this->hasil = null;
        $this->pesan = null;
        $this->login = null;
        $this->pesanLogin = null;
        $this->loginSelesai = false;

        try {
            $this->login = Api::get('/qr-login/' . $token)['data'] ?? null;
        } catch (RequestException $e) {
            $this->pesanLogin = $e->response->json('message') ?? 'Kode QR tidak bisa dipakai.';
            $this->loginSelesai = true;
        } catch (\Throwable $e) {
            $this->pesanLogin = 'Gagal menghubungi server. Coba lagi.';
            $this->loginSelesai = true;
        }

        // Kamera dibekukan apa pun hasilnya: layar ini menuntut jawaban, dan
        // kode berikutnya yang lewat di depan lensa tidak boleh menggantinya.
        $this->dispatch('produk-ketemu');
    }

    /** Komputer di seberang boleh masuk sebagai saya. */
    public function setujuiLogin(): void
    {
        $token = $this->login['token'] ?? null;

        if (! $token) {
            return;
        }

        try {
            $hasil = Api::post('/qr-login/' . $token . '/approve');
            $nama = $hasil['data']['nama'] ?? 'akun Anda';

            $this->pesanLogin = 'Disetujui. Komputer itu sedang dibukakan sebagai ' . $nama . '.';
        } catch (RequestException $e) {
            $this->pesanLogin = $e->response->json('message') ?? 'Persetujuan ditolak server.';
        } catch (\Throwable $e) {
            $this->pesanLogin = 'Gagal menghubungi server. Belum ada yang disetujui.';
        }

        $this->login = null;
        $this->loginSelesai = true;
    }

    /** Bukan saya yang membuka ERP — kodenya dimatikan. */
    public function tolakLogin(): void
    {
        $token = $this->login['token'] ?? null;

        if (! $token) {
            return;
        }

        try {
            Api::post('/qr-login/' . $token . '/reject');
            $this->pesanLogin = 'Permintaan ditolak. Kode itu tidak bisa dipakai lagi.';
        } catch (\Throwable $e) {
            $this->pesanLogin = 'Gagal menghubungi server. Kode belum tentu tertolak.';
        }

        $this->login = null;
        $this->loginSelesai = true;
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
