<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi pesan chat baru untuk PWA (Web Push).
 *
 * Aplikasi Android memakai Pusher Beams (FCM) lewat App\Services\BeamsNotifier.
 * Beams tidak menjangkau browser di sini, jadi PWA memakai kanal keduanya:
 * Web Push VAPID yang langganannya disimpan lewat route /push/subscribe.
 * Keduanya dipicu dari titik yang sama — ChatController::sendPushNotification —
 * supaya satu pesan tidak pernah punya dua sumber kebenaran.
 *
 * Isi judul/badan sengaja sama persis dengan muatan Beams, supaya petugas yang
 * memakai Android dan PWA sekaligus tidak melihat dua bunyi yang berbeda.
 */
class PesanChatBaru extends Notification
{
    /**
     * @param  string  $judul   Nama pengirim (personal) atau nama grup.
     * @param  string  $isi     Cuplikan pesan, sudah didekripsi dan dipotong.
     * @param  string  $tautan  Alamat halaman percakapan di PWA.
     * @param  string  $tanda   Tag notifikasi; sama untuk satu percakapan.
     */
    public function __construct(
        public string $judul,
        public string $isi,
        public string $tautan,
        public string $tanda,
    ) {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->judul)
            ->body($this->isi)
            ->icon('/pwa/icon-192.png')
            ->badge('/badge-72.png')
            /*
            | Satu tag per percakapan. Tanpa ini, sepuluh pesan beruntun dari
            | orang yang sama menumpuk menjadi sepuluh baris di rak notifikasi;
            | dengan tag yang sama, yang terlihat selalu pesan terakhir saja.
            */
            ->tag($this->tanda)
            ->data(['url' => $this->tautan]);
    }
}
