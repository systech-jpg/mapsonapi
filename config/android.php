<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lokasi File Ikon Menu
    |--------------------------------------------------------------------------
    |
    | Ikon menu di-upload lewat aplikasi ERP (mapsonerp), bukan lewat API ini,
    | jadi file fisiknya berada di direktori milik ERP. API cuma menyajikan
    | ulang file tersebut supaya Android tidak perlu tahu domain ERP.
    |
    | Path-nya berbeda di tiap mesin (Windows saat development, Linux di
    | server), karena itu diambil dari .env. Kalau tidak diset, API akan
    | mencari di public/android_icons milik project ini sendiri.
    |
    */

    'icons_path' => env('ANDROID_ICONS_PATH', public_path('android_icons')),

];
