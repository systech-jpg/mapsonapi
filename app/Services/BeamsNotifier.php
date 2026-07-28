<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\PushNotifications\PushNotifications;

/**
 * Pembungkus Pusher Beams.
 *
 * Beams menangani notifikasi saat aplikasi tertutup atau di background,
 * kondisi yang tidak bisa dijangkau Pusher Channels karena koneksi
 * WebSocket-nya ikut mati saat proses aplikasi dihentikan Android.
 */
class BeamsNotifier
{
    /** Batas user ID per satu panggilan publishToUsers. */
    private const MAX_USERS_PER_PUBLISH = 1000;

    private ?PushNotifications $client = null;

    public function __construct()
    {
        $instanceId = config('services.beams.instance_id');
        $secretKey = config('services.beams.secret_key');

        if (empty($instanceId) || empty($secretKey)) {
            Log::warning('Beams tidak dikonfigurasi. Set BEAMS_INSTANCE_ID dan BEAMS_SECRET_KEY di .env');
            return;
        }

        $this->client = new PushNotifications([
            'instanceId' => $instanceId,
            'secretKey' => $secretKey,
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->client !== null;
    }

    /**
     * Token untuk perangkat agar boleh mengaku sebagai $userId.
     * Berlaku 24 jam dan diperbarui sendiri oleh SDK client.
     */
    public function generateToken(string $userId): array
    {
        if (!$this->client) {
            throw new \RuntimeException('Beams belum dikonfigurasi.');
        }

        return $this->client->generateToken($userId);
    }

    /**
     * Mengirim notifikasi ke sekumpulan user.
     *
     * Sengaja tidak melempar exception: gagal mengirim notifikasi tidak boleh
     * menggagalkan pengiriman pesan yang sudah tersimpan di database.
     *
     * @param  array<int|string>  $userIds
     * @param  array<string,string>  $data  Muatan untuk deep link di client
     */
    public function notifyUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        if (!$this->client) {
            return;
        }

        $userIds = array_values(array_unique(array_map('strval', array_filter($userIds))));
        if (empty($userIds)) {
            return;
        }

        foreach (array_chunk($userIds, self::MAX_USERS_PER_PUBLISH) as $chunk) {
            try {
                $response = $this->client->publishToUsers($chunk, [
                    'fcm' => [
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $data,
                    ],
                ]);

                // publishToUsers mengembalikan stdClass, sedangkan generateToken
                // mengembalikan array. Ditangani keduanya supaya tidak rapuh.
                $publishId = is_array($response)
                    ? ($response['publishId'] ?? null)
                    : ($response->publishId ?? null);

                Log::info('Beams publish', [
                    'publishId' => $publishId,
                    'users' => count($chunk),
                ]);
            } catch (\Throwable $e) {
                Log::error('Beams publish gagal: ' . $e->getMessage(), [
                    'users' => count($chunk),
                ]);
            }
        }
    }
}
