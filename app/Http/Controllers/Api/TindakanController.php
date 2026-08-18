<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\LogsDolibarrActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TindakanController extends Controller
{
    use LogsDolibarrActivity;

    /**
     * Helper mapping status tindakan ke string (label).
     */
    private function getStatusLabel($status)
    {
        switch ($status) {
            case 0: return 'Draft';
            case 1: return 'Confirmed (Need Delivery)';
            case 2: return 'In Delivery';
            case 3: return 'Delivered / Ready';
            case 4: return 'CLOSED / DONE';
            case 9: return 'Cancelled';
            default: return 'Unknown';
        }
    }

    /**
     * Label status Usage Report.
     *
     * Nilainya HARUS sama persis dengan badge di custom/tindakanmedis/usage.php,
     * supaya status yang dilihat TS di mobile identik dengan yang dilihat admin
     * di ERP. Perhatikan urutannya tidak berurutan: 4 disisipkan di antara 1 dan
     * 2, dan itu memang skema di database -- jangan "dirapikan".
     */
    private function getUsageStatusLabel($status)
    {
        switch ((int) $status) {
            case 0: return 'Draft';
            case 1: return 'Validated (Menunggu Tarik Barang)';
            case 4: return 'Barang Ditarik (Menunggu Accept)';
            case 2: return 'Accepted (Warehouse)';
            case 3: return 'Ordered (SO Created)';
            default: return 'Unknown';
        }
    }

    /**
     * Jumlah baris per halaman bila klien tidak mengirim per_page.
     */
    private const TINDAKAN_PER_PAGE = 50;

    /**
     * Batas atas per_page, supaya klien tidak bisa memaksa menarik satu bulan
     * penuh sekaligus lewat ?per_page=99999.
     */
    private const TINDAKAN_MAX_PER_PAGE = 100;

    /**
     * Menampilkan seluruh tindakan pada bulan berjalan (semua status), dipaginasi.
     *
     * Query param opsional:
     *   ?page=1        halaman ke berapa (default 1)
     *   ?per_page=50   baris per halaman (default 50, maksimum 100)
     */
    public function index(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Filter bulan berjalan berdasarkan tanggal pelaksanaan (bukan tanggal pembuatan).
        // Kolom t.tanggal bertipe DATE, jadi whereBetween dengan string tanggal sudah
        // presisi dan tetap bisa memakai index.
        $awalBulan  = Carbon::now()->startOfMonth()->toDateString();
        $akhirBulan = Carbon::now()->endOfMonth()->toDateString();

        // Input dari klien tidak dipercaya. Nilai non-numerik dikembalikan ke default
        // (bukan di-cast jadi 0, karena itu akan menyisakan 1 baris saja setelah dijepit),
        // lalu hasilnya dibatasi ke rentang wajar.
        $perPage = $request->query('per_page', self::TINDAKAN_PER_PAGE);
        $perPage = is_numeric($perPage) ? (int) $perPage : self::TINDAKAN_PER_PAGE;
        $perPage = max(1, min($perPage, self::TINDAKAN_MAX_PER_PAGE));

        $page = $request->query('page', 1);
        $page = is_numeric($page) ? (int) $page : 1;
        $page = max(1, $page);

        $paginator = DB::table('llxjp_tindakan as t')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 't.fk_soc')
            ->leftJoin('llxjp_c_doctor as d', 'd.rowid', '=', 't.dokter')
            // Status usage report ikut dibawa supaya daftar di mobile bisa
            // menampilkan tahapan yang sama dengan halaman usage di ERP.
            // Selama usage report belum dibuat, kolom ini null dan klien
            // menampilkan status tindakan seperti sebelumnya.
            ->leftJoin('llxjp_usage_report as ur', 'ur.fk_tindakan', '=', 't.id')
            ->select(
                't.id', 't.ref', 't.status', 't.tanggal',
                's.nom as rs_name', 'd.fullname as dokter_name',
                't.pasien', 't.ref_sj',
                'ur.status as usage_status'
            )
            ->whereBetween('t.tanggal', [$awalBulan, $akhirBulan])
            // Tanpa filter status: Draft s/d Cancelled semuanya ikut tampil.
            ->orderBy('t.tanggal', 'desc')
            ->orderBy('t.id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Overwrite nilai 'status' langsung dengan keterangannya
        $paginator->getCollection()->transform(function ($item) {
            $item->status = $this->getStatusLabel($item->status);

            $item->usage_status_label = is_null($item->usage_status)
                ? null
                : $this->getUsageStatusLabel($item->usage_status);

            return $item;
        });

        // PENTING: 'data' sengaja dikirim sebagai array datar (bukan objek paginator)
        // supaya klien lama yang mem-parse data sebagai list tidak rusak.
        // Info halaman dipisah ke 'meta'.
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
            'has_more'     => $paginator->hasMorePages(),
            'periode'      => [
                'start' => $awalBulan,
                'end'   => $akhirBulan,
            ],
        ];

        return $this->successResponse(
            array_values($paginator->items()),
            'Berhasil mengambil daftar tindakan bulan ini.',
            200,
            $meta
        );
    }

    /**
     * Mengambil daftar Rumah Sakit / Mitra dengan opsi pencarian (lookup).
     */
    public function getHospitals(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $search = $request->query('search', '');
        $limit = $request->query('limit', 50);
        
        $query = DB::table('llxjp_societe')
            ->select('rowid as id', 'name_alias as nom', 'code_client')
            ->where('client', 1)
            ->where(function($q) {
                $q->where('code_client', 'like', 'PH%')
                  ->orWhere('code_client', 'like', 'GV%');
            });

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name_alias', 'like', '%' . $search . '%')
                  ->orWhere('nom', 'like', '%' . $search . '%')
                  ->orWhere('code_client', 'like', '%' . $search . '%');
            });
        }

        $hospitals = $query->orderBy('nom', 'asc')->limit($limit)->get();

        // Format return menjadi label seperti di UI
        $hospitals->transform(function ($item) {
            $item->label = $item->nom . ($item->code_client ? ' (' . $item->code_client . ')' : '');
            return $item;
        });

        return $this->successResponse($hospitals, 'Berhasil mengambil daftar Rumah Sakit.');
    }

    /**
     * Mengambil daftar Dokter Operator dengan opsi pencarian (lookup).
     */
    public function getDoctors(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $search = $request->query('search', '');
        $limit = $request->query('limit', 50);
        
        $query = DB::table('llxjp_c_doctor')
            ->select('rowid as id', 'fullname as label');

        if (!empty($search)) {
            $query->where('fullname', 'like', '%' . $search . '%');
        }

        $doctors = $query->orderBy('fullname', 'asc')->limit($limit)->get();

        return $this->successResponse($doctors, 'Berhasil mengambil daftar Dokter Operator.');
    }

    /**
     * Mengambil daftar TS / PIC Lapangan dengan opsi pencarian (lookup).
     */
    public function getTechnicalSupports(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $search = $request->query('search', '');
        $limit = $request->query('limit', 50);
        
        $query = DB::table('llxjp_user')
            ->select('rowid as id', DB::raw("TRIM(CONCAT(firstname, ' ', lastname)) as label"))
            ->where('job', '1')
            ->where('statut', 1);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('firstname', 'like', '%' . $search . '%')
                  ->orWhere('lastname', 'like', '%' . $search . '%');
            });
        }

        $technicalSupports = $query->orderBy('firstname', 'asc')->limit($limit)->get();

        return $this->successResponse($technicalSupports, 'Berhasil mengambil daftar TS (PIC Lapangan).');
    }
    /**
     * Membuat jadwal tindakan operasi baru dari mobile (TS).
     */
    public function store(Request $request)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'waktu' => 'nullable|string',
            'fk_soc' => 'required|integer',
            'dokter' => 'required|integer',
            'jenis_tindakan' => 'nullable|string',
            'fk_ts' => 'required|integer',
            'pasien' => 'required|string',
            'pasien_dob' => 'nullable|date',
            'rencana_alat' => 'required|string', // Sesuai label di UI "Pesanan / Alat"
            'diagnosa' => 'nullable|string', // Sesuai label di UI "Catatan Lain"
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 400);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $tindakanId = DB::table('llxjp_tindakan')->insertGetId([
                'ref' => '(PROV)', // akan diupdate nanti
                'entity' => 1,
                'datec' => $this->dolibarrNow(),
                // PK llxjp_user adalah rowid. $user->id di sini menghasilkan
                // NULL diam-diam, sehingga jadwal buatan mobile tampil tanpa
                // pembuat di ERP (dan jatuh ke fallback "SuperAdmin").
                'fk_user_author' => $user->rowid,
                'status' => 0, // Draft
                'tanggal' => $validated['tanggal'],
                'waktu' => $validated['waktu'] ?? null,
                'fk_soc' => $validated['fk_soc'],
                'dokter' => $validated['dokter'],
                'pasien' => $validated['pasien'],
                'pasien_dob' => $validated['pasien_dob'] ?? null,
                'jenis_tindakan' => $validated['jenis_tindakan'] ?? null,
                'nama_ts' => $validated['fk_ts'],
                'rencana_alat' => $validated['rencana_alat'] ?? null,
                'diagnosa' => $validated['diagnosa'] ?? null,
            ]);

            // Update ref sesuai pattern
            DB::table('llxjp_tindakan')
                ->where('id', $tindakanId)
                ->update(['ref' => 'TDPROV' . $tindakanId]);

            DB::commit();

            $this->logTindakanActivity($tindakanId, 'CREATE', $user, 'Dibuat dari aplikasi mobile', 0);

            return $this->successResponse(['id' => $tindakanId], 'Berhasil membuat jadwal operasi.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Memperbarui data jadwal tindakan operasi (update).
     */
    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'waktu' => 'nullable|string',
            'fk_soc' => 'required|integer',
            'dokter' => 'required|integer',
            'jenis_tindakan' => 'nullable|string',
            'fk_ts' => 'required|integer',
            'pasien' => 'required|string',
            'pasien_dob' => 'nullable|date',
            'rencana_alat' => 'required|string',
            'diagnosa' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 400);
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $tindakan = DB::table('llxjp_tindakan')->where('id', $id)->first();
            if (!$tindakan) {
                return $this->errorResponse('Data jadwal tindakan tidak ditemukan.', 404);
            }

            DB::table('llxjp_tindakan')
                ->where('id', $id)
                ->update([
                    'tanggal' => $validated['tanggal'],
                    'waktu' => $validated['waktu'] ?? null,
                    'fk_soc' => $validated['fk_soc'],
                    'dokter' => $validated['dokter'],
                    'pasien' => $validated['pasien'],
                    'pasien_dob' => $validated['pasien_dob'] ?? null,
                    'jenis_tindakan' => $validated['jenis_tindakan'] ?? null,
                    'nama_ts' => $validated['fk_ts'],
                    'rencana_alat' => $validated['rencana_alat'] ?? null,
                    'diagnosa' => $validated['diagnosa'] ?? null,
                ]);

            DB::commit();

            $this->logTindakanActivity($id, 'UPDATE', $user, 'Diubah dari aplikasi mobile', $tindakan->status);

            return $this->successResponse(['id' => $id], 'Berhasil memperbarui jadwal operasi.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Memvalidasi jadwal tindakan operasi.
     * Mengubah status dari Draft (0) menjadi Validated (1) dan men-generate nomor referensi final.
     */
    public function validateTindakan(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        try {
            DB::beginTransaction();

            $tindakan = DB::table('llxjp_tindakan')->where('id', $id)->first();
            if (!$tindakan) {
                return $this->errorResponse('Data jadwal tindakan tidak ditemukan.', 404);
            }

            if ($tindakan->status != 0) {
                return $this->errorResponse('Hanya jadwal dengan status Draft yang bisa divalidasi.', 400);
            }

            $now = $this->dolibarrNow();
            // Format referensi sesuai dengan logic di class Tindakan: TD/ym/0000X
            $new_ref = 'TD/' . $now->format('ym') . '/' . sprintf('%05d', $id);

            DB::table('llxjp_tindakan')
                ->where('id', $id)
                ->update([
                    'status' => 1,
                    // rowid, bukan id — lihat catatan di store().
                    'fk_user_valid' => $user->rowid,
                    'datev' => $now,
                    'ref' => $new_ref,
                ]);

            DB::commit();

            $this->logTindakanActivity($id, 'VALIDATE', $user, 'Ref menjadi '.$new_ref, 1);

            return $this->successResponse([
                'id' => $id,
                'ref' => $new_ref,
                'status' => 'Confirmed (Need Delivery)' // label untuk status 1
            ], 'Berhasil memvalidasi jadwal operasi.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Menampilkan detail informasi tindakan beserta Paket Tray dan Set Implant.
     */
    public function show(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Ambil data header Tindakan
        $tindakan = DB::table('llxjp_tindakan as t')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 't.fk_soc')
            ->leftJoin('llxjp_c_doctor as d', 'd.rowid', '=', 't.dokter')
            ->leftJoin('llxjp_user as u', 'u.rowid', '=', 't.nama_ts')
            ->select('t.*', 's.nom as rs_name', 'd.fullname as dokter_name', 'u.firstname as ts_firstname', 'u.lastname as ts_lastname')
            ->where('t.id', $id)
            ->first();

        if (!$tindakan) {
            return $this->errorResponse('Data tindakan tidak ditemukan.', 404);
        }

        // Overwrite status dengan labelnya
        $tindakan->status = $this->getStatusLabel($tindakan->status);

        // Ambil Paket Tray
        $trayKits = DB::table('llxjp_tindakan_kit as k')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'k.fk_product')
            ->select('k.rowid as kit_id', 'k.qty', 'k.note', 'p.label', 'p.ref')
            ->where('k.fk_tindakan', $id)
            ->where('k.jenis', 'tray')
            ->get();

        foreach ($trayKits as $kit) {
            $kit->details = DB::table('llxjp_tindakan_kit_det as d')
                ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
                ->select('d.rowid as detail_id', 'd.qty', 'd.note', 'd.rang', 'p.label', 'p.ref')
                ->where('d.fk_kit', $kit->kit_id)
                ->orderBy('d.rang', 'asc')
                ->orderBy('d.rowid', 'asc')
                ->get();
        }

        // Ambil Set Implant
        $implantKits = DB::table('llxjp_tindakan_kit as k')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'k.fk_product')
            ->select('k.rowid as kit_id', 'k.qty', 'k.note', 'p.label', 'p.ref')
            ->where('k.fk_tindakan', $id)
            ->where('k.jenis', 'implant')
            ->get();

        foreach ($implantKits as $kit) {
            $kit->details = DB::table('llxjp_tindakan_kit_det as d')
                ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
                ->select('d.rowid as detail_id', 'd.qty', 'd.note', 'd.rang', 'p.label', 'p.ref')
                ->where('d.fk_kit', $kit->kit_id)
                ->orderBy('d.rang', 'asc')
                ->orderBy('d.rowid', 'asc')
                ->get();
        }

        // Gabungkan response
        $data = [
            'info' => $tindakan,
            'paket_tray' => $trayKits,
            'set_implant' => $implantKits
        ];

        return $this->successResponse($data, 'Berhasil mengambil detail tindakan.');
    }

    /**
     * Konfirmasi Barang Sampai (Ubah status dari 2 -> 3)
     */
    public function confirmArrival(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek apakah tindakan ada
        $tindakan = DB::table('llxjp_tindakan')->where('id', $id)->first();

        if (!$tindakan) {
            return $this->errorResponse('Data tindakan tidak ditemukan.', 404);
        }

        // Pastikan statusnya 2 (In Delivery) sebelum bisa dikonfirmasi sampai
        if ($tindakan->status != 2) {
            return $this->errorResponse('Tindakan ini tidak dalam status In Delivery.', 400);
        }

        // Update status menjadi 3 (Delivered / Ready).
        // fk_user_arrival / date_arrival ikut diisi supaya halaman Info di ERP
        // menampilkan siapa yang mengonfirmasi — set_delivered() di sisi web
        // mengisi kolom yang sama. Kedua kolom itu ditambahkan belakangan lewat
        // self-heal ALTER di ERP, jadi kalau instalasi belum punya kolomnya
        // konfirmasi tetap diteruskan tanpa atribusi, bukan gagal.
        try {
            DB::table('llxjp_tindakan')->where('id', $id)->update([
                'status' => 3,
                'fk_user_arrival' => $user->rowid,
                'date_arrival' => $this->dolibarrNow(),
            ]);
        } catch (\Exception $e) {
            DB::table('llxjp_tindakan')->where('id', $id)->update([
                'status' => 3
            ]);
        }

        $this->logTindakanActivity($id, 'ARRIVAL', $user, 'Dikonfirmasi dari aplikasi mobile', 3);

        return $this->successResponse(null, 'Barang dikonfirmasi sampai di RS (Ready).');
    }

    /**
     * Helper: Mendapatkan Usage Report, atau otomatis men-generate jika belum ada (Auto-Create).
     */
    private function getOrCreateUsageReport($tindakan, $user = null)
    {
        if (!empty($tindakan->fk_usage)) {
            $usage = DB::table('llxjp_usage_report')->where('rowid', $tindakan->fk_usage)->first();
            if ($usage) return $usage;
        }

        // Cek fallback (jika ada tapi fk_usage di tindakan belum terupdate)
        $usage = DB::table('llxjp_usage_report')->where('fk_tindakan', $tindakan->id)->first();
        if ($usage) {
            DB::table('llxjp_tindakan')->where('id', $tindakan->id)->update(['fk_usage' => $usage->rowid]);
            return $usage;
        }

        // GENERATE BARU
        // Cari nomor ref terakhir
        $prefix = 'PRMM/' . date('y/m') . '/';
        $lastUsage = DB::table('llxjp_usage_report')->where('ref', 'like', $prefix . '%')->orderBy('ref', 'desc')->first();
        $new_num = 1;
        if ($lastUsage) {
            $last_num = (int) substr($lastUsage->ref, -5);
            $new_num = $last_num + 1;
        }
        $new_ref = $prefix . sprintf("%05d", $new_num);

        // Buat record Usage Report
        $usageId = DB::table('llxjp_usage_report')->insertGetId([
            'ref' => $new_ref,
            'fk_tindakan' => $tindakan->id,
            'fk_soc' => $tindakan->fk_soc,
            'date_creation' => $this->dolibarrNow(),
            // rowid, bukan id — lihat catatan di store().
            'fk_user_author' => ($user && $user->rowid) ? $user->rowid : 1,
            'status' => 0
        ]);

        $this->logUsageActivity($usageId, 'CREATE', $user, 'Dibuat otomatis dari aplikasi mobile', 0);

        // Hitung ulang qty produk (gabungan implant & tray jika diperlukan, atau seluruh kit)
        $new_items = DB::table('llxjp_tindakan_kit_det as d')
            ->join('llxjp_tindakan_kit as k', 'k.rowid', '=', 'd.fk_kit')
            ->where('k.fk_tindakan', $tindakan->id)
            ->groupBy('d.fk_product')
            ->select('d.fk_product', DB::raw('SUM(d.qty) as total_qty'))
            ->get();

        foreach ($new_items as $item) {
            DB::table('llxjp_usage_report_det')->insert([
                'fk_usage_report' => $usageId,
                'fk_product' => $item->fk_product,
                'qty_sent' => $item->total_qty,
                'qty_used' => 0
            ]);
        }

        // Update fk_usage pada tindakan
        DB::table('llxjp_tindakan')->where('id', $tindakan->id)->update(['fk_usage' => $usageId]);

        return DB::table('llxjp_usage_report')->where('rowid', $usageId)->first();
    }

    /**
     * Mengambil kit beserta barisnya untuk Usage Report.
     *
     * Detail-nya sengaja diambil dari llxjp_usage_report_det, bukan dari
     * llxjp_tindakan_kit_det, supaya det_id yang dikirim ke client adalah id
     * baris usage report -- id yang sama yang dipakai saveUsageLines() untuk
     * mencari baris saat menyimpan qty_used.
     *
     * @param  int    $tindakanId
     * @param  string $jenis     'tray' atau 'implant'
     * @param  int    $usageId   rowid llxjp_usage_report
     */
    private function buildUsageKits($tindakanId, $jenis, $usageId)
    {
        $kits = DB::table('llxjp_tindakan_kit as k')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'k.fk_product')
            ->select('k.rowid as kit_id', 'k.qty', 'k.note', 'p.label', 'p.ref')
            ->where('k.fk_tindakan', $tindakanId)
            ->where('k.jenis', $jenis)
            ->get();

        foreach ($kits as $kit) {
            $kit->details = DB::table('llxjp_tindakan_kit_det as d')
                ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
                ->join('llxjp_usage_report_det as u', function ($join) use ($usageId) {
                    $join->on('u.fk_product', '=', 'd.fk_product')
                         ->where('u.fk_usage_report', '=', $usageId);
                })
                ->select(
                    'u.rowid as det_id',
                    'u.fk_product',
                    'u.qty_sent',
                    'u.qty_used',
                    DB::raw('(u.qty_sent - u.qty_used) as qty_return'),
                    'p.ref as product_ref',
                    'p.label as product_label'
                )
                ->where('d.fk_kit', $kit->kit_id)
                ->orderBy('d.rang', 'asc')
                ->orderBy('d.rowid', 'asc')
                ->get();
        }

        return $kits;
    }

    /**
     * Menampilkan data Usage Report (Pemakaian) berdasarkan ID Tindakan.
     */
    public function getUsage(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cari Tindakan
        $tindakan = DB::table('llxjp_tindakan')->where('id', $id)->first();
        if (!$tindakan) {
            return $this->errorResponse('Data Tindakan tidak ditemukan.', 404);
        }

        // Dapatkan atau Generate Usage Report
        $usage = $this->getOrCreateUsageReport($tindakan, $user);

        $usage = DB::table('llxjp_usage_report as u')
            ->leftJoin('llxjp_tindakan as t', 't.fk_usage', '=', 'u.rowid')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 'u.fk_soc')
            ->select('u.*', 't.ref as tindakan_ref', 's.nom as rs_name')
            ->where('u.rowid', $usage->rowid)
            ->first();

        $usage->status_label = $this->getUsageStatusLabel($usage->status);

        // Klien perlu tahu buktinya sudah ada atau belum tanpa menembak
        // endpoint gambarnya lebih dulu; null berarti belum diunggah.
        $usage->bukti_tarik = $this->berkasBuktiTarik($tindakan->ref)
            ? 'tindakan/usage/' . (int) $id . '/bukti-tarik'
            : null;

        // Paket Tray IKUT ditampilkan. getOrCreateUsageReport() menyalin produk
        // dari SELURUH kit tanpa memfilter jenis, jadi baris tray memang ada di
        // llxjp_usage_report_det dan tampil di halaman ERP. Sebelumnya bagian ini
        // dikosongkan paksa, sehingga mobile tidak pernah menerima det_id milik
        // baris tray -- akibatnya Simpan Draft mengirim id dari tabel lain dan
        // tidak ada satu baris pun yang terupdate.
        $trayKits = $this->buildUsageKits($id, 'tray', $usage->rowid);
        $implantKits = $this->buildUsageKits($id, 'implant', $usage->rowid);

        $data = [
            'info' => $usage,
            'paket_tray' => $trayKits,
            'set_implant' => $implantKits
        ];

        return $this->successResponse($data, 'Berhasil mengambil data Usage Report.');
    }

    /**
     * Mencari baris Usage Report yang ditunjuk satu item payload.
     *
     * Identifier yang diterima sengaja lebih dari satu (det_id, fk_product,
     * product_id) karena versi klien yang beredar tidak seragam. Dipakai
     * bersama oleh pemeriksaan batas qty dan proses simpannya, supaya keduanya
     * tidak mungkin menunjuk baris yang berbeda.
     *
     * @return \Illuminate\Database\Query\Builder|null null bila payload tidak
     *         membawa satu pun identifier yang dikenali.
     */
    private function cariBarisUsage($usageId, array $line)
    {
        $query = DB::table('llxjp_usage_report_det as d')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
            ->where('d.fk_usage_report', $usageId);

        if (!empty($line['det_id'])) {
            return $query->where('d.rowid', $line['det_id']);
        }

        if (!empty($line['fk_product'])) {
            return $query->where('d.fk_product', $line['fk_product']);
        }

        if (!empty($line['product_id'])) {
            return $query->where('d.fk_product', $line['product_id']);
        }

        return null;
    }

    /**
     * Menyimpan (Save Draft) Qty Terpakai (Used) pada Usage Report.
     */
    public function saveUsageLines(Request $request, $tindakan_id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Coba cari sebagai tindakan_id dulu
        $tindakan = DB::table('llxjp_tindakan')->where('id', $tindakan_id)->first();
        
        // Jika tidak ketemu, mungkin mobile mengirim usage_report.rowid ke parameter ini
        if (!$tindakan) {
            $usageCheck = DB::table('llxjp_usage_report')->where('rowid', $tindakan_id)->first();
            if ($usageCheck && $usageCheck->fk_tindakan) {
                $tindakan = DB::table('llxjp_tindakan')->where('id', $usageCheck->fk_tindakan)->first();
            }
        }

        if (!$tindakan) {
            return $this->errorResponse('Data Tindakan tidak ditemukan.', 404);
        }

        $usage = $this->getOrCreateUsageReport($tindakan, $user);

        if ($usage->status != 0) {
            return $this->errorResponse('Gagal menyimpan: Usage Report tidak dalam status Draft.', 400);
        }

        // Handle format payload dari mobile (bisa {lines: [...]} atau langsung [...])
        $lines = $request->input('lines');
        if (empty($lines) && is_array($request->all()) && isset($request->all()[0])) {
            $lines = $request->all(); // Fallback jika mobile kirim array mentah
        }

        if (empty($lines) || !is_array($lines)) {
            return $this->errorResponse('Format data lines tidak valid atau kosong.', 400);
        }

        // Batas qty diperiksa lebih dulu, sebelum satu baris pun ditulis.
        // Tanpa ini qty_used boleh melebihi qty_sent, dan kolom Qty Kembali di
        // halaman usage ERP maupun di surat jalan menjadi negatif -- dokumennya
        // seolah mengembalikan barang yang tidak pernah dikirim.
        // Diperiksa semuanya dulu, baru ditolak sekaligus, supaya petugas tidak
        // membetulkan satu baris lalu ditolak lagi karena baris berikutnya.
        $ditolak = [];

        foreach ($lines as $line) {
            $qty_used = $line['qty_used'] ?? $line['qty'] ?? $line['used'] ?? null;
            if ($qty_used === null || $qty_used === '') continue;

            $target = $this->cariBarisUsage($usage->rowid, $line);
            if (!$target) continue; // identifier tidak dikenali; ditangani di bawah

            foreach ($target->select('d.qty_sent', 'p.ref')->get() as $row) {
                if ((int) $qty_used < 0 || (int) $qty_used > (int) $row->qty_sent) {
                    $ditolak[] = ($row->ref ?: 'Produk')
                        . ' (dikirim ' . (int) $row->qty_sent . ', diisi ' . (int) $qty_used . ')';
                }
            }
        }

        if (!empty($ditolak)) {
            return $this->errorResponse(
                'Qty terpakai tidak boleh melebihi qty dikirim: ' . implode('; ', $ditolak) . '.',
                422
            );
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            foreach ($lines as $line) {
                $qty_used = $line['qty_used'] ?? $line['qty'] ?? $line['used'] ?? null;
                if ($qty_used === null || $qty_used === '') continue; // Skip if no qty provided

                $query = DB::table('llxjp_usage_report_det')
                    ->where('fk_usage_report', $usage->rowid);

                if (!empty($line['det_id'])) {
                    $query->where('rowid', $line['det_id']);
                } elseif (!empty($line['fk_product'])) {
                    $query->where('fk_product', $line['fk_product']);
                } elseif (!empty($line['product_id'])) {
                    $query->where('fk_product', $line['product_id']);
                } else {
                    continue; // No identifier
                }

                $affected = $query->update([
                    'qty_used' => (int) $qty_used
                ]);
                
                if ($affected) $updatedCount++;
            }
            // Tidak ada satu baris pun yang cocok berarti identifier yang dikirim
            // client tidak dikenali di usage report ini. Dulu kondisi ini tetap
            // dijawab sukses, sehingga user melihat "berhasil disimpan" padahal
            // tidak ada yang tersimpan dan baru ketahuan dari halaman ERP.
            if ($updatedCount === 0) {
                DB::rollBack();
                return $this->errorResponse(
                    'Tidak ada baris yang cocok di Usage Report ini. Pastikan det_id atau product_id yang dikirim benar.',
                    422
                );
            }

            DB::commit();

            $this->logUsageActivity(
                $usage->rowid,
                'SAVE_LINES',
                $user,
                $updatedCount.' baris diperbarui',
                0
            );

            return $this->successResponse(['updated' => $updatedCount], 'Data pemakaian (Qty Used) berhasil disimpan sebagai Draft.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat menyimpan data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Memvalidasi Usage Report (Status menjadi 1 - Validated).
     */
    public function validateUsage(Request $request, $tindakan_id)
    {
        $user = $request->attributes->get('dolibarr_user');
        
        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek Tindakan
        $tindakan = DB::table('llxjp_tindakan')->where('id', $tindakan_id)->first();
        if (!$tindakan) {
            return $this->errorResponse('Data Tindakan tidak ditemukan.', 404);
        }

        $usage = $this->getOrCreateUsageReport($tindakan, $user);

        if ($usage->status != 0) {
            return $this->errorResponse('Gagal validasi: Usage Report harus dalam status Draft.', 400);
        }

        DB::beginTransaction();
        try {
            DB::table('llxjp_usage_report')
                ->where('rowid', $usage->rowid)
                ->update([
                    'status' => 1,
                    'fk_user_valid' => $user->rowid,
                    'datev' => $this->dolibarrNow()
                ]);

            DB::commit();

            $this->logUsageActivity($usage->rowid, 'VALIDATE', $user, 'Divalidasi dari aplikasi mobile', 1);

            return $this->successResponse([
                'ref' => $usage->ref,
                'status' => 1
            ], 'Data Laporan Pemakaian berhasil divalidasi (Final).');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat validasi data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Awalan nama berkas bukti untuk tahap tarik barang.
     *
     * Nilainya HARUS sama dengan prefix di custom/tindakanmedis/usage.php,
     * karena halaman ERP mencari buktinya dengan glob TARIK_* — bukan dengan
     * membaca kolom atau baris log.
     */
    private const BUKTI_TARIK_PREFIX = 'TARIK';

    /**
     * Salinan dol_sanitizeFileName() Dolibarr seperlunya: karakter terlarang
     * diganti '_'. Wajib sama hasilnya, karena nama folder bukti dibentuk dari
     * Ref Tindakan di kedua sisi ("TD/2608/00572" -> "TD_2608_00572"). Kalau
     * beda satu karakter saja, ERP dan mobile menulis ke folder yang berlainan.
     */
    private function sanitasiNamaBerkas($nama)
    {
        $terlarang = ['<', '>', '/', '\\', '?', '*', '|', '"', ':', '°', '$', ';', '`'];

        $hasil = str_replace($terlarang, '_', (string) $nama);
        $hasil = preg_replace('/\-\-+/', '_', $hasil);

        return str_replace('..', '', $hasil);
    }

    /**
     * Folder bukti satu Tindakan di dalam dokumen Dolibarr.
     *
     * @return string|null null bila ERP_DOC_ROOT belum diisi — sengaja tidak
     *                     jatuh ke folder lain, karena bukti yang tersimpan di
     *                     tempat yang tidak dibaca ERP sama saja hilang.
     */
    private function direktoriBukti($tindakanRef)
    {
        $akar = config('services.erp.doc_root');

        if (blank($akar) || blank($tindakanRef)) {
            return null;
        }

        return rtrim($akar, "/\\") . '/' . $this->sanitasiNamaBerkas($tindakanRef);
    }

    /**
     * Berkas bukti terbaru untuk satu tahap. Nama berkas memuat timestamp,
     * jadi urutan nama sama dengan urutan waktu — persis alasan yang ditulis
     * di tm_proof_files().
     */
    private function berkasBuktiTarik($tindakanRef)
    {
        $dir = $this->direktoriBukti($tindakanRef);

        if (!$dir || !is_dir($dir)) {
            return null;
        }

        $berkas = glob($dir . '/' . self::BUKTI_TARIK_PREFIX . '_*');

        if (!is_array($berkas) || empty($berkas)) {
            return null;
        }

        rsort($berkas);

        return $berkas[0];
    }

    /**
     * Tautan document.php ke satu berkas bukti, sama bentuknya dengan yang
     * dibuat UsageReport::tarikBarang() di ERP supaya baris log dari mobile
     * dan dari web terlihat identik di tab Log.
     */
    private function tautanBukti($tindakanRef, $namaBerkas)
    {
        $berkas = $this->sanitasiNamaBerkas($tindakanRef) . '/' . $namaBerkas;

        return rtrim((string) config('services.erp.url_root'), '/')
            . '/document.php?modulepart=tindakanmedis&file=' . urlencode($berkas);
    }

    /**
     * Tarik Barang Usage Report (Status 1 -> 4).
     *
     * Wajib menyertakan foto bukti (field `bukti`), mengikuti form Tarik Barang
     * di halaman usage ERP. Requestnya harus multipart/form-data.
     */
    public function tarikBarang(Request $request, $tindakan_id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek Tindakan
        $tindakan = DB::table('llxjp_tindakan')->where('id', $tindakan_id)->first();
        if (!$tindakan) {
            return $this->errorResponse('Data Tindakan tidak ditemukan.', 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            // Daftar ekstensi mengikuti tm_proof_allowed_ext() di ERP, dikurangi
            // heic/pdf yang tidak bisa ditampilkan langsung sebagai <img> di web.
            // 8 MB: foto kamera ponsel masa kini bisa lewat 4 MB, tapi di atas
            // itu hampir pasti salah unggah.
            'bukti' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:8192',
        ], [
            'bukti.required' => 'Foto bukti tarik barang wajib disertakan.',
            'bukti.image' => 'Bukti tarik barang harus berupa foto.',
            'bukti.max' => 'Ukuran foto maksimal 8 MB.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $dir = $this->direktoriBukti($tindakan->ref);

        if (!$dir) {
            return $this->errorResponse(
                'Folder dokumen ERP belum dikonfigurasi (ERP_DOC_ROOT). '
                . 'Hubungi administrator: tanpa itu bukti foto tidak akan terbaca di ERP.',
                500
            );
        }

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return $this->errorResponse('Folder bukti di server tidak bisa dibuat. Periksa izin tulis.', 500);
        }

        $usage = $this->getOrCreateUsageReport($tindakan, $user);

        if ($usage->status != 1) {
            return $this->errorResponse('Gagal Tarik Barang: Usage Report harus dalam status Validated.', 400);
        }

        $berkas = $request->file('bukti');
        $ekstensi = strtolower($berkas->getClientOriginalExtension() ?: $berkas->extension());

        // Waktu server, sama dengan date('Ymd_His') di ERP. Urutan nama berkas
        // inilah cara ERP menentukan bukti mana yang terbaru, jadi kedua sumber
        // harus memakai zona yang sama.
        $namaBaru = self::BUKTI_TARIK_PREFIX . '_' . Carbon::now()->format('Ymd_His') . '.' . $ekstensi;

        $lama = $this->berkasBuktiTarik($tindakan->ref);

        // Berkas baru ditulis lebih dulu, yang lama dibuang setelahnya —
        // urutan yang sama dengan tm_store_proof(), supaya kegagalan di tengah
        // jalan tidak menyisakan dokumen tanpa bukti sama sekali.
        //
        // move() melempar FileException saat gagal (bukan mengembalikan false),
        // dan tanpa tangkapan ini kegagalan izin tulis muncul sebagai 500 mentah
        // dengan jejak stack, bukan kalimat yang bisa dipahami petugas.
        try {
            $berkas->move($dir, $namaBaru);
        } catch (\Throwable $e) {
            return $this->errorResponse(
                'Foto bukti gagal disimpan ke folder dokumen ERP. Periksa izin tulis folder.',
                500
            );
        }

        if ($lama && basename($lama) !== $namaBaru && is_file($lama)) {
            @unlink($lama);
        }

        DB::beginTransaction();
        try {
            DB::table('llxjp_usage_report')
                ->where('rowid', $usage->rowid)
                ->update([
                    'status' => 4, // 4 = Tarik Barang
                    'fk_user_tarik' => $user->rowid,
                    'date_tarik' => $this->dolibarrNow()
                ]);

            DB::commit();

            // Catatan log memuat tautan HTML yang sama dengan buatan ERP, jadi
            // tombol "Lihat Bukti" di tab Log bekerja untuk kedua sumber.
            $this->logUsageActivity(
                $usage->rowid,
                'TARIK_BARANG',
                $user,
                '<a href="' . $this->tautanBukti($tindakan->ref, $namaBaru) . '" target="_blank" '
                    . 'class="badge badge-info" style="color:#fff;">Lihat Bukti</a>',
                4
            );

            return $this->successResponse([
                'ref' => $usage->ref,
                'status' => 4,
                'bukti_nama' => $namaBaru,
                // Path relatif terhadap root API, tanpa host -- host dari request
                // salah di belakang tunnel/proxy. Pola yang sama dipakai lampiran
                // chat; setiap klien menyusunnya dari base URL-nya sendiri.
                'bukti_tarik' => 'tindakan/usage/' . (int) $tindakan_id . '/bukti-tarik',
            ], 'Barang berhasil ditarik (Tarik Barang).');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan saat proses Tarik Barang: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Menampilkan foto bukti tarik barang.
     *
     * Berkasnya ada di folder dokumen Dolibarr yang tidak bisa diakses browser
     * langsung, jadi disajikan lewat endpoint ini yang sudah melewati
     * middleware dolibarr.auth.
     */
    public function buktiTarik(Request $request, $tindakan_id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        $tindakan = DB::table('llxjp_tindakan')->where('id', $tindakan_id)->first();
        if (!$tindakan) {
            return $this->errorResponse('Data Tindakan tidak ditemukan.', 404);
        }

        $berkas = $this->berkasBuktiTarik($tindakan->ref);

        if (!$berkas) {
            return $this->errorResponse('Bukti tarik barang belum diunggah.', 404);
        }

        return response()->file($berkas);
    }

    /**
     * Download PDF Surat Jalan (Hanya jika Usage Report sudah divalidasi)
     */
    public function downloadSuratJalan(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (!$user) {
            return $this->errorResponse('User tidak terautentikasi.', 401);
        }

        // Cek apakah Usage Report sudah divalidasi (bukan Draft / status > 0)
        $usage = DB::table('llxjp_usage_report')->where('fk_tindakan', $id)->orderBy('rowid', 'desc')->first();
        if (!$usage || $usage->status == 0) {
            return $this->errorResponse('Surat Jalan hanya bisa didownload jika Laporan Pemakaian (Usage Report) sudah divalidasi (Final).', 403);
        }

        // Ambil data header Tindakan
        $tindakan = DB::table('llxjp_tindakan as t')
            ->leftJoin('llxjp_societe as s', 's.rowid', '=', 't.fk_soc')
            ->leftJoin('llxjp_c_doctor as d', 'd.rowid', '=', 't.dokter')
            ->select('t.*', 's.nom as rs_name', 's.name_alias', 's.address as alamat', 'd.fullname as dokter_name')
            ->where('t.id', $id)
            ->first();

        if (!$tindakan) {
            return $this->errorResponse('Data tindakan tidak ditemukan.', 404);
        }

        // Pastikan Surat Jalan sudah ter-create (status >= 2)
        if ($tindakan->status < 2) {
            return $this->errorResponse('Surat Jalan belum diterbitkan (Status belum In Delivery).', 400);
        }

        // Ambil Paket Tray
        $paket_tray = DB::table('llxjp_tindakan_kit as k')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'k.fk_product')
            ->select('k.rowid as kit_id', 'k.qty', 'k.note', 'p.label', 'p.ref')
            ->where('k.fk_tindakan', $id)
            ->where('k.jenis', 'tray')
            ->get();

        // Ambil Set Implant (Beserta Detailnya dan Qty Used)
        $set_implant = DB::table('llxjp_tindakan_kit_det as d')
            ->leftJoin('llxjp_product as p', 'p.rowid', '=', 'd.fk_product')
            ->leftJoin('llxjp_tindakan_kit as k', 'k.rowid', '=', 'd.fk_kit')
            ->leftJoin('llxjp_product_extrafields as pe', 'pe.fk_object', '=', 'p.rowid')
            ->leftJoin('llxjp_usage_report_det as u', function($join) use ($usage) {
                 $join->on('u.fk_product', '=', 'd.fk_product')
                      ->where('u.fk_usage_report', '=', $usage->rowid);
            })
            ->select('d.qty', 'd.note', 'p.label', 'p.ref', 'p.rowid as prodid', 'pe.noakl', 'u.qty_used')
            ->where('k.fk_tindakan', $id)
            ->where('k.jenis', 'implant')
            ->orderBy('k.rowid', 'asc')
            ->orderBy('d.rang', 'asc')
            ->orderBy('d.rowid', 'asc')
            ->get();

        // Hitung Grand Total
        $grand_total = 0;
        foreach ($paket_tray as $item) $grand_total += $item->qty;
        foreach ($set_implant as $item) $grand_total += $item->qty;

        $data = [
            'info' => $tindakan,
            'paket_tray' => $paket_tray,
            'set_implant' => $set_implant,
            'grand_total' => $grand_total
        ];

        // Generate PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat_jalan', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Surat-Jalan-' . str_replace('/', '-', $tindakan->ref_sj) . '.pdf';

        // Dicatat sejajar dengan tombol Cetak SJ di ERP (print_sj.php): dokumen
        // ini yang dibawa ke RS, jadi siapa yang mengunduh dan kapan itu penting
        // saat ada selisih barang.
        $this->logTindakanActivity($id, 'PRINT_SJ', $user, $tindakan->ref_sj, $tindakan->status);

        return $pdf->download($filename);
    }
}
