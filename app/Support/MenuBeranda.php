<?php

namespace App\Support;

use Illuminate\Http\Client\RequestException;

/**
 * Menu beranda PWA, mengikuti penugasan menu di Dolibarr.
 *
 * Sumbernya endpoint yang sama persis dengan aplikasi Android:
 * GET /api/fragment-menus, yang membaca llxjp_android_fragment_menus dan
 * llxjp_android_fragment_menu_user. Jadi sekali menekan "ASSIGN USER" di
 * halaman menu ERP, hasilnya berlaku untuk Android DAN web tanpa perlu
 * mengubah kode di sini.
 *
 * Sebelumnya daftar menu ditulis tetap di routes/web.php dengan alasan route
 * Android tidak bisa dipetakan ke route web. Alasan itu benar, tapi jalan
 * keluarnya bukan meninggalkan daftarnya statis — cukup satu peta kecil di
 * bawah. Yang statis berarti setiap petugas melihat kelima menu, termasuk yang
 * tidak pernah diberi akses.
 */
class MenuBeranda
{
    /**
     * Peta route Android ke route web berikut ikonnya.
     *
     * Kolom `route` di ERP berisi nama tujuan navigasi Android
     * (nav_stocktake, nav_request_stock, ...) yang tidak sama dengan nama route
     * web. Ikon ERP juga tidak dipakai apa adanya: di sana tersimpan berkas PNG
     * yang dilayani /api/android-icons, sedangkan kartu menu PWA memakai
     * Bootstrap Icons supaya tajam di kepadatan layar mana pun dan tidak
     * menambah lima permintaan gambar hanya untuk menggambar beranda.
     *
     * Menu ERP yang tidak ada di peta ini sengaja DILEWATI, bukan digambar
     * dengan tujuan seadanya: kartu yang diketuk lalu berujung 404 lebih
     * membingungkan daripada kartu yang memang belum ada. Kalau nanti ada menu
     * baru di ERP, tambahkan barisnya di sini.
     */
    private const PETA = [
        'nav_stocktake' => ['route' => 'stocktake', 'icon' => 'bi-list-ul'],
        'nav_tindakan' => ['route' => 'tindakan', 'icon' => 'bi-file-earmark-plus-fill'],
        'nav_sales_order' => ['route' => 'sales-order', 'icon' => 'bi-file-earmark-text-fill'],
        'nav_request_stock' => ['route' => 'forecast', 'icon' => 'bi-graph-up-arrow'],
        'nav_sph' => ['route' => 'sph', 'icon' => 'bi-file-earmark-ruled-fill'],
    ];

    /**
     * Menu untuk pengguna yang sedang masuk.
     *
     * Kegagalan TIDAK dilempar keluar: beranda adalah halaman pertama sesudah
     * masuk, dan halaman 500 di situ membuat aplikasi terlihat mati total.
     * Yang dikembalikan menu kosong beserta kalimat galatnya, supaya berandanya
     * tetap tergambar lengkap dengan tombol Pesan dan tab bar.
     *
     * @return array{menu: list<array{label: string, route: string, icon: string}>, galat: string|null}
     */
    public static function untukPenggunaSaatIni(): array
    {
        try {
            $data = Api::get('/fragment-menus')['data'] ?? [];
        } catch (RequestException $e) {
            return ['menu' => [], 'galat' => $e->response->json('message') ?? 'Gagal mengambil daftar menu.'];
        } catch (\Throwable $e) {
            return ['menu' => [], 'galat' => 'Gagal menghubungi server saat mengambil daftar menu.'];
        }

        $menu = [];

        // Urutannya dibiarkan apa adanya: endpoint sudah mengurutkan lewat
        // order_index, kolom yang diatur di halaman menu ERP.
        foreach ($data as $baris) {
            $kunci = $baris['route'] ?? '';

            if (! isset(self::PETA[$kunci])) {
                continue;
            }

            $menu[] = [
                // Nama diambil dari ERP, bukan ditulis di sini, supaya menu
                // yang diganti namanya di sana ikut berganti di PWA.
                'label' => $baris['name'] ?? $kunci,
                'route' => self::PETA[$kunci]['route'],
                'icon' => self::PETA[$kunci]['icon'],
            ];
        }

        return ['menu' => $menu, 'galat' => null];
    }
}
