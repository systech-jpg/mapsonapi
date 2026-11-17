<?php

namespace App\Livewire\ServiceReport;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Formulir Service Report untuk pelapor tanpa akun (dibuka dari kode QR).
 *
 * Isiannya mengikuti formulir kertas 007/01/TKS-MAP/F-03 bagian A sampai D
 * plus nama di kotak tanda tangan. Pengiriman tetap lewat routes/api.php,
 * bukan menulis tabel langsung, supaya Android kelak bisa memakai endpoint
 * yang sama.
 */
class FormPublik extends Component
{
    private const MAKS_BARIS = 20;

    public array $jenis = [];

    public string $customer_nama = '';

    public string $customer_alamat = '';

    public string $department = '';

    public string $position = '';

    public string $customer_ref = '';

    public string $telepon = '';

    public string $contact_person = '';

    public string $email = '';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public array $produk = [];

    public array $pemeriksaan = [];

    public string $nama_engineer = '';

    public string $nama_customer = '';

    /** Isian jebakan untuk robot; disembunyikan dari manusia. */
    public string $situs = '';

    public ?string $galat = null;

    /** Nomor laporan sesudah terkirim; mengganti formulir dengan layar terima kasih. */
    public ?string $terkirim = null;

    public function mount(): void
    {
        $this->produk = [$this->barisProduk()];
        $this->pemeriksaan = [$this->barisPemeriksaan()];
    }

    public function tambahProduk(): void
    {
        if (count($this->produk) < self::MAKS_BARIS) {
            $this->produk[] = $this->barisProduk();
        }
    }

    public function hapusProduk(int $i): void
    {
        unset($this->produk[$i]);
        $this->produk = array_values($this->produk) ?: [$this->barisProduk()];
    }

    public function tambahPemeriksaan(): void
    {
        if (count($this->pemeriksaan) < self::MAKS_BARIS) {
            $this->pemeriksaan[] = $this->barisPemeriksaan();
        }
    }

    public function hapusPemeriksaan(int $i): void
    {
        unset($this->pemeriksaan[$i]);
        $this->pemeriksaan = array_values($this->pemeriksaan) ?: [$this->barisPemeriksaan()];
    }

    public function kirim(): void
    {
        $this->galat = null;

        // Diperiksa di sini supaya pesan galat muncul di samping isiannya.
        // API memeriksa ulang dengan aturan yang sama.
        $this->validate([
            'jenis' => ['required', 'array', 'min:1'],
            'customer_nama' => ['required', 'string', 'max:150'],
            'telepon' => ['required', 'string', 'max:50'],
            'contact_person' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'tanggal_selesai' => ['nullable', 'date', $this->tanggal_mulai !== '' ? 'after_or_equal:tanggal_mulai' : 'nullable'],
            'pemeriksaan.0.tahap' => ['required', 'string', 'max:2000'],
        ], [
            ...\App\Http\Controllers\Api\ServiceReportController::PESAN,
            'jenis.required' => 'Pilih minimal satu jenis pekerjaan.',
            'pemeriksaan.0.tahap.required' => 'Uraikan keluhan atau tahap pemeriksaan pada baris pertama.',
        ], [
            'customer_nama' => 'nama customer',
            'telepon' => 'nomor telepon',
            'contact_person' => 'contact person',
            'tanggal_selesai' => 'tanggal selesai',
            'email' => 'email',
            'pemeriksaan.0.tahap' => 'tahap pemeriksaan',
        ]);

        // Semua kiriman Livewire datang ke /livewire/update dan semua
        // panggilan API datang dari server ini sendiri, jadi pembatas yang
        // berarti hanya yang dikunci ke IP pelapor.
        $kunci = 'service-report-publik:'.request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->galat = 'Terlalu banyak kiriman dari perangkat ini. Coba lagi dalam beberapa menit.';

            return;
        }

        RateLimiter::hit($kunci, 600);

        try {
            $respons = Api::client()
                // trustProxies('*') membuat API membaca header ini sebagai IP
                // pengirim, sehingga throttle dan ip_pelapor di sana memakai
                // IP pelapor, bukan IP server ini.
                ->withHeaders(['X-Forwarded-For' => request()->ip()])
                ->post('/service-report/publik', [
                    'jenis' => $this->jenis,
                    'customer_nama' => $this->customer_nama,
                    'customer_alamat' => $this->customer_alamat,
                    'department' => $this->department,
                    'position' => $this->position,
                    'customer_ref' => $this->customer_ref,
                    'telepon' => $this->telepon,
                    'contact_person' => $this->contact_person,
                    'email' => $this->email,
                    'tanggal_mulai' => $this->tanggal_mulai ?: null,
                    'tanggal_selesai' => $this->tanggal_selesai ?: null,
                    'produk' => $this->produk,
                    'pemeriksaan' => $this->pemeriksaan,
                    'nama_engineer' => $this->nama_engineer,
                    'nama_customer' => $this->nama_customer,
                    'situs' => $this->situs,
                ])
                ->throw();
        } catch (RequestException $e) {
            $this->galat = $e->response->status() === 429
                ? 'Terlalu banyak kiriman. Tunggu sebentar lalu coba lagi.'
                : ($e->response->json('message') ?? 'Laporan gagal dikirim. Coba lagi.');

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Periksa sinyal lalu coba lagi.';

            return;
        }

        $this->terkirim = $respons->json('data.no_report') ?: '-';
    }

    /** Mulai laporan baru dari layar terima kasih. */
    public function laporLagi(): void
    {
        $this->reset();
        $this->mount();
    }

    private function barisProduk(): array
    {
        return ['description' => '', 'type' => '', 'nie' => '', 'sn' => '', 'sw_version' => ''];
    }

    private function barisPemeriksaan(): array
    {
        return ['tahap' => '', 'solusi' => ''];
    }

    public function render()
    {
        return view('livewire.service-report.form-publik', [
            'pilihanJenis' => \App\Http\Controllers\Api\ServiceReportController::JENIS,
        ]);
    }
}
