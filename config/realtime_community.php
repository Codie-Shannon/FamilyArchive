<?php

return [
    'presence_ttl_seconds' => 90,
    'typing_ttl_seconds' => 8,
    'voice_message_max_seconds' => 600,
    'calls_enabled' => env('COMMUNITY_CALLS_ENABLED', false),
    'signalling_url' => env('COMMUNITY_SIGNALLING_URL'),
    'turn_server_configured' => filled(env('COMMUNITY_TURN_URL')),
];
