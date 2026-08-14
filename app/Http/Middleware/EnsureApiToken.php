<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengganti middleware `auth` untuk frontend web/PWA.
 *
 * Frontend ini tidak memakai guard `web`, model User lokal, maupun tabel
 * `users`. Satu-satunya penanda "sudah masuk" adalah adanya api_key Dolibarr
 * di session server.
 */
class EnsureApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('api_token')) {
            // Simpan tujuan awal supaya redirect()->intended() bekerja setelah login.
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        $response = $next($request);

        // Tanpa ini, tombol Back setelah logout masih menampilkan salinan halaman
        // dari cache browser (dan bfcache) walau session sudah dibuang.
        // 'no-store' mematikan keduanya sekaligus.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
