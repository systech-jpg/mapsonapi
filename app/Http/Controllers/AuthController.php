<?php

namespace App\Http\Controllers;

use App\Support\Api;
use Illuminate\Http\Request;

/**
 * Login untuk frontend web/PWA.
 *
 * Memakai endpoint /api/login yang sama persis dengan aplikasi Android.
 * api_key hasil login disimpan di session server, tidak pernah dikirim ke
 * JavaScript, sehingga tidak bisa dicuri lewat XSS.
 *
 * Catatan: controller API di App\Http\Controllers\Api\AuthController tidak
 * diubah sama sekali.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('api_token')) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Endpoint API memakai nama field 'login', bukan 'username'.
        $response = Api::client()->post('/login', [
            'login' => $credentials['username'],
            'password' => $credentials['password'],
        ]);

        // 404 (user tidak ada) dan 401 (password salah) sengaja disamakan
        // pesannya, supaya tidak bisa dipakai menebak username yang valid.
        if ($response->failed()) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $user = $response->json('data') ?? [];
        $token = $user['api_key'] ?? null;

        // Login bisa lolos walau api_key kosong. Tanpa key, semua request
        // berikutnya pasti ditolak middleware dolibarr.auth, jadi hentikan
        // di sini dengan pesan yang jelas.
        if (blank($token)) {
            return back()
                ->withErrors(['username' => 'Akun ini belum memiliki API key di Dolibarr. Hubungi administrator.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put([
            'api_token' => $token,
            'api_user' => $user,
        ]);

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        // Tidak ada endpoint /api/logout: api_key Dolibarr bersifat statis dan
        // tidak dicabut per sesi. Cukup buang session di sisi web.
        $request->session()->flush();
        $request->session()->regenerateToken();

        // Perintahkan browser membuang cache origin ini, supaya halaman
        // dashboard yang sempat tersimpan tidak bisa dimunculkan lagi lewat
        // tombol Back. Sengaja hanya "cache": menambahkan "storage" akan ikut
        // menghapus service worker dan cache offline PWA.
        // Header ini hanya berlaku di HTTPS; di HTTP diabaikan browser.
        return redirect()->route('login')
            ->header('Clear-Site-Data', '"cache"');
    }
}
