<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use App\Events\MessageSent;
use App\Services\BeamsNotifier;
use App\Models\DolibarrUser;
use App\Notifications\PesanChatBaru;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Fitur 1: Inbox / Riwayat Chat (Personal & Grup) Terpadu
     */
    public function getInbox(Request $request)
    {
        $currentUser = $request->attributes->get('dolibarr_user');

        if (!$currentUser) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $myId = $currentUser->rowid;
        $inbox = [];

        // --- A. Ambil Riwayat 1-on-1 (Personal) ---
        // Cari semua user yang pernah chat dengan saya
        $personalChats = DB::table('llxjp_chat_messages')
            ->selectRaw('IF(sender_id = ?, receiver_id, sender_id) as other_user_id, MAX(id) as last_msg_id', [$myId])
            ->whereNull('group_id')
            ->where(function($q) use ($myId) {
                $q->where('sender_id', $myId)
                  ->orWhere('receiver_id', $myId);
            })
            ->groupBy('other_user_id')
            ->get();

        foreach ($personalChats as $chat) {
            $otherUser = DB::table('llxjp_user')->where('rowid', $chat->other_user_id)->first();
            $lastMsg = DB::table('llxjp_chat_messages')->where('id', $chat->last_msg_id)->first();
            $unreadCount = DB::table('llxjp_chat_messages')
                ->where('sender_id', $chat->other_user_id)
                ->where('receiver_id', $myId)
                ->whereNull('group_id')
                ->where('is_read', 0)
                ->count();

            $decryptedMsg = $this->tryDecrypt($lastMsg->message);

            $fullname = $otherUser ? trim($otherUser->firstname . ' ' . $otherUser->lastname) : 'Unknown';
            if (empty($fullname) && $otherUser) $fullname = $otherUser->login;

            $inbox[] = [
                'id' => $chat->other_user_id,
                'type' => 'personal',
                'name' => $fullname,
                'last_message' => $decryptedMsg ?: ($lastMsg->attachments ? '[File Attachment]' : ''),
                'last_message_time' => $lastMsg->created_at,
                'unread_count' => $unreadCount
            ];
        }

        // --- B. Ambil Riwayat Group ---
        $myGroups = DB::table('llxjp_chat_group_members as m')
            ->join('llxjp_chat_groups as g', 'g.id', '=', 'm.group_id')
            ->select('g.id', 'g.name', 'm.last_read_message_id')
            ->where('m.user_id', $myId)
            ->get();

        foreach ($myGroups as $group) {
            $lastMsg = DB::table('llxjp_chat_messages')
                ->where('group_id', $group->id)
                ->orderBy('id', 'desc')
                ->first();

            $unreadCount = 0;
            $decryptedMsg = '';
            $lastMsgTime = null;

            if ($lastMsg) {
                $decryptedMsg = $this->tryDecrypt($lastMsg->message) ?: ($lastMsg->attachments ? '[File Attachment]' : '');
                $lastMsgTime = $lastMsg->created_at;

                // Hitung unread group
                $query = DB::table('llxjp_chat_messages')->where('group_id', $group->id);
                if ($group->last_read_message_id) {
                    $query->where('id', '>', $group->last_read_message_id);
                }
                $unreadCount = $query->count();
            }

            $inbox[] = [
                'id' => $group->id,
                'type' => 'group',
                'name' => $group->name,
                'last_message' => $decryptedMsg,
                'last_message_time' => $lastMsgTime,
                'unread_count' => $unreadCount
            ];
        }

        // Urutkan berdasarkan waktu terakhir (Descending)
        usort($inbox, function($a, $b) {
            $timeA = $a['last_message_time'] ? strtotime($a['last_message_time']) : 0;
            $timeB = $b['last_message_time'] ? strtotime($b['last_message_time']) : 0;
            return $timeB <=> $timeA;
        });

        return $this->successResponse($inbox, 'Berhasil mengambil daftar Inbox.');
    }

    /**
     * Membuat Grup Chat Baru
     */
    public function createGroup(Request $request)
    {
        $currentUser = $request->attributes->get('dolibarr_user');

        if (!$currentUser) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'member_ids' => 'required|array',
            'member_ids.*' => 'integer|exists:llxjp_user,rowid'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 400);
        }

        $validated = $validator->validated();
        $members = $validated['member_ids'];
        
        // Pastikan pembuat grup masuk dalam daftar member
        if (!in_array($currentUser->rowid, $members)) {
            $members[] = $currentUser->rowid;
        }

        DB::beginTransaction();
        try {
            $groupId = DB::table('llxjp_chat_groups')->insertGetId([
                'name' => $validated['name'],
                'created_by' => $currentUser->rowid,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            foreach (array_unique($members) as $memberId) {
                DB::table('llxjp_chat_group_members')->insert([
                    'group_id' => $groupId,
                    'user_id' => $memberId,
                    'last_read_message_id' => null,
                    'joined_at' => Carbon::now()
                ]);
            }
            DB::commit();

            return $this->successResponse(['group_id' => $groupId], 'Grup obrolan berhasil dibuat.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Gagal membuat grup: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Menambahkan member baru ke grup yang sudah ada
     */
    public function addGroupMembers(Request $request, $group_id)
    {
        $currentUser = $request->attributes->get('dolibarr_user');

        if (!$currentUser) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $group = DB::table('llxjp_chat_groups')->where('id', $group_id)->first();
        if (!$group) {
            return $this->errorResponse('Grup tidak ditemukan.', 404);
        }

        $validator = Validator::make($request->all(), [
            'member_ids' => 'required|array',
            'member_ids.*' => 'integer|exists:llxjp_user,rowid'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 400);
        }

        $newMembers = 0;
        foreach ($request->member_ids as $memberId) {
            $exists = DB::table('llxjp_chat_group_members')
                ->where('group_id', $group_id)
                ->where('user_id', $memberId)
                ->exists();

            if (!$exists) {
                // Supaya tidak membaca chat lama sebagai "unread", 
                // set last_read_message_id ke ID pesan terakhir di grup ini.
                $lastGroupMsg = DB::table('llxjp_chat_messages')->where('group_id', $group_id)->orderBy('id', 'desc')->first();

                DB::table('llxjp_chat_group_members')->insert([
                    'group_id' => $group_id,
                    'user_id' => $memberId,
                    'last_read_message_id' => $lastGroupMsg ? $lastGroupMsg->id : null,
                    'joined_at' => Carbon::now()
                ]);
                $newMembers++;
            }
        }

        return $this->successResponse(null, "Berhasil menambahkan $newMembers anggota ke grup.");
    }

    /**
     * Mengambil riwayat pesan untuk Grup Chat
     */
    public function getGroupMessages(Request $request, $group_id)
    {
        $currentUser = $request->attributes->get('dolibarr_user');

        if (!$currentUser) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek apakah user adalah anggota grup
        $isMember = DB::table('llxjp_chat_group_members')
            ->where('group_id', $group_id)
            ->where('user_id', $currentUser->rowid)
            ->exists();

        if (!$isMember) {
            return $this->errorResponse('Anda bukan anggota grup ini.', 403);
        }

        $messages = DB::table('llxjp_chat_messages as m')
            ->join('llxjp_user as u', 'u.rowid', '=', 'm.sender_id')
            ->select('m.*', 'u.firstname as sender_firstname', 'u.lastname as sender_lastname', 'u.login as sender_login')
            ->where('m.group_id', $group_id)
            ->orderBy('m.created_at', 'asc')
            ->get();

        $messages->transform(function ($msg) {
            $msg->sender_name = trim($msg->sender_firstname . ' ' . $msg->sender_lastname) ?: $msg->sender_login;
            $msg->message = $this->tryDecrypt($msg->message);
            $msg->attachments = $this->normalizeAttachments($msg->attachments);
            return $msg;
        });

        return $this->successResponse($messages, 'Berhasil mengambil riwayat chat grup.');
    }

    /**
     * Membaca pesan di Grup (Tandai telah dibaca)
     */
    public function markGroupAsRead(Request $request, $group_id)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return $this->errorResponse('Unauthorized', 401);

        $lastMsg = DB::table('llxjp_chat_messages')->where('group_id', $group_id)->orderBy('id', 'desc')->first();

        if ($lastMsg) {
            DB::table('llxjp_chat_group_members')
                ->where('group_id', $group_id)
                ->where('user_id', $currentUser->rowid)
                ->update(['last_read_message_id' => $lastMsg->id]);
        }

        return $this->successResponse(null, 'Pesan grup telah ditandai dibaca.');
    }


    /* =========================================================
       METODE YANG SUDAH ADA (Diperbarui untuk mendukung Grup)
       ========================================================= */

    public function getUsers(Request $request) { /* (Sama seperti sebelumnya) */ 
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return $this->errorResponse('Unauthorized.', 401);

        $users = DB::table('llxjp_user')
            ->select('rowid as id', 'firstname', 'lastname', 'login', 'email')
            ->where('statut', 1)->where('rowid', '!=', $currentUser->rowid)->orderBy('firstname', 'asc')->get();

        $users->transform(function ($user) {
            $user->fullname = trim($user->firstname . ' ' . $user->lastname);
            if (empty($user->fullname)) $user->fullname = $user->login;
            return $user;
        });
        return $this->successResponse($users, 'Berhasil mengambil daftar kontak.');
    }

    public function getMessages(Request $request, $user_id)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return $this->errorResponse('Unauthorized.', 401);

        $myId = $currentUser->rowid;
        $messages = DB::table('llxjp_chat_messages')
            ->whereNull('group_id')
            ->where(function ($query) use ($myId, $user_id) {
                $query->where('sender_id', $myId)->where('receiver_id', $user_id);
            })
            ->orWhere(function ($query) use ($myId, $user_id) {
                $query->where('sender_id', $user_id)->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'asc')->get();

        $messages->transform(function ($msg) {
            $msg->message = $this->tryDecrypt($msg->message);
            $msg->attachments = $this->normalizeAttachments($msg->attachments);
            return $msg;
        });
        return $this->successResponse($messages, 'Berhasil mengambil riwayat chat.');
    }

    public function sendMessage(Request $request)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return $this->errorResponse('Unauthorized.', 401);

        $validator = Validator::make($request->all(), [
            'receiver_id' => 'nullable|integer|exists:llxjp_user,rowid',
            'group_id' => 'nullable|integer|exists:llxjp_chat_groups,id',
            'message' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240'
        ]);

        if ($validator->fails()) return $this->errorResponse($validator->errors()->first(), 400);

        $validated = $validator->validated();

        if (empty($validated['receiver_id']) && empty($validated['group_id'])) {
            return $this->errorResponse('Harus menyertakan receiver_id atau group_id.', 400);
        }
        if (empty($validated['message']) && empty($request->file('attachments'))) {
            return $this->errorResponse('Pesan atau lampiran tidak boleh kosong.', 400);
        }

        // Cek keamanan jika ngirim ke grup
        if (!empty($validated['group_id'])) {
            $isMember = DB::table('llxjp_chat_group_members')
                ->where('group_id', $validated['group_id'])
                ->where('user_id', $currentUser->rowid)
                ->exists();
            if (!$isMember) return $this->errorResponse('Anda bukan anggota grup ini.', 403);
        }

        // Encrypt Text Message
        $encryptedText = !empty($validated['message']) ? Crypt::encryptString($validated['message']) : null;

        // Handle File Uploads (Encrypted)
        $attachmentUrls = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . uniqid() . '.enc';
                $fileContents = file_get_contents($file->getRealPath());
                $encryptedFile = Crypt::encrypt($fileContents);
                Storage::put('chat_secure/' . $filename, $encryptedFile);

                // Path relatif terhadap root API, tanpa host. Host diambil dari
                // request yang masuk dan salah di belakang tunnel/proxy, jadi
                // biarkan setiap client menyusunnya sendiri dari base URL-nya.
                $attachmentUrls[] = [
                    'id'        => null,
                    'file_path' => 'chat/download/' . $filename,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
                ];
            }
        }

        $messageId = DB::table('llxjp_chat_messages')->insertGetId([
            'sender_id' => $currentUser->rowid,
            'receiver_id' => $validated['receiver_id'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
            'message' => $encryptedText,
            'attachments' => !empty($attachmentUrls) ? json_encode($attachmentUrls) : null,
            'is_read' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $newMessage = DB::table('llxjp_chat_messages')->where('id', $messageId)->first();
        $newMessage->message = $validated['message'] ?? null;
        $newMessage->attachments = !empty($attachmentUrls) ? $attachmentUrls : [];

        // Jika ini chat grup, otomatis update last_read diri sendiri
        if (!empty($validated['group_id'])) {
            DB::table('llxjp_chat_group_members')
                ->where('group_id', $validated['group_id'])
                ->where('user_id', $currentUser->rowid)
                ->update(['last_read_message_id' => $messageId]);
        }

        // Trigger Event Pusher (realtime saat app dibuka)
        event(new MessageSent($newMessage));

        // Notifikasi push (saat app di background / tertutup)
        $this->sendPushNotification($currentUser, $newMessage);

        return $this->successResponse($newMessage, 'Pesan berhasil dikirim dengan aman.', 201);
    }

    public function downloadAttachment(Request $request, $filename)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return response()->json(['error' => 'Unauthorized'], 401);

        $path = 'chat_secure/' . $filename;
        if (!Storage::exists($path)) return response()->json(['error' => 'File not found'], 404);

        try {
            $decryptedContents = Crypt::decrypt(Storage::get($path));
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return response($decryptedContents)
                    ->header('Content-Type', $finfo->buffer($decryptedContents))
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to decrypt file'], 500);
        }
    }

    public function markAsRead(Request $request, $sender_id)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) return $this->errorResponse('Unauthorized.', 401);

        DB::table('llxjp_chat_messages')
            ->whereNull('group_id')
            ->where('sender_id', $sender_id)
            ->where('receiver_id', $currentUser->rowid)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'updated_at' => Carbon::now()]);

        return $this->successResponse(null, 'Pesan telah ditandai dibaca.');
    }

    /**
     * Mengirim notifikasi push untuk satu pesan baru.
     *
     * Personal -> hanya penerima.
     * Grup     -> semua anggota kecuali pengirim, dalam satu panggilan publish.
     */
    private function sendPushNotification($sender, $message): void
    {
        $senderName = trim(($sender->firstname ?? '') . ' ' . ($sender->lastname ?? ''));
        if ($senderName === '') {
            $senderName = $sender->login ?? 'Seseorang';
        }

        // Pesan sudah didekripsi di sendMessage sebelum dilempar ke sini
        $preview = $message->message
            ? Str::limit($message->message, 120)
            : 'Mengirim lampiran';

        if (!empty($message->group_id)) {
            $group = DB::table('llxjp_chat_groups')->where('id', $message->group_id)->first();

            $recipients = DB::table('llxjp_chat_group_members')
                ->where('group_id', $message->group_id)
                ->where('user_id', '!=', $sender->rowid)
                ->pluck('user_id')
                ->toArray();

            $title = $group->name ?? 'Grup';
            $body = $senderName . ': ' . $preview;
            $data = [
                'chat_type' => 'group',
                'group_id' => (string) $message->group_id,
                'other_user_name' => $title,
            ];
        } else {
            $recipients = [$message->receiver_id];

            $title = $senderName;
            $body = $preview;
            $data = [
                'chat_type' => 'personal',
                'other_user_id' => (string) $sender->rowid,
                'other_user_name' => $senderName,
            ];
        }

        // Android: Pusher Beams (FCM).
        $beams = app(BeamsNotifier::class);
        if ($beams->isConfigured()) {
            $beams->notifyUsers($recipients, $title, $body, $data);
        }

        // PWA: Web Push VAPID. Dipanggil terpisah, BUKAN di dalam penjagaan
        // Beams di atas — sebelumnya seluruh method berhenti lebih awal saat
        // Beams belum dikonfigurasi, sehingga browser ikut tidak kebagian.
        $this->kirimWebPush($recipients, $title, $body, $data);
    }

    /**
     * Notifikasi ke browser (PWA) untuk penerima yang perangkatnya sudah
     * berlangganan lewat route /push/subscribe.
     *
     * Gagal-diam dengan alasan yang sama seperti BeamsNotifier: pesannya sudah
     * tersimpan di database, dan notifikasi yang gagal tidak boleh membuat
     * POST /chat/messages dijawab 500.
     *
     * @param  array<int|string>  $recipients
     * @param  array<string,string>  $data
     */
    private function kirimWebPush(array $recipients, string $title, string $body, array $data): void
    {
        // Alamat halaman percakapan di PWA, dilihat dari sisi PENERIMA: untuk
        // chat personal yang dibuka adalah ruang milik si PENGIRIM.
        if (($data['chat_type'] ?? '') === 'group') {
            $tautan = '/pesan/g/' . $data['group_id'];
            $tanda = 'chat-grup-' . $data['group_id'];
        } else {
            $tautan = '/pesan/u/' . $data['other_user_id'];
            $tanda = 'chat-personal-' . $data['other_user_id'];
        }

        $ids = array_values(array_filter(array_map('intval', $recipients)));

        if (empty($ids)) {
            return;
        }

        try {
            foreach (DolibarrUser::whereIn('rowid', $ids)->get() as $user) {
                $user->notify(new PesanChatBaru($title, $body, $tautan, $tanda));
            }
        } catch (\Throwable $e) {
            Log::warning('Web push chat gagal: ' . $e->getMessage());
        }
    }

    /**
     * Helper Method untuk normalisasi attachment.
     *
     * Client memetakan attachment sebagai objek {id, file_path, file_name, file_type}
     * dengan file_path RELATIF terhadap root API ("chat/download/xxx.enc").
     *
     * Dua bentuk lama ikut dikonversi di sini supaya riwayat chat tidak rusak:
     * - string URL absolut biasa
     * - objek dengan file_path berisi URL absolut (host-nya salah di belakang tunnel)
     */
    private function normalizeAttachments($rawJson)
    {
        $items = $rawJson ? json_decode($rawJson, true) : [];
        if (!is_array($items)) return [];

        return array_map(function ($item) {
            if (!is_array($item)) {
                $item = ['file_path' => $item];
            }

            $fileName = basename((string) parse_url((string) ($item['file_path'] ?? ''), PHP_URL_PATH));

            return [
                'id'        => $item['id'] ?? null,
                'file_path' => 'chat/download/' . $fileName,
                'file_name' => $item['file_name'] ?? $fileName,
                'file_type' => $item['file_type'] ?? 'file',
            ];
        }, $items);
    }

    /**
     * Helper Method untuk Dekripsi
     */
    private function tryDecrypt($encryptedText)
    {
        if (empty($encryptedText)) return null;
        try {
            return Crypt::decryptString($encryptedText);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return '[Pesan tidak dapat didekripsi / Corrupted]';
        }
    }
}
