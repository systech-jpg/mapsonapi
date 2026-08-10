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
    Route::get('/stocktake/products', [StocktakeController::class, 'getProducts']);
    Route::post('/stocktake/update', [StocktakeController::class, 'updateProducts']);
    Route::post('/stocktake/scan', [StocktakeController::class, 'scanProduct']);
    Route::get('/stocktake/history', [StocktakeController::class, 'getHistory']);
    Route::post('/stocktake/signature', [StocktakeController::class, 'requestSignature']);
    Route::post('/stocktake/watermark', [StocktakeController::class, 'requestWatermark']);

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
    Route::get('/tindakan/{id}/surat-jalan', [TindakanController::class, 'downloadSuratJalan']);
    
    // Rute Usage Report (Pemakaian)
    Route::get('/tindakan/{id}/usage', [TindakanController::class, 'getUsage']);
    Route::post('/tindakan/usage/{usage_id}/save-lines', [TindakanController::class, 'saveUsageLines']);
    Route::post('/tindakan/usage/{usage_id}/validate', [TindakanController::class, 'validateUsage']);
    Route::post('/tindakan/usage/{usage_id}/tarik-barang', [TindakanController::class, 'tarikBarang']);

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
});
