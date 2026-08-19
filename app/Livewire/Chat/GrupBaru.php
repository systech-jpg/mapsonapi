<?php

namespace App\Livewire\Chat;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Buat grup obrolan — padanan CreateGroupFragment.
 *
 * Pembuat grup tidak perlu dicentang: ChatController::createGroup selalu
 * memasukkan dirinya sendiri ke daftar anggota.
 */
class GrupBaru extends Component
{
    public string $nama = '';

    /** rowid anggota yang dicentang. Nilai checkbox selalu string di HTML. */
    public array $anggota = [];

    public array $kontak = [];

    public string $cari = '';

    public ?string $galat = null;

    public function mount(): void
    {
        try {
            $this->kontak = Api::get('/chat/users')['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal memuat daftar kontak.';
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba lagi.';
        }
    }

    public function hasil(): array
    {
        $kata = trim($this->cari);

        if ($kata === '') {
            return $this->kontak;
        }

        return array_values(array_filter($this->kontak, function ($baris) use ($kata) {
            $target = ($baris['fullname'] ?? '') . ' ' . ($baris['login'] ?? '');

            return stripos($target, $kata) !== false;
        }));
    }

    public function simpan()
    {
        $this->galat = null;

        $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'anggota' => ['required', 'array', 'min:1'],
        ], [
            'nama.required' => 'Nama grup wajib diisi.',
            'anggota.required' => 'Pilih minimal satu anggota.',
            'anggota.min' => 'Pilih minimal satu anggota.',
        ]);

        try {
            $hasil = Api::post('/chat/groups', [
                'name' => trim($this->nama),
                'member_ids' => array_map('intval', $this->anggota),
            ]);
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Grup gagal dibuat.';

            return null;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Grup belum dibuat.';

            return null;
        }

        $grupId = (int) ($hasil['data']['group_id'] ?? 0);

        // Tanpa id grup, satu-satunya tempat yang pasti benar adalah daftar
        // pesan — grup yang baru dibuat selalu muncul di sana.
        if ($grupId <= 0) {
            session()->flash('pesan', 'Grup berhasil dibuat.');

            return $this->redirect(route('pesan'), navigate: true);
        }

        return $this->redirect(route('pesan.grup', $grupId), navigate: true);
    }

    public function render()
    {
        return view('livewire.chat.grup-baru', ['baris' => $this->hasil()]);
    }
}
