<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Scan product by barcode to get title, description, and current stock.
     */
    public function scan(Request $request)
    {
        $barcode = $request->input('barcode');

        if (!$barcode) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode wajib diisi (parameter: barcode).'
            ], 400);
        }

        // Cari produk berdasarkan barcode di tabel llxjp_product
        $productRow = DB::table('llxjp_product')
            ->where('barcode', $barcode)
            ->select('rowid', 'ref', 'label', 'description', 'stock')
            ->first();

        if (!$productRow) {
            return response()->json([
                'success' => false,
                'message' => 'Produk dengan barcode tersebut tidak ditemukan.'
            ], 404);
        }

        $desc = trim($productRow->label);
        if (!empty($productRow->description)) {
            // Bersihkan tag HTML dan decode entitas (seperti &times; menjadi karakter aslinya)
            $cleanDescription = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $productRow->description)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            
            // Cek agar tidak duplikat: jika deskripsi (setelah dibersihkan) berbeda dari label, baru ditambahkan
            if ($cleanDescription !== '' && strcasecmp($cleanDescription, $desc) !== 0) {
                $desc .= "\n" . $cleanDescription;
            }
        }

        // Hitung real stok (Saldo Akhir) dari pergerakan (sama seperti di report_all_stock.php)
        $productId = $productRow->rowid;

        // 1. Total dari stock_mouvement
        $sql_mov = "SELECT SUM(sm.value * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) as total_mov
                    FROM llxjp_stock_mouvement sm
                    LEFT JOIN llxjp_product_association pa ON pa.fk_product_pere = sm.fk_product
                    LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
                    WHERE COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) = ?";
        $result_mov = DB::select($sql_mov, [$productId]);
        $total_mov = $result_mov[0]->total_mov ?? 0;

        // 2. Total dari usage_report (penggunaan)
        $sql_ur = "SELECT SUM(urd.qty_used * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) as total_ur
                   FROM llxjp_usage_report_det urd
                   JOIN llxjp_usage_report ur ON ur.rowid = urd.fk_usage_report
                   LEFT JOIN llxjp_product_association pa ON pa.fk_product_pere = urd.fk_product
                   LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
                   WHERE urd.qty_used > 0
                   AND (ur.fk_so IS NULL OR ur.fk_so = 0 OR NOT EXISTS (
                       SELECT 1 FROM llxjp_element_element ee
                       JOIN llxjp_expedition ex ON ex.rowid = ee.fk_target
                       WHERE ee.sourcetype = 'commande' AND ee.targettype = 'shipping' AND ee.fk_source = ur.fk_so AND ex.fk_statut > 0
                   ))
                   AND COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) = ?";
        $result_ur = DB::select($sql_ur, [$productId]);
        $total_ur = $result_ur[0]->total_ur ?? 0;

        $realStock = (float) ($total_mov - $total_ur);

        return response()->json([
            'success' => true,
            'message' => 'Produk ditemukan.',
            'data' => [
                'judul' => $productRow->ref, // cth: ZA100
                'deskripsi' => $desc, // cth: MIS Set Screw...
                'stok_saat_ini' => $realStock,
            ]
        ], 200);
    }
}
