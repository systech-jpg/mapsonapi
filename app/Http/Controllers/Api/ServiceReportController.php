<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Service Report / Maintenance Alat — padanan formulir kertas
 * 007/01/TKS-MAP/F-03.
 *
 * Alurnya dua sisi:
 *  1. Pelapor membuka alamat statis (dari kode QR yang dicetak di luar
 *     aplikasi ini) lalu mengisi formulir TANPA login → storePublic().
 *  2. Tim teknis masuk dengan akunnya, membuka menu Service Report, lalu
 *     menindaklanjuti → index(), show(), update(), pdf().
 *
 * Tidak ada modul ERP-nya; tabelnya `service_reports` milik Laravel.
 */
class ServiceReportController extends Controller
{
    private const PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /** Urutan sama dengan kotak centang di kepala formulir kertas. */
    public const JENIS = [
        'installation' => 'Installation',
        'service' => 'Service',
        'pm' => 'PM',
        'training' => 'Training',
    ];

    public const STATUS = [
        0 => 'Baru',
        1 => 'Diproses',
        2 => 'Selesai',
    ];

    /**
     * Pesan galat validasi. Bahasa aplikasi ini masih 'en', sedangkan
     * formulir publik dibaca pelapor dari luar perusahaan — pesan bawaan
     * Laravel ("The ... field is required.") tidak boleh sampai ke mereka.
     * Dipakai juga oleh App\Livewire\ServiceReport\FormPublik.
     */
    public const PESAN = [
        'required' => ':Attribute wajib diisi.',
        'min' => ':Attribute wajib diisi.',
        'email' => ':Attribute harus berupa alamat email yang benar.',
        'date' => ':Attribute harus berupa tanggal.',
        'after_or_equal' => ':Attribute tidak boleh sebelum tanggal mulai.',
        'max' => ':Attribute terlalu panjang.',
        'in' => ':Attribute tidak dikenal.',
        'array' => ':Attribute tidak sah.',
        'string' => ':Attribute tidak sah.',
        'integer' => ':Attribute tidak sah.',
    ];

    /** Formulir kertas menyediakan enam baris; dua puluh cukup longgar. */
    private const MAKS_BARIS = 20;

    /**
     * POST /api/service-report/publik — TANPA autentikasi.
     *
     * Dijaga throttle per alamat IP di routes/api.php. Kiriman dari halaman
     * PWA datang dari server ini sendiri, jadi halaman itu meneruskan IP asli
     * pelapor lewat X-Forwarded-For (dibaca karena trustProxies '*').
     */
    public function storePublic(Request $request)
    {
        // Isian jebakan yang disembunyikan dari manusia. Robot pengisi
        // formulir mengisi semua kotak, termasuk yang ini.
        if (filled($request->input('situs'))) {
            return $this->errorResponse('Kiriman ditolak.', 422);
        }

        $validator = Validator::make($request->all(), $this->aturan(true), self::PESAN, $this->namaIsian());

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $data = $validator->validated();
        $pemeriksaan = $this->bersihkanBaris($data['pemeriksaan'] ?? [], ['tahap', 'solusi']);

        if (! collect($pemeriksaan)->contains(fn ($b) => $b['tahap'] !== '')) {
            return $this->errorResponse('Isi minimal satu baris Tahap Pemeriksaan / keluhan.', 422);
        }

        try {
            $id = DB::table('service_reports')->insertGetId([
                'jenis' => json_encode(array_values(array_unique($data['jenis']))),
                'customer_nama' => trim($data['customer_nama']),
                'customer_alamat' => $this->teks($data['customer_alamat'] ?? null),
                'department' => $this->teks($data['department'] ?? null),
                'position' => $this->teks($data['position'] ?? null),
                'customer_ref' => $this->teks($data['customer_ref'] ?? null),
                'telepon' => $this->teks($data['telepon'] ?? null),
                'contact_person' => $this->teks($data['contact_person'] ?? null),
                'email' => $this->teks($data['email'] ?? null),
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                'produk' => json_encode($this->bersihkanBaris(
                    $data['produk'] ?? [],
                    ['description', 'type', 'nie', 'sn', 'sw_version']
                )),
                'pemeriksaan' => json_encode($pemeriksaan),
                'nama_engineer' => $this->teks($data['nama_engineer'] ?? null),
                'nama_customer' => $this->teks($data['nama_customer'] ?? null),
                'status' => 0,
                'ip_pelapor' => $request->ip(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $noReport = $this->nomori($id);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorResponse('Laporan gagal disimpan. Coba kirim lagi.', 500);
        }

        return $this->successResponse(
            ['id' => $id, 'no_report' => $noReport],
            'Service report terkirim.',
            201
        );
    }

    /**
     * GET /api/service-report
     *
     *   ?page=1  ?per_page=25  ?search=  ?status=0|1|2
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', self::PER_PAGE);
        $perPage = is_numeric($perPage) ? max(1, min((int) $perPage, self::MAX_PER_PAGE)) : self::PER_PAGE;

        $page = $request->query('page', 1);
        $page = is_numeric($page) ? max(1, (int) $page) : 1;

        try {
            $query = DB::table('service_reports')->select(
                'id', 'no_report', 'jenis', 'customer_nama', 'department',
                'contact_person', 'telepon', 'status', 'created_at', 'pemeriksaan'
            );

            $search = trim((string) $request->query('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('no_report', 'like', '%'.$search.'%')
                        ->orWhere('customer_nama', 'like', '%'.$search.'%')
                        ->orWhere('department', 'like', '%'.$search.'%')
                        ->orWhere('contact_person', 'like', '%'.$search.'%')
                        // Nomor seri dicari di dalam JSON sebagai teks biasa;
                        // cukup untuk pencarian petugas, tanpa indeks khusus.
                        ->orWhere('produk', 'like', '%'.$search.'%');
                });
            }

            // is_numeric, bukan filled(): status=0 (Baru) adalah saringan sah.
            $status = $request->query('status');
            if (is_numeric($status)) {
                $query->where('status', (int) $status);
            }

            $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())->map(function ($r) {
                $pemeriksaan = json_decode((string) $r->pemeriksaan, true) ?: [];

                return [
                    'id' => $r->id,
                    'no_report' => $r->no_report,
                    'jenis' => $this->labelJenis($r->jenis),
                    'customer_nama' => $r->customer_nama,
                    'department' => $r->department,
                    'contact_person' => $r->contact_person,
                    'telepon' => $r->telepon,
                    'status' => (int) $r->status,
                    'status_label' => self::STATUS[(int) $r->status] ?? 'Unknown',
                    'created_at' => $r->created_at,
                    // Potongan keluhan pertama supaya daftar bisa dipilah
                    // tanpa membuka satu per satu.
                    'ringkasan' => mb_strimwidth((string) ($pemeriksaan[0]['tahap'] ?? ''), 0, 120, '…'),
                ];
            })->values();

            return $this->successResponse($items, 'Berhasil mengambil daftar service report.', 200, [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse('Gagal mengambil daftar service report: '.$e->getMessage(), 500);
        }
    }

    /** GET /api/service-report/{id} */
    public function show($id)
    {
        $data = $this->ambil((int) $id);

        if (! $data) {
            return $this->errorResponse('Service report tidak ditemukan.', 404);
        }

        return $this->successResponse($data, 'Berhasil mengambil service report.');
    }

    /**
     * PUT /api/service-report/{id} — tindak lanjut oleh tim teknis.
     *
     * Bagian A–C (data pelapor dan alat) sengaja tidak bisa diubah di sini:
     * itu pernyataan pelapor. Yang diisi teknisi hanya tanggal pengerjaan,
     * bagian D, nama engineer, status, dan catatan.
     */
    public function update(Request $request, $id)
    {
        $user = $request->attributes->get('dolibarr_user');

        if (! DB::table('service_reports')->where('id', (int) $id)->exists()) {
            return $this->errorResponse('Service report tidak ditemukan.', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'integer', 'in:'.implode(',', array_keys(self::STATUS))],
            'catatan_tindak_lanjut' => ['nullable', 'string', 'max:5000'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'nama_engineer' => ['nullable', 'string', 'max:100'],
            'pemeriksaan' => ['nullable', 'array', 'max:'.self::MAKS_BARIS],
            'pemeriksaan.*.tahap' => ['nullable', 'string', 'max:2000'],
            'pemeriksaan.*.solusi' => ['nullable', 'string', 'max:2000'],
        ], self::PESAN, $this->namaIsian());

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422, $validator->errors());
        }

        $data = $validator->validated();

        DB::table('service_reports')->where('id', (int) $id)->update([
            'status' => (int) $data['status'],
            'catatan_tindak_lanjut' => $this->teks($data['catatan_tindak_lanjut'] ?? null),
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'nama_engineer' => $this->teks($data['nama_engineer'] ?? null),
            'pemeriksaan' => json_encode($this->bersihkanBaris($data['pemeriksaan'] ?? [], ['tahap', 'solusi'])),
            // PK llxjp_user adalah rowid; $user->id selalu NULL.
            'fk_user_tindak_lanjut' => $user?->rowid,
            'tanggal_tindak_lanjut' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return $this->successResponse($this->ambil((int) $id), 'Tindak lanjut tersimpan.');
    }

    /** GET /api/service-report/{id}/pdf — tata letak mengikuti formulir kertas. */
    public function pdf($id)
    {
        $data = $this->ambil((int) $id);

        if (! $data) {
            return $this->errorResponse('Service report tidak ditemukan.', 404);
        }

        $logo = public_path('pwa/logo.png');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.service-report', [
            'sr' => $data,
            'jenisSemua' => self::JENIS,
            // Di-embed: DomPDF tidak mengambil gambar lewat URL.
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : '',
        ]);
        $pdf->setPaper('A4', 'portrait');

        $nama = 'Service-Report-'.str_replace('/', '-', (string) ($data['no_report'] ?? $id)).'.pdf';

        return $pdf->download($nama);
    }

    // ------------------------------------------------------------------

    private function ambil(int $id): ?array
    {
        $r = DB::table('service_reports as s')
            ->leftJoin('llxjp_user as u', 'u.rowid', '=', 's.fk_user_tindak_lanjut')
            ->where('s.id', $id)
            ->select('s.*', 'u.login as user_login', 'u.firstname as user_firstname', 'u.lastname as user_lastname')
            ->first();

        if (! $r) {
            return null;
        }

        $nama = trim(($r->user_firstname ?? '').' '.($r->user_lastname ?? ''));

        return [
            'id' => $r->id,
            'no_report' => $r->no_report,
            'jenis' => json_decode((string) $r->jenis, true) ?: [],
            'jenis_label' => $this->labelJenis($r->jenis),
            'customer_nama' => $r->customer_nama,
            'customer_alamat' => $r->customer_alamat,
            'department' => $r->department,
            'position' => $r->position,
            'customer_ref' => $r->customer_ref,
            'telepon' => $r->telepon,
            'contact_person' => $r->contact_person,
            'email' => $r->email,
            'tanggal_mulai' => $r->tanggal_mulai,
            'tanggal_selesai' => $r->tanggal_selesai,
            'produk' => json_decode((string) $r->produk, true) ?: [],
            'pemeriksaan' => json_decode((string) $r->pemeriksaan, true) ?: [],
            'nama_engineer' => $r->nama_engineer,
            'nama_customer' => $r->nama_customer,
            'status' => (int) $r->status,
            'status_label' => self::STATUS[(int) $r->status] ?? 'Unknown',
            'catatan_tindak_lanjut' => $r->catatan_tindak_lanjut,
            'user_tindak_lanjut' => $nama !== '' ? $nama : $r->user_login,
            'tanggal_tindak_lanjut' => $r->tanggal_tindak_lanjut,
            'created_at' => $r->created_at,
        ];
    }

    /**
     * Nomor "No. urut/SR/Bln/Thn" sesuai kepala formulir, mis. 007/SR/11/2026.
     *
     * Urutannya dihitung dari banyaknya laporan bulan yang sama dengan id
     * sampai id ini, bukan MAX()+1: dua kiriman bersamaan tetap mendapat
     * nomor berbeda tanpa perlu mengunci tabel.
     */
    private function nomori(int $id): string
    {
        $sekarang = Carbon::now();

        $urut = DB::table('service_reports')
            ->where('id', '<=', $id)
            ->whereYear('created_at', $sekarang->year)
            ->whereMonth('created_at', $sekarang->month)
            ->count();

        $nomor = sprintf('%03d/SR/%s/%s', $urut, $sekarang->format('m'), $sekarang->format('Y'));

        DB::table('service_reports')->where('id', $id)->update(['no_report' => $nomor]);

        return $nomor;
    }

    private function aturan(bool $publik): array
    {
        return [
            'jenis' => ['required', 'array', 'min:1'],
            'jenis.*' => ['string', 'in:'.implode(',', array_keys(self::JENIS))],
            'customer_nama' => ['required', 'string', 'max:150'],
            'customer_alamat' => ['nullable', 'string', 'max:500'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'customer_ref' => ['nullable', 'string', 'max:100'],
            'telepon' => ['required', 'string', 'max:50'],
            'contact_person' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'produk' => ['nullable', 'array', 'max:'.self::MAKS_BARIS],
            'produk.*.description' => ['nullable', 'string', 'max:200'],
            'produk.*.type' => ['nullable', 'string', 'max:100'],
            'produk.*.nie' => ['nullable', 'string', 'max:100'],
            'produk.*.sn' => ['nullable', 'string', 'max:100'],
            'produk.*.sw_version' => ['nullable', 'string', 'max:100'],
            'pemeriksaan' => [$publik ? 'required' : 'nullable', 'array', 'max:'.self::MAKS_BARIS],
            'pemeriksaan.*.tahap' => ['nullable', 'string', 'max:2000'],
            'pemeriksaan.*.solusi' => ['nullable', 'string', 'max:2000'],
            'nama_engineer' => ['nullable', 'string', 'max:100'],
            'nama_customer' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function namaIsian(): array
    {
        return [
            'jenis' => 'jenis pekerjaan',
            'pemeriksaan' => 'tahap pemeriksaan / keluhan',
            'customer_nama' => 'nama customer',
            'customer_alamat' => 'alamat',
            'telepon' => 'nomor telepon',
            'contact_person' => 'contact person',
            'tanggal_mulai' => 'tanggal mulai service',
            'tanggal_selesai' => 'tanggal selesai service',
            'nama_engineer' => 'nama engineer',
            'nama_customer' => 'nama customer (tanda tangan)',
        ];
    }

    /**
     * Rapikan baris tabel: hanya kunci yang dikenal, teks dipangkas, baris
     * yang seluruh kolomnya kosong dibuang. Formulir di ponsel selalu
     * mengirim baris kosong yang belum sempat diisi.
     */
    private function bersihkanBaris(array $baris, array $kunci): array
    {
        $hasil = [];

        foreach ($baris as $b) {
            if (! is_array($b)) {
                continue;
            }

            $rapi = [];
            foreach ($kunci as $k) {
                $rapi[$k] = trim((string) ($b[$k] ?? ''));
            }

            if (implode('', $rapi) !== '') {
                $hasil[] = $rapi;
            }
        }

        return $hasil;
    }

    private function teks(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }

    private function labelJenis($json): string
    {
        $kode = is_array($json) ? $json : (json_decode((string) $json, true) ?: []);

        return collect($kode)->map(fn ($k) => self::JENIS[$k] ?? $k)->implode(', ');
    }
}
