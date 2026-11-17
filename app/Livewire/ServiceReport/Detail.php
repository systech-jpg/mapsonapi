<?php

namespace App\Livewire\ServiceReport;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Detail satu service report beserta isian tindak lanjut tim teknis.
 *
 * Bagian A–C hanya ditampilkan: itu pernyataan pelapor dan API memang tidak
 * menerima perubahannya. Yang bisa diisi teknisi: status, tanggal pengerjaan,
 * baris pemeriksaan + solusi (bagian D), nama engineer, dan catatan.
 */
class Detail extends Component
{
    private const MAKS_BARIS = 20;

    public int $srId;

    public array $info = [];

    public ?string $galat = null;

    public ?string $pesan = null;

    // --- Isian tindak lanjut ---

    public string $status = '0';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $nama_engineer = '';

    public string $catatan = '';

    public array $pemeriksaan = [];

    public function mount(int $id): void
    {
        $this->srId = $id;
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $data = Api::get("/service-report/{$this->srId}")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Service report tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->isi($data);
    }

    private function isi(array $data): void
    {
        $this->info = $data;
        $this->status = (string) ($data['status'] ?? 0);
        $this->tanggal_mulai = (string) ($data['tanggal_mulai'] ?? '');
        $this->tanggal_selesai = (string) ($data['tanggal_selesai'] ?? '');
        $this->nama_engineer = (string) ($data['nama_engineer'] ?? '');
        $this->catatan = (string) ($data['catatan_tindak_lanjut'] ?? '');
        $this->pemeriksaan = $data['pemeriksaan'] ?? [];

        if ($this->pemeriksaan === []) {
            $this->pemeriksaan = [['tahap' => '', 'solusi' => '']];
        }
    }

    public function tambahPemeriksaan(): void
    {
        if (count($this->pemeriksaan) < self::MAKS_BARIS) {
            $this->pemeriksaan[] = ['tahap' => '', 'solusi' => ''];
        }
    }

    public function hapusPemeriksaan(int $i): void
    {
        unset($this->pemeriksaan[$i]);
        $this->pemeriksaan = array_values($this->pemeriksaan) ?: [['tahap' => '', 'solusi' => '']];
    }

    public function simpan(): void
    {
        $this->galat = null;
        $this->pesan = null;

        if ($this->tanggal_mulai !== '' && $this->tanggal_selesai !== '' && $this->tanggal_selesai < $this->tanggal_mulai) {
            $this->galat = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';

            return;
        }

        try {
            $data = Api::put("/service-report/{$this->srId}", [
                'status' => (int) $this->status,
                'tanggal_mulai' => $this->tanggal_mulai ?: null,
                'tanggal_selesai' => $this->tanggal_selesai ?: null,
                'nama_engineer' => $this->nama_engineer,
                'catatan_tindak_lanjut' => $this->catatan,
                'pemeriksaan' => $this->pemeriksaan,
            ])['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Tindak lanjut gagal disimpan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba lagi.';

            return;
        }

        $this->isi($data);
        $this->pesan = 'Tindak lanjut tersimpan.';
    }

    public function render()
    {
        return view('livewire.service-report.detail', [
            'pilihanStatus' => \App\Http\Controllers\Api\ServiceReportController::STATUS,
        ]);
    }
}
