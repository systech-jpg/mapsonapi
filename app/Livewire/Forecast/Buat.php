<?php

namespace App\Livewire\Forecast;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Layar pertama modul Forecast: membuat header dokumen.
 *
 * POST /forecast sekaligus men-generate snapshot stok di server, jadi begitu
 * berhasil user langsung diarahkan ke tabel input tanpa langkah tambahan.
 */
class Buat extends Component
{
    public array $principals = [];

    public ?int $principal = null;

    public int $bulan = 1;

    public string $tanggal = '';

    public ?string $galat = null;

    /** Dipakai untuk label dropdown; API hanya menerima angka 1-12. */
    public array $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function mount(): void
    {
        // Default ke bulan dan tanggal berjalan, kasus paling umum saat TS
        // membuat permintaan.
        $this->bulan = (int) Carbon::today()->month;
        $this->tanggal = Carbon::today()->toDateString();

        $this->muatPrincipal();
    }

    public function muatPrincipal(): void
    {
        $this->galat = null;

        try {
            $this->principals = Api::get('/forecast/principals')['data'] ?? [];
        } catch (\Throwable $e) {
            $this->principals = [];
            $this->galat = 'Gagal memuat daftar principal. Coba muat ulang.';
        }
    }

    public function buat()
    {
        $data = $this->validate([
            'principal' => ['required', 'integer'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tanggal' => ['required', 'date'],
        ], attributes: [
            'principal' => 'principal',
            'bulan' => 'bulan forecast',
            'tanggal' => 'tanggal forecast',
        ]);

        try {
            $hasil = Api::post('/forecast', [
                'fk_principal' => $data['principal'],
                'forecast_month' => $data['bulan'],
                'date_forecast' => $data['tanggal'],
            ]);
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal membuat dokumen forecast.';

            return null;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Periksa koneksi lalu coba lagi.';

            return null;
        }

        $id = $hasil['data']['id'] ?? null;

        if (! $id) {
            $this->galat = 'Server tidak mengembalikan nomor dokumen.';

            return null;
        }

        return $this->redirect(route('forecast.produk', $id), navigate: true);
    }

    public function render()
    {
        return view('livewire.forecast.buat');
    }
}
