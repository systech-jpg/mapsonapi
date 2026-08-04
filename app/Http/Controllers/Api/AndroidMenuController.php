<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AndroidMenu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AndroidMenuController extends Controller
{
    /**
     * Get menus based on the authenticated user's roles.
     */
    public function index(Request $request)
    {
        // Mendapatkan data user yang telah dicek oleh middleware
        $user = $request->attributes->get('dolibarr_user');

        // Menyusun daftar role user (termasuk Super Admin jika admin=1)
        $userRoles = $user->groups->pluck('nom')->toArray();
        if (isset($user->admin) && $user->admin == 1) {
            $userRoles[] = 'Super Admin';
        }

        // Ambil semua menu yang aktif
        $allMenus = AndroidMenu::where('is_active', true)
                               ->orderBy('order_index')
                               ->get();

        // Filter menu berdasarkan allowed_roles
        $filteredMenus = $allMenus->filter(function ($menu) use ($userRoles) {
            $allowedRoles = $menu->allowed_roles;

            // Jika allowed_roles kosong atau null, artinya menu bisa diakses oleh semua role
            if (empty($allowedRoles)) {
                return true;
            }

            // Jika user adalah Super Admin, beri akses ke semua menu (opsional, tapi disarankan)
            if (in_array('Super Admin', $userRoles)) {
                return true;
            }

            // Cek apakah ada irisan (intersection) antara role user dan role yang diizinkan menu
            $hasAccess = array_intersect($userRoles, $allowedRoles);
            
            return !empty($hasAccess);
        });

        // Ubah struktur menjadi nested (parent-child) jika diperlukan,
        // tapi untuk saat ini kembalikan dalam bentuk flat data yang sudah terurut.
        $mappedMenus = $filteredMenus->values()->map(function ($menu) {
            // Check if icon exists and is not already a full URL
            if ($menu->icon && !filter_var($menu->icon, FILTER_VALIDATE_URL)) {
                $menu->icon = asset('icons/' . $menu->icon);
            }
            return $menu;
        });

        return $this->successResponse(
            $mappedMenus->all(), 
            'Berhasil mengambil data menu Android'
        );
    }

    /**
     * Get fragment menus assigned to the authenticated user.
     */
    public function getFragmentMenus(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        // Query the fragment menus assigned to this user
        $menus = DB::table('llxjp_android_fragment_menus as m')
            ->join('llxjp_android_fragment_menu_user as mu', 'm.rowid', '=', 'mu.fk_menu')
            ->where('mu.fk_user', $user->rowid)
            ->where('m.is_active', 1)
            ->orderBy('m.order_index', 'asc')
            ->orderBy('m.rowid', 'asc')
            ->select('m.rowid as id', 'm.name', 'm.icon', 'm.color', 'm.route', 'm.parent_id', 'm.order_index')
            ->get();

        // Process icons to dynamically use the API's domain (e.g. ngrok)
        $mappedMenus = $menus->map(function ($menu) {
            if ($menu->icon) {
                // Ambil nama file dari URL mapsonerp.test
                $basename = basename($menu->icon);
                // Ubah menjadi URL mapsonapi (yang saat ini terekspos ngrok)
                $menu->icon = url('/api/android-icons/' . $basename);
            }
            return $menu;
        });

        // You might have a standardized response macro or method.
        // Assuming $this->successResponse is available from a Base Controller trait.
        if (method_exists($this, 'successResponse')) {
            return $this->successResponse($mappedMenus, 'Berhasil mengambil fragment menu');
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil fragment menu',
            'data' => $mappedMenus
        ]);
    }

    /**
     * Serve image icons from mapsonerp folder to bypass domain/ngrok issues
     *
     * Direktori sumbernya diatur lewat ANDROID_ICONS_PATH di .env karena
     * berbeda antara mesin development dan server.
     */
    public function serveIcon($filename)
    {
        $directory = rtrim((string) config('android.icons_path'), '/\\');
        $path = $directory . DIRECTORY_SEPARATOR . basename($filename);

        if (!file_exists($path)) {
            // Di-log supaya ikon yang hilang tidak cuma tampak sebagai gambar
            // kosong di Android, tanpa jejak apa pun di server.
            Log::warning('Ikon menu tidak ditemukan', [
                'file' => basename($filename),
                'directory' => $directory,
            ]);

            abort(404, 'Image not found');
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
