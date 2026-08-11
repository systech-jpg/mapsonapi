<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$ref = $_GET['ref'] ?? '102036549';
$productRow = DB::table('llxjp_product')->where('ref', $ref)->first();

if (!$productRow) {
    echo "Product not found";
    exit;
}

$productId = $productRow->rowid;
$p_stock = $productRow->stock;

$sql_mov = "SELECT SUM(sm.value * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) as total_mov
            FROM llxjp_stock_mouvement sm
            LEFT JOIN llxjp_product_association pa ON pa.fk_product_pere = sm.fk_product
            LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
            WHERE COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) = ?";
$result_mov = DB::select($sql_mov, [$productId]);
$total_mov = $result_mov[0]->total_mov ?? 0;

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

echo json_encode([
    'ref' => $ref,
    'rowid' => $productId,
    'p_stock' => $p_stock,
    'total_mov' => $total_mov,
    'total_ur' => $total_ur,
    'calculated_stock' => $total_mov - $total_ur
]);
