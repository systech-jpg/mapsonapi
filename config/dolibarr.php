<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Direktori Dokumen Dolibarr
    |--------------------------------------------------------------------------
    |
    | Ini adalah DOL_DATA_ROOT milik ERP (mapsonerp) -- folder tempat Dolibarr
    | menyimpan logo perusahaan, lampiran, dan dokumen lainnya. Folder itu
    | sengaja berada di luar document root web dan tidak bisa diambil lewat
    | HTTP, jadi API membacanya langsung dari disk.
    |
    | Dipakai antara lain untuk menempelkan logo pada PDF SPH.
    |
    | Path-nya berbeda di tiap mesin (Windows saat development, Linux di
    | server), karena itu diambil dari .env. Kalau tidak diset, fitur yang
    | membutuhkannya akan melewati bagian itu dengan tenang -- PDF tetap
    | terbit, hanya tanpa logo.
    |
    */

    'documents_path' => env('DOLIBARR_DOCUMENTS_PATH'),

];
