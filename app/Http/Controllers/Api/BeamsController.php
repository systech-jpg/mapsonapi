<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BeamsNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BeamsController extends Controller
{
    /**
     * Endpoint yang dipanggil SDK Beams di perangkat untuk membuktikan bahwa
     * perangkat itu memang milik user tertentu.
     *
     * User ID-nya diambil dari API key pada header, bukan dari parameter
     * request, supaya perangkat tidak bisa meminta token atas nama orang lain.
     */
    public function auth(Request $request, BeamsNotifier $beams)
    {
        $currentUser = $request->attributes->get('dolibarr_user');
        if (!$currentUser) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$beams->isConfigured()) {
            return response()->json(['message' => 'Beams belum dikonfigurasi di server.'], 503);
        }

        Log::info('Beams auth', [
            'user_id' => $currentUser->rowid,
            'login' => $currentUser->login ?? null,
            'agent' => substr((string) $request->userAgent(), 0, 120),
        ]);

        // SDK client mengharapkan bentuk {"token": "..."} apa adanya,
        // jadi jangan dibungkus successResponse().
        return response()->json($beams->generateToken((string) $currentUser->rowid));
    }
}
