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

    'tcgplayer' => [
        'seller_id' => env('TCGPLAYER_SELLER_ID'),
        'seller_slug' => env('TCGPLAYER_SELLER_SLUG'),
        'storefront_url' => env('TCGPLAYER_SELLER_ID') && env('TCGPLAYER_SELLER_SLUG')
            ? sprintf('https://www.tcgplayer.com/sellers/%s/%s', env('TCGPLAYER_SELLER_SLUG'), env('TCGPLAYER_SELLER_ID'))
            : null,
    ],

    'scryfall' => [
        'base_url' => env('SCRYFALL_BASE_URL', 'https://api.scryfall.com'),
    ],

    'lorcast' => [
        'base_url' => env('LORCAST_BASE_URL', 'https://api.lorcast.com'),
    ],

    'fab_cards' => [
        'base_url' => env('FAB_CARDS_BASE_URL', 'https://raw.githubusercontent.com/the-fab-cube/flesh-and-blood-cards/main/json/english/'),
    ],

    'do_spaces' => [
        'key' => env('DO_SPACES_KEY'),
        'secret' => env('DO_SPACES_SECRET'),
        'region' => env('DO_SPACES_REGION'),
        'bucket' => env('DO_SPACES_BUCKET'),
        'endpoint' => env('DO_SPACES_ENDPOINT'),
    ],

];
