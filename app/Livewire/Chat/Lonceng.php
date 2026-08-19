<?php

namespace App\Livewire\Chat;

use App\Support\Api;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Angka pesan belum dibaca di tombol Pesan pada beranda — padanan ChatBadge.
 *
 * Angkanya SELALU dihitung ulang dari server (jumlah unread_count seluruh
 * percakapan di /api/chat/inbox), tidak pernah dinaikkan sendiri di sisi
 * aplikasi. Alasannya sama dengan catatan di ChatBadge.kt: satu pesan masuk
 * bisa terdeteksi dua kali — lewat Pusher dan lewat notifikasi push — sehingga
 * penambahan lokal bisa menghitung satu pesan menjadi dua.
 *
 * Gagal-diam: beranda tidak boleh menampilkan galat hanya karena angka kecil
 * di pojok tombol tidak bisa diambil.
 */
class Lonceng extends Component
{
    public int $jumlah = 0;

    public function mount(): void
    {
        $this->hitung();
    }

    /**
     * Parameternya tidak dipakai, tapi wajib ada: Livewire meneruskan muatan
     * event sebagai named argument, dan method tanpa parameter akan dijawab
     * "Unknown named parameter $senderId".
     */
    #[On('chat-masuk')]
    public function chatMasuk(?int $senderId = null, ?int $groupId = null): void
    {
        $this->hitung();
    }

    public function hitung(): void
    {
        try {
            $baris = Api::get('/chat/inbox')['data'] ?? [];
        } catch (\Throwable $e) {
            return;
        }

        $this->jumlah = array_sum(array_map(
            fn ($b) => (int) ($b['unread_count'] ?? 0),
            $baris
        ));
    }

    public function render()
    {
        return view('livewire.chat.lonceng');
    }
}
