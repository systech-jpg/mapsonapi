<?php

namespace App\Livewire\SalesOrder;

use App\Support\Api;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Daftar sales order, datanya diambil dari endpoint yang sama persis dengan
 * aplikasi Android. Token diambil otomatis dari session oleh Api::client().
 */
class Daftar extends Component
{
    public string $tanggal = '';

    public string $cari = '';

    public array $items = [];

    public ?string $galat = null;

    public function mount(): void
    {
        $this->tanggal = Carbon::today()->toDateString();

        $this->ambilData();
    }

    public function updatedTanggal(): void
    {
        $this->ambilData();
    }

    public function ambilData(): void
    {
        $this->galat = null;

        try {
            // Endpoint memfilter per tanggal, bukan pencarian teks.
            $this->items = Api::get('/sales-orders', ['date' => $this->tanggal])['data'] ?? [];
        } catch (\Throwable $e) {
            // Kegagalan jaringan/server tidak boleh membuat seluruh halaman 500.
            $this->items = [];
            $this->galat = 'Gagal memuat data sales order. Coba lagi.';
        }
    }

    /**
     * Pencarian dilakukan di sisi web karena endpoint tidak menyediakannya.
     */
    public function getHasilProperty(): array
    {
        $kata = trim($this->cari);

        if ($kata === '') {
            return $this->items;
        }

        return array_values(array_filter($this->items, function ($item) use ($kata) {
            $target = implode(' ', [
                $item['ref'] ?? '',
                $item['ref_client'] ?? '',
                $item['third_party'] ?? '',
            ]);

            return stripos($target, $kata) !== false;
        }));
    }

    public function render()
    {
        return view('livewire.sales-order.daftar');
    }
}
