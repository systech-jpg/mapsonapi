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
| SPH | daftar, buat dokumen (Draft), detail + unduh PDF. Baris barang masih lewat ERP |
| Scan Produk | selesai (kamera ZXing, wajib HTTPS) |
| Stocktake | daftar dokumen, isi hitungan fisik (rak/tray/container), saring principal, cari + scan barcode, catatan per baris. Dokumen tetap dibuat, divalidasi, dan di-approve di ERP |
| Chat | daftar percakapan + badge belum dibaca, chat personal & grup, kirim teks + lampiran, buat grup, realtime Pusher, notifikasi Web Push. Tambah anggota grup belum ada di UI (endpoint-nya ada) |
| Login QR ERP | selesai. Halaman login ERP menampilkan QR, PWA memindainya dari layar Scan yang sama dengan scan produk, lalu menyetujui. Modul ERP-nya di `mapsonerp/custom/qrlogin/` |

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

8. **Menu beranda PWA datang dari ERP, bukan dari kode.** `App\Support\MenuBeranda`
   memanggil `GET /api/fragment-menus` — endpoint yang sama dengan Android,
   membaca `llxjp_android_fragment_menus` + `llxjp_android_fragment_menu_user`.
   Jadi tombol **ASSIGN USER** di halaman menu ERP berlaku untuk kedua aplikasi
   sekaligus. Catatan lama di `routes/web.php` yang menyebut daftar menu
   "sengaja statis" sudah tidak berlaku.

   Kolom `route` di ERP berisi nama tujuan Android (`nav_stocktake`,
   `nav_request_stock`, …), jadi ada peta kecil `MenuBeranda::PETA` yang
   menerjemahkannya ke nama route web berikut ikon Bootstrap-nya. **Menu ERP
   yang belum ada di peta itu dilewati diam-diam** — kalau menambah menu baru
   di ERP dan ia tidak muncul di PWA, di situlah tempat menambahkannya.

9. **Penanda memuat cuma satu, dan tempatnya di layout — jangan bikin lagi
   per halaman.** `#pemuat` di `resources/views/layouts/app.blade.php` menyala
   untuk tiga hal sekaligus: muat halaman pertama/refresh, perpindahan
   `wire:navigate`, dan setiap permintaan komponen Livewire (lewat
   `Livewire.hook('commit')`). Blok `wire:loading` + `wire:loading.remove` yang
   dulu ada di empat halaman daftar sudah dicopot supaya tidak ada dua tulisan
   "Memuat…" sekaligus.

   Tiga hal yang mudah dirusak tanpa sengaja:

   - **`data-navigate-once` pada tag `<script>`-nya wajib.** Tanpa itu Livewire
     menjalankan ulang skripnya setiap `wire:navigate` dan pendengarnya
     menumpuk.
   - **Waktu tampil MINIMUM 450 ms, bukan tenggang sebelum tampil.** Permintaan
     di aplikasi ini selesai 150–300 ms; penanda yang mengikuti durasi
     sebenarnya tidak sempat tertangkap mata, dan layar terlihat seperti tidak
     bereaksi. Pernah dicoba `wire:loading.delay.longer` — hasilnya justru
     penanda tidak pernah muncul sama sekali. Jangan ulangi.
   - **Cukup `respond()`, jangan ditambah `fail()`.** Di `livewire.js`,
     `handleFailure` memanggil `respond()` dulu baru `fail()`, jadi memasang
     keduanya membuat satu permintaan dihitung selesai dua kali.

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

    TIGA tahap, semuanya wajib foto, urutannya mengikuti ERP:

    | Tahap | Endpoint | Prefix | Syarat | Ubah status? |
    |---|---|---|---|---|
    | Pickup | `POST /tindakan/{id}/pickup` | PICKUP | tindakan status 2 | tidak |
    | Barang sampai | `POST /tindakan/{id}/confirm-arrival` | ARRIVE | tindakan status 2 | ya, 2 → 3 |
    | Tarik barang | `POST /tindakan/usage/{id}/tarik-barang` | TARIK | usage status 1 | ya, 1 → 4 |

    **Tahap keempat SERAH TERIMA DOKUMEN (DOK_TERIMA) sudah dihapus** — dari
    `custom/tindakanmedis/usage.php` di ERP lebih dulu, lalu dari PWA menyusul.
    Sesudah Tarik Barang, langkah berikutnya adalah tombol **ACCEPT
    (WAREHOUSE)** yang mengubah status 4 → 2 dan memberi nomor PRMM; itu aksi
    gudang di ERP, bukan aksi lapangan, jadi tidak ada padanannya di PWA. Yang
    ikut dibuang: `dokumenTerima()`, `buktiDokumen()`, `BUKTI_DOKUMEN_PREFIX`,
    field `bukti_dokumen` di respons usage, route API `dokumen-terima` dan
    `bukti-dokumen`, route web `tindakan.bukti-dokumen`, serta
    `bisaSerahTerima()`/`serahTerima()` di komponen Detail. Aplikasi Android
    tidak pernah memanggil endpoint itu, jadi tidak ada klien yang ikut rusak.

    Berkas `DOK_TERIMA_*` milik dokumen lama **tetap ada di disk** dan masih
    tampil di `history.php` ERP. Yang dihapus tahapnya, bukan buktinya —
    jangan membersihkan berkasnya.

    Pickup menolak unggahan kedua dengan 409 kecuali diberi `ganti=1`,
    mengikuti penjagaan yang sama di ERP.

    Di PWA, konfirmasi "Barang Sampai" menuntut bukti pickup sudah ada lebih
    dulu — sama seperti ERP yang baru menggambar form berikutnya setelah bukti
    pickup tersimpan. Ketiga kartunya memakai satu partial bersama
    `resources/views/partials/bukti-unggah.blade.php`.

    Log tahap barang sampai memakai action `ARRIVAL`, bukan `STATUS_DELIVERED`
    seperti ERP: `Tindakan::getLogActionLabel()` mengenal ARRIVAL dan
    menerjemahkannya, sedangkan STATUS_DELIVERED tampil sebagai kode mentah.

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

17. **Tabel stocktake di salinan lokal sudah dipangkas, dan endpoint lamanya
    ikut mati karenanya.** `llxjp_stocktake_det` tidak lagi punya
    `counter_qty_rak/tray/container/physical`, `fk_user_counter_update`, dan
    `fk_user_verifikator_update` — kolom yang menopang pemisahan angka Counter
    dan Verifikator. Sekarang cuma ada SATU set angka, sama seperti
    `custom/stocktake/card.php`.

    `qty_theoretical` **ditampilkan ke semua peran** yang boleh membuka modul
    ini. Sempat disembunyikan dari grup Warehouse dengan alasan angka sistem
    membuat orang menyalin alih-alih menghitung, tapi alasan itu tidak berlaku:
    yang memakai modul ini verifikator, dan dia toh bisa membuka angka yang
    sama di halaman stocktake ERP kapan saja. Jangan hidupkan lagi
    penyembunyian itu tanpa alasan baru — dulu ia menyeret parameter
    `$lihatTeori` ke tujuh method.

    Ikutannya: `llxjp_userstocktake` dan `llxjp_userstocktake_detail` kosong
    (0 baris) dan **tidak ada satu pun berkas di `custom/` yang menulisinya** —
    `grep -rl "userstocktake" custom/` tidak menghasilkan apa-apa. Jadi gerbang
    "jadwal aktif yang di-assign untuk user Anda" selalu berujung 404, dan
    endpoint `signature` + `watermark` (iLovePDF, berbayar) menandatangani
    berkas dari kolom `file_pdf` milik tabel kosong itu. Keduanya dihapus.
    Principal turun pangkat jadi penyaring yang datanya dari isi dokumen.

    **Penanda "sudah dihitung" tidak ada kolomnya lagi**, jadi diturunkan dari
    `qty_physical > 0` — lihat `const TERISI` di controller. Jangan diganti
    menjadi `(qty_rak + qty_tray + qty_container) > 0`; itu versi pertama dan
    itu keliru. **Dokumen lama dihitung dengan mengisi qty fisik langsung
    tanpa rincian rak/tray/container:**

    | Dokumen | Total | Punya rincian | Punya qty fisik |
    |---|---|---|---|
    | STK/2601/0001 | 270 | 0 | 239 |
    | STK/2601/0002 | 310 | 0 | 283 |
    | STK/2606/0001 | 313 | 294 | 294 |
    | STK/2608/0001 | 317 | 316 | 316 |

    Dengan aturan rincian, halaman dokumen lama berbunyi "0 dari 270 terhitung"
    tepat di sebelah "Fisik 36.346" — saling bertentangan di layar yang sama.
    Aturan `qty_physical > 0` aman karena di dokumen berincian kedua aturan
    memberi angka identik, dan tidak ada satu pun baris berincian yang qty
    fisiknya nol (endpoint ini dan `card.php` sama-sama selalu menulis ulang
    `qty_physical` sebagai jumlah ketiganya).

    Akibat yang harus disadari: barang yang memang berjumlah nol tidak bisa
    dibedakan dari barang yang belum disentuh. ERP menghadapi keterbatasan yang
    sama, jadi angka progres di kedua sisi tetap sepakat. Kalau suatu saat
    perbedaan itu penting, yang diperlukan kolom baru (mis. `date_count`) — dan
    itu ALTER TABLE ke salinan production, wajib minta izin dulu.

    Dua baris di STK/2608/0001 punya `principal = '0'` yang tidak cocok dengan
    societe mana pun. Semua query baris memakai LEFT JOIN ke
    `product_extrafields` dan `societe`, plus kelompok "Lainnya" (id 0); dengan
    INNER JOIN kedua barang itu hilang diam-diam dan tak pernah bisa dihitung.

    `qty_physical` selalu dihitung ulang di server sebagai rak + tray +
    container dan tidak pernah diterima dari klien, meniru `calcQty()` di ERP.
    Menulis hanya boleh saat status dokumen 0 (Draft); 1 dan 2 dijawab 409.

18. **Kolom baru di `custom/*/sql/llx_*.sql` TIDAK pernah sampai ke server yang
    tabelnya sudah ada.** Berkas itu memakai `CREATE TABLE IF NOT EXISTS`, jadi
    begitu tabelnya ada, seluruh isi berkas dilewati — termasuk kolom yang baru
    ditambahkan. Menonaktifkan lalu mengaktifkan ulang modul di ERP tidak
    menolong sama sekali.

    Sudah menggigit sekali: `llxjp_sph` di server dibuat dari definisi lama
    (`custom/sph/sql/legacy/llxjp_sph.superseded.sql`) yang belum punya
    `ref_quotation` dan `fk_principal`. Di mesin lokal kedua kolom itu ada,
    sehingga `GET /api/sph` menjawab 200 di sini tapi 500 di server dengan
    `Unknown column 't.fk_principal' in 'on clause'`. Perbaikannya ALTER TABLE
    manual di server, bukan tambalan di kode — kolomnya memang bagian dari
    rancangan modul dan dipakai `card.php` maupun PDF-nya.

    Jadi setiap kali menambah kolom ke definisi tabel modul ERP, tulis juga
    perintah ALTER-nya untuk server yang sudah berjalan. Bandingkan dengan
    `SHOW COLUMNS FROM <tabel>` sebelum menyalahkan kode aplikasi: gejalanya
    selalu berupa jalan di lokal, gagal di server.

19. **Chat punya DUA jalur notifikasi, dan keduanya berangkat dari satu titik.**
    `ChatController::sendPushNotification()` mengirim ke Pusher Beams (FCM,
    untuk Android) DAN Web Push VAPID (untuk PWA, lewat
    `App\Notifications\PesanChatBaru`). Dulu method itu berhenti lebih awal bila
    Beams belum dikonfigurasi — penjagaan itu sudah dipindah ke dalam supaya
    browser tidak ikut kehilangan notifikasi.

    Langganan Web Push tersimpan di tabel `push_subscriptions` dan ditautkan ke
    `App\Models\DolibarrUser` (rowid `llxjp_user`), jadi penerimanya sama persis
    dengan penerima di Beams. Petugas yang belum pernah menekan **Aktifkan
    notifikasi** di halaman Profil tidak punya baris di sana, dan pesan untuknya
    lewat begitu saja tanpa galat — itu bukan kerusakan.

20. **Kanal realtime chat sama untuk Android dan PWA: `chat.user.{rowid}`,
    event `new-message`.** Di PWA dipasang lewat
    `resources/views/partials/pusher-chat.blade.php` yang di-`@include` di
    layout, bukan per halaman — badge belum dibaca di beranda ikut hidup
    karenanya.

    Tiga hal yang mudah merusaknya:

    - **`wsHost` JANGAN diisi dari `config('broadcasting...options.host')`.**
      Nilai itu tidak pernah kosong: tanpa `PUSHER_HOST` ia jatuh ke
      `api-<cluster>.pusher.com`, yaitu alamat REST API — BUKAN alamat
      WebSocket (`ws-<cluster>.pusher.com`). Meneruskannya ke pusher-js membuat
      koneksi tidak pernah tersambung. Partial-nya membuang host yang
      berakhiran `.pusher.com` dan membiarkan SDK menyusunnya dari cluster.
    - **Penjaga `window.__chatPusherTerpasang` wajib.** `wire:navigate`
      menjalankan ulang skrip di `<body>`, dan tanpa penjaga itu satu pesan
      masuk dihitung sebanyak jumlah halaman yang pernah dibuka.
    - **Method pendengar `#[On('chat-masuk')]` HARUS punya parameter
      `?int $senderId`, `?int $groupId`** walau tidak dipakai. Livewire
      meneruskan muatan event sebagai named argument; method tanpa parameter
      dijawab `Unknown named parameter $senderId`.

    Sengaja **tidak ada `wire:poll` sebagai cadangan**: penanda "Memuat…"
    bersama di layout menyala untuk setiap permintaan Livewire, jadi polling
    membuat layar berkedip terus-menerus (lihat bagian 3 nomor 9). Bila Pusher
    tidak terjangkau, pesan baru tetap muncul saat halaman dimuat ulang.

21. **Lampiran chat tidak bisa ditautkan langsung dari browser.** Berkasnya
    tersimpan TERENKRIPSI di `storage/app/private/chat_secure/` dan hanya bisa
    dibuka lewat `GET /api/chat/download/{filename}` yang menuntut header
    Authorization — sedangkan browser tidak pernah memegang api_key. Route web
    `pesan.berkas` yang mengambilkannya server ke server, pola yang sama dengan
    surat jalan Tindakan.

    Nama simpanannya (`1785895336_6a72....enc`) tidak menyimpan nama asli, jadi
    nama aslinya dititipkan lewat query `?nama=` dan dibersihkan di route.
    Berkas yang diunggah dari server produksi TIDAK ada di disk mesin lokal —
    404 di sini normal, bukan tanda endpoint-nya rusak.

22. **Kotak tulis pesan TIDAK boleh memakai `position: sticky`.** Sticky baru
    menempel ke dasar layar setelah isinya lebih tinggi daripada layar; pada
    percakapan yang baru berisi satu-dua pesan, kotaknya berhenti mengambang di
    tengah. Sudah pernah menggigit sekali.

    Yang dipakai sekarang `.ch-layar`: satu layar penuh (`height: 100dvh`)
    berisi tiga bagian flex — header tetap, `.ch-isi` yang mengambil sisa ruang
    dan menggulir sendiri, lalu kotak tulis. Halamannya sendiri tidak pernah
    bergulir, jadi **`window.scrollTo()` tidak menggerakkan apa pun**; yang
    digulir ke bawah setelah pesan terkirim adalah `.ch-isi`.

    Ikutannya, `<meta name="viewport">` di layout diberi
    `interactive-widget=resizes-content` supaya viewport menyusut saat keyboard
    muncul dan kotak tulis naik ke ATAS keyboard. Tanpa itu kotaknya tertutup
    keyboard persis saat sedang dipakai mengetik. Diabaikan browser yang belum
    mengenalnya, jadi tidak merusak halaman lain.

    `.ch-layar > .app-header` juga memangkas `padding-bottom` bawaan
    `.app-header.ringkas` (3.25rem). Angka itu ada supaya kartu di halaman lain
    bisa menaiki header dengan `margin-top` negatif — di ruang chat tidak ada
    yang menaikinya, jadi yang tersisa cuma pita emas kosong.

23. **Label mengambang di halaman masuk butuh `placeholder=" "`.** Aturannya di
    `app.css` memakai `:placeholder-shown` untuk tahu kotaknya masih kosong;
    tanpa placeholder berisi satu spasi, label tidak pernah turun kembali saat
    isiannya dikosongkan dan akan menumpuk di atas teks yang diketik.

    `public/pwa/logo.png` adalah **salinan** `drawable/logo.png` milik Android,
    bukan berkas yang dibuat sendiri. Kalau logo di Android diganti, salin lagi
    ke sini — kalau tidak, kedua aplikasi diam-diam memakai logo berbeda.

24. **Login QR ERP: satu fitur, dua project.** Kodenya terbagi antara PWA ini
    (`QrLoginController` + layar Scan) dan modul ERP di
    `C:\Users\USER\Herd\mapsonerp\custom\qrlogin\`. Jembatannya bukan HTTP,
    melainkan **database yang sama**: `mapsonerpdb`, tabel `llxjp_qr_login`.
    Kalau salah satu sisi diubah, baca README modul ERP-nya lebih dulu.

    Teks di kode QR adalah `MAPSONLOGIN:<token>`. Awalan itu ditulis di DUA
    tempat — `Scan\Produk::AWALAN_QR_LOGIN` dan `custom/qrlogin/api.php` — dan
    keduanya wajib sama persis. Awalan inilah yang membuat satu layar kamera
    bisa melayani dua tujuan: yang berawalan itu tidak pernah dicari sebagai
    produk (kalau dicari, jawabannya pasti 404 dan petugas melihat "Produk
    tidak ditemukan" untuk kode yang sebenarnya sehat).

    Empat hal yang mudah merusaknya:

    - **`conf/conf.php` ERP harus `'dolibarr,qrlogin'`**, dengan `dolibarr` di
      DEPAN. Tanpa `qrlogin`, token tidak pernah diperiksa dan QR-nya diam saja.
      Kalau `qrlogin` ditaruh di depan, setiap login biasa ikut mencari kata
      sandinya di tabel token. Cadangan berkas aslinya:
      `conf/conf.php.sebelum-qrlogin`.
    - **`module_parts['hooks']` berisi KONTEKS, bukan nama method.** Halaman
      login memanggil `initHooks(array('mainloginpage'))`, jadi isinya harus
      `array('mainloginpage')`. Sempat diisi nama method
      (`getLoginPageOptions`) dan akibatnya hook tidak pernah berjalan — tanpa
      pesan galat apa pun.
    - **Nama berkas dan kelas hook tidak boleh diubah.**
      `hookmanager.class.php` menyusunnya sendiri dari nama modul:
      `actions_qrlogin.class.php` dan `ActionsQrlogin`.
    - **Token saja tidak cukup untuk masuk; ada `qrsecret`.** Token tampil di
      layar dan bisa difoto, rahasianya tidak pernah masuk ke gambar QR. Kalau
      suatu saat isian tersembunyi `qrsecret` hilang dari form login, login QR
      akan selalu gagal walau QR-nya terpindai dengan benar.

    Persetujuan di PWA **sengaja tidak otomatis**. Memindai saja tidak cukup:
    kode QR palsu yang ditempel di gudang bisa membuat petugas memberikan sesi
    ERP atas namanya kepada penyerang. Jangan hilangkan layar konfirmasinya.

25. **Status Usage Report tidak berurutan, dan 5 mudah terlupa.** Alurnya di
    ERP: `0 Draft → 1 Validated → (4 Barang Ditarik | 5 Transit Barang) →
    2 Accepted → 3 Ordered`. Angka 4 dan 5 disisipkan belakangan di antara 1 dan
    2; jangan "dirapikan" menjadi berurutan.

    Status **5 Transit Barang** sempat tidak ada di `getUsageStatusLabel()`,
    sehingga laporan yang ditransit tampil sebagai "Unknown" di mobile padahal
    statusnya sah — dan di database memang sudah ada barisnya. Transit adalah
    cabang dari status 1: sisa barang dipindahkan ke tindakan lain alih-alih
    ditarik ke gudang (`UsageReport::transitBarang()`), lalu sama-sama berujung
    di ACCEPT.

    Label-labelnya harus sama persis dengan badge di
    `custom/tindakanmedis/usage.php`. Kalau ERP menambah status lagi, di
    `getUsageStatusLabel()` tempat menambahkannya.

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
