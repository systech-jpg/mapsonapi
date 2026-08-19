<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AndroidMenuController;
use App\Http\Controllers\Api\StocktakeController;
use App\Http\Controllers\Api\TindakanController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\BeamsController;
use App\Http\Controllers\Api\ForecastController;
use App\Http\Controllers\Api\SphController;
use App\Http\Controllers\Api\ProductController;


Route::post('/login', [AuthController::class, 'login']);

Route::get('/test-tindakan', function() {
    return response()->json(['success' => true, 'message' => 'API is updating!']);
});

Route::get('/test-columns', function() {
    $tables = DB::select("SHOW TABLES");
    $list = [];
    foreach ($tables as $t) {
        $list[] = array_values((array)$t)[0];
    }
    return response()->json($list);
});

Route::get('/test-schema', function() {
    $columns = DB::select("SHOW COLUMNS FROM llxjp_tindakan");
    return response()->json($columns);
});

// Endpoint untuk mengambil icon (publik, bisa dipanggil Glide tanpa header auth)
Route::get('/android-icons/{filename}', [AndroidMenuController::class, 'serveIcon']);

// Rute yang butuh API Key
Route::middleware('dolibarr.auth')->group(function () {
    Route::get('/menus', [AndroidMenuController::class, 'index']);
    Route::get('/fragment-menus', [AndroidMenuController::class, 'getFragmentMenus']);
    /*
    | Rute Stocktake (stock opname).
    |
    | Keempat rute literal di bawah adalah bentuk LAMA yang dipakai APK Android
    | yang sudah beredar. Letaknya harus tetap di atas /stocktake/{id}, mengikuti
    | aturan yang sama seperti di blok SPH.
    |
    | Dua rute lama yang dihapus: /stocktake/signature dan /stocktake/watermark.
    | Keduanya memanggil layanan berbayar iLovePDF untuk menandatangani berkas
    | yang alamatnya diambil dari kolom file_pdf milik llxjp_userstocktake_detail
    | — tabel yang kosong dan tidak ada satu pun berkas ERP yang mengisinya.
    */
    Route::get('/stocktake/products', [StocktakeController::class, 'getProducts']);
    Route::post('/stocktake/update', [StocktakeController::class, 'updateProducts']);
    Route::post('/stocktake/scan', [StocktakeController::class, 'scanProduct']);
    Route::get('/stocktake/history', [StocktakeController::class, 'getHistory']);

    Route::get('/stocktake', [StocktakeController::class, 'index']);
    Route::get('/stocktake/{id}', [StocktakeController::class, 'show'])->whereNumber('id');
    Route::get('/stocktake/{id}/baris', [StocktakeController::class, 'lines'])->whereNumber('id');
    Route::post('/stocktake/{id}/baris', [StocktakeController::class, 'saveLines'])->whereNumber('id');

    // Rute Lookup
    Route::get('/hospitals', [TindakanController::class, 'getHospitals']);
    Route::get('/doctors', [TindakanController::class, 'getDoctors']);
    Route::get('/technical-supports', [TindakanController::class, 'getTechnicalSupports']);

    // Rute Tindakan
    Route::post('/tindakan', [TindakanController::class, 'store']);
    Route::put('/tindakan/{id}', [TindakanController::class, 'update']);
    Route::post('/tindakan/{id}/validate', [TindakanController::class, 'validateTindakan']);
    Route::get('/tindakan', [TindakanController::class, 'index']);
    Route::get('/tindakan/{id}', [TindakanController::class, 'show']);
    Route::post('/tindakan/{id}/confirm-arrival', [TindakanController::class, 'confirmArrival']);
    /*
    | Bukti pickup (barang diambil kurir). Multipart, field `bukti`.
    | Hanya menyimpan foto dan mencatat log — status dokumen tidak berubah,
    | sama seperti action do_pickup di halaman prepare ERP.
    */
    Route::post('/tindakan/{id}/pickup', [TindakanController::class, 'pickup']);
    Route::get('/tindakan/{id}/bukti-pickup', [TindakanController::class, 'buktiPickup']);
    Route::get('/tindakan/{id}/bukti-arrive', [TindakanController::class, 'buktiArrive']);
    Route::get('/tindakan/{id}/surat-jalan', [TindakanController::class, 'downloadSuratJalan']);
    
    // Rute Usage Report (Pemakaian)
    Route::get('/tindakan/{id}/usage', [TindakanController::class, 'getUsage']);
    Route::post('/tindakan/usage/{usage_id}/save-lines', [TindakanController::class, 'saveUsageLines']);
    Route::post('/tindakan/usage/{usage_id}/validate', [TindakanController::class, 'validateUsage']);
    Route::post('/tindakan/usage/{usage_id}/tarik-barang', [TindakanController::class, 'tarikBarang']);
    // Foto bukti tarik barang. Disimpan di storage/app, bukan public/, jadi
    // hanya bisa dibaca lewat route ini yang ikut middleware dolibarr.auth.
    Route::get('/tindakan/usage/{usage_id}/bukti-tarik', [TindakanController::class, 'buktiTarik']);
    /*
    | Serah terima dokumen. Foto saja, status usage report tidak berubah —
    | yang berganti hanya labelnya di ERP, dan label itu ditentukan dari ada
    | tidaknya berkas DOK_TERIMA.
    */
    Route::post('/tindakan/usage/{usage_id}/dokumen-terima', [TindakanController::class, 'dokumenTerima']);
    Route::get('/tindakan/usage/{usage_id}/bukti-dokumen', [TindakanController::class, 'buktiDokumen']);

    // Rute Sales Orders
    Route::get('/sales-orders', [SalesOrderController::class, 'index']);
    Route::get('/sales-orders/{id}', [SalesOrderController::class, 'show']);

    // Autentikasi perangkat ke Pusher Beams (notifikasi push)
    Route::get('/beams-auth', [BeamsController::class, 'auth']);

    // Rute Chat & Inbox
    Route::get('/chat/inbox', [ChatController::class, 'getInbox']);
    Route::get('/chat/users', [ChatController::class, 'getUsers']);
    Route::get('/chat/messages/{user_id}', [ChatController::class, 'getMessages']);
    Route::post('/chat/messages', [ChatController::class, 'sendMessage']);
    Route::post('/chat/messages/{sender_id}/read', [ChatController::class, 'markAsRead']);
    Route::get('/chat/download/{filename}', [ChatController::class, 'downloadAttachment']);

    // Rute Group Chat
    Route::post('/chat/groups', [ChatController::class, 'createGroup']);
    Route::post('/chat/groups/{group_id}/members', [ChatController::class, 'addGroupMembers']);
    Route::get('/chat/groups/{group_id}/messages', [ChatController::class, 'getGroupMessages']);
    Route::post('/chat/groups/{group_id}/read', [ChatController::class, 'markGroupAsRead']);

    // Rute Forecast
    Route::get('/forecast/principals', [ForecastController::class, 'getPrincipals']);
    Route::post('/forecast', [ForecastController::class, 'store']);
    Route::get('/forecast/{id}/products', [ForecastController::class, 'getProducts']);
    Route::get('/forecast/{id}/search-products', [ForecastController::class, 'searchProducts']);
    Route::post('/forecast/{id}/add-product', [ForecastController::class, 'addProduct']);
    Route::post('/forecast/{id}/save', [ForecastController::class, 'save']);

    // Rute SPH (Surat Penawaran Harga)
    //
    // Dua rute berikut HARUS berada di atas /sph/{id}: tanpa itu 'form-options'
    // dan 'customers' ditangkap lebih dulu sebagai id. whereNumber di bawah
    // sebenarnya sudah menutup celah itu, tapi urutannya dipertahankan supaya
    // penambahan rute literal berikutnya tidak perlu mengingat aturan ini.
    Route::get('/sph/form-options', [SphController::class, 'formOptions']);
    Route::get('/sph/customers', [SphController::class, 'customers']);
    Route::get('/sph/products', [SphController::class, 'products']);

    Route::get('/sph', [SphController::class, 'index']);
    Route::post('/sph', [SphController::class, 'store']);
    Route::get('/sph/{id}', [SphController::class, 'show'])->whereNumber('id');
    Route::put('/sph/{id}', [SphController::class, 'update'])->whereNumber('id');
    Route::get('/sph/{id}/pdf', [SphController::class, 'downloadPdf'])->whereNumber('id');

    // Baris barang dan perpindahan status, padanan tombol di custom/sph/card.php
    Route::post('/sph/{id}/lines', [SphController::class, 'storeLine'])->whereNumber('id');
    Route::delete('/sph/{id}/lines/{lineId}', [SphController::class, 'destroyLine'])
        ->whereNumber('id')->whereNumber('lineId');
    Route::post('/sph/{id}/validate', [SphController::class, 'validateDocument'])->whereNumber('id');
    Route::post('/sph/{id}/reopen', [SphController::class, 'reopen'])->whereNumber('id');

    // Rute Scan Produk (Android)
    Route::post('/products/scan', [ProductController::class, 'scan']);
});

// TEST ROUTE
Route::get('/test-stock', [ProductController::class, 'scan']);
