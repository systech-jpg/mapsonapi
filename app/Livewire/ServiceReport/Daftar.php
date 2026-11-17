<?php

namespace App\Livewire\ServiceReport;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Daftar service report masuk, terbaru dulu. Bentuknya sama dengan daftar
 * SPH: kotak cari, kartu per laporan, "Muat lebih banyak".
 */
class Daftar extends Component
{
    public array $items = [];

    public string $cari = '';

    /** '' = semua; selain itu angka status (0 Baru, 1 Diproses, 2 Selesai). */
    public string $status = '';

    public int $halaman = 1;

    public bool $adaLagi = false;

    public int $total = 0;

    public ?string $galat = null;

    public function mount(): void
    {
        $this->muat();
    }

    public function updatedCari(): void
    {
        $this->ulang();
    }

    public function saring(string $status): void
    {
        $this->status = $status;
        $this->ulang();
    }

    private function ulang(): void
    {
        $this->halaman = 1;
        $this->items = [];
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $respons = Api::get('/service-report', array_filter([
                'page' => $this->halaman,
                'search' => $this->cari,
                'status' => $this->status,
            ], fn ($v) => $v !== ''));
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil daftar service report.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $baris = $respons['data'] ?? [];
        $meta = $respons['meta'] ?? [];

        $this->items = $this->halaman > 1 ? array_merge($this->items, $baris) : $baris;
        $this->adaLagi = (bool) ($meta['has_more'] ?? false);
        $this->total = (int) ($meta['total'] ?? count($this->items));
    }

    public function muatLagi(): void
    {
        if (! $this->adaLagi) {
            return;
        }

        $this->halaman++;
        $this->muat();
    }

    public static function warna(int $status): string
    {
        return match ($status) {
            0 => 'oranye',
            1 => 'biru',
            2 => 'hijau',
            default => 'netral',
        };
    }

    public function render()
    {
        return view('livewire.service-report.daftar');
    }
}
