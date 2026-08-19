<?php

namespace App\Livewire\Chat;

use App\Support\Api;
use Illuminate\Http\Client\RequestException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Ruang percakapan — padanan ChatRoomFragment di Android.
 *
 * Satu komponen melayani dua bentuk, dibedakan oleh $tipe:
 *  - personal : GET  /api/chat/messages/{user_id}
 *               POST /api/chat/messages/{sender_id}/read
 *  - group    : GET  /api/chat/groups/{group_id}/messages
 *               POST /api/chat/groups/{group_id}/read
 *
 * Dijadikan satu, bukan dua komponen, karena bentuk balasan pesannya identik
 * dan seluruh tampilan gelembung chat-nya sama persis; yang berbeda cuma
 * alamat endpoint dan ada/tidaknya nama pengirim di atas gelembung.
 */
class Ruang extends Component
{
    use WithFileUploads;

    /** 'personal' atau 'group'. */
    public string $tipe = 'personal';

    /** other_user_id untuk personal, group_id untuk grup. */
    public int $lawan = 0;

    public string $judul = 'Chat';

    public array $pesan = [];

    public string $teks = '';

    /** Berkas yang dipilih tapi belum terkirim. */
    public array $lampiran = [];

    public ?string $galat = null;

    /** rowid saya sendiri, dipakai menentukan gelembung kiri/kanan. */
    public int $sayaId = 0;

    public function mount(string $tipe, int $id): void
    {
        $this->tipe = $tipe === 'group' ? 'group' : 'personal';
        $this->lawan = $id;
        $this->sayaId = (int) session('api_user.rowid');

        $this->judul = $this->ambilJudul();

        $this->muat();
        $this->tandaiDibaca();
    }

    /**
     * Nama lawan bicara / nama grup.
     *
     * Tidak ada endpoint "detail percakapan", jadi diambil dari daftar yang
     * sudah ada: kontak untuk personal, inbox untuk grup (inbox memuat semua
     * grup yang saya ikuti, termasuk yang belum ada pesannya).
     */
    protected function ambilJudul(): string
    {
        try {
            if ($this->tipe === 'group') {
                foreach (Api::get('/chat/inbox')['data'] ?? [] as $baris) {
                    if (($baris['type'] ?? '') === 'group' && (int) ($baris['id'] ?? 0) === $this->lawan) {
                        return (string) ($baris['name'] ?? 'Grup');
                    }
                }

                return 'Grup';
            }

            foreach (Api::get('/chat/users')['data'] ?? [] as $baris) {
                if ((int) ($baris['id'] ?? 0) === $this->lawan) {
                    return (string) ($baris['fullname'] ?? $baris['login'] ?? 'Chat');
                }
            }
        } catch (\Throwable $e) {
            // Judul bukan alasan yang cukup untuk menggagalkan halaman.
        }

        return 'Chat';
    }

    public function muat(): void
    {
        $this->galat = null;

        $path = $this->tipe === 'group'
            ? "/chat/groups/{$this->lawan}/messages"
            : "/chat/messages/{$this->lawan}";

        try {
            $this->pesan = Api::get($path)['data'] ?? [];
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Gagal memuat percakapan.';
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Coba lagi.';
        }
    }

    /**
     * Gagal-diam: tanda "sudah dibaca" tidak layak memunculkan pesan galat di
     * layar percakapan yang isinya sudah tergambar.
     */
    public function tandaiDibaca(): void
    {
        $path = $this->tipe === 'group'
            ? "/chat/groups/{$this->lawan}/read"
            : "/chat/messages/{$this->lawan}/read";

        try {
            Api::post($path);
        } catch (\Throwable $e) {
            // dibiarkan
        }
    }

    /**
     * Pesan baru dari Pusher. Payload-nya dipakai untuk menyaring: satu kanal
     * `chat.user.{id}` membawa SEMUA percakapan saya, jadi tanpa saringan ini
     * chat dari orang lain ikut memuat ulang layar yang sedang dibaca.
     */
    #[On('chat-masuk')]
    public function chatMasuk(?int $senderId = null, ?int $groupId = null): void
    {
        $milikRuangIni = $this->tipe === 'group'
            ? $groupId === $this->lawan
            : (empty($groupId) && $senderId === $this->lawan);

        if (! $milikRuangIni) {
            return;
        }

        $this->muat();
        $this->tandaiDibaca();

        $this->dispatch('gulir-ke-bawah');
    }

    public function kirim(): void
    {
        $this->galat = null;

        $teks = trim($this->teks);

        if ($teks === '' && empty($this->lampiran)) {
            return;
        }

        // Batas 10 MB mengikuti aturan di ChatController::sendMessage
        // ('attachments.*' => 'file|max:10240'). Ditulis ulang di sini supaya
        // berkas kebesaran ditolak sebelum dikirim, bukan sesudah menunggu
        // unggahan selesai lalu dijawab 400.
        $this->validate([
            'lampiran.*' => ['file', 'max:10240'],
        ], [
            'lampiran.*.max' => 'Ukuran tiap berkas maksimal 10 MB.',
        ]);

        $data = array_filter([
            'message' => $teks !== '' ? $teks : null,
            $this->tipe === 'group' ? 'group_id' : 'receiver_id' => $this->lawan,
        ], fn ($v) => $v !== null);

        try {
            if (empty($this->lampiran)) {
                Api::post('/chat/messages', $data);
            } else {
                $berkas = array_map(fn ($b) => [
                    'isi' => $b->get(),
                    'nama' => $b->getClientOriginalName(),
                ], $this->lampiran);

                Api::unggahBanyak('/chat/messages', 'attachments', $berkas, $data);
            }
        } catch (RequestException $e) {
            $this->galat = $e->response->json('message') ?? 'Pesan ditolak server.';

            return;
        } catch (\Throwable $e) {
            $this->galat = 'Gagal menghubungi server. Pesan belum terkirim.';

            return;
        }

        $this->teks = '';
        $this->lampiran = [];

        $this->muat();

        $this->dispatch('gulir-ke-bawah');
    }

    /** Buang satu berkas dari antrean sebelum dikirim. */
    public function buangLampiran(int $indeks): void
    {
        unset($this->lampiran[$indeks]);

        $this->lampiran = array_values($this->lampiran);
    }

    /**
     * Alamat unduhan lampiran di sisi web.
     *
     * API mengirim file_path relatif ("chat/download/xxx.enc") dan endpoint
     * aslinya menuntut header Authorization — sesuatu yang tidak pernah
     * dipegang browser. Jadi yang dipakai route web yang mengambilkannya
     * server ke server.
     */
    public function tautanBerkas(array $berkas): string
    {
        $simpanan = basename((string) ($berkas['file_path'] ?? ''));

        return route('pesan.berkas', [
            'berkas' => $simpanan,
            // Nama aslinya cuma ada di isi pesan; dititipkan supaya berkas yang
            // diunduh tidak bernama "1756...e3f.enc".
            'nama' => $berkas['file_name'] ?? $simpanan,
        ]);
    }

    public function render()
    {
        return view('livewire.chat.ruang');
    }
}
