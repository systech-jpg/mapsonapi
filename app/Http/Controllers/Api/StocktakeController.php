<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StocktakeController extends Controller
{
    /**
     * Get products for the authenticated user's assigned stocktake principal.
     */
    public function getProducts(Request $request)
    {
        // Mendapatkan data user dari middleware dolibarr.auth
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek Role User untuk menyembunyikan/menampilkan qty_theoretical
        $userRoles = $user->groups->pluck('nom')->toArray();
        if (isset($user->admin) && $user->admin == 1) {
            $userRoles[] = 'Super Admin';
        }
        $isVerifikator = in_array('Verifikator', $userRoles) || in_array('Super Admin', $userRoles);
        $isCounter = in_array('Warehouse', $userRoles);

        if (!$isVerifikator && !$isCounter) {
            return $this->errorResponse('User tidak memiliki akses sebagai Counter atau Verifikator.', 403);
        }

        // 1. Cari master stocktake di tabel ERP yang statusnya Draft (0)
        $erpStocktake = DB::table('llxjp_stocktake')->where('status', 0)->first();
        if (!$erpStocktake) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }
        $fk_stocktake = $erpStocktake->rowid;

        // 2. Cari jadwal stocktake aktif (status = 0) dan cek assign principal untuk user ini
        $stocktakeDetails = DB::table('llxjp_userstocktake_detail as d')
            ->join('llxjp_userstocktake as m', 'm.rowid', '=', 'd.fk_stocktake')
            ->where('m.status', 0) // Stocktake belum di-validate / close
            ->where(function ($query) use ($user) {
                $query->where('d.fk_user_counter', $user->rowid)
                      ->orWhere('d.fk_user_verifikator', $user->rowid);
            })
            ->select('d.fk_principal', 'm.rowid as stocktake_id', 'm.date_stocktake', 'd.rowid as detail_id')
            ->orderBy('m.date_stocktake', 'desc')
            ->get();

        if ($stocktakeDetails->isEmpty()) {
            return $this->errorResponse('Tidak ada jadwal stocktake aktif yang di-assign untuk user Anda.', 404);
        }

        $principalIds = [];
        foreach ($stocktakeDetails as $sd) {
            $ids = explode(',', $sd->fk_principal);
            foreach ($ids as $idStr) {
                if (trim($idStr) !== '') {
                    $principalIds[] = trim($idStr);
                }
            }
        }
        $principalIds = array_unique($principalIds);
        $firstDetail = $stocktakeDetails->first();

        // 3. Tarik data produk khusus untuk principal tersebut langsung dari stocktake_det ERP (Draft)
        // Ambil juga data qty fisik yang sudah di-input sebelumnya agar Android bisa memunculkannya untuk di-edit
        $selectCols = "
                p.rowid, 
                p.ref, 
                p.label,
                s.nom as principal_name,
                sd.qty_theoretical";

        if ($isVerifikator) {
            $selectCols .= ", sd.qty_rak, sd.qty_tray, sd.qty_container, sd.qty_physical, sd.fk_user_verifikator_update";
        } else {
            $selectCols .= ", sd.counter_qty_rak as qty_rak, sd.counter_qty_tray as qty_tray, sd.counter_qty_container as qty_container, sd.counter_qty_physical as qty_physical, sd.fk_user_counter_update";
        }

        $placeholders = implode(',', array_fill(0, count($principalIds), '?'));
        $queryBindings = array_merge([$fk_stocktake], $principalIds);

        $products = DB::select("
            SELECT " . $selectCols . "
            FROM llxjp_stocktake_det sd
            JOIN llxjp_product p ON p.rowid = sd.fk_product
            JOIN llxjp_product_extrafields pe ON pe.fk_object = p.rowid
            LEFT JOIN llxjp_societe s ON s.rowid = pe.principal
            WHERE sd.fk_stocktake = ?
              AND pe.principal IN (" . $placeholders . ")
        ", $queryBindings);

        // 4. Proses data (Sembunyikan qty_theoretical jika Counter, dan beri status is_updated)
        if ($isCounter && !$isVerifikator) {
            foreach ($products as &$prod) {
                unset($prod->qty_theoretical); // Hapus properties qty_theoretical
                $prod->is_updated = !is_null($prod->fk_user_counter_update) ? true : false;
                unset($prod->fk_user_counter_update);
            }
        } else {
            foreach ($products as &$prod) {
                $prod->is_updated = !is_null($prod->fk_user_verifikator_update) ? true : false;
                unset($prod->fk_user_verifikator_update);
            }
        }

        // 5. Return data success 
        $principalNamesList = DB::table('llxjp_societe')
            ->whereIn('rowid', $principalIds)
            ->pluck('nom')
            ->toArray();
        $principalNameStr = implode(', ', $principalNamesList);

        // Menggunakan successResponse() bawaan dari base Controller mapsonapi
        return $this->successResponse([
            'stocktake_id'   => $firstDetail->stocktake_id,
            'detail_id'      => $firstDetail->detail_id,
            'date_stocktake' => $firstDetail->date_stocktake,
            'fk_principal'   => (int) explode(',', $firstDetail->fk_principal)[0],
            'principal_name' => $principalNameStr,
            'total_items'    => count($products),
            'products'       => $products
        ], 'Berhasil mengambil data produk stocktake');
    }

    /**
     * Update stocktake items based on role (Counter or Verifikator)
     */
    public function updateProducts(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'products' => 'required|array',
            'products.*.fk_product' => 'required', // Bisa menerima rowid (integer) atau ref (string/numeric)
            'products.*.qty_rak' => 'nullable|numeric',
            'products.*.qty_tray' => 'nullable|numeric',
            'products.*.qty_container' => 'nullable|numeric',
        ]);

        // Cek Role
        $userRoles = $user->groups->pluck('nom')->toArray();
        if (isset($user->admin) && $user->admin == 1) {
            $userRoles[] = 'Super Admin';
        }

        $isVerifikator = in_array('Verifikator', $userRoles) || in_array('Super Admin', $userRoles);
        $isCounter = in_array('Warehouse', $userRoles);

        if (!$isVerifikator && !$isCounter) {
            return $this->errorResponse('User tidak memiliki akses sebagai Counter atau Verifikator.', 403);
        }

        // Cari master stocktake di tabel ERP yang statusnya Draft (0)
        $erpStocktake = DB::table('llxjp_stocktake')->where('status', 0)->first();
        if (!$erpStocktake) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }
        $fk_stocktake = $erpStocktake->rowid;

        DB::beginTransaction();
        try {
            foreach ($request->products as $prod) {
                $refOrId = $prod['fk_product'];
                
                // Cari ID produk sebenarnya di llxjp_product (berdasarkan rowid atau ref)
                $productRow = DB::table('llxjp_product')
                    ->where('rowid', $refOrId)
                    ->orWhere('ref', (string) $refOrId)
                    ->first();
                    
                if (!$productRow) {
                    continue; // Skip jika produk tidak ditemukan
                }
                
                $fk_product = $productRow->rowid;

                $existing = DB::table('llxjp_stocktake_det')
                    ->where('fk_stocktake', $fk_stocktake)
                    ->where('fk_product', $fk_product)
                    ->first();
                    
                if (!$existing) {
                    continue; // Skip jika anehnya tidak ada di detail stocktake
                }

                $parseQty = function($val, $existing_val) {
                    if (!isset($val) || trim((string)$val) === '') {
                        return (float)$existing_val;
                    }
                    return (float)str_replace(',', '.', $val);
                };

                if ($isVerifikator) {
                    $rak = $parseQty($prod['qty_rak'] ?? null, $existing->qty_rak);
                    $tray = $parseQty($prod['qty_tray'] ?? null, $existing->qty_tray);
                    $container = $parseQty($prod['qty_container'] ?? null, $existing->qty_container);
                    $physical = $rak + $tray + $container;

                    // Update kolom utama ERP
                    DB::table('llxjp_stocktake_det')
                        ->where('fk_stocktake', $fk_stocktake)
                        ->where('fk_product', $fk_product)
                        ->update([
                            'qty_rak'                    => $rak,
                            'qty_tray'                   => $tray,
                            'qty_container'              => $container,
                            'qty_physical'               => $physical,
                            'fk_user_verifikator_update' => $user->rowid
                        ]);
                } else {
                    $rak = $parseQty($prod['qty_rak'] ?? null, $existing->counter_qty_rak);
                    $tray = $parseQty($prod['qty_tray'] ?? null, $existing->counter_qty_tray);
                    $container = $parseQty($prod['qty_container'] ?? null, $existing->counter_qty_container);
                    $physical = $rak + $tray + $container;

                    // Update kolom historis untuk Counter
                    DB::table('llxjp_stocktake_det')
                        ->where('fk_stocktake', $fk_stocktake)
                        ->where('fk_product', $fk_product)
                        ->update([
                            'counter_qty_rak'        => $rak,
                            'counter_qty_tray'       => $tray,
                            'counter_qty_container'  => $container,
                            'counter_qty_physical'   => $physical,
                            'fk_user_counter_update' => $user->rowid
                        ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengupdate data fisik produk.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan sistem: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get product by barcode/qrcode for the active stocktake.
     */
    public function scanProduct(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'barcode' => 'required|string',
        ]);

        $barcode = $request->input('barcode');

        // Cek Role User untuk menyembunyikan/menampilkan qty_theoretical
        $userRoles = $user->groups->pluck('nom')->toArray();
        if (isset($user->admin) && $user->admin == 1) {
            $userRoles[] = 'Super Admin';
        }
        $isVerifikator = in_array('Verifikator', $userRoles) || in_array('Super Admin', $userRoles);
        $isCounter = in_array('Warehouse', $userRoles);

        if (!$isVerifikator && !$isCounter) {
            return $this->errorResponse('User tidak memiliki akses sebagai Counter atau Verifikator.', 403);
        }

        // 1. Cari master stocktake di tabel ERP yang statusnya Draft (0)
        $erpStocktake = DB::table('llxjp_stocktake')->where('status', 0)->first();
        if (!$erpStocktake) {
            return $this->errorResponse('Tidak ada dokumen Stocktake (Draft) yang aktif di sistem ERP.', 404);
        }
        $fk_stocktake = $erpStocktake->rowid;

        // 2. Cari jadwal stocktake aktif (status = 0) dan cek assign principal untuk user ini
        $stocktakeDetail = DB::table('llxjp_userstocktake_detail as d')
            ->join('llxjp_userstocktake as m', 'm.rowid', '=', 'd.fk_stocktake')
            ->where('m.status', 0) // Stocktake belum di-validate / close
            ->where(function ($query) use ($user) {
                $query->where('d.fk_user_counter', $user->rowid)
                      ->orWhere('d.fk_user_verifikator', $user->rowid);
            })
            ->select('d.fk_principal', 'm.rowid as stocktake_id', 'm.date_stocktake', 'd.rowid as detail_id')
            ->orderBy('m.date_stocktake', 'desc')
            ->first();

        if (!$stocktakeDetail) {
            return $this->errorResponse('Tidak ada jadwal stocktake aktif yang di-assign untuk user Anda.', 200);
        }

        // --- DEBUGGING STEP-BY-STEP ---
        // A. Cek apakah produk dengan barcode tersebut ada di tabel llxjp_product
        $productRow = DB::table('llxjp_product')->where('barcode', $barcode)->first();
        if (!$productRow) {
            return $this->errorResponse('Produk dengan barcode tersebut tidak ditemukan di master data product (mungkin nama kolomnya bukan barcode di database).', 200);
        }

        // B. Cek apakah produk ini ada di detail stocktake ERP
        $stocktakeDetRow = DB::table('llxjp_stocktake_det')
            ->where('fk_stocktake', $fk_stocktake)
            ->where('fk_product', $productRow->rowid)
            ->first();
        if (!$stocktakeDetRow) {
            return $this->errorResponse('Produk ditemukan di master (Ref: ' . $productRow->ref . '), tapi TIDAK ADA dalam dokumen Stocktake Draft (ID: ' . $fk_stocktake . ').', 200);
        }

        // C. Cek apakah principal-nya cocok dengan yang di-assign ke user
        $principalIds = explode(',', $stocktakeDetail->fk_principal);
        $extrafields = DB::table('llxjp_product_extrafields')
            ->where('fk_object', $productRow->rowid)
            ->whereIn('principal', $principalIds)
            ->first();
        if (!$extrafields) {
            $actualPrincipalName = 'Unknown';
            $actualExtra = DB::table('llxjp_product_extrafields')
                ->where('fk_object', $productRow->rowid)
                ->first();
            
            if ($actualExtra && $actualExtra->principal) {
                $soc = DB::table('llxjp_societe')->where('rowid', $actualExtra->principal)->first();
                if ($soc) {
                    $actualPrincipalName = $soc->nom;
                }
            }

            return $this->errorResponse('Produk ini milik principal "' . $actualPrincipalName . '", yang tidak ditugaskan kepada Anda.', 200);
        }

        // 3. Tarik data produk utuh jika lolos semua
        $selectCols = "
            p.rowid, 
            p.ref, 
            p.label,
            p.barcode,
            sd.qty_theoretical";

        if ($isVerifikator) {
            $selectCols .= ", sd.qty_rak, sd.qty_tray, sd.qty_container, sd.qty_physical, sd.fk_user_verifikator_update";
        } else {
            $selectCols .= ", sd.counter_qty_rak as qty_rak, sd.counter_qty_tray as qty_tray, sd.counter_qty_container as qty_container, sd.counter_qty_physical as qty_physical, sd.fk_user_counter_update";
        }

        $inClauses = implode(',', array_fill(0, count($principalIds), '?'));
        
        $bindings = [$fk_stocktake];
        $bindings = array_merge($bindings, $principalIds);
        $bindings[] = $barcode;

        $product = collect(DB::select("
            SELECT " . $selectCols . "
            FROM llxjp_stocktake_det sd
            JOIN llxjp_product p ON p.rowid = sd.fk_product
            JOIN llxjp_product_extrafields pe ON pe.fk_object = p.rowid
            WHERE sd.fk_stocktake = ?
              AND pe.principal IN ({$inClauses})
              AND p.barcode = ?
            LIMIT 1
        ", $bindings))->first();

        if (!$product) {
            return $this->errorResponse('Produk tidak ditemukan saat digabungkan, ada anomali data.', 200);
        }

        // 4. Sembunyikan qty_theoretical jika role user adalah Counter (dan bukan Verifikator), serta set flag is_updated
        if ($isCounter && !$isVerifikator) {
            unset($product->qty_theoretical);
            $product->is_updated = !is_null($product->fk_user_counter_update) ? true : false;
            unset($product->fk_user_counter_update);
        } else {
            $product->is_updated = !is_null($product->fk_user_verifikator_update) ? true : false;
            unset($product->fk_user_verifikator_update);
        }

        return $this->successResponse(
            $product, 
            'Berhasil menemukan data produk.'
        );
    }

    /**
     * Get stocktake history for the authenticated user.
     */
    public function getHistory(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek Role
        $userRoles = $user->groups->pluck('nom')->toArray();
        if (isset($user->admin) && $user->admin == 1) {
            $userRoles[] = 'Super Admin';
        }
        $isVerifikator = in_array('Verifikator', $userRoles) || in_array('Super Admin', $userRoles);
        $isCounter = in_array('Warehouse', $userRoles);

        if (!$isVerifikator && !$isCounter) {
            return $this->errorResponse('User tidak memiliki akses sebagai Counter atau Verifikator.', 403);
        }

        // Ambil riwayat jadwal stocktake untuk user ini (hanya yang di-assign ke user tersebut)
        $histories = DB::table('llxjp_userstocktake_detail as d')
            ->join('llxjp_userstocktake as m', 'm.rowid', '=', 'd.fk_stocktake')
            ->where(function ($query) use ($user) {
                $query->where('d.fk_user_counter', $user->rowid)
                      ->orWhere('d.fk_user_verifikator', $user->rowid);
            })
            ->select(
                'm.rowid as schedule_id', 
                'm.date_stocktake', 
                'd.fk_principal', 
                DB::raw("(SELECT GROUP_CONCAT(nom SEPARATOR ', ') FROM llxjp_societe WHERE FIND_IN_SET(rowid, d.fk_principal)) as principal_name"), 
                'm.status as schedule_status',
                'd.signature_counter',
                'd.signature_verifikator'
            )
            ->orderBy('m.date_stocktake', 'desc')
            ->get();

        $result = [];
        foreach ($histories as $history) {
            // Karena relasi langsung antara llxjp_userstocktake dan llxjp_stocktake tidak eksplisit,
            // kita cari dokumen stocktake ERP yang BENAR-BENAR memuat produk dari principal ini.
            $histPrincipalIds = explode(',', $history->fk_principal);
            $erpStocktake = DB::table('llxjp_stocktake_det as sd')
                ->join('llxjp_product_extrafields as pe', 'pe.fk_object', '=', 'sd.fk_product')
                ->join('llxjp_stocktake as s', 's.rowid', '=', 'sd.fk_stocktake')
                ->whereIn('pe.principal', $histPrincipalIds)
                ->select('s.rowid', 's.status')
                ->orderBy('s.rowid', 'desc')
                ->first();
                
            // Jika dokumen stocktake belum ada atau statusnya masih 0 (Draft/Sedang Berjalan),
            // maka data tersebut tidak boleh muncul di History.
            if (!$erpStocktake || $erpStocktake->status == 0) {
                continue;
            }
            
            $erpStocktakeId = $erpStocktake->rowid;
                
            $products = [];
            $total_items = 0;
            $total_qty = 0;
            
            if ($erpStocktakeId) {
                $selectCols = "
                    p.rowid, 
                    p.ref, 
                    p.label,
                    sd.qty_theoretical";

                if ($isVerifikator) {
                    $selectCols .= ", sd.qty_rak, sd.qty_tray, sd.qty_container, sd.qty_physical, sd.fk_user_verifikator_update";
                } else {
                    $selectCols .= ", sd.counter_qty_rak as qty_rak, sd.counter_qty_tray as qty_tray, sd.counter_qty_container as qty_container, sd.counter_qty_physical as qty_physical, sd.fk_user_counter_update";
                }

                $inClauses = implode(',', array_fill(0, count($histPrincipalIds), '?'));
                $bindings = [$erpStocktakeId];
                $bindings = array_merge($bindings, $histPrincipalIds);

                $products = DB::select("
                    SELECT " . $selectCols . "
                    FROM llxjp_stocktake_det sd
                    JOIN llxjp_product p ON p.rowid = sd.fk_product
                    JOIN llxjp_product_extrafields pe ON pe.fk_object = p.rowid
                    WHERE sd.fk_stocktake = ?
                      AND pe.principal IN ({$inClauses})
                ", $bindings);
                
                if ($isCounter && !$isVerifikator) {
                    foreach ($products as &$prod) {
                        unset($prod->qty_theoretical);
                        $prod->is_updated = !is_null($prod->fk_user_counter_update) ? true : false;
                        unset($prod->fk_user_counter_update);
                        $total_qty += $prod->qty_physical;
                    }
                } else {
                    foreach ($products as &$prod) {
                        $prod->is_updated = !is_null($prod->fk_user_verifikator_update) ? true : false;
                        unset($prod->fk_user_verifikator_update);
                        $total_qty += $prod->qty_physical;
                    }
                }
                
                $total_items = count($products);
            }
            
            // Format ulang urutan output agar lebih rapi di JSON
            $result[] = [
                'schedule_id'           => $history->schedule_id,
                'date_stocktake'        => $history->date_stocktake,
                'fk_principal'          => (int) explode(',', $history->fk_principal)[0],
                'principal_name'        => $history->principal_name,
                'schedule_status'       => $erpStocktake->status, // Menggunakan status asli dari dokumen ERP
                'signature_counter'     => $history->signature_counter,
                'signature_verifikator' => $history->signature_verifikator,
                'total_items'           => $total_items,
                'total_qty'             => $total_qty,
                'products'              => $products
            ];
        }

        return $this->successResponse($result, 'Berhasil mengambil riwayat stocktake.');
    }

    /**
     * Integrasi iLovePDF untuk melakukan request digital signature.
     */
    public function requestSignature(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'schedule_id' => 'required|integer',
            'fk_principal' => 'required|integer',
        ]);

        $scheduleId = $request->input('schedule_id');
        $fkPrincipal = $request->input('fk_principal');

        // Cari PDF dari database berdasarkan jadwal dan principal
        $schedule = DB::table('llxjp_userstocktake_detail as d')
            ->join('llxjp_userstocktake as m', 'm.rowid', '=', 'd.fk_stocktake')
            ->leftJoin('llxjp_user as uc', 'uc.rowid', '=', 'd.fk_user_counter')
            ->leftJoin('llxjp_user as uv', 'uv.rowid', '=', 'd.fk_user_verifikator')
            ->where('m.rowid', $scheduleId)
            ->whereRaw('FIND_IN_SET(?, d.fk_principal)', [$fkPrincipal])
            ->select(
                'd.file_pdf',
                'uc.firstname as counter_first', 'uc.lastname as counter_last', 'uc.email as counter_email',
                'uv.firstname as verifikator_first', 'uv.lastname as verifikator_last', 'uv.email as verifikator_email'
            )
            ->first();

        if (!$schedule) {
            return $this->errorResponse('Jadwal tidak ditemukan.', 404);
        }

        if (empty($schedule->file_pdf)) {
            return $this->errorResponse('File PDF belum tersedia untuk jadwal ini (kolom file_pdf kosong).', 400);
        }

        $publicKey = env('ILOVEPDF_PUBLIC_KEY');
        $secretKey = env('ILOVEPDF_SECRET_KEY');

        if (empty($publicKey) || empty($secretKey)) {
            return $this->errorResponse('Konfigurasi iLovePDF (Key) belum disetup di .env', 500);
        }

        try {
            // 1. Auth
            $authResp = Http::post('https://api.ilovepdf.com/v1/auth', [
                'public_key' => $publicKey
            ]);
            
            if (!$authResp->successful()) {
                return $this->errorResponse('Gagal autentikasi iLovePDF: ' . $authResp->body(), 500);
            }
            $token = $authResp->json('token');

            // 2. Start Task (Signature)
            $startResp = Http::withToken($token)->get('https://api.ilovepdf.com/v1/start/sign');
            if (!$startResp->successful()) {
                return $this->errorResponse('Gagal memulai task signature iLovePDF: ' . $startResp->body(), 500);
            }
            $server = $startResp->json('server');
            $task = $startResp->json('task');

            // 3. Download PDF dari URL ke Temporary File
            $pdfUrl = $schedule->file_pdf; 
            if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                return $this->errorResponse('Nilai kolom file_pdf bukan URL yang valid: ' . $pdfUrl . '. Pastikan menyimpan full URL atau gabungkan dengan base URL Dolibarr di kode API.', 400);
            }

            $pdfContent = @file_get_contents($pdfUrl);
            if (!$pdfContent) {
                return $this->errorResponse('Gagal mendownload file PDF dari URL: ' . $pdfUrl, 500);
            }
            $tempPath = sys_get_temp_dir() . '/stocktake_' . time() . '.pdf';
            file_put_contents($tempPath, $pdfContent);

            // 4. Upload File ke Server iLovePDF
            $uploadUrl = 'https://' . $server . '/v1/upload';
            $uploadResp = Http::withToken($token)
                ->attach('file', file_get_contents($tempPath), 'document.pdf')
                ->post($uploadUrl, [
                    'task' => $task
                ]);

            @unlink($tempPath);

            if (!$uploadResp->successful()) {
                return $this->errorResponse('Gagal mengunggah file ke iLovePDF: ' . $uploadResp->body(), 500);
            }
            $serverFilename = $uploadResp->json('server_filename');

            // 5. Create Signature Request
            $signers = [];
            
            if (!empty($schedule->counter_email)) {
                $signers[] = [
                    'name' => trim($schedule->counter_first . ' ' . $schedule->counter_last) ?: 'Counter',
                    'email' => $schedule->counter_email,
                    'signer_type' => 'signer',
                ];
            }
            
            if (!empty($schedule->verifikator_email)) {
                $signers[] = [
                    'name' => trim($schedule->verifikator_first . ' ' . $schedule->verifikator_last) ?: 'Verifikator',
                    'email' => $schedule->verifikator_email,
                    'signer_type' => 'signer',
                ];
            }

            if (empty($signers)) {
                return $this->errorResponse('Tidak ada data email penandatangan (Counter & Verifikator email kosong).', 400);
            }

            $signUrl = 'https://' . $server . '/v1/signature';
            $signResp = Http::withToken($token)->post($signUrl, [
                'task' => $task,
                'files' => [
                    [
                        'server_filename' => $serverFilename,
                        'filename' => 'document.pdf',
                        'elements' => [
                            'position' => 'top middle',
                            'pages' => 1,
                            'size' => 12

                        ]
                    ]
                ],
                'signers' => $signers,
                'mode' => 'email'
            ]);

            if (!$signResp->successful()) {
                return $this->errorResponse('Gagal membuat request signature iLovePDF: ' . $signResp->body(), 500);
            }

            return $this->successResponse([
                'ilovepdf_response' => $signResp->json(),
                'task_id' => $task
            ], 'Permintaan signature berhasil dibuat.');
            
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan sistem: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Integrasi iLovePDF untuk melakukan request Watermark.
     */
    public function requestWatermark(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $request->validate([
            'schedule_id' => 'required|integer',
            'fk_principal' => 'required|integer',
        ]);

        $scheduleId = $request->input('schedule_id');
        $fkPrincipal = $request->input('fk_principal');

        $schedule = DB::table('llxjp_userstocktake_detail as d')
            ->join('llxjp_userstocktake as m', 'm.rowid', '=', 'd.fk_stocktake')
            ->where('m.rowid', $scheduleId)
            ->where('d.fk_principal', $fkPrincipal)
            ->select('d.file_pdf')
            ->first();

        if (!$schedule || empty($schedule->file_pdf)) {
            return $this->errorResponse('Jadwal atau File PDF tidak ditemukan.', 404);
        }

        $publicKey = env('ILOVEPDF_PUBLIC_KEY');
        $secretKey = env('ILOVEPDF_SECRET_KEY');

        try {
            $authResp = Http::post('https://api.ilovepdf.com/v1/auth', ['public_key' => $publicKey]);
            if (!$authResp->successful()) return $this->errorResponse('Gagal autentikasi: ' . $authResp->body(), 500);
            $token = $authResp->json('token');

            $startResp = Http::withToken($token)->get('https://api.ilovepdf.com/v1/start/watermark');
            if (!$startResp->successful()) return $this->errorResponse('Gagal start watermark: ' . $startResp->body(), 500);
            $server = $startResp->json('server');
            $task = $startResp->json('task');

            $pdfUrl = $schedule->file_pdf; 
            if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                return $this->errorResponse('URL PDF tidak valid: ' . $pdfUrl, 400);
            }
            $pdfContent = @file_get_contents($pdfUrl);
            $tempPath = sys_get_temp_dir() . '/watermark_' . time() . '.pdf';
            file_put_contents($tempPath, $pdfContent);

            $uploadUrl = 'https://' . $server . '/v1/upload';
            $uploadResp = Http::withToken($token)->attach('file', file_get_contents($tempPath), 'document.pdf')->post($uploadUrl, ['task' => $task]);
            @unlink($tempPath);
            if (!$uploadResp->successful()) return $this->errorResponse('Upload gagal: ' . $uploadResp->body(), 500);
            $serverFilename = $uploadResp->json('server_filename');

            $processUrl = 'https://' . $server . '/v1/process';
            $processResp = Http::withToken($token)->post($processUrl, [
                'task' => $task,
                'tool' => 'watermark',
                'files' => [['server_filename' => $serverFilename, 'filename' => 'document.pdf']],
                'mode' => 'text',
                'text' => 'CONFIDENTIAL',
                'pages' => 'all',
                'vertical_position' => 'middle',
                'horizontal_position' => 'center',
                'rotation' => 45,
                'font_size' => 100,
                'font_style' => 'Bold',
                'transparency' => 10
            ]);

            if (!$processResp->successful()) return $this->errorResponse('Gagal proses watermark: ' . $processResp->body(), 500);

            $downloadUrl = 'https://' . $server . '/v1/download/' . $task;
            $downloadResp = Http::withToken($token)->get($downloadUrl);
            
            $resultFilename = 'watermarked_' . time() . '.pdf';
            file_put_contents(public_path($resultFilename), $downloadResp->body());
            
            return $this->successResponse([
                'watermarked_pdf_url' => url('/' . $resultFilename),
                'task_id' => $task
            ], 'Watermark berhasil dibuat.');

            
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan sistem: ' . $e->getMessage(), 500);
        }
    }
}
