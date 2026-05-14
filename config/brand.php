<?php

return [
    'name' => env('BRAND_NAME', 'Mythic Fox Games'),
    'contact_email' => env('BRAND_CONTACT_EMAIL'),
    'return_address' => [
        'name' => env('BRAND_RETURN_ADDRESS_NAME', 'Mythic Fox Games'),
        'line1' => env('BRAND_RETURN_ADDRESS_LINE1', '3030 Junction Bay'),
        'line2' => env('BRAND_RETURN_ADDRESS_LINE2', 'San Antonio, TX 78109'),
    ],
];
