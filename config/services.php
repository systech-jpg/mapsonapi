<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'beams' => [
        'instance_id' => env('BEAMS_INSTANCE_ID'),
        'secret_key' => env('BEAMS_SECRET_KEY'),
    ],

    /*
    | Base URL API yang dipanggil frontend PWA. Saat ini menunjuk ke aplikasi
    | ini sendiri, karena routes/api.php berada di project yang sama.
    */
    /*
    | Dolibarr (ERP) di sisi berkas, bukan database.
    |
    | doc_root menunjuk ke folder bukti modul tindakanmedis, yaitu
    | <DOL_DATA_ROOT>/tindakanmedis — nilai yang sama dengan yang dipakai
    | tm_proof_dir() di custom/tindakanmedis/lib/tindakanmedis_proof.lib.php.
    | Bukti foto dari mobile ditulis ke sana supaya muncul di halaman usage ERP.
    |
    | url_root adalah DOL_URL_ROOT: kosong bila Dolibarr dipasang di akar
    | domain, atau '/dolibarr' bila di dalam subfolder. Dipakai menyusun tautan
    | document.php yang disimpan di catatan log.
    */
    'erp' => [
        'doc_root' => env('ERP_DOC_ROOT'),
        'url_root' => env('ERP_URL_ROOT', ''),
    ],

    'backend' => [
        'url' => env('API_BASE_URL'),
    ],

];
