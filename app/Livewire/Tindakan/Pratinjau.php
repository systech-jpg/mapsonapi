<?php

namespace App\Livewire\Tindakan;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Pratinjau Laporan Pemakaian sebelum divalidasi.
 *
 * Dibuka setelah TS menyimpan qty terpakai di halaman detail. Barisnya
 * ditampilkan datar (tanpa pengelompokan kit) supaya mudah diperiksa sekali
 * lihat sebelum dikunci — validasi tidak bisa dibatalkan dari aplikasi.
 */
class Pratinjau extends Component
{
    public int $tindakanId;

    public array $usage = [];

    public array $baris = [];

    public ?string $galat = null;

    public ?string $pesan = null;

    public function mount(int $id): void
    {
        $this->tindakanId = $id;

        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $data = Api::get("/tindakan/{$this->tindakanId}/usage")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Laporan pemakaian tidak bisa diambil.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->usage = $data['info'] ?? [];

        $this->baris = [];

        foreach ([...($data['paket_tray'] ?? []), ...($data['set_implant'] ?? [])] as $kit) {
            foreach ($kit['details'] ?? [] as $d) {
                $kirim = (int) ($d['qty_sent'] ?? 0);
                $pakai = (int) ($d['qty_used'] ?? 0);

                $this->baris[] = [
                    'ref' => $d['product_ref'] ?? '-',
                    'nama' => $d['product_label'] ?? '-',
                    'kirim' => $kirim,
                    'pakai' => $pakai,
                    'kembali' => (int) ($d['qty_return'] ?? ($kirim - $pakai)),
                ];
            }
        }
    }

    public function terkunci(): bool
    {
        return (int) ($this->usage['status'] ?? 0) !== 0;
    }

    /**
     * Baris yang qty pakainya melebihi qty kirim (kolom Kembali negatif).
     *
     * Bisa berasal dari data lama yang tersimpan sebelum batas ini ada, atau
     * dari perubahan lewat ERP — jadi diperiksa ulang di sini, bukan hanya
     * mengandalkan penjagaan di halaman pengisian.
     */
    public function barisSalah(): array
    {
        return array_values(array_filter(
            $this->baris,
            fn ($b) => $b['pakai'] > $b['kirim'] || $b['pakai'] < 0
        ));
    }

    /** Laporan dengan baris cacat tidak boleh dikunci: angkanya pasti salah. */
    public function bisaValidasi(): bool
    {
        return ! $this->terkunci() && empty($this->barisSalah());
    }

    public function validasi(): void
    {
        if (! $this->bisaValidasi()) {
            $this->pesan = 'Perbaiki dulu baris yang qty pakainya melebihi qty kirim.';

            return;
        }

        try {
            Api::post("/tindakan/usage/{$this->tindakanId}/validate");
        } catch (RequestException $e) {
            $pesanServer = $e->response->json('message') ?? 'Validasi ditolak server.';

            // Penyebab tersering penolakan adalah laporan sudah divalidasi dari
            // ERP selagi halaman ini terbuka. Datanya ditarik ulang dulu supaya
            // pesannya menyebut sebab yang sebenarnya, bukan "validasi gagal".
            $this->muat();

            $this->pesan = $this->terkunci()
                ? 'Laporan pemakaian ini sudah divalidasi. Status: ' . ($this->usage['status_label'] ?? '-')
                : $pesanServer;

            return;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Laporan belum divalidasi.';

            return;
        }

        $this->muat();
        $this->pesan = 'Laporan pemakaian berhasil divalidasi (Final).';
    }

    public function render()
    {
        return view('livewire.tindakan.pratinjau');
    }
}
