<?php

return [
    'end_to_end_encryption' => [
        'enabled' => env('MESSAGING_E2EE_ENABLED', false),
        'protocol_version' => 1,
    ],
    'attachments' => [
        'max_kilobytes' => 25 * 1024,
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'audio/mpeg', 'audio/mp4', 'application/pdf'],
    ],
    'guidance_bot' => [
        'enabled' => env('GUIDANCE_BOT_ENABLED', false),
        'may_access_private_archive' => false,
    ],
    'providers' => [
        'whatsapp' => [
            'mode' => 'business_cloud_api',
            'configured' => filled(env('WHATSAPP_ACCESS_TOKEN')) && filled(env('WHATSAPP_PHONE_NUMBER_ID')),
            'personal_chat_federation' => false,
        ],
        'messenger' => [
            'mode' => 'messenger_platform',
            'configured' => filled(env('MESSENGER_PAGE_ACCESS_TOKEN')),
            'personal_chat_federation' => false,
        ],
    ],
];
