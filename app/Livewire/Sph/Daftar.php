<?php

namespace App\Livewire\Sph;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Daftar SPH (Surat Penawaran Harga), terbaru dulu.
 *
 * Bentuknya mengikuti daftar Tindakan: satu kotak pencarian, kartu per baris,
 * dan tombol "Muat lebih banyak" — bukan penomoran halaman, karena di ponsel
 * menggulir lebih mudah daripada menekan angka halaman kecil-kecil.
 */
class Daftar extends Component
{
    public array $items = [];

    public string $cari = '';

    public int $halaman = 1;

    public bool $adaLagi = false;

    public int $total = 0;

    public ?string $galat = null;

    public function mount(): void
    {
        $this->muat();
    }

    /** Setiap ketikan baru mengulang dari halaman pertama. */
    public function updatedCari(): void
    {
        $this->halaman = 1;
        $this->items = [];
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $respons = Api::get('/sph', [
                'page' => $this->halaman,
                'search' => $this->cari,
            ]);
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil daftar SPH.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $baris = $respons['data'] ?? [];
        $meta = $respons['meta'] ?? [];

        // Halaman berikutnya ditambahkan, bukan menggantikan: tombol "Muat
        // lebih banyak" harus menumpuk hasil, bukan melompat.
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

    public function render()
    {
        return view('livewire.sph.daftar');
    }
}
