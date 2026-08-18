# CLAUDE.md — Aturan Baku Proyek mapsonapi

Berkas ini dibaca otomatis setiap sesi. Isinya mengikat: ikuti tanpa perlu
diingatkan ulang.

---

## 1. Aturan wajib

### 1.1 Jangan menjalankan test apa pun

**Dilarang** menjalankan perintah berikut, dalam bentuk apa pun:

```
php artisan test
./vendor/bin/phpunit
./vendor/bin/pest
```

Alasannya menghemat token. Berlaku juga saat kamu merasa "cuma satu file".

**Konsekuensi yang harus kamu sadari dan sampaikan:** tanpa test, kebenaran kode
tidak terbukti. Karena itu, saat melaporkan hasil kerja kamu **wajib** memisahkan
dengan jujur mana yang sudah diperiksa dan mana yang belum. Jangan menulis
"sudah berfungsi" untuk sesuatu yang hanya kamu baca, bukan kamu jalankan.
Gunakan kalimat seperti: "belum diuji, perlu dicoba di perangkat".

### 1.2 Pakai pemeriksaan murah sebagai gantinya

Ini **boleh dan dianjurkan**, karena cepat serta hampir tidak memakan token:

| Tujuan | Perintah |
|---|---|
| Cek sintaks PHP | `php -l path/file.php` |
| Cek semua Blade bisa dikompilasi | `php artisan view:cache` lalu `php artisan view:clear` |
| Cek route terdaftar | `php artisan route:list --path=nama` |
| Cek endpoint API sungguhan | `curl -s -H "Authorization: Bearer <key>" http://mapsonapi.test/api/...` |
| Cek struktur tabel/data | `php artisan tinker --execute="..."` |

Untuk `curl`, ambil api_key dari `llxjp_user` (kolom `api_key`). **Jangan pernah
mencetak key-nya utuh ke layar.**

### 1.3 Bahasa dan gaya jawaban

- **Selalu bahasa Indonesia.** Termasuk komentar kode dan pesan commit.
- Bahasa yang **mudah dimengerti**. Hindari jargon tanpa penjelasan.
- **Selalu sertakan langkah demi langkah** saat menjelaskan cara melakukan
  sesuatu — bernomor, satu tindakan per langkah, lengkap dengan perintah yang
  bisa disalin.
- Sebutkan **di mana** (nama berkas dan baris), bukan hanya "di config".
- Bila ada temuan penting, tampilkan buktinya (potongan output), jangan hanya
  klaim.

### 1.4 Jangan menulis ke database tanpa izin

Database lokal `mapsonerpdb` adalah **salinan production**. Bukti: ikon menu
menunjuk `http://202.10.42.242/...`.

- Operasi **baca** (SELECT, GET) bebas dijalankan.
- Operasi **tulis** (INSERT/UPDATE/DELETE, POST ke endpoint yang menyimpan)
  wajib minta izin dulu, jelaskan apa yang akan berubah.

---

## 2. Gambaran proyek

PWA Laravel 12 + Livewire 3 + Bootstrap 5 (via CDN) untuk petugas lapangan
Mapson. Satu aplikasi berisi dua sisi:

- `routes/api.php` — API untuk aplikasi Android (`C:\android\warehousmap`).
  Autentikasi lewat header `Authorization: Bearer <api_key Dolibarr>`.
- `routes/web.php` — halaman web/PWA. Autentikasi lewat `api_token` di session.

Halaman web **tidak** query database langsung. Semuanya lewat
`App\Support\Api` yang memanggil `routes/api.php` melalui HTTP, memakai token
dari session. Jadi web dan Android berbagi endpoint yang sama persis.

**Tidak ada** model User Laravel, tabel `users`, atau guard `web`. Penanda
"sudah masuk" hanya `session('api_token')`.

### Status modul

| Modul | Status |
|---|---|
| Sales Order | selesai (daftar saja) |
| Forecast | selesai (buat dokumen + isi qty + submit) |
| Tindakan | selesai (daftar, buat/ubah jadwal, validasi, isi pemakaian, pratinjau + validasi laporan, konfirmasi sampai, tarik barang, unduh surat jalan) |
| Stocktake, SPH | masih placeholder |

Modul Android di `C:\android\warehousmap\app\src\main\java\com\mapson\id\ui\`
adalah **acuan alur**. Baca dulu sebelum membuat modul baru — komentar di
dalamnya sering menjelaskan bug yang sudah pernah terjadi.

---

## 3. Konvensi kode

1. **Komponen Livewire** di `app/Livewire/<Modul>/`, view-nya di
   `resources/views/livewire/<modul>/`. Halaman pembungkus di
   `resources/views/<modul>/`.
2. **Penamaan bahasa Indonesia** untuk properti dan method milik sendiri
   (`$galat`, `$cari`, `muat()`, `simpan()`). Nama dari API tetap apa adanya
   (`product_id`, `qty_forecast`).
3. **Komentar menjelaskan "kenapa", bukan "apa".** Tulis komentar hanya bila
   ada alasan tidak jelas yang perlu diamankan. Contoh gaya yang dipakai:
   ```php
   // PK llxjp_user adalah rowid; tidak ada kolom id. $user->id
   // mengembalikan NULL diam-diam, sehingga dokumen tersimpan tanpa pembuat.
   ```
4. **Panggilan API selalu dibungkus try/catch.** Tangkap `RequestException`
   untuk mengambil pesan server (`$e->response->json('message')`), lalu
   `\Throwable` untuk kegagalan jaringan. Kegagalan tidak boleh membuat halaman
   500.
5. **Halaman kerja penuh isian** memakai `@section('tanpa-menu', 'ya')` supaya
   tab bar dan FAB Scan tidak digambar.
6. **`wire:model` biarkan deferred** (tanpa `.live`) untuk input yang jumlahnya
   banyak. Pakai `.live.debounce.300ms` hanya untuk kotak pencarian.
7. **Jangan pakai modal bawaan Bootstrap** bersama Livewire. Pakai pola
   `.sheet` + `.sheet-backdrop` yang sudah ada di `public/css/app.css`.

---

## 4. Jebakan yang sudah pernah menggigit

Jangan mengulang analisis dari nol untuk hal-hal berikut.

1. **CSS/HTML basi di perangkat.** Apache tidak mengirim `Cache-Control` untuk
   `public/css/app.css`, jadi browser menyimpannya sesuka hati. URL-nya sudah
   diberi versi `?v={{ filemtime(...) }}`. Bila mengubah aset statis lain,
   lakukan hal yang sama.

2. **ngrok gratis merusak PWA.** Interstitial-nya mengembalikan `text/html`
   untuk `/sw.js`, manifest, dan ikon, sehingga service worker gagal terdaftar
   dan aplikasi tidak bisa di-install. Header `ngrok-skip-browser-warning`
   tidak menolong karena tidak bisa disisipkan ke navigasi browser. Solusinya
   ganti tunnel (Cloudflare Tunnel) atau ngrok berbayar.

3. **Push notification terikat origin.** Langganan yang dibuat di
   `mobile.mapsonarya.com` akan membuka domain itu walau server pengirimnya
   lokal. Setiap ganti domain, langganan harus dibuat ulang.

4. **Push tidak butuh install PWA** di Android — cukup tab biasa. Hanya iOS yang
   mewajibkan Add to Home Screen.

5. **`APP_URL` hampir tidak berpengaruh** untuk PWA. Di dalam request HTTP,
   `url()`/`route()`/`asset()` mengikuti host yang dibuka browser.
   `APP_URL` hanya dipakai di luar request (artisan, queue).

6. **`API_BASE_URL` adalah panggilan server ke dirinya sendiri.** Biarkan
   `http://mapsonapi.test/api` walaupun diakses lewat tunnel.

7. **Waktu Dolibarr disimpan sebagai waktu SERVER, bukan UTC.** Pakai
   `Carbon::now()` — atau lebih baik `dolibarrNow()` dari trait
   `LogsDolibarrActivity`. Catatan lama di berkas ini menyuruh sebaliknya dan
   itu keliru. Buktinya tiga lapis:

   - `DoliDB::idate($ts, $gm = 'tzserver')` di `core/db/DoliDB.class.php` —
     parameter defaultnya `tzserver`, dan ada TODO yang mengakui idealnya
     `gmt` tapi belum diubah. Semua kolom datetime lewat sini.
   - `MAIN_SERVER_TZ = Asia/Jakarta` di `llxjp_const`, dipasang lewat
     `date_default_timezone_set()` di `core/class/conf.class.php`.
   - Berkas `PICKUP_20260818_103029.png` (dinamai `date('Ymd_His')`) dan baris
     log PICKUP-nya sama-sama bertanda 10:30:29.

   Akibat kekeliruan itu, semua baris mobile sebelum 18 Agustus 2026 tersimpan
   7 jam lebih mundur, sehingga di `history.php` (ORDER BY datelog ASC) aksi
   mobile menyembul ke urutan paling awal. Syarat mutlak: `config/app.php`
   harus memakai zona yang sama dengan `MAIN_SERVER_TZ`.

8. **PK tabel Dolibarr adalah `rowid`, bukan `id`.**

9. **`GET /api/tindakan/{id}/usage` itu operasi tulis.** Endpoint ini membuat
   record `llxjp_usage_report` beserta barisnya bila belum ada
   (`getOrCreateUsageReport`). Jadi jangan memanggilnya hanya karena halaman
   detail dibuka. `App\Livewire\Tindakan\Detail::perluUsage()` menjaganya:
   dipanggil hanya bila `fk_usage` sudah terisi, atau status tindakan sudah
   In Delivery / Delivered / Ready.

10. **Kolom `nama_ts` di `llxjp_tindakan` berisi rowid user, bukan nama.**
    Sementara `fk_soc` adalah rumah sakitnya — bukan `entity` (Android salah
    memakai `entity` saat mode ubah).

11. **`POST /api/tindakan/usage/{id}/tarik-barang` sekarang WAJIB membawa
    foto** (field `bukti`, multipart, maks 8 MB). Mengikuti form Upload Bukti
    Tarik Barang di halaman usage ERP. **Aplikasi Android belum mengirim berkas
    ini, jadi tombol Tarik Barang di sana akan dijawab 422** sampai
    `TindakanDetailViewModel.pullGoods()` diubah memakai `@Multipart`.

    **Fotonya masuk ke folder dokumen Dolibarr, bukan ke storage Laravel:**
    `<ERP_DOC_ROOT>/<REF_TINDAKAN_disanitasi>/TARIK_<Ymd_His>.<ext>` —
    pola yang sama persis dengan `tm_store_proof()` di
    `custom/tindakanmedis/lib/tindakanmedis_proof.lib.php`. ERP menentukan
    "sudah upload atau belum" dari ADA/TIDAKNYA berkas di disk (glob `TARIK_*`),
    bukan dari kolom atau baris log — jadi nama berkas dan nama foldernya wajib
    sama, termasuk hasil sanitasi `/` menjadi `_` (TD/2608/00572 →
    `TD_2608_00572`). Diatur lewat `ERP_DOC_ROOT` di `.env`; bila kosong,
    endpoint menolak dengan 500 dan tidak menyimpan ke tempat lain.

    Timestamp nama berkas memakai waktu LOKAL (`Carbon::now()`), bukan
    `dolibarrNow()` yang UTC — karena ERP memakai `date('Ymd_His')`, dan urutan
    nama berkas adalah cara ERP menentukan mana bukti terbaru.

    Tidak ada kolom baru di tabel Dolibarr. Jejaknya dicatat di
    `llxjp_usage_report_log` dengan tautan `document.php` yang bentuknya sama
    dengan buatan `UsageReport::tarikBarang()`.

12. **Bukti foto tiap tahap memakai satu pola yang sama.** Selain TARIK ada
    PICKUP (`POST /api/tindakan/{id}/pickup`, multipart, field `bukti`).
    Keduanya menulis ke `<ERP_DOC_ROOT>/<REF_disanitasi>/<PREFIX>_<Ymd_His>.<ext>`
    lewat `simpanBukti()`, dan mencatat log dengan tautan `document.php` yang
    bentuknya sama dengan `tm_proof_badge()` di ERP.

    Empat tahap, semuanya wajib foto, urutannya mengikuti ERP:

    | Tahap | Endpoint | Prefix | Syarat | Ubah status? |
    |---|---|---|---|---|
    | Pickup | `POST /tindakan/{id}/pickup` | PICKUP | tindakan status 2 | tidak |
    | Barang sampai | `POST /tindakan/{id}/confirm-arrival` | ARRIVE | tindakan status 2 | ya, 2 → 3 |
    | Tarik barang | `POST /tindakan/usage/{id}/tarik-barang` | TARIK | usage status 1 | ya, 1 → 4 |
    | Serah terima | `POST /tindakan/usage/{id}/dokumen-terima` | DOK_TERIMA | usage status 4 | tidak |

    Pickup dan serah terima menolak unggahan kedua dengan 409 kecuali diberi
    `ganti=1`, mengikuti penjagaan yang sama di ERP.

    Di PWA, konfirmasi "Barang Sampai" menuntut bukti pickup sudah ada lebih
    dulu — sama seperti ERP yang baru menggambar form berikutnya setelah bukti
    pickup tersimpan. Keempat kartunya memakai satu partial bersama
    `resources/views/partials/bukti-unggah.blade.php`.

    Log tahap barang sampai memakai action `ARRIVAL`, bukan `STATUS_DELIVERED`
    seperti ERP: `Tindakan::getLogActionLabel()` mengenal ARRIVAL dan
    menerjemahkannya, sedangkan STATUS_DELIVERED tampil sebagai kode mentah.
    Serah terima memakai action `'DOKUMEN DITERIMA'` — dengan spasi, bukan garis
    bawah, karena halaman usage ERP mencarinya persis begitu.

13. **`qty_used` tidak boleh melebihi `qty_sent`.** Dijaga di
    `saveUsageLines()` (422, diperiksa sebelum transaksi dibuka) dan sekali
    lagi di web sebelum tombol Simpan/Validasi bekerja. Sebelum penjagaan ini
    ada, PRMM/26/08/00063 sempat tervalidasi dengan qty_used 5 dari qty_sent 1
    dan kolom Qty Kembali menjadi -4.

14. **Surat jalan PDF harus diambil server ke server.** Browser tidak pernah
    memegang api_key, jadi tautan langsung ke `/api/tindakan/{id}/surat-jalan`
    pasti 401. Route web `tindakan.surat-jalan` di `routes/web.php` yang
    mengambilkannya lalu meneruskan isinya.

15. **Halaman ERP menilai tahap dari BERKAS di disk, bukan dari kolom
    status — dan itu harus dijaga di kedua sisi.** Unggahan dari PWA menulis
    berkas bukti lewat jalurnya sendiri, jadi setiap halaman ERP yang hanya
    melihat kolom status akan terus menampilkan form unggah walau fotonya sudah
    ada. Itu yang terjadi di `custom/tindakanmedis/prepare.php`: tahap PICKUP
    sudah membaca disk (`tm_proof_files`), tahap ARRIVE belum. Sekarang keduanya
    membaca disk, lengkap dengan tombol **TANDAI SAMPAI (READY)** untuk kasus
    berkas sudah ada tapi status belum naik.

    Sisa yang belum diseragamkan: `usage.php` masih menilai tahap TARIK murni
    dari status usage (1), dengan alasan yang ditulis di komentarnya — berkas
    TARIK bisa tertinggal dari percobaan yang gagal. Bila nanti tarik barang
    dari PWA berhenti setengah jalan, di situlah tempat memeriksanya.

16. **Sesudah unggah bukti di PWA, halaman dimuat ulang — jangan hanya
    `muat()`.** `Detail::unggahBukti()` mengakhiri dengan
    `redirect(..., navigate: true)` dan pesannya lewat `session()->flash()`.
    Dua alasannya: (a) keempat kartu bukti memakai partial yang sama sehingga
    penggabungan DOM Livewire bisa keliru mencocokkan kartu yang hilang dengan
    kartu yang muncul — kini masing-masing diberi `wire:key`; (b) `muat()`
    keluar lebih awal bila API tidak terhubung, meninggalkan `$info` versi LAMA
    sehingga tahapnya seolah mundur. Penolakan server (termasuk 409) juga
    dimuat ulang, karena 409 justru pertanda layar yang ketinggalan.

---

## 5. Rekomendasi

Bagian ini saran, bukan aturan. Silakan setujui atau tolak.

1. **Hapus `tests/Feature/ForecastSmokeTest.php`.** Dengan aturan 1.1, berkas
   itu tidak akan pernah dijalankan. Isinya juga terikat mesin ini (nama
   database, dokumen id 5, product id tertentu) sehingga pasti gagal di
   komputer lain. Menyimpan test yang tidak pernah dijalankan lebih berbahaya
   daripada tidak punya test — orang mengira ada jaring pengaman padahal tidak.

2. **Sediakan satu dokumen forecast khusus uji coba.** Karena test dilarang dan
   database salinan production, sebaiknya ada satu dokumen Draft tetap yang
   boleh dikotori bebas untuk mencoba tombol Simpan/Submit tanpa merusak data
   nyata.

3. **Pindah dari ngrok ke Cloudflare Tunnel.** Gratis, tanpa interstitial,
   HTTPS asli. Ini menyelesaikan sekaligus: install PWA, service worker, dan
   push notification. Perintahnya:
   ```
   winget install --id Cloudflare.cloudflared
   cloudflared tunnel --url http://mapsonapi.test
   ```

4. **Kalau nanti ingin jaring pengaman tanpa biaya token**, buat satu berkas
   test yang dijalankan **oleh Anda sendiri**, bukan oleh Claude. Claude yang
   menulis, Anda yang menjalankan lalu menempelkan hasilnya bila gagal. Cara ini
   tetap hemat token tapi tidak kehilangan pengaman.

5. **Bereskan modul tersisa dengan urutan yang sama seperti Forecast:** baca
   controller API-nya dulu, lalu fragment Android-nya, baru tulis komponen
   Livewire. Urutan ini yang membuat modul Forecast selesai tanpa salah tebak
   bentuk respons.

6. **Perbarui berkas ini** setiap kali menemukan jebakan baru atau menyelesaikan
   modul. Berkas yang basi lebih menyesatkan daripada tidak ada.
