<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
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
     * Pilihan nama sales pada form SPH.
     *
     * Di ERP daftar ini ditulis langsung di custom/sph/card.php (dua <option>
     * yang di-hardcode), bukan diambil dari tabel user — kolom sales_name
     * memang menyimpan teks, bukan id. Disalin apa adanya ke sini supaya nilai
     * yang tersimpan dari mobile sama persis dengan yang dari web; kalau
     * daftarnya berubah di ERP, ubah juga di sini.
     */
    private const SALES_OPTIONS = [
        'Kristina Ribka',
        'Nur Ahmad Zaynudin',
    ];

    /**
     * Batas hasil pencarian pelanggan. Tabel societe berisi ratusan baris dan
     * pemilihnya di ponsel tidak berguna kalau semuanya diturunkan sekaligus.
     */
    private const CUSTOMER_LIMIT = 30;

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
     * GET /api/sph/form-options
     *
     * Isi tiga pemilih di form SPH Baru sekaligus: nomor referensi berikutnya,
     * daftar principal, dan daftar nama sales. Dikirim dalam satu panggilan
     * karena ketiganya selalu dibutuhkan bersamaan saat form dibuka.
     */
    public function formOptions(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Sumbernya sama persis dengan dropdown Principal di card.php: societe
        // yang benar-benar dipakai sebagai principal pada extrafield produk.
        // JOIN + GROUP BY, bukan seluruh societe, supaya daftarnya tidak
        // dipenuhi perusahaan yang tidak pernah jadi principal.
        $principals = DB::table('llxjp_societe as s')
            ->join('llxjp_product_extrafields as pe', 's.rowid', '=', 'pe.principal')
            ->groupBy('s.rowid', 's.nom')
            ->orderBy('s.nom', 'asc')
            ->select('s.rowid', 's.nom')
            ->get();

        return $this->successResponse([
            'next_ref'   => $this->nextRef(),
            'principals' => $principals,
            'sales'      => self::SALES_OPTIONS,
        ], 'Berhasil mengambil pilihan form SPH.');
    }

    /**
     * GET /api/sph/customers?search=
     *
     * Pencarian pelanggan untuk isian Pelanggan. Di ERP tempat ini diisi
     * select_company() yang menurunkan seluruh societe sekaligus; di ponsel itu
     * tidak masuk akal, jadi di sini dicari per kata kunci dan dibatasi.
     */
    public function customers(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = DB::table('llxjp_societe')
            ->where('entity', 1)
            ->select('rowid', 'nom', 'town', 'client');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', '%'.$search.'%')
                  ->orWhere('name_alias', 'like', '%'.$search.'%')
                  ->orWhere('town', 'like', '%'.$search.'%');
            });
        }

        return $this->successResponse(
            $query->orderBy('nom', 'asc')->limit(self::CUSTOMER_LIMIT)->get(),
            'Berhasil mengambil daftar pelanggan.'
        );
    }

    /**
     * POST /api/sph
     *
     * Membuat SPH baru berstatus Draft — padanan action 'add' di
     * custom/sph/card.php. Baris barang belum ikut di sini, sama seperti di
     * ERP: dokumen dibuat dulu, barangnya ditambahkan di halaman berikutnya.
     */
    public function store(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'fk_soc'        => 'required|integer|min:1',
            'fk_principal'  => 'nullable|integer|min:1',
            'ref_quotation' => 'nullable|string|max:50',
            'sales_name'    => 'nullable|string|in:'.implode(',', self::SALES_OPTIONS),
            'date_sph'      => 'required|date',
            'date_valid'    => 'nullable|date|after_or_equal:date_sph',
            'note'          => 'nullable|string',
        ], [
            'fk_soc.required'   => 'Pelanggan wajib dipilih.',
            'date_sph.required' => 'Tanggal SPH wajib diisi.',
            'date_valid.after_or_equal' => 'Tanggal Valid until tidak boleh mendahului Tanggal SPH.',
            'sales_name.in'     => 'Nama sales tidak dikenal.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // Pelanggan dan principal diperiksa benar-benar ada. Tanpa ini, fk_soc
        // salah ketik tersimpan diam-diam dan dokumennya tampil tanpa nama
        // pelanggan di ERP.
        $customer = DB::table('llxjp_societe')->where('rowid', $request->input('fk_soc'))->first();

        if (!$customer) {
            return $this->errorResponse('Pelanggan tidak ditemukan.', 404);
        }

        $principalId = $request->input('fk_principal');

        if ($principalId && !DB::table('llxjp_societe')->where('rowid', $principalId)->exists()) {
            return $this->errorResponse('Principal tidak ditemukan.', 404);
        }

        try {
            DB::beginTransaction();

            // Nomor dihitung di dalam transaksi, sedekat mungkin dengan
            // INSERT-nya. Sama seperti getNextNumRef() di ERP, dua pembuatan
            // yang benar-benar bersamaan masih bisa berebut nomor — itu risiko
            // yang sudah ada di web dan tidak diperbesar di sini.
            $ref = $this->nextRef();

            // Waktu Dolibarr adalah waktu SERVER, bukan UTC (lihat catatan di
            // LogsDolibarrActivity). Tanggal dari klien dipatok ke awal hari,
            // sama dengan dol_mktime(0,0,0,...) yang dipakai form ERP.
            $dateSph = Carbon::parse($request->input('date_sph'))->startOfDay();
            $dateValid = $request->filled('date_valid')
                ? Carbon::parse($request->input('date_valid'))->startOfDay()
                : null;

            $id = DB::table('llxjp_sph')->insertGetId([
                'ref'            => $ref,
                'ref_quotation'  => $request->input('ref_quotation'),
                'fk_soc'         => (int) $request->input('fk_soc'),
                'fk_principal'   => $principalId ? (int) $principalId : null,
                // Kolom customer diisi nama pelanggan apa adanya, mengikuti
                // kolom yang sama di skema ERP: dokumen lama menampilkannya
                // walau societe-nya sudah berganti nama.
                'customer'       => $customer->nom,
                'sales_name'     => $request->input('sales_name'),
                'fk_user_author' => (int) $user->rowid,
                'date_sph'       => $dateSph,
                'date_valid'     => $dateValid,
                'note'           => $request->input('note'),
                'total_ht'       => 0,
                'total_ttc'      => 0,
                'status'         => 0,
                'entity'         => 1,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Gagal menyimpan SPH: '.$e->getMessage(), 500);
        }

        return $this->successResponse([
            'id'           => $id,
            'ref'          => $ref,
            'status'       => 0,
            'status_label' => $this->getStatusLabel(0),
        ], 'SPH berhasil dibuat sebagai Draft.', 201);
    }

    /**
     * PUT /api/sph/{id}
     *
     * Ubah header SPH — padanan tombol MODIFY (action 'update') di card.php.
     * Hanya dokumen Draft: begitu divalidasi, nomornya sudah terbit dan isinya
     * tidak boleh berubah diam-diam.
     */
    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = DB::table('llxjp_sph')->where('rowid', $id)->first();

        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        if ((int) $sph->status !== 0) {
            return $this->errorResponse('SPH yang sudah divalidasi tidak bisa diubah. Buka kembali (reopen) dulu.', 400);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'fk_soc'        => 'required|integer|min:1',
            'fk_principal'  => 'nullable|integer|min:1',
            'ref_quotation' => 'nullable|string|max:50',
            'sales_name'    => 'nullable|string|in:'.implode(',', self::SALES_OPTIONS),
            'date_sph'      => 'required|date',
            'date_valid'    => 'nullable|date|after_or_equal:date_sph',
            'note'          => 'nullable|string',
        ], [
            'fk_soc.required'   => 'Pelanggan wajib dipilih.',
            'date_sph.required' => 'Tanggal SPH wajib diisi.',
            'date_valid.after_or_equal' => 'Tanggal Valid until tidak boleh mendahului Tanggal SPH.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $customer = DB::table('llxjp_societe')->where('rowid', $request->input('fk_soc'))->first();

        if (!$customer) {
            return $this->errorResponse('Pelanggan tidak ditemukan.', 404);
        }

        DB::table('llxjp_sph')->where('rowid', $id)->update([
            'ref_quotation' => $request->input('ref_quotation'),
            'fk_soc'        => (int) $request->input('fk_soc'),
            'fk_principal'  => $request->filled('fk_principal') ? (int) $request->input('fk_principal') : null,
            'customer'      => $customer->nom,
            'sales_name'    => $request->input('sales_name'),
            'date_sph'      => Carbon::parse($request->input('date_sph'))->startOfDay(),
            'date_valid'    => $request->filled('date_valid')
                ? Carbon::parse($request->input('date_valid'))->startOfDay()
                : null,
            'note'          => $request->input('note'),
        ]);

        return $this->successResponse(['id' => (int) $id], 'SPH berhasil diperbarui.');
    }

    /**
     * GET /api/sph/products?search=
     *
     * Pemilih produk untuk baris SPH. Harga dan PPN ikut dikirim supaya klien
     * bisa mengisi kolomnya otomatis — di ERP dua nilai itu diambil terpisah
     * lewat ajax/get_product_details.php, di sini digabung agar tidak ada
     * perjalanan kedua ke server hanya untuk mengisi satu kolom.
     */
    public function products(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $search = trim((string) $request->query('search', ''));

        $query = DB::table('llxjp_product')
            ->where('entity', 1)
            ->where('tosell', 1)
            ->select('rowid', 'ref', 'label', 'description', 'price', 'tva_tx');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', '%'.$search.'%')
                  ->orWhere('label', 'like', '%'.$search.'%');
            });
        }

        $produk = $query->orderBy('ref', 'asc')->limit(self::CUSTOMER_LIMIT)->get();

        $produk->transform(function ($p) {
            // Deskripsi master sering berisi HTML. Dibersihkan di sini, sama
            // seperti html_entity_decode() di ajax/get_product_details.php,
            // supaya yang masuk ke kolom deskripsi baris berupa teks biasa.
            $p->description = trim(html_entity_decode(
                strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $p->description)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ));

            return $p;
        });

        return $this->successResponse($produk, 'Berhasil mengambil daftar produk.');
    }

    /**
     * POST /api/sph/{id}/lines
     *
     * Tambah satu baris barang — padanan action 'addline' di card.php.
     */
    public function storeLine(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = DB::table('llxjp_sph')->where('rowid', $id)->first();

        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        // Sama dengan ERP: form tambah baris hanya digambar saat status 0.
        if ((int) $sph->status !== 0) {
            return $this->errorResponse('Baris hanya bisa ditambah saat SPH masih Draft.', 400);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'fk_product'       => 'nullable|integer|min:1',
            'description'      => 'nullable|string',
            'qty'              => 'required|numeric|gt:0',
            'subprice'         => 'required|numeric|min:0',
            'tva_tx'           => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'qty.required' => 'Qty wajib diisi.',
            'qty.gt'       => 'Qty harus lebih besar dari nol.',
            'subprice.required' => 'Unit price wajib diisi.',
            'discount_percent.max' => 'Diskon tidak boleh lebih dari 100%.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $produk = null;

        if ($request->filled('fk_product')) {
            $produk = DB::table('llxjp_product')->where('rowid', $request->input('fk_product'))->first();

            if (!$produk) {
                return $this->errorResponse('Produk tidak ditemukan.', 404);
            }
        }

        $qty = (float) $request->input('qty');
        $price = (float) $request->input('subprice');
        $diskon = (float) $request->input('discount_percent', 0);
        $tva = (float) $request->input('tva_tx', 0);

        // Rumusnya disalin dari Sph::add_line(); kalau berbeda sedikit saja,
        // Total HT di mobile dan di ERP tidak akan pernah cocok.
        $totalHt = $qty * $price * (1 - ($diskon / 100));
        $totalTtc = $totalHt * (1 + ($tva / 100));

        $deskripsi = trim((string) $request->input('description'));

        // Deskripsi kosong diisi label produk, mengikuti kebiasaan ERP yang
        // menampilkan nama produk saat kolom deskripsinya kosong.
        if ($deskripsi === '' && $produk) {
            $deskripsi = (string) $produk->label;
        }

        try {
            DB::beginTransaction();

            // Baris baru selalu paling bawah, sama seperti add_line().
            $position = (int) (DB::table('llxjp_sphdet')->where('fk_sph', $id)->max('position') ?? 0) + 1;

            $lineId = DB::table('llxjp_sphdet')->insertGetId([
                'fk_sph'           => (int) $id,
                'fk_product'       => $produk ? (int) $produk->rowid : null,
                'description'      => $deskripsi,
                'qty'              => $qty,
                'subprice'         => $price,
                'discount_percent' => $diskon,
                'tva_tx'           => $tva,
                'total_ht'         => $totalHt,
                'total_ttc'        => $totalTtc,
                'position'         => $position,
                'entity'           => 1,
            ]);

            $this->refreshTotals($id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Gagal menambah baris: '.$e->getMessage(), 500);
        }

        return $this->successResponse([
            'line_id'   => $lineId,
            'total_ht'  => $totalHt,
            'total_ttc' => $totalTtc,
        ], 'Baris berhasil ditambahkan.', 201);
    }

    /**
     * DELETE /api/sph/{id}/lines/{lineId}
     *
     * Hapus satu baris — padanan action 'deleteline' di card.php.
     */
    public function destroyLine(Request $request, $id, $lineId)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = DB::table('llxjp_sph')->where('rowid', $id)->first();

        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        if ((int) $sph->status !== 0) {
            return $this->errorResponse('Baris hanya bisa dihapus saat SPH masih Draft.', 400);
        }

        // fk_sph ikut disyaratkan, bukan cuma rowid baris: tanpa itu satu id
        // baris milik dokumen lain bisa terhapus lewat URL yang dikarang.
        $terhapus = DB::table('llxjp_sphdet')
            ->where('rowid', $lineId)
            ->where('fk_sph', $id)
            ->delete();

        if (!$terhapus) {
            return $this->errorResponse('Baris tidak ditemukan pada SPH ini.', 404);
        }

        $this->refreshTotals($id);

        return $this->successResponse(['id' => (int) $id], 'Baris berhasil dihapus.');
    }

    /**
     * POST /api/sph/{id}/validate
     *
     * Menerbitkan nomor resmi: awalan DRAFT- dibuang dan status menjadi 1.
     * Persis Sph::validate() di ERP.
     */
    public function validateDocument(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = DB::table('llxjp_sph')->where('rowid', $id)->first();

        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        if ((int) $sph->status !== 0) {
            return $this->errorResponse('SPH ini sudah divalidasi.', 400);
        }

        // Dokumen tanpa barang tidak punya isi untuk ditawarkan. ERP tidak
        // menjaganya, tapi hasilnya PDF kosong bernomor resmi — dan nomor yang
        // sudah terbit tidak bisa ditarik kembali.
        if (DB::table('llxjp_sphdet')->where('fk_sph', $id)->count() === 0) {
            return $this->errorResponse('SPH belum punya baris barang. Tambahkan dulu sebelum divalidasi.', 400);
        }

        $refBaru = str_starts_with((string) $sph->ref, 'DRAFT-')
            ? substr((string) $sph->ref, strlen('DRAFT-'))
            : (string) $sph->ref;

        DB::table('llxjp_sph')->where('rowid', $id)->update([
            'ref'    => $refBaru,
            'status' => 1,
        ]);

        return $this->successResponse([
            'id'           => (int) $id,
            'ref'          => $refBaru,
            'status'       => 1,
            'status_label' => $this->getStatusLabel(1),
        ], 'SPH berhasil divalidasi.');
    }

    /**
     * POST /api/sph/{id}/reopen
     *
     * Kembalikan ke Draft. Nomornya sengaja TIDAK dikembalikan ke DRAFT-...,
     * mengikuti Sph::reopen() di ERP yang juga membiarkannya — nomor yang sudah
     * dipakai di surat penawaran tidak pantas berubah lagi.
     */
    public function reopen(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $sph = DB::table('llxjp_sph')->where('rowid', $id)->first();

        if (!$sph) {
            return $this->errorResponse('Data SPH tidak ditemukan.', 404);
        }

        if ((int) $sph->status !== 1) {
            return $this->errorResponse('Hanya SPH yang sudah divalidasi yang bisa dibuka kembali.', 400);
        }

        DB::table('llxjp_sph')->where('rowid', $id)->update(['status' => 0]);

        return $this->successResponse([
            'id'           => (int) $id,
            'status'       => 0,
            'status_label' => $this->getStatusLabel(0),
        ], 'SPH dibuka kembali sebagai Draft.');
    }

    /**
     * Hitung ulang total header dari baris-barisnya, salinan
     * Sph::update_totals().
     *
     * total_ttc baris yang bernilai 0 dihitung ulang dari total_ht + PPN:
     * baris buatan versi ERP lama tidak pernah mengisi kolom itu, dan
     * menjumlahkannya apa adanya membuat total dokumen jadi 0.
     */
    private function refreshTotals($id)
    {
        $total = DB::table('llxjp_sphdet')
            ->where('fk_sph', $id)
            ->selectRaw('COALESCE(SUM(total_ht), 0) AS tot_ht')
            ->selectRaw('COALESCE(SUM(CASE WHEN total_ttc > 0 THEN total_ttc ELSE total_ht * (1 + COALESCE(tva_tx, 0) / 100) END), 0) AS tot_ttc')
            ->first();

        DB::table('llxjp_sph')->where('rowid', $id)->update([
            'total_ht'  => $total->tot_ht ?? 0,
            'total_ttc' => $total->tot_ttc ?? 0,
        ]);
    }

    /**
     * Nomor SPH berikutnya, disalin dari Sph::getNextNumRef() di ERP.
     *
     * Yang dicari angka urutan tertinggi bulan ini tanpa memedulikan prefiks
     * ('%/yymm/%'), karena satu bulan bisa berisi campuran DRAFT-SPH/... dan
     * SPH/... — dokumen yang sudah divalidasi kehilangan awalan DRAFT-. Memakai
     * MAX(ref) seperti versi lama membuat nomor terulang begitu ada yang
     * divalidasi.
     */
    private function nextRef()
    {
        $yymm = Carbon::now()->format('ym');

        $max = DB::table('llxjp_sph')
            ->where('ref', 'like', '%/'.$yymm.'/%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(ref, '/', -1) AS UNSIGNED)) as max_num")
            ->value('max_num');

        return 'DRAFT-SPH/'.$yymm.'/'.sprintf('%05d', ((int) $max) + 1);
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
