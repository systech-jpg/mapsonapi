<?php

namespace App\Livewire\Stocktake;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Halaman kerja stocktake: mengisi hitungan fisik satu dokumen.
 *
 * Angkanya diketik langsung di daftar. Tiga kotak per baris (Rak, Tray,
 * Container) dan totalnya dijumlahkan browser tanpa memanggil server — sama
 * seperti calcQty() di card.php ERP. Yang dikirim ke server hanya baris yang
 * benar-benar berubah, saat tombol Simpan ditekan.
 */
class Hitung extends Component
{
    public int $id;

    public array $dokumen = [];

    public array $ringkasan = [];

    public array $principals = [];

    /** Baris yang sedang tergambar di layar. */
    public array $baris = [];

    /**
     * Isian pengguna, det_id => ['rak','tray','container','catatan'].
     *
     * Sengaja TIDAK dikosongkan saat penyaring diganti. Isian yang belum
     * disimpan tetap tersimpan di sini walau barisnya sedang tidak tampak,
     * dan tetap ikut terkirim saat Simpan ditekan — kalau dikosongkan,
     * mengetuk chip principal lain berarti kehilangan ketikan diam-diam.
     */
    public array $isian = [];

    /** Nilai versi server, pembanding untuk menentukan baris mana yang berubah. */
    public array $awal = [];

    /** det_id yang kotak catatannya sedang dibuka. */
    public array $catatanTerbuka = [];

    public string $cari = '';

    /** '' = semua principal, '0' = kelompok Lainnya. */
    public string $principal = '';

    public bool $hanyaBelum = false;

    public int $halaman = 1;

    public bool $adaLagi = false;

    public int $total = 0;

    public bool $dialogScan = false;

    public ?string $galat = null;

    public ?string $pesan = null;

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->muatDokumen();
        $this->muat();
    }

    /* ------------------------------------------------------------------
     | Memuat
     * ------------------------------------------------------------------ */

    public function muatDokumen(): void
    {
        try {
            $data = Api::get('/stocktake/'.$this->id)['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil dokumen stocktake.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $this->dokumen = $data['dokumen'] ?? [];
        $this->ringkasan = $data['ringkasan'] ?? [];
        $this->principals = $data['principals'] ?? [];
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $respons = Api::get('/stocktake/'.$this->id.'/baris', array_filter([
                'principal' => $this->principal,
                'cari' => $this->cari,
                'hanya' => $this->hanyaBelum ? 'belum' : null,
                'page' => $this->halaman,
            ], fn ($v) => $v !== null && $v !== ''));
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal mengambil baris stocktake.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba muat ulang.';

            return;
        }

        $baru = $respons['data'] ?? [];
        $meta = $respons['meta'] ?? [];

        $this->baris = $this->halaman > 1 ? array_merge($this->baris, $baru) : $baru;
        $this->adaLagi = (bool) ($meta['has_more'] ?? false);
        $this->total = (int) ($meta['total'] ?? count($this->baris));

        foreach ($baru as $b) {
            $detId = (int) $b['det_id'];

            $terbaru = [
                'rak' => $this->teks($b['qty_rak'] ?? 0),
                'tray' => $this->teks($b['qty_tray'] ?? 0),
                'container' => $this->teks($b['qty_container'] ?? 0),
                'catatan' => (string) ($b['catatan'] ?? ''),
            ];

            // Baris yang belum disentuh pengguna ikut nilai terbaru dari server;
            // yang sudah disentuh dibiarkan apa adanya supaya ketikannya aman.
            $lama = $this->awal[$detId] ?? null;
            if ($lama === null || ($this->isian[$detId] ?? null) === $lama) {
                $this->isian[$detId] = $terbaru;
            }

            $this->awal[$detId] = $terbaru;
        }
    }

    public function muatLagi(): void
    {
        if (! $this->adaLagi) {
            return;
        }

        $this->halaman++;
        $this->muat();
    }

    /* ------------------------------------------------------------------
     | Penyaring
     * ------------------------------------------------------------------ */

    public function updatedCari(): void
    {
        $this->ulangDariAwal();
    }

    public function updatedPrincipal(): void
    {
        $this->ulangDariAwal();
    }

    public function updatedHanyaBelum(): void
    {
        $this->ulangDariAwal();
    }

    public function pilihPrincipal(string $id): void
    {
        $this->principal = $id;
        $this->ulangDariAwal();
    }

    private function ulangDariAwal(): void
    {
        $this->halaman = 1;
        $this->baris = [];
        $this->pesan = null;
        $this->muat();
    }

    /* ------------------------------------------------------------------
     | Scan barcode
     * ------------------------------------------------------------------ */

    public function bukaScan(): void
    {
        $this->dialogScan = true;
        $this->dispatch('scan-dibuka');
    }

    public function tutupScan(): void
    {
        $this->dialogScan = false;
        $this->dispatch('scan-ditutup');
    }

    /**
     * Barcode hasil kamera tidak dicarikan sendiri ke server, tapi dijatuhkan
     * ke kotak cari. Hasilnya baris yang sama dengan pencarian biasa, lengkap
     * dengan kotak isiannya — jadi petugas langsung bisa mengetik angka.
     */
    #[On('barcode-terbaca')]
    public function terimaBarcode(string $kode): void
    {
        $kode = trim($kode);

        if ($kode === '') {
            return;
        }

        $this->cari = $kode;
        $this->tutupScan();
        $this->ulangDariAwal();

        if (empty($this->baris) && ! $this->galat) {
            $this->pesan = 'Barcode '.$kode.' tidak ada di dokumen ini. Barang bersaldo nol memang tidak ikut ditarik ERP.';
        }
    }

    /* ------------------------------------------------------------------
     | Menyimpan
     * ------------------------------------------------------------------ */

    public function bukaCatatan(int $detId): void
    {
        $this->catatanTerbuka[$detId] = ! ($this->catatanTerbuka[$detId] ?? false);
    }

    public function simpan(): void
    {
        $this->galat = null;
        $this->pesan = null;

        if (! ($this->dokumen['boleh_isi'] ?? false)) {
            $this->galat = 'Dokumen ini sudah terkunci, isian tidak bisa disimpan.';

            return;
        }

        $perubahan = $this->perubahan();

        if (empty($perubahan)) {
            $this->pesan = 'Tidak ada perubahan untuk disimpan.';

            return;
        }

        $tersimpan = 0;

        // Server membatasi 200 baris per permintaan. Isian bisa menumpuk lebih
        // dari itu karena penyaring boleh berganti-ganti sebelum Simpan ditekan.
        foreach (array_chunk($perubahan, 200) as $bagian) {
            try {
                $respons = Api::post('/stocktake/'.$this->id.'/baris', ['baris' => $bagian]);
            } catch (RequestException $e) {
                $this->galat = $e->response->json('message') ?? 'Gagal menyimpan hitungan.';
                $this->muatDokumen();

                return;
            } catch (\Throwable $e) {
                $this->galat = 'Gagal menghubungi server. Hitungan belum tersimpan, jangan tutup halaman ini.';

                return;
            }

            $tersimpan += (int) ($respons['data']['tersimpan'] ?? 0);

            // Baris yang baru saja tersimpan menjadi pembanding baru, supaya
            // tombol Simpan berikutnya tidak mengirim ulang baris yang sama.
            foreach ($bagian as $b) {
                $detId = (int) $b['det_id'];
                if (isset($this->isian[$detId])) {
                    $this->awal[$detId] = $this->isian[$detId];
                }
            }
        }

        $this->pesan = $tersimpan.' baris tersimpan.';
        $this->muatDokumen();
    }

    /** Baris yang isinya berbeda dari nilai server. */
    private function perubahan(): array
    {
        $ubah = [];

        foreach ($this->isian as $detId => $nilai) {
            $awal = $this->awal[$detId] ?? null;

            if ($awal === null || $nilai === $awal) {
                continue;
            }

            $baris = [
                'det_id' => (int) $detId,
                'qty_rak' => $nilai['rak'] ?? '',
                'qty_tray' => $nilai['tray'] ?? '',
                'qty_container' => $nilai['container'] ?? '',
            ];

            // Catatan hanya ikut dikirim bila memang berubah; endpoint tidak
            // menyentuh kolom note kalau kuncinya tidak ada.
            if (($nilai['catatan'] ?? '') !== ($awal['catatan'] ?? '')) {
                $baris['catatan'] = $nilai['catatan'] ?? '';
            }

            $ubah[] = $baris;
        }

        return $ubah;
    }

    /** Jumlah baris yang belum disimpan, untuk label tombol. */
    #[Computed]
    public function belumDisimpan(): int
    {
        return count($this->perubahan());
    }

    /**
     * Nol digambar sebagai kotak kosong, bukan angka "0": satu layar penuh
     * angka nol membuat baris yang sudah dihitung dan yang belum terlihat sama.
     */
    private function teks($nilai): string
    {
        $angka = (float) $nilai;

        if ($angka == 0.0) {
            return '';
        }

        return $angka == floor($angka) ? (string) (int) $angka : (string) $angka;
    }

    public function render()
    {
        return view('livewire.stocktake.hitung');
    }
}
