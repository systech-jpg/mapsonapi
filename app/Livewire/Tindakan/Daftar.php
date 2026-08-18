<?php

namespace App\Livewire\Tindakan;

use App\Support\Api;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Daftar tindakan bulan berjalan. Endpoint memaginasi hasilnya (default 50
 * baris), jadi halaman berikutnya ditarik lewat tombol "Muat lebih banyak" —
 * bukan gulir tak hingga seperti di Android, supaya tidak butuh JavaScript
 * tambahan dan pengguna tetap bisa memilih kapan menarik data.
 */
class Daftar extends Component
{
    public array $items = [];

    public string $cari = '';

    public int $halaman = 1;

    public bool $adaLagi = false;

    public array $periode = [];

    public ?string $galat = null;

    public function mount(): void
    {
        $this->muat();
    }

    /** Selalu mulai dari halaman pertama; akumulasi lama dibuang. */
    public function muat(): void
    {
        $this->halaman = 1;
        $this->items = [];

        $this->ambil(1);
    }

    public function muatLagi(): void
    {
        if (! $this->adaLagi) {
            return;
        }

        $this->ambil($this->halaman + 1);
    }

    protected function ambil(int $halaman): void
    {
        $this->galat = null;

        try {
            $hasil = Api::get('/tindakan', ['page' => $halaman, 'per_page' => 50]);
        } catch (\Throwable $e) {
            // Kegagalan jaringan/server tidak boleh membuat seluruh halaman 500.
            $this->galat = 'Gagal memuat daftar tindakan. Coba lagi.';

            return;
        }

        $baris = $hasil['data'] ?? [];
        $meta = $hasil['meta'] ?? [];

        // Halaman 1 mengganti isi, halaman berikutnya menambah ke bawah.
        $this->items = $halaman === 1 ? $baris : array_merge($this->items, $baris);

        $this->halaman = (int) ($meta['current_page'] ?? $halaman);
        $this->adaLagi = (bool) ($meta['has_more'] ?? false);
        $this->periode = $meta['periode'] ?? [];
    }

    /**
     * Endpoint tidak menyediakan pencarian, jadi saringan ini hanya bekerja
     * pada baris yang sudah ditarik. Baris di halaman yang belum dimuat tidak
     * ikut tersaring — itu sebabnya tombol "Muat lebih banyak" tetap terlihat
     * walaupun kotak saring sedang terisi.
     */
    #[Computed]
    public function hasil(): array
    {
        $kata = trim($this->cari);

        if ($kata === '') {
            return $this->items;
        }

        return array_values(array_filter($this->items, function ($item) use ($kata) {
            $target = implode(' ', [
                $item['ref'] ?? '',
                $item['rs_name'] ?? '',
                $item['dokter_name'] ?? '',
                $item['pasien'] ?? '',
            ]);

            return stripos($target, $kata) !== false;
        }));
    }

    /**
     * Tahapan usage report menang begitu ia bergerak dari Draft, sama seperti
     * badge di daftar Android.
     */
    public function label(array $item): string
    {
        $usageStatus = $item['usage_status'] ?? null;
        $usageLabel = $item['usage_status_label'] ?? null;

        // Usage report yang masih Draft (0) tidak boleh menutupi status
        // tindakan: keduanya sama-sama berlabel "Draft", padahal yang perlu
        // dilihat petugas adalah sudah sampai mana pengiriman barangnya.
        if ($usageStatus !== null && (int) $usageStatus !== 0 && filled($usageLabel)) {
            return $usageLabel;
        }

        return $item['status'] ?? '-';
    }

    public function render()
    {
        return view('livewire.tindakan.daftar');
    }
}
