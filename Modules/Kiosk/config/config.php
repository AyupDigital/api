<?php

return [
    'name' => 'Kiosk',
    'deploymentName' => 'Hounslow Connect Kiosk',
    'frontendUrl' => 'https://hounslowconnect.com',
    // 'apiBaseUrl' => env('APP_URL', 'https://api.hounslowconnect.com'),
    'apiBaseUrl' => 'https://api.hounslowconnect.com',
    'slack' => [
        'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
        'channel' => env('SLACK_CHANNEL'),
    ],
    'clicksend' => [
        'username' => env('CLICKSEND_USERNAME'),
        'api_key' => env('CLICKSEND_API_KEY'),
        'email_address_id' => env('CLICKSEND_EMAIL_ADDRESS_ID'),
        'email_address_name' => 'Hounslow Connect Kiosk',
        'letter_return_id' => '712062',
    ],
    'printnode' => [
        'api_key' => env('PRINTNODE_API_KEY'),
    ],
];
