<?php

namespace App\Livewire\Tindakan;

use App\Support\Api;
use App\Support\Peran;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detail satu tindakan: informasi jadwal, daftar Paket Tray & Set Implant,
 * dan tombol aksi yang berbeda per peran.
 *
 * TS mengisi qty terpakai lalu menyimpannya sebagai Draft, gudang mengonfirmasi
 * kedatangan barang dan menarik barang. Semua kewenangan tetap diperiksa
 * server; peran di sini hanya menentukan tombol mana yang digambar.
 */
class Detail extends Component
{
    use WithFileUploads;

    public int $tindakanId;

    public array $info = [];

    public array $usage = [];

    /** Hasil normalisasi: dua seksi (tray & implant) berisi kit dan barisnya. */
    public array $seksi = [];

    /** Qty terpakai yang diisi user, dikunci ke det_id baris usage report. */
    public array $qty = [];

    /**
     * Qty kirim per det_id, dipakai sebagai batas atas saat menyimpan.
     * Disimpan terpisah dari $seksi supaya pemeriksaannya tidak perlu
     * menelusuri ulang struktur bertingkatnya.
     */
    public array $kirim = [];

    /**
     * Foto bukti tarik barang yang dipilih petugas gudang. Masih berupa berkas
     * sementara Livewire sampai tombol Tarik Barang ditekan.
     */
    public $bukti = null;

    public bool $isTS = false;

    /**
     * User yang masuk adalah TS yang ditugaskan pada tindakan ini (kolom
     * nama_ts). Diperiksa terpisah dari grup, karena penugasan per dokumen
     * lebih spesifik daripada keanggotaan grup — dan tetap dihormati walau
     * suatu saat TS-nya dikeluarkan dari grup.
     */
    public bool $tsDokumen = false;

    public ?string $galat = null;

    public ?string $pesan = null;

    /**
     * Menandai daftar alat berasal dari usage report (punya det_id), bukan dari
     * detail tindakan. Hanya sumber inilah yang qty-nya bisa disimpan.
     */
    public bool $dariUsage = false;

    public function mount(int $id): void
    {
        $this->tindakanId = $id;
        $this->isTS = Peran::isTS();

        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $data = Api::get("/tindakan/{$this->tindakanId}")['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Data tindakan tidak ditemukan.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->info = $data['info'] ?? [];

        // nama_ts menyimpan rowid user, bukan nama. Dibandingkan dengan rowid
        // di session supaya TS yang ditugaskan tetap dikenali walau grupnya
        // di Dolibarr belum diatur.
        $this->tsDokumen = filled($this->info['nama_ts'] ?? null)
            && (int) $this->info['nama_ts'] === (int) session('api_user.rowid');

        $tray = $data['paket_tray'] ?? [];
        $implant = $data['set_implant'] ?? [];
        $this->dariUsage = false;
        $this->usage = [];

        if ($this->perluUsage()) {
            $usage = $this->ambilUsage();

            // Baris usage report yang kosong berarti belum ada apa pun untuk
            // diisi; daftar dari detail tindakan tetap dipakai supaya alatnya
            // masih terlihat, meski qty-nya belum bisa disimpan.
            if ($usage && (! empty($usage['paket_tray']) || ! empty($usage['set_implant']))) {
                $this->usage = $usage['info'] ?? [];
                $tray = $usage['paket_tray'] ?? [];
                $implant = $usage['set_implant'] ?? [];
                $this->dariUsage = true;
            } elseif ($usage) {
                $this->usage = $usage['info'] ?? [];
            }
        }

        $this->seksi = array_values(array_filter([
            $this->normalkan('1. Paket Tray (Instrument)', $tray),
            $this->normalkan('2. Set Implant', $implant),
        ]));

        $this->qty = [];
        $this->kirim = [];

        foreach ($this->seksi as $seksi) {
            foreach ($seksi['kits'] as $kit) {
                foreach ($kit['baris'] as $baris) {
                    if ($baris['id']) {
                        $this->qty[$baris['id']] = $baris['pakai'];
                        $this->kirim[$baris['id']] = $baris['kirim'];
                    }
                }
            }
        }
    }

    /**
     * GET /tindakan/{id}/usage MEMBUAT usage report bila belum ada, jadi ia
     * tidak boleh dipanggil hanya karena halaman detail dibuka. Dipanggil
     * hanya bila laporannya memang sudah ada (fk_usage terisi), atau barangnya
     * sudah dikirim — titik di mana laporan pemakaian memang harus lahir.
     */
    protected function perluUsage(): bool
    {
        if (filled($this->info['fk_usage'] ?? null)) {
            return true;
        }

        return $this->statusMemuat(['In Delivery', 'Delivered', 'Ready']);
    }

    protected function ambilUsage(): ?array
    {
        try {
            return Api::get("/tindakan/{$this->tindakanId}/usage")['data'] ?? null;
        } catch (\Throwable $e) {
            // Laporan pemakaian belum bisa diambil bukan kegagalan yang perlu
            // menghentikan halaman: informasi jadwalnya tetap berguna.
            return null;
        }
    }

    /**
     * Menyeragamkan dua bentuk respons yang berbeda. Detail tindakan memakai
     * detail_id/qty/ref/label, sedangkan usage report memakai
     * det_id/qty_sent/qty_used/product_ref/product_label.
     */
    protected function normalkan(string $judul, array $kits): ?array
    {
        if (empty($kits)) {
            return null;
        }

        $hasil = [];

        foreach ($kits as $kit) {
            $baris = [];

            foreach ($kit['details'] ?? [] as $d) {
                $kirim = (int) ($d['qty_sent'] ?? $d['qty'] ?? 0);
                $pakai = (int) ($d['qty_used'] ?? 0);

                $baris[] = [
                    'id' => isset($d['det_id']) ? (int) $d['det_id'] : null,
                    'ref' => $d['product_ref'] ?? $d['ref'] ?? '-',
                    'nama' => $d['product_label'] ?? $d['label'] ?? '-',
                    'kirim' => $kirim,
                    'pakai' => $pakai,
                    'kembali' => (int) ($d['qty_return'] ?? ($kirim - $pakai)),
                ];
            }

            $hasil[] = [
                'ref' => $kit['ref'] ?? '-',
                'label' => $kit['label'] ?? '-',
                'qty' => (int) ($kit['qty'] ?? 0),
                'note' => $kit['note'] ?? '',
                'baris' => $baris,
            ];
        }

        return ['judul' => $judul, 'kits' => $hasil];
    }

    // --- Status ---

    protected function statusMemuat(array $kata): bool
    {
        $status = (string) ($this->info['status'] ?? '');

        foreach ($kata as $k) {
            if (stripos($status, $k) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Usage report dianggap terkunci begitu statusnya bukan Draft (0). */
    public function usageTerkunci(): bool
    {
        return (int) ($this->usage['status'] ?? 0) !== 0;
    }

    /** Badge: tahapan usage report menang begitu ia bergerak dari Draft. */
    public function labelStatus(): string
    {
        $label = $this->usage['status_label'] ?? null;

        if ($this->usageTerkunci() && filled($label)) {
            return $label;
        }

        return (string) ($this->info['status'] ?? '-');
    }

    /**
     * Menentukan halaman ini digambar sebagai layar TS (validasi jadwal,
     * isi pemakaian) atau layar gudang (barang sampai, tarik barang).
     */
    public function sisiTs(): bool
    {
        return $this->isTS || $this->tsDokumen;
    }

    /**
     * Satu-satunya penentu tabel bisa diisi atau tidak: barang harus sudah
     * sampai di RS, laporannya masih Draft, dan barisnya memang berasal dari
     * usage report sehingga punya det_id untuk disimpan.
     */
    public function bisaIsi(): bool
    {
        return $this->sisiTs()
            && $this->dariUsage
            && ! $this->usageTerkunci()
            && $this->statusMemuat(['Delivered', 'Ready']);
    }

    public function jadwalDraft(): bool
    {
        return $this->statusMemuat(['Draft']);
    }

    /**
     * Validasi jadwal adalah langkah milik TS, sama seperti tombol VALIDATE di
     * kartu tindakan Dolibarr. Peran lain tetap melihat jadwalnya, tapi tanpa
     * tombol — bukan disembunyikan diam-diam, ada keterangannya di layar.
     */
    public function bisaValidasiJadwal(): bool
    {
        return $this->sisiTs() && $this->jadwalDraft();
    }

    public function bisaKonfirmasiSampai(): bool
    {
        return ! $this->sisiTs() && $this->statusMemuat(['In Delivery']);
    }

    /** Tarik barang hanya pada status usage 1 (Validated, menunggu ditarik). */
    public function bisaTarikBarang(): bool
    {
        return ! $this->sisiTs() && (int) ($this->usage['status'] ?? 0) === 1;
    }

    /** Foto bukti tarik barang sudah pernah diunggah untuk laporan ini. */
    public function adaBukti(): bool
    {
        return filled($this->usage['bukti_tarik'] ?? null);
    }

    // --- Aksi ---

    /**
     * Mengembalikan kode produk yang qty pakainya di luar batas wajar:
     * lebih besar dari qty kirim, atau negatif. Kode dipakai apa adanya di
     * pesan galat supaya petugas tahu baris mana yang harus dibetulkan.
     */
    public function barisMelebihiKirim(): array
    {
        $salah = [];

        foreach ($this->seksi as $seksi) {
            foreach ($seksi['kits'] as $kit) {
                foreach ($kit['baris'] as $baris) {
                    if (! $baris['id']) {
                        continue;
                    }

                    $nilai = (int) ($this->qty[$baris['id']] ?? 0);

                    if ($nilai < 0 || $nilai > (int) ($this->kirim[$baris['id']] ?? 0)) {
                        $salah[] = $baris['ref'];
                    }
                }
            }
        }

        return $salah;
    }

    public function simpanDraft()
    {
        if (! $this->bisaIsi()) {
            return null;
        }

        // Endpoint save-lines menerima angka berapa pun, termasuk yang lebih
        // besar dari qty kirim — hasilnya kolom Kembali menjadi negatif dan
        // surat jalan mengaku mengembalikan barang yang tidak pernah dikirim.
        // Karena itu batasnya dijaga di sini, sebelum apa pun terkirim.
        $salah = $this->barisMelebihiKirim();

        if (! empty($salah)) {
            $this->pesan = 'Qty pakai tidak boleh melebihi qty kirim: ' . implode(', ', $salah) . '.';

            return null;
        }

        $lines = [];

        foreach ($this->qty as $detId => $nilai) {
            $lines[] = ['det_id' => (int) $detId, 'qty_used' => (int) $nilai];
        }

        if (empty($lines)) {
            $this->pesan = 'Tidak ada baris alat untuk disimpan.';

            return null;
        }

        try {
            // Endpoint menerima id tindakan maupun id usage report; id tindakan
            // dipakai supaya sama dengan aplikasi Android.
            Api::post("/tindakan/usage/{$this->tindakanId}/save-lines", ['lines' => $lines]);
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Gagal menyimpan qty pemakaian.';

            return null;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Angka Anda belum tersimpan.';

            return null;
        }

        session()->flash('pesan', 'Qty pemakaian tersimpan sebagai Draft. Periksa lalu validasi.');

        return $this->redirect(route('tindakan.pratinjau', $this->tindakanId), navigate: true);
    }

    /**
     * Endpoint /validate tidak memeriksa peran sama sekali, jadi penjagaannya
     * ada di sini. Tanpa ini, tombol yang tidak digambar tetap bisa dipanggil
     * lewat request Livewire buatan sendiri.
     */
    public function validasiJadwal(): void
    {
        if (! $this->bisaValidasiJadwal()) {
            return;
        }

        $this->jalankan("/tindakan/{$this->tindakanId}/validate", 'Jadwal tindakan berhasil divalidasi.');
    }

    public function konfirmasiSampai(): void
    {
        if (! $this->bisaKonfirmasiSampai()) {
            return;
        }

        $this->jalankan("/tindakan/{$this->tindakanId}/confirm-arrival", 'Barang dikonfirmasi sampai di RS.');
    }

    /**
     * Tarik barang selalu membawa foto bukti, mengikuti form di halaman usage
     * ERP. Karena itu aksinya tidak lewat jalankan(): payload-nya multipart,
     * bukan POST kosong seperti dua aksi lainnya.
     */
    public function tarikBarang(): void
    {
        if (! $this->bisaTarikBarang()) {
            return;
        }

        $this->validate([
            'bukti' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ], [
            'bukti.required' => 'Ambil atau pilih foto bukti tarik barang dulu.',
            'bukti.image' => 'Berkas yang dipilih bukan foto.',
            'bukti.max' => 'Ukuran foto maksimal 8 MB.',
        ]);

        try {
            Api::unggah(
                "/tindakan/usage/{$this->tindakanId}/tarik-barang",
                'bukti',
                $this->bukti->get(),
                $this->bukti->getClientOriginalName()
            );
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Tarik barang ditolak server.';
            $this->muat();

            return;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Barang belum ditarik.';

            return;
        }

        // Berkas sementaranya dilepas supaya tidak ikut tergambar lagi setelah
        // dokumen berpindah status.
        $this->bukti = null;

        $this->pesan = 'Barang berhasil ditarik. Foto bukti tersimpan.';
        $this->muat();
    }

    /**
     * Ketiga aksi di atas berbentuk sama: POST tanpa isi, lalu tarik ulang
     * data supaya tombol yang tampil mengikuti status terbaru dari server.
     */
    protected function jalankan(string $path, string $pesanSukses): void
    {
        try {
            Api::post($path);
        } catch (RequestException $e) {
            $this->pesan = $e->response->json('message') ?? 'Aksi ditolak server.';
            $this->muat();

            return;
        } catch (\Throwable $e) {
            $this->pesan = 'Gagal menghubungi server. Coba lagi.';

            return;
        }

        $this->pesan = $pesanSukses;
        $this->muat();
    }

    public function render()
    {
        return view('livewire.tindakan.detail');
    }
}
