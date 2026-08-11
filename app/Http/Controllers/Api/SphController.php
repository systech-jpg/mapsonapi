<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SphController extends Controller
{
    /**
     * Jumlah baris per halaman bila klien tidak mengirim per_page.
     */
    private const SPH_PER_PAGE = 25;

    /**
     * Batas atas per_page, supaya klien tidak bisa menarik seluruh tabel
     * sekaligus lewat ?per_page=99999.
     */
    private const SPH_MAX_PER_PAGE = 100;

    /**
     * Label status SPH.
     *
     * Harus sama persis dengan badge di custom/sph/card.php, supaya status yang
     * dilihat sales di mobile identik dengan yang dilihat admin di ERP.
     */
    private function getStatusLabel($status)
    {
        switch ((int) $status) {
            case 0: return 'Draft';
            case 1: return 'Validated';
            default: return 'Unknown';
        }
    }

    /**
     * GET /api/sph
     *
     * Daftar SPH, terbaru dulu, dipaginasi.
     *
     * Query param opsional:
     *   ?page=1        halaman ke berapa (default 1)
     *   ?per_page=25   baris per halaman (default 25, maksimum 100)
     *   ?search=       cari di nomor SPH, nomor quotation, atau nama pelanggan
     *   ?status=0|1    saring per status
     */
    public function index(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Input klien tidak dipercaya. Nilai non-numerik dikembalikan ke default
        // (bukan di-cast jadi 0, karena itu menyisakan 1 baris setelah dijepit).
        $perPage = $request->query('per_page', self::SPH_PER_PAGE);
        $perPage = is_numeric($perPage) ? (int) $perPage : self::SPH_PER_PAGE;
        $perPage = max(1, min($perPage, self::SPH_MAX_PER_PAGE));

        $page = $request->query('page', 1);
        $page = is_numeric($page) ? (int) $page : 1;
        $page = max(1, $page);

        try {
            $query = DB::table('llxjp_sph as t')
                ->leftJoin('llxjp_societe as s', 's.rowid', '=', 't.fk_soc')
                ->leftJoin('llxjp_societe as p', 'p.rowid', '=', 't.fk_principal')
                ->select(
                    't.rowid',
                    't.ref',
                    't.ref_quotation',
                    't.fk_soc',
                    's.nom as customer_name',
                    't.fk_principal',
                    'p.nom as principal_name',
                    't.sales_name',
                    't.date_sph',
                    't.date_valid',
                    't.total_ht',
                    't.total_ttc',
                    't.status'
                )
                ->where('t.entity', 1);

            $search = trim((string) $request->query('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('t.ref', 'like', '%'.$search.'%')
                      ->orWhere('t.ref_quotation', 'like', '%'.$search.'%')
                      ->orWhere('s.nom', 'like', '%'.$search.'%');
                });
            }

            // is_numeric, bukan !empty: status=0 (Draft) adalah filter yang sah
            // dan akan hilang kalau diperiksa dengan empty().
            $status = $request->query('status');
            if (is_numeric($status)) {
                $query->where('t.status', (int) $status);
            }

            $paginator = $query->orderBy('t.rowid', 'desc')
                               ->paginate($perPage, ['*'], 'page', $page);

            $paginator->getCollection()->transform(function ($item) {
                $item->status_label = $this->getStatusLabel($item->status);
                return $item;
            });

            // 'data' sengaja dikirim sebagai array datar (bukan objek paginator)
            // supaya konsisten dengan endpoint tindakan; info halaman di 'meta'.
            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
                'has_more'     => $paginator->hasMorePages(),
            ];

            return $this->successResponse(
                array_values($paginator->items()),
                'Berhasil mengambil daftar SPH.',
                200,
                $meta
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Terjadi kesalahan saat mengambil data SPH: '.$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/sph/{id}
     *
     * Detail SPH beserta baris barangnya.
     */
    public function show(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = $this->fetchHeader($id);
        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        return $this->successResponse([
            'info'  => $sph,
            'lines' => $this->fetchLines($id),
        ], 'Berhasil mengambil detail SPH.');
    }

    /**
     * GET /api/sph/{id}/pdf
     *
     * Unduh SPH sebagai PDF.
     *
     * Dokumennya dibangun ulang di sini, bukan mengambil file yang dihasilkan
     * ERP: file itu tersimpan di disk server Dolibarr yang belum tentu satu
     * mesin dengan API, dan hanya ada kalau seseorang pernah menekan CETAK PDF
     * di web. Membangun sendiri membuat mobile tidak bergantung pada itu.
     */
    public function downloadPdf(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = $this->fetchHeader($id);
        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        $lines = $this->fetchLines($id);

        // Total dihitung dari baris, bukan dari kolom header: header bisa basi
        // untuk dokumen lama yang dibuat sebelum update_totals() ada di ERP.
        $total_ht  = 0;
        $total_ttc = 0;
        foreach ($lines as $line) {
            $total_ht  += (float) $line->total_ht;
            $total_ttc += (float) $line->total_ttc;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.sph', [
            'info'      => $sph,
            'lines'     => $lines,
            'total_ht'  => $total_ht,
            'total_ttc' => $total_ttc,
            'logo'      => $this->getLogoDataUri(),
        ]);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'SPH-'.str_replace('/', '-', (string) $sph->ref).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Logo perusahaan sebagai data URI.
     *
     * Di-embed, bukan dirujuk lewat URL: DomPDF harus bisa mengambil gambarnya
     * saat render, dan file logo ada di direktori dokumen Dolibarr yang tidak
     * dilayani lewat HTTP publik.
     *
     * Pencocokan nama file tidak peduli huruf besar-kecil karena konstanta
     * Dolibarr ("LOGO BLACK.png") berbeda kapitalisasi dari file di disk
     * ("Logo Black.png") -- aman di Windows, gagal di server Linux.
     *
     * @return string '' bila logo tidak ditemukan
     */
    private function getLogoDataUri()
    {
        $dir = config('dolibarr.documents_path');
        if (empty($dir)) return '';

        // Daftar karakter yang dipangkas: "/" dan "\" (path Windows maupun Linux).
        $dir = rtrim($dir, "/\\").'/mycompany/logos';
        if (!is_dir($dir)) return '';

        $name = DB::table('llxjp_const')->where('name', 'MAIN_INFO_SOCIETE_LOGO')->value('value');
        if (empty($name)) return '';

        $path = '';
        if (is_file($dir.'/'.$name)) {
            $path = $dir.'/'.$name;
        } else {
            $target = strtolower($name);
            foreach (scandir($dir) as $f) {
                if (strtolower($f) === $target && is_file($dir.'/'.$f)) {
                    $path = $dir.'/'.$f;
                    break;
                }
            }
        }

        if ($path === '') return '';

        $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    /** Header SPH lengkap dengan nama pelanggan, principal, dan pembuat. */
    private function fetchHeader($id)
    {
        $sph = DB::table('llxjp_sph as t')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 't.fk_soc')
            ->leftJoin('llxjp_societe as p', 'p.rowid', '=', 't.fk_principal')
            ->leftJoin('llxjp_user as u', 'u.rowid', '=', 't.fk_user_author')
            ->select(
                't.rowid',
                't.ref',
                't.ref_quotation',
                't.fk_soc',
                's.nom as customer_name',
                's.address as customer_address',
                't.fk_principal',
                'p.nom as principal_name',
                't.sales_name',
                't.date_sph',
                't.date_valid',
                't.note',
                't.total_ht',
                't.total_ttc',
                't.status',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,''))) as author_name")
            )
            ->where('t.rowid', $id)
            ->first();

        if ($sph) {
            $sph->status_label = $this->getStatusLabel($sph->status);
        }

        return $sph;
    }

    /** Baris barang SPH, urut sesuai posisi di dokumen. */
    private function fetchLines($id)
    {
        // d.description, BUKAN p.description: deskripsi penawaran disimpan per
        // baris SPH dan sering berbeda dari deskripsi master produk.
        $lines = DB::table('llxjp_sphdet as d')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
            ->select(
                'd.rowid',
                'd.fk_product',
                'p.ref as product_ref',
                'p.label as product_label',
                'd.description',
                'd.qty',
                'd.subprice',
                'd.discount_percent',
                'd.tva_tx',
                'd.total_ht',
                'd.total_ttc'
            )
            ->where('d.fk_sph', $id)
            ->orderBy('d.position', 'asc')
            ->orderBy('d.rowid', 'asc')
            ->get();

        $lines->transform(function ($line) {
            // Deskripsi boleh kosong; label produk dipakai sebagai cadangan
            // supaya klien tidak perlu menangani baris tanpa teks.
            if (trim((string) $line->description) === '') {
                $line->description = $line->product_label;
            }

            // Baris yang dibuat sebelum add_line() di ERP diperbaiki punya
            // total_ttc = 0 karena kolomnya memang tidak pernah diisi. Dihitung
            // ulang di sini supaya total di mobile tidak lebih kecil dari ERP.
            if ((float) $line->total_ttc <= 0) {
                $line->total_ttc = (float) $line->total_ht * (1 + ((float) $line->tva_tx / 100));
            }

            return $line;
        });

        return $lines;
    }
}
