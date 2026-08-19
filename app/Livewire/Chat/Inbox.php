<?php

namespace App\Livewire\Chat;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Daftar percakapan (personal + grup) — padanan ChatListFragment di Android.
 *
 * Sumbernya satu endpoint: GET /api/chat/inbox, yang sudah menggabungkan
 * keduanya dan mengurutkannya dari pesan terbaru. Jadi tidak ada penggabungan
 * atau pengurutan ulang di sini; kalau urutannya terasa salah, tempatnya di
 * ChatController::getInbox, bukan di komponen ini.
 *
 * Tidak memakai wire:poll. Alasannya penanda "Memuat…" bersama di
 * layouts/app.blade.php menyala untuk SETIAP permintaan Livewire — polling tiap
 * beberapa detik akan membuatnya berkedip terus-menerus. Penyegarannya dipicu
 * Pusher (partials/pusher-chat.blade.php), persis seperti Android.
 */
class Inbox extends Component
{
    public array $item = [];

    public string $cari = '';

    public ?string $galat = null;

    public function mount(): void
    {
        $this->muat();
    }

    public function muat(): void
    {
        $this->galat = null;

        try {
            $this->item = Api::get('/chat/inbox')['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal memuat daftar pesan.';
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba lagi.';
        }
    }

    /**
     * Dipicu dari JavaScript saat Pusher mengabarkan pesan baru. Senyap:
     * yang berubah cuma cuplikan pesan terakhir dan angka belum dibaca.
     *
     * Kedua parameternya tidak dipakai di sini — daftar ini selalu dimuat
     * ulang seluruhnya — tapi TETAP harus ada di tanda tangan method. Livewire
     * meneruskan muatan event sebagai named argument, dan method tanpa
     * parameter akan dijawab "Unknown named parameter $senderId".
     */
    #[On('chat-masuk')]
    public function chatMasuk(?int $senderId = null, ?int $groupId = null): void
    {
        $this->muat();
    }

    /** Saringan di sisi klien; endpoint inbox tidak punya parameter pencarian. */
    public function hasil(): array
    {
        $kata = trim($this->cari);

        if ($kata === '') {
            return $this->item;
        }

        return array_values(array_filter(
            $this->item,
            fn ($baris) => stripos((string) ($baris['name'] ?? ''), $kata) !== false
        ));
    }

    public function render()
    {
        return view('livewire.chat.inbox', ['baris' => $this->hasil()]);
    }
}
