<?php

namespace App\Livewire\Tindakan;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Form buat / ubah jadwal tindakan, dua langkah seperti di Android:
 * isian dulu, lalu pratinjau sebelum benar-benar dikirim ke server.
 *
 * Rumah sakit dan dokter dipilih lewat pencarian ke server (daftarnya panjang),
 * sedangkan TS ditarik sekali di awal karena isinya sedikit dan jarang berubah.
 */
class Form extends Component
{
    /** Terisi hanya saat mengubah jadwal yang sudah ada. */
    public ?int $tindakanId = null;

    /** 'isian' atau 'pratinjau'. */
    public string $langkah = 'isian';

    public string $tanggal = '';

    public string $waktu = '';

    public ?int $rsId = null;

    public string $rsNama = '';

    public ?int $dokterId = null;

    public string $dokterNama = '';

    public ?int $tsId = null;

    public array $tsList = [];

    public string $jenisTindakan = '';

    public string $pasien = '';

    public string $pasienDob = '';

    public string $rencanaAlat = '';

    public string $diagnosa = '';

    public ?string $galat = null;

    // --- Dialog pencarian ---

    /** null, 'rs', atau 'dokter'. */
    public ?string $dialog = null;

    public string $cariDialog = '';

    public array $hasilDialog = [];

    public ?string $pesanDialog = null;

    public function mount(?int $id = null): void
    {
        $this->tindakanId = $id;

        $this->muatTs();

        if ($id) {
            $this->muatJadwal($id);
        }
    }

    protected function muatTs(): void
    {
        try {
            // Query kosong berarti "ambil semua", bukan mencari.
            $this->tsList = Api::get('/technical-supports', ['search' => '', 'limit' => 100])['data'] ?? [];
        } catch (\Throwable $e) {
            $this->tsList = [];
            $this->galat = 'Daftar TS gagal dimuat. Muat ulang halaman ini sebelum menyimpan.';
        }
    }

    protected function muatJadwal(int $id): void
    {
        try {
            $data = Api::get("/tindakan/{$id}")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Jadwal tindakan tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang halaman.';

            return;
        }

        $info = $data['info'] ?? [];

        // Tanggal dari Dolibarr bisa membawa jam ("2026-08-18 00:00:00"),
        // sedangkan <input type="date"> hanya menerima bagian tanggalnya.
        $this->tanggal = substr((string) ($info['tanggal'] ?? ''), 0, 10);
        $this->waktu = substr((string) ($info['waktu'] ?? ''), 0, 5);

        // fk_soc, bukan entity. Android mengisi hospital_id dari info.entity
        // saat mode ubah, sehingga rumah sakitnya bisa berubah diam-diam
        // begitu jadwal disimpan ulang dari sana.
        $this->rsId = isset($info['fk_soc']) ? (int) $info['fk_soc'] : null;
        $this->rsNama = (string) ($info['rs_name'] ?? '');

        $this->dokterId = isset($info['dokter']) ? (int) $info['dokter'] : null;
        $this->dokterNama = (string) ($info['dokter_name'] ?? '');

        // Kolom penampung TS bernama nama_ts, tapi isinya rowid user.
        $this->tsId = isset($info['nama_ts']) ? (int) $info['nama_ts'] : null;

        $this->pastikanTsAdaDiDaftar($info);

        $this->jenisTindakan = (string) ($info['jenis_tindakan'] ?? '');
        $this->pasien = (string) ($info['pasien'] ?? '');
        $this->pasienDob = substr((string) ($info['pasien_dob'] ?? ''), 0, 10);
        $this->rencanaAlat = (string) ($info['rencana_alat'] ?? '');
        $this->diagnosa = (string) ($info['diagnosa'] ?? '');
    }

    /**
     * Endpoint /technical-supports hanya mengembalikan user aktif ber-job "1".
     * TS yang sudah nonaktif atau pindah jabatan tidak ada di daftar itu,
     * sehingga dropdown akan tampil kosong dan menyimpan ulang jadwal justru
     * mengganti PIC-nya diam-diam. Karena itu TS lama disisipkan sendiri.
     */
    protected function pastikanTsAdaDiDaftar(array $info): void
    {
        if (! $this->tsId) {
            return;
        }

        foreach ($this->tsList as $ts) {
            if ((int) ($ts['id'] ?? 0) === $this->tsId) {
                return;
            }
        }

        $nama = trim(($info['ts_firstname'] ?? '') . ' ' . ($info['ts_lastname'] ?? ''));

        $this->tsList[] = [
            'id' => $this->tsId,
            'label' => $nama !== '' ? $nama . ' (tidak aktif)' : 'TS lama (#' . $this->tsId . ')',
        ];
    }

    protected function aturan(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'waktu' => ['nullable', 'string'],
            'rsId' => ['required', 'integer'],
            'dokterId' => ['required', 'integer'],
            'tsId' => ['required', 'integer'],
            'pasien' => ['required', 'string', 'max:255'],
            'pasienDob' => ['nullable', 'date'],
            'jenisTindakan' => ['nullable', 'string'],
            'rencanaAlat' => ['required', 'string'],
            'diagnosa' => ['nullable', 'string'],
        ];
    }

    protected function namaField(): array
    {
        return [
            'tanggal' => 'tanggal operasi',
            'waktu' => 'jam operasi',
            'rsId' => 'rumah sakit',
            'dokterId' => 'dokter operator',
            'tsId' => 'TS / PIC lapangan',
            'pasien' => 'nama pasien',
            'pasienDob' => 'tanggal lahir pasien',
            'rencanaAlat' => 'pesanan / alat',
            'diagnosa' => 'catatan lain',
        ];
    }

    public function lanjut(): void
    {
        $this->validate($this->aturan(), attributes: $this->namaField());

        $this->langkah = 'pratinjau';
    }

    public function kembaliIsian(): void
    {
        $this->langkah = 'isian';
    }

    // --- Dialog pencarian rumah sakit / dokter ---

    public function bukaDialog(string $jenis): void
    {
        $this->dialog = $jenis;
        $this->cariDialog = '';
        $this->hasilDialog = [];
        $this->pesanDialog = 'Ketik minimal 3 huruf untuk mencari.';
    }

    public function tutupDialog(): void
    {
        $this->dialog = null;
        $this->cariDialog = '';
        $this->hasilDialog = [];
        $this->pesanDialog = null;
    }

    public function updatedCariDialog(): void
    {
        $kata = trim($this->cariDialog);

        // Ambang 3 huruf mengikuti Android: di bawah itu hasilnya terlalu
        // banyak dan setiap ketikan membebani server tanpa gunanya.
        if (mb_strlen($kata) < 3) {
            $this->hasilDialog = [];
            $this->pesanDialog = 'Ketik minimal 3 huruf untuk mencari.';

            return;
        }

        $path = $this->dialog === 'dokter' ? '/doctors' : '/hospitals';

        try {
            $this->hasilDialog = Api::get($path, ['search' => $kata, 'limit' => 25])['data'] ?? [];
        } catch (\Throwable $e) {
            $this->hasilDialog = [];
            $this->pesanDialog = 'Pencarian gagal. Coba lagi.';

            return;
        }

        $this->pesanDialog = empty($this->hasilDialog) ? 'Tidak ditemukan.' : null;
    }

    public function pilih(int $id, string $label): void
    {
        if ($this->dialog === 'dokter') {
            $this->dokterId = $id;
            $this->dokterNama = $label;
        } else {
            $this->rsId = $id;
            $this->rsNama = $label;
        }

        $this->tutupDialog();
    }

    // --- Simpan ---

    public function simpan()
    {
        $this->validate($this->aturan(), attributes: $this->namaField());

        $muatan = [
            'tanggal' => $this->tanggal,
            'waktu' => $this->waktu ?: null,
            'fk_soc' => $this->rsId,
            'dokter' => $this->dokterId,
            'fk_ts' => $this->tsId,
            'jenis_tindakan' => $this->jenisTindakan ?: null,
            'pasien' => $this->pasien,
            'pasien_dob' => $this->pasienDob ?: null,
            'rencana_alat' => $this->rencanaAlat,
            'diagnosa' => $this->diagnosa ?: null,
        ];

        try {
            $hasil = $this->tindakanId
                ? Api::put("/tindakan/{$this->tindakanId}", $muatan)
                : Api::post('/tindakan', $muatan);
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal menyimpan jadwal tindakan.';
            $this->langkah = 'isian';

            return null;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Jadwal belum tersimpan.';
            $this->langkah = 'isian';

            return null;
        }

        $id = $this->tindakanId ?? ($hasil['data']['id'] ?? null);

        if (! $id) {
            $this->galat = 'Server tidak mengembalikan nomor jadwal.';
            $this->langkah = 'isian';

            return null;
        }

        session()->flash('pesan', $this->tindakanId
            ? 'Jadwal tindakan berhasil diperbarui.'
            : 'Jadwal tindakan berhasil dibuat. Validasi bila datanya sudah benar.');

        return $this->redirect(route('tindakan.detail', $id), navigate: true);
    }

    /** Nama TS untuk ditampilkan di langkah pratinjau. */
    public function namaTs(): string
    {
        foreach ($this->tsList as $ts) {
            if ((int) ($ts['id'] ?? 0) === (int) $this->tsId) {
                return (string) ($ts['label'] ?? '-');
            }
        }

        return '-';
    }

    public function render()
    {
        return view('livewire.tindakan.form');
    }
}
