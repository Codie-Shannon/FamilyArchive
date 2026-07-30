<?php

return [
    'enabled' => env('PORTFOLIO_DEMO_MODE', false),
    'dataset' => 'fictional-aotearoa-family',
    'owner_email' => env('PORTFOLIO_DEMO_OWNER_EMAIL', 'archive-owner@example.test'),
    'password' => env('PORTFOLIO_DEMO_PASSWORD'),
    'write_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
    'positioning' => [
        'core' => [
            'Preservation integrity and provenance',
            'Human-reviewed metadata and restoration',
            'Private family access and collaboration',
            'Cloud import, backup and recovery',
            'Privacy-safe public stories and maps',
        ],
        'supporting' => [
            'Encrypted family messaging and attachments',
            'Presence, typing and voice notes',
        ],
        'deferred' => [
            'Large public community servers',
            'Anonymous public posting and public direct messages',
            'Voice calls',
            'Guidance bots',
            'WhatsApp and Messenger publishing bridges',
            'Broad social publishing',
        ],
    ],
];
