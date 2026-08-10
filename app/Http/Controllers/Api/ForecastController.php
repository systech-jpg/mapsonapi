<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ForecastController extends Controller
{
    /**
     * GET /api/forecast/principals
     * Mendapatkan daftar Principal (vendor).
     */
    public function getPrincipals(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        try {
            $principals = DB::table('llxjp_societe as s')
                ->join('llxjp_product_extrafields as pe', 's.rowid', '=', 'pe.principal')
                ->select('s.rowid as id', 's.nom as name')
                ->groupBy('s.rowid', 's.nom')
                ->orderBy('s.nom', 'asc')
                ->get();

            return $this->successResponse($principals, 'Berhasil mengambil daftar Principal.');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/forecast
     * Membuat dokumen forecast dan otomatis meng-generate snapshot (staging).
     */
    public function store(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'fk_principal' => 'required|integer',
            'forecast_month' => 'required|integer|min:1|max:12',
            'date_forecast' => 'required|date'
        ]);

        $fk_principal = $request->fk_principal;
        $forecast_month = $request->forecast_month;
        $date_forecast = Carbon::parse($request->date_forecast);

        DB::beginTransaction();
        try {
            // Generate Reference FC/YYYY/MM/INC
            $year = $date_forecast->format('Y');
            $month = $date_forecast->format('m');
            $prefix = "FC/{$year}/{$month}/";

            $max_ref = DB::table('llxjp_forecast')
                ->where('ref', 'like', $prefix . '%')
                ->orderByRaw('CAST(SUBSTRING_INDEX(ref, "/", -1) AS UNSIGNED) DESC')
                ->value('ref');

            $next_inc = 1;
            if ($max_ref) {
                $last_part = explode('/', $max_ref);
                $next_inc = (int)end($last_part) + 1;
            }
            $ref = $prefix . sprintf("%03d", $next_inc);

            // Insert Forecast Header
            $forecast_id = DB::table('llxjp_forecast')->insertGetId([
                'ref' => $ref,
                'fk_principal' => $fk_principal,
                'forecast_month' => $forecast_month,
                'date_forecast' => $date_forecast->toDateString(),
                // UTC, mengikuti konvensi penyimpanan Dolibarr. Carbon::now()
                // memakai Asia/Jakarta (config/app.php) dan akan tersimpan 7 jam
                // lebih maju daripada baris yang ditulis ERP.
                'datec' => Carbon::now('UTC')->toDateTimeString(),
                // PK llxjp_user adalah rowid; tidak ada kolom id. $user->id
                // mengembalikan NULL diam-diam, sehingga dokumen forecast dari
                // mobile tersimpan tanpa pembuat.
                'fk_user_creat' => $user->rowid,
                'status' => 0 // Draft
            ]);

            // GENERATE SNAPSHOT (Staging)
            $this->generateSnapshot($forecast_id, $fk_principal, $forecast_month, $date_forecast);

            DB::commit();

            return $this->successResponse([
                'id' => $forecast_id,
                'ref' => $ref
            ], 'Berhasil membuat dokumen Forecast dan menarik data snapshot.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat membuat forecast: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logic Generate Snapshot (Dipanggil saat Store)
     */
    private function generateSnapshot($forecast_id, $fk_principal, $forecast_month, $date_forecast)
    {
        $anchor_year = $date_forecast->format('Y');

        // Ambil produk dan reel stock
        // Note: Query kompleks dari Dolibarr ini disesuaikan strukturnya, ini versi simplifikasinya 
        // yang secara logika mirip dengan di file PHP
        
        $sql_prod = "SELECT 
                        p.rowid, p.seuil_stock_alerte as buffer,
                        (COALESCE(sm.total_mov, 0) - COALESCE(su.total_ur, 0)) AS reel
                    FROM llxjp_product p
                    LEFT JOIN ( 
                        SELECT 
                            COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) AS fk_product,
                            SUM(sm.value * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS total_mov
                        FROM llxjp_stock_mouvement sm
                        LEFT JOIN llxjp_product_association pa  ON pa.fk_product_pere   = sm.fk_product
                        LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere  = pa.fk_product_fils
                        GROUP BY COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) 
                    ) sm ON sm.fk_product = p.rowid
                    LEFT JOIN ( 
                        SELECT 
                            COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) AS fk_product,
                            SUM(urd.qty_used * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS total_ur
                        FROM llxjp_usage_report_det urd
                        JOIN llxjp_usage_report ur ON ur.rowid = urd.fk_usage_report
                        LEFT JOIN llxjp_product_association pa  ON pa.fk_product_pere  = urd.fk_product
                        LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
                        WHERE urd.qty_used > 0
                            AND (
                            ur.fk_so IS NULL OR ur.fk_so = 0
                            OR NOT EXISTS (
                                SELECT 1
                                FROM llxjp_element_element ee
                                JOIN llxjp_expedition ex ON ex.rowid = ee.fk_target
                                WHERE ee.sourcetype = 'commande'
                                    AND ee.targettype = 'shipping'
                                    AND ee.fk_source = ur.fk_so
                                    AND ex.fk_statut > 0
                            )
                            )
                        GROUP BY COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) 
                    ) su ON su.fk_product = p.rowid
                    JOIN llxjp_product_extrafields pe ON p.rowid = pe.fk_object
                    WHERE p.tosell = 1 
                    AND p.fk_product_type = 0
                    AND p.ref NOT LIKE '%-MAP'
                    AND pe.principal = ?
                    HAVING reel > 0";

        $products = DB::select($sql_prod, [$fk_principal]);

        $staging_data = [];
        foreach ($products as $obj_prod) {
            $staging_data[] = [
                'fk_forecast' => $forecast_id,
                'fk_product' => $obj_prod->rowid,
                'buffer' => $obj_prod->buffer ?? 0,
                'saldo_akhir' => $obj_prod->reel ?? 0,
                'out_1' => 0,
                'out_2' => 0,
                'out_3' => 0,
                'out_4' => 0,
                'out_5' => 0,
                'out_6' => 0,
            ];
        }

        // Insert into staging (batch)
        if (count($staging_data) > 0) {
            DB::table('llxjp_forecast_staging')->insert($staging_data);
        }
    }

    /**
     * GET /api/forecast/{id}/products
     * Mengambil daftar produk forecast beserta buffer, saldo akhir, rekomendasi dan forecast_qty jika ada.
     */
    public function getProducts(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        try {
            $forecast = DB::table('llxjp_forecast')->where('rowid', $id)->first();
            if (!$forecast) {
                return $this->errorResponse('Forecast tidak ditemukan.', 404);
            }

            // Ambil staging data
            $staging = DB::table('llxjp_forecast_staging as stg')
                ->join('llxjp_product as p', 'p.rowid', '=', 'stg.fk_product')
                ->leftJoin('llxjp_forecast_det as fd', function($join) use ($id) {
                    $join->on('fd.fk_product', '=', 'stg.fk_product')
                         ->where('fd.fk_forecast', '=', $id);
                })
                ->select(
                    'stg.fk_product as product_id',
                    'p.ref as product_kode',
                    'p.label as product_name',
                    'stg.buffer',
                    'stg.saldo_akhir',
                    'fd.qty_forecast'
                )
                ->where('stg.fk_forecast', $id)
                ->orderBy('p.ref', 'asc')
                ->get();

            // Hitung rekomendasi butuh
            $staging->transform(function ($item) {
                $target_stock = $item->buffer * 2;
                $diff = $target_stock - $item->saldo_akhir;
                $item->rekomendasi_butuh = ($diff > 0) ? $diff : 0;
                
                // Jika null, jadikan 0
                $item->qty_forecast = $item->qty_forecast ?? 0;
                
                return $item;
            });

            return $this->successResponse([
                'forecast' => [
                    'id' => $forecast->rowid,
                    'ref' => $forecast->ref,
                    'status' => $forecast->status,
                    'is_validated' => ($forecast->status == 1)
                ],
                'products' => $staging
            ], 'Berhasil mengambil daftar produk forecast.');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/forecast/{id}/search-products
     * Cari produk milik principal yang belum ada di staging (untuk ditambah manual)
     */
    public function searchProducts(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $query = $request->query('q', '');
        
        try {
            $forecast = DB::table('llxjp_forecast')->where('rowid', $id)->first();
            if (!$forecast) {
                return $this->errorResponse('Forecast tidak ditemukan.', 404);
            }

            $products = DB::table('llxjp_product as p')
                ->join('llxjp_product_extrafields as pe', 'p.rowid', '=', 'pe.fk_object')
                ->where('pe.principal', $forecast->fk_principal)
                ->where('p.tosell', 1)
                ->where('p.fk_product_type', 0)
                ->where('p.ref', 'NOT LIKE', '%-MAP')
                ->whereNotIn('p.rowid', function($q) use ($id) {
                    $q->select('fk_product')->from('llxjp_forecast_staging')->where('fk_forecast', $id);
                });

            if (!empty($query)) {
                $products->where(function($q) use ($query) {
                    $q->where('p.ref', 'LIKE', "%{$query}%")
                      ->orWhere('p.label', 'LIKE', "%{$query}%");
                });
            }

            $result = $products->select('p.rowid as id', 'p.ref', 'p.label')->limit(50)->get();
            return $this->successResponse($result, 'Berhasil mencari produk.');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/forecast/{id}/add-product
     * Tambah manual produk ke staging beserta kalkulasi buffer & saldo
     */
    public function addProduct(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'product_id' => 'required|integer'
        ]);

        $product_id = $request->product_id;

        try {
            $forecast = DB::table('llxjp_forecast')->where('rowid', $id)->first();
            if (!$forecast) {
                return $this->errorResponse('Forecast tidak ditemukan.', 404);
            }

            if ($forecast->status == 1) {
                return $this->errorResponse('Forecast sudah divalidasi dan tidak dapat diubah.', 400);
            }

            // Cek apakah sudah ada
            $exists = DB::table('llxjp_forecast_staging')
                ->where('fk_forecast', $id)
                ->where('fk_product', $product_id)
                ->exists();
            if ($exists) {
                return $this->errorResponse('Produk sudah ada di dalam daftar forecast.', 400);
            }

            // Hitung buffer & reel stock untuk 1 produk ini
            $sql_prod = "SELECT 
                            p.rowid, p.ref, p.label, p.seuil_stock_alerte as buffer,
                            (COALESCE(sm.total_mov, 0) - COALESCE(su.total_ur, 0)) AS reel
                        FROM llxjp_product p
                        LEFT JOIN ( 
                            SELECT 
                                COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) AS fk_product,
                                SUM(sm.value * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS total_mov
                            FROM llxjp_stock_mouvement sm
                            LEFT JOIN llxjp_product_association pa  ON pa.fk_product_pere   = sm.fk_product
                            LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere  = pa.fk_product_fils
                            GROUP BY COALESCE(pa2.fk_product_fils, pa.fk_product_fils, sm.fk_product) 
                        ) sm ON sm.fk_product = p.rowid
                        LEFT JOIN ( 
                            SELECT 
                                COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) AS fk_product,
                                SUM(urd.qty_used * COALESCE(pa.qty, 1) * COALESCE(pa2.qty, 1)) AS total_ur
                            FROM llxjp_usage_report_det urd
                            JOIN llxjp_usage_report ur ON ur.rowid = urd.fk_usage_report
                            LEFT JOIN llxjp_product_association pa  ON pa.fk_product_pere  = urd.fk_product
                            LEFT JOIN llxjp_product_association pa2 ON pa2.fk_product_pere = pa.fk_product_fils
                            WHERE urd.qty_used > 0
                                AND (
                                ur.fk_so IS NULL OR ur.fk_so = 0
                                OR NOT EXISTS (
                                    SELECT 1
                                    FROM llxjp_element_element ee
                                    JOIN llxjp_expedition ex ON ex.rowid = ee.fk_target
                                    WHERE ee.sourcetype = 'commande'
                                        AND ee.targettype = 'shipping'
                                        AND ee.fk_source = ur.fk_so
                                        AND ex.fk_statut > 0
                                )
                                )
                            GROUP BY COALESCE(pa2.fk_product_fils, pa.fk_product_fils, urd.fk_product) 
                        ) su ON su.fk_product = p.rowid
                        WHERE p.rowid = ?";
                        
            $productData = DB::select($sql_prod, [$product_id]);
            $obj_prod = $productData[0] ?? null;

            if (!$obj_prod) {
                return $this->errorResponse('Data produk tidak ditemukan.', 404);
            }

            // Insert to staging
            DB::table('llxjp_forecast_staging')->insert([
                'fk_forecast' => $id,
                'fk_product' => $product_id,
                'buffer' => $obj_prod->buffer ?? 0,
                'saldo_akhir' => $obj_prod->reel ?? 0,
                'out_1' => 0, 'out_2' => 0, 'out_3' => 0,
                'out_4' => 0, 'out_5' => 0, 'out_6' => 0,
            ]);

            // Format response sama dengan getProducts
            $buffer = $obj_prod->buffer ?? 0;
            $saldo_akhir = $obj_prod->reel ?? 0;
            $target_stock = $buffer * 2;
            $diff = $target_stock - $saldo_akhir;
            $rekomendasi_butuh = ($diff > 0) ? $diff : 0;

            $responseData = [
                'product_id' => $product_id,
                'product_kode' => $obj_prod->ref,
                'product_name' => $obj_prod->label,
                'buffer' => $buffer,
                'saldo_akhir' => $saldo_akhir,
                'rekomendasi_butuh' => $rekomendasi_butuh,
                'qty_forecast' => 0
            ];

            return $this->successResponse($responseData, 'Berhasil menambahkan produk ke forecast.');
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/forecast/{id}/save
     * Bulk save products (qty_forecast) dan opsional langsung validate.
     */
    public function save(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:0',
            'validate' => 'boolean'
        ]);

        $forecast = DB::table('llxjp_forecast')->where('rowid', $id)->first();
        if (!$forecast) {
            return $this->errorResponse('Forecast tidak ditemukan.', 404);
        }

        if ($forecast->status == 1) {
            return $this->errorResponse('Forecast sudah divalidasi dan tidak dapat diubah.', 400);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                $fk_product = $item['product_id'];
                $qty = $item['qty'];

                $exists = DB::table('llxjp_forecast_det')
                    ->where('fk_forecast', $id)
                    ->where('fk_product', $fk_product)
                    ->exists();

                if ($exists) {
                    DB::table('llxjp_forecast_det')
                        ->where('fk_forecast', $id)
                        ->where('fk_product', $fk_product)
                        ->update(['qty_forecast' => $qty]);
                } else {
                    DB::table('llxjp_forecast_det')->insert([
                        'fk_forecast' => $id,
                        'fk_product' => $fk_product,
                        'qty_forecast' => $qty
                    ]);
                }
            }

            $message = 'Berhasil menyimpan daftar forecast.';

            // Auto validate jika dikirim flag validate = true
            if ($request->validate) {
                // Cek logic khusus PT Asia Actual Indonesia seperti di web
                if ($forecast->fk_principal == 3) {
                    DB::table('llxjp_forecast')->where('rowid', $id)->update(['fk_principal' => 7]);
                }

                DB::table('llxjp_forecast')->where('rowid', $id)->update(['status' => 1]);
                $message .= ' Forecast telah divalidasi.';
            }

            DB::commit();

            return $this->successResponse(null, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat menyimpan forecast: ' . $e->getMessage(), 500);
        }
    }
}
