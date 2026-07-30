<?php

namespace App\Domain\Community\Services;

use Carbon\CarbonInterface;

final class RealtimeStatus
{
    /** @return array{online: bool, typing: bool} */
    public function resolve(CarbonInterface $lastSeenAt, ?CarbonInterface $typingUntil, CarbonInterface $now): array
    {
        return [
            'online' => $lastSeenAt->greaterThanOrEqualTo($now->copy()->subSeconds((int) config('realtime_community.presence_ttl_seconds'))),
            'typing' => $typingUntil?->greaterThan($now) ?? false,
        ];
    }

    /** @return array{calls_enabled: bool, signalling_ready: bool, turn_ready: bool} */
    public function callReadiness(): array
    {
        return [
            'calls_enabled' => (bool) config('realtime_community.calls_enabled'),
            'signalling_ready' => filled(config('realtime_community.signalling_url')),
            'turn_ready' => (bool) config('realtime_community.turn_server_configured'),
        ];
    }
}
