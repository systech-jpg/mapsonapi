<?php

namespace App\Livewire\Chat;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Component;

/**
 * Daftar kontak untuk memulai chat personal — padanan ContactListFragment.
 *
 * Endpoint /api/chat/users sudah membuang diri sendiri dan user nonaktif
 * (statut = 1), jadi tidak ada penyaringan tambahan di sini.
 */
class Kontak extends Component
{
    public array $item = [];

    public string $cari = '';

    public ?string $galat = null;

    public function mount(): void
    {
        try {
            $this->item = Api::get('/chat/users')['data'] ?? [];
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
            return $this->item;
        }

        return array_values(array_filter($this->item, function ($baris) use ($kata) {
            $target = ($baris['fullname'] ?? '') . ' ' . ($baris['login'] ?? '');

            return stripos($target, $kata) !== false;
        }));
    }

    public function render()
    {
        return view('livewire.chat.kontak', ['baris' => $this->hasil()]);
    }
}
