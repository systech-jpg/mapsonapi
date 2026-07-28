<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DolibarrUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate input
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find the user by login
        $user = DolibarrUser::with('groups')->where('login', $request->login)->first();

        if (!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        // The user confirmed they use bcrypt for passwords (typically stored in pass_crypted).
        // Check the provided password against the bcrypt hash.
        $isValidPassword = false;
        
        if (!empty($user->pass_crypted)) {
            $isValidPassword = Hash::check($request->password, $user->pass_crypted);
        } else if (!empty($user->pass_encoding)) {
             // Fallback if password hash is somehow in pass_encoding
            $isValidPassword = Hash::check($request->password, $user->pass_encoding);
        }

        if (!$isValidPassword) {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        // Ambil data role dari database
        $roles = $user->groups->pluck('nom')->toArray();
        
        // Jika user adalah admin (admin = 1 di Dolibarr), tambahkan role 'Super Admin'
        if (isset($user->admin) && $user->admin == 1) {
            $roles[] = 'Super Admin';
        }

        // Return user data and roles
        return $this->successResponse([
            'rowid' => $user->rowid,
            'login' => $user->login,
            'api_key' => $user->api_key ?? null,
            'roles' => $roles
        ], 'Login successful');
    }
}
