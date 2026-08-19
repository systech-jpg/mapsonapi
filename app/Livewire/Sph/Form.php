<?php

namespace App\Livewire\Sph;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Form buat / ubah header SPH, isinya disamakan dengan
 * custom/sph/card.php?action=create: Nomor Quotation, Principal, Pelanggan
 * (wajib), Sales, Tanggal SPH (wajib), Valid until, dan Note.
 *
 * Satu komponen untuk dua keperluan, mengikuti pola Tindakan\Form: $sphId
 * kosong berarti membuat baru, terisi berarti mengubah (tombol MODIFY di ERP).
 *
 * Yang berbeda dari ERP hanya pemilih Pelanggan: di web ia select_company()
 * yang menurunkan ratusan societe sekaligus, sedangkan di ponsel dipakai
 * pencarian ketik-cari lewat lembar pilih (.sheet).
 */
class Form extends Component
{
    /** Terisi hanya saat mengubah dokumen yang sudah ada. */
    public ?int $sphId = null;

    public string $refBerikut = '';

    /** Daftar principal dan nama sales, keduanya datang dari API. */
    public array $principals = [];

    public array $sales = [];

    // --- Isian form ---

    public string $ref_quotation = '';

    public string $fk_principal = '';

    public ?int $fk_soc = null;

    public string $namaPelanggan = '';

    public string $sales_name = '';

    public string $date_sph = '';

    public string $date_valid = '';

    public string $note = '';

    // --- Lembar pilih pelanggan ---

    public bool $sheetPelanggan = false;

    public string $cariPelanggan = '';

    public array $hasilPelanggan = [];

    public ?string $galat = null;

    public function mount(?int $id = null): void
    {
        $this->sphId = $id;

        // Tanggal SPH default hari ini, sama dengan dol_now() di form ERP.
        $this->date_sph = now()->format('Y-m-d');

        try {
            $data = Api::get('/sph/form-options')['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil pilihan form.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Muat ulang halaman ini.';

            return;
        }

        $this->refBerikut = (string) ($data['next_ref'] ?? '');
        $this->principals = $data['principals'] ?? [];
        $this->sales = $data['sales'] ?? [];

        // Sales pertama dipilih lebih dulu, meniru <select> di ERP yang memang
        // sudah menampilkan pilihan pertama sebelum disentuh.
        $this->sales_name = (string) ($this->sales[0] ?? '');

        if ($this->sphId) {
            $this->muatDokumen();
        }
    }

    /** Isi form dari dokumen yang sudah ada (mode ubah). */
    protected function muatDokumen(): void
    {
        try {
            $info = Api::get("/sph/{$this->sphId}")['data']['info'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Data SPH tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->refBerikut = (string) ($info['ref'] ?? '');
        $this->ref_quotation = (string) ($info['ref_quotation'] ?? '');
        $this->fk_principal = (string) ($info['fk_principal'] ?? '');
        $this->fk_soc = isset($info['fk_soc']) ? (int) $info['fk_soc'] : null;
        $this->namaPelanggan = (string) ($info['customer_name'] ?? '');
        $this->sales_name = (string) ($info['sales_name'] ?? $this->sales_name);
        $this->note = (string) ($info['note'] ?? '');

        // Kolom datetime dipangkas ke tanggal saja: <input type="date"> menolak
        // nilai yang membawa jam dan diam-diam tampil kosong.
        $this->date_sph = ! empty($info['date_sph'])
            ? \Illuminate\Support\Carbon::parse($info['date_sph'])->format('Y-m-d')
            : $this->date_sph;

        $this->date_valid = ! empty($info['date_valid'])
            ? \Illuminate\Support\Carbon::parse($info['date_valid'])->format('Y-m-d')
            : '';
    }

    public function bukaPelanggan(): void
    {
        $this->sheetPelanggan = true;
        $this->cariPelanggan = '';

        $this->cariPelangganSekarang();
    }

    public function tutupPelanggan(): void
    {
        $this->sheetPelanggan = false;
    }

    public function updatedCariPelanggan(): void
    {
        $this->cariPelangganSekarang();
    }

    protected function cariPelangganSekarang(): void
    {
        try {
            $this->hasilPelanggan = Api::get('/sph/customers', ['search' => $this->cariPelanggan])['data'] ?? [];
        } catch (\Throwable $e) {
            $this->hasilPelanggan = [];
            $this->galat = 'Gagal mencari pelanggan. Periksa koneksi.';
        }
    }

    public function pilihPelanggan(int $id, string $nama): void
    {
        $this->fk_soc = $id;
        $this->namaPelanggan = $nama;
        $this->sheetPelanggan = false;
        $this->galat = null;
    }

    public function simpan()
    {
        $this->galat = null;

        $aturan = [
            'fk_soc' => ['required', 'integer'],
            'date_sph' => ['required', 'date'],
            'ref_quotation' => ['nullable', 'string', 'max:50'],
        ];

        // <input type="date"> yang dikosongkan mengirim string kosong, BUKAN
        // null, dan aturan 'date' menolak string kosong walau diberi
        // 'nullable' -- nullable hanya melewatkan null. Kalau diperiksa apa
        // adanya, "Valid until" jadi wajib diisi padahal di ERP boleh kosong.
        if (trim($this->date_valid) !== '') {
            $aturan['date_valid'] = ['date', 'after_or_equal:date_sph'];
        }

        // Diperiksa di sini supaya petugas tidak perlu menunggu perjalanan ke
        // server hanya untuk diberi tahu isian yang jelas-jelas kosong.
        // Server tetap memeriksa ulang semuanya.
        $this->validate($aturan, [
            'fk_soc.required' => 'Pelanggan wajib dipilih.',
            'date_sph.required' => 'Tanggal SPH wajib diisi.',
            'date_valid.after_or_equal' => 'Valid until tidak boleh mendahului Tanggal SPH.',
            'ref_quotation.max' => 'Nomor Quotation maksimal 50 karakter.',
        ]);

        $muatan = [
            'fk_soc' => $this->fk_soc,
            'fk_principal' => $this->fk_principal !== '' ? (int) $this->fk_principal : null,
            'ref_quotation' => $this->ref_quotation !== '' ? $this->ref_quotation : null,
            'sales_name' => $this->sales_name !== '' ? $this->sales_name : null,
            'date_sph' => $this->date_sph,
            'date_valid' => $this->date_valid !== '' ? $this->date_valid : null,
            'note' => $this->note !== '' ? $this->note : null,
        ];

        try {
            $data = $this->sphId
                ? (Api::put("/sph/{$this->sphId}", $muatan)['data'] ?? [])
                : (Api::post('/sph', $muatan)['data'] ?? []);
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'SPH gagal disimpan.';

            return null;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Perubahan belum tersimpan.';

            return null;
        }

        // Sesudah dibuat, langsung ke detailnya: di situlah baris barang
        // ditambahkan, dan dokumen tanpa barang belum ada gunanya.
        $tujuan = (int) ($data['id'] ?? $this->sphId);

        session()->flash('pesan', $this->sphId
            ? 'Perubahan SPH tersimpan.'
            : 'SPH ' . ($data['ref'] ?? '') . ' dibuat sebagai Draft. Tambahkan barangnya di bawah.');

        return $this->redirect(route('sph.detail', $tujuan), navigate: true);
    }

    public function render()
    {
        return view('livewire.sph.form');
    }
}
