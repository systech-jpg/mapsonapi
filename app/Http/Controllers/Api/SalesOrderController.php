<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesOrderController extends Controller
{
    /**
     * Menampilkan daftar sales order (commande) berdasarkan tanggal order.
     */
    public function index(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Ambil filter tanggal (default hari ini jika tidak ada)
        $date = $request->input('date', Carbon::today()->toDateString());

        try {
            // Ambil data sales order
            $orders = DB::table('llxjp_commande as c')
                ->leftJoin('llxjp_societe as s', 's.rowid', '=', 'c.fk_soc')
                ->leftJoin('llxjp_user as u', 'u.rowid', '=', 'c.fk_user_author')
                ->select(
                    'c.rowid',
                    'c.ref',
                    'c.ref_client',
                    's.nom as third_party',
                    'c.date_commande as order_date',
                    'c.date_livraison as planned_delivery_date',
                    'c.total_ht as amount_excl_tax',
                    'c.fk_statut as status',
                    'u.login as author_login',
                    DB::raw("CONCAT(u.firstname, ' ', u.lastname) as author_name")
                )
                ->whereDate('c.date_commande', $date)
                ->orderBy('c.rowid', 'desc')
                ->get();

            // Mapping status Dolibarr ke label (bisa disesuaikan dengan kebutuhan)
            $orders->transform(function ($item) {
                $item->status_label = $this->getStatusLabel($item->status);
                // Clean up author name if both are null
                if (trim($item->author_name) == '') {
                    $item->author_name = $item->author_login;
                }
                return $item;
            });

            return $this->successResponse($orders, 'Berhasil mengambil daftar Sales Orders untuk tanggal ' . $date);
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat mengambil data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper mapping status commande ke string (label).
     */
    private function getStatusLabel($status)
    {
        switch ($status) {
            case 0: return 'Draft';
            case 1: return 'Validated';
            case 2: return 'In Process';
            case 3: return 'Delivered';
            case -1: return 'Canceled';
            default: return 'Unknown';
        }
    }

    /**
     * Menampilkan detail informasi sales order beserta baris itemnya.
     */
    public function show(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        try {
            // Ambil header order beserta extrafields
            $order = DB::table('llxjp_commande as c')
                ->leftJoin('llxjp_societe as s', 's.rowid', '=', 'c.fk_soc')
                ->leftJoin('llxjp_user as u', 'u.rowid', '=', 'c.fk_user_author')
                ->leftJoin('llxjp_commande_extrafields as ce', 'ce.fk_object', '=', 'c.rowid')
                ->select(
                    'c.rowid',
                    'c.ref',
                    'c.ref_client',
                    's.nom as third_party',
                    'c.date_commande as order_date',
                    'c.date_livraison as planned_delivery_date',
                    'c.total_ht as amount_excl_tax',
                    'c.total_tva as amount_tax',
                    'c.total_ttc as amount_inc_tax',
                    'c.fk_statut as status',
                    'u.login as author_login',
                    DB::raw("CONCAT(u.firstname, ' ', u.lastname) as author_name"),
                    'ce.*' // Ambil semua custom fields
                )
                ->where('c.rowid', $id)
                ->first();

            if (!$order) {
                return $this->errorResponse('Data Sales Order tidak ditemukan.', 404);
            }

            // Rapikan data author & status
            $order->status_label = $this->getStatusLabel($order->status);
            if (trim($order->author_name) == '') {
                $order->author_name = $order->author_login;
            }

            // Ambil lines/items (produk yang dipesan)
            $lines = DB::table('llxjp_commandedet as cd')
                ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'cd.fk_product')
                ->select(
                    'cd.rowid as line_id',
                    'cd.description',
                    'cd.tva_tx as sales_tax_rate',
                    'cd.subprice as up_net',
                    'cd.price as up_inc_tax', // Tergantung setting dolibarr, kadang price = pu_ht
                    'cd.qty',
                    'cd.remise_percent as discount_percent',
                    'cd.buy_price_ht as cost_price',
                    'cd.total_ht as total_excl',
                    'cd.total_tva as total_tax',
                    'cd.total_ttc as total_inc_tax',
                    'p.ref as product_ref',
                    'p.label as product_label'
                )
                ->where('cd.fk_commande', $id)
                ->orderBy('cd.rang', 'asc')
                ->orderBy('cd.rowid', 'asc')
                ->get();

            // Format response gabungan
            $data = [
                'info' => $order,
                'lines' => $lines
            ];

            return $this->successResponse($data, 'Berhasil mengambil detail Sales Order.');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat mengambil data: ' . $e->getMessage(), 500);
        }
    }
}
