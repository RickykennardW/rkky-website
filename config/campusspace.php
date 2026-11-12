<?php

return [
    'timezone' => 'Asia/Jakarta',
    'verify_email' => env('CAMPUSSPACE_VERIFY_EMAIL', false),
    'legacy_auth' => [
        'url' => env('SUPABASE_URL'),
        'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
    ],
];
