<?php

use App\Services\Channels\EmailDriver;
use App\Services\Channels\PortalDriver;
use App\Services\Channels\SmsDriver;

return [
    'drivers' => [
        'email' => EmailDriver::class,
        'sms' => SmsDriver::class,
        'portal' => PortalDriver::class,
    ],
    'sms' => [
        'url' => env('SMS_URL', 'http://112.35.1.155:1992/sms/norsubmit'),
        'ec_name' => env('SMS_EC_NAME'),
        'ap_id' => env('SMS_AP_ID'),
        'secret_key' => env('SMS_SECRET_KEY'),
        'sign' => env('SMS_SIGN'),
        'add_serial' => env('SMS_ADD_SERIAL', ''),
    ],
    'portal' => [
        'base_url' => env('PORTAL_BASE_URL'),
        'poa_token' => env('PORTAL_POA_TOKEN'),
        'token_url' => env('PORTAL_TOKEN_URL'),
        'client_id' => env('PORTAL_CLIENT_ID'),
        'client_secret' => env('PORTAL_CLIENT_SECRET'),
        'scope' => env('PORTAL_SCOPE', 'ttc:v1:writeTransaction'),
        'app_id' => env('PORTAL_APP_ID'),
    ],
];
