<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Kapital Bank e-commerce (masa hesabının onlayn ödənişi). Merchant login/parol hər biznesin öz ayarlarındadır;
    // test rejimində login boşdursa bankın sənədlərdəki ümumi test hesabı işlədilir.
    'kapitalbank' => [
        'test_url' => env('KAPITALBANK_TEST_URL', 'https://txpgtst.kapitalbank.az/api'),
        'prod_url' => env('KAPITALBANK_PROD_URL', 'https://e-commerce.kapitalbank.az/api'),
        'test_login' => env('KAPITALBANK_TEST_LOGIN', 'TerminalSys/kapital'),
        'test_password' => env('KAPITALBANK_TEST_PASSWORD', 'kapital123'),
    ],

    // Cloudflare Turnstile (giriş və qeydiyyat captcha-sı). Açarlar boşdursa yoxlama söndürülür.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // Tokenin verildiyi sayt (siteverify cavabındakı hostname); boşdursa APP_URL-in hostu
        'hostnames' => array_filter(explode(',', (string) env('TURNSTILE_HOSTNAMES', parse_url((string) env('APP_URL'), PHP_URL_HOST)))),
    ],

];
