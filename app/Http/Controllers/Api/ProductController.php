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
            ->select('ref', 'label', 'description', 'stock')
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

        return response()->json([
            'success' => true,
            'message' => 'Produk ditemukan.',
            'data' => [
                'judul' => $productRow->ref, // cth: ZA100
                'deskripsi' => $desc, // cth: MIS Set Screw...
                'stok_saat_ini' => (float) $productRow->stock,
            ]
        ], 200);
    }
}
