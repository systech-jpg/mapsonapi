<?php

namespace App\Livewire\Stocktake;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Daftar dokumen stocktake, terbaru dulu.
 *
 * Dokumen dibuat di ERP, bukan di sini — halaman ini hanya pintu masuk ke
 * layar pengisian. Karena itu tidak ada tombol "Buat".
 */
class Daftar extends Component
{
    public array $items = [];

    public ?string $galat = null;

    public function mount(): void
    {
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $respons = Api::get('/stocktake');
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil daftar stocktake.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->items = $respons['data'] ?? [];
    }

    public function render()
    {
        return view('livewire.stocktake.daftar');
    }
}
