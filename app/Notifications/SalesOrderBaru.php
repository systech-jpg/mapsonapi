<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi push untuk sales order baru.
 *
 * Dokumen memakai model Pesanan yang tidak ada di project ini, jadi datanya
 * diterima sebagai array — bentuk yang sama dengan yang dikembalikan
 * endpoint /api/sales-orders.
 *
 * Cara memicu:
 *   App\Models\DolibarrUser::find($rowid)->notify(new SalesOrderBaru($order));
 */
class SalesOrderBaru extends Notification
{
    public function __construct(public array $order)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $ref = $this->order['ref'] ?? '-';
        $pelanggan = $this->order['third_party'] ?? null;

        return (new WebPushMessage)
            ->title('Sales order baru masuk')
            ->body($pelanggan ? "{$ref} — {$pelanggan}" : "{$ref} menunggu konfirmasi.")
            ->icon('/icons/icon-192.png')
            ->badge('/badge-72.png')
            ->tag('sales-order-' . ($this->order['rowid'] ?? $ref))
            ->data(['url' => '/sales-order']);
    }
}
