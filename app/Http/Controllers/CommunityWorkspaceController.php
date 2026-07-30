<?php

namespace App\Http\Controllers;

use App\Domain\Community\Services\RealtimeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CommunityWorkspaceController extends Controller
{
    public function __invoke(RealtimeStatus $status): View
    {
        $userId = (int) request()->user()->getAuthIdentifier();
        $spaces = $this->visibleSpaces($userId);
        $selectedSpace = $spaces->first();

        if ($selectedSpace === null) {
            return view('community.index', [
                'spaces' => $spaces,
                'selectedSpace' => null,
                'channels' => collect(),
                'roles' => collect(),
                'presence' => collect(),
                'voiceMessages' => collect(),
                'readiness' => $status->callReadiness(),
            ]);
        }

        $selectedSpaceId = $selectedSpace['internal_id'];

        return view('community.index', [
            'spaces' => $spaces,
            'selectedSpace' => $selectedSpace,
            'channels' => DB::table('community_channels')
                ->where('community_space_id', $selectedSpaceId)
                ->select(['name', 'kind'])
                ->orderBy('kind')
                ->orderBy('name')
                ->get(),
            'roles' => DB::table('community_memberships')
                ->where('community_space_id', $selectedSpaceId)
                ->whereNull('suspended_at')
                ->select(['role', DB::raw('count(*) as member_count')])
                ->groupBy('role')
                ->orderBy('role')
                ->get(),
            'presence' => $this->presence($selectedSpaceId, $status),
            'voiceMessages' => DB::table('voice_messages')
                ->join('community_channels', 'community_channels.id', '=', 'voice_messages.community_channel_id')
                ->join('users', 'users.id', '=', 'voice_messages.user_id')
                ->join('community_memberships', function ($join) use ($selectedSpaceId): void {
                    $join->on('community_memberships.user_id', '=', 'users.id')
                        ->where('community_memberships.community_space_id', '=', $selectedSpaceId)
                        ->whereNull('community_memberships.suspended_at');
                })
                ->where('community_channels.community_space_id', $selectedSpaceId)
                ->where('voice_messages.moderation_state', 'allowed')
                ->select([
                    'users.name as member_name',
                    'community_channels.name as channel_name',
                    'voice_messages.duration_seconds',
                    'voice_messages.mime_type',
                ])
                ->latest('voice_messages.created_at')
                ->get(),
            'readiness' => $status->callReadiness(),
        ]);
    }

    /** @return Collection<int, array{internal_id: int, name: string, visibility: string, role: string}> */
    private function visibleSpaces(int $userId): Collection
    {
        return DB::table('community_spaces')
            ->join('community_memberships', 'community_memberships.community_space_id', '=', 'community_spaces.id')
            ->where('community_memberships.user_id', $userId)
            ->whereNull('community_memberships.suspended_at')
            ->select([
                'community_spaces.id as internal_id',
                'community_spaces.name',
                'community_spaces.visibility',
                'community_memberships.role',
            ])
            ->orderByRaw("case community_memberships.role when 'owner' then 0 when 'moderator' then 1 else 2 end")
            ->orderBy('community_spaces.name')
            ->get()
            ->map(function (object $space): array {
                $row = (array) $space;

                return [
                    'internal_id' => (int) $row['internal_id'],
                    'name' => (string) $row['name'],
                    'visibility' => (string) $row['visibility'],
                    'role' => (string) $row['role'],
                ];
            });
    }

    /** @return Collection<int, array{member_name: string, channel_name: string, state: string, typing: bool}> */
    private function presence(int $spaceId, RealtimeStatus $status): Collection
    {
        $now = now();

        return DB::table('community_presence')
            ->join('community_channels', 'community_channels.id', '=', 'community_presence.community_channel_id')
            ->join('users', 'users.id', '=', 'community_presence.user_id')
            ->join('community_memberships', function ($join) use ($spaceId): void {
                $join->on('community_memberships.user_id', '=', 'users.id')
                    ->where('community_memberships.community_space_id', '=', $spaceId)
                    ->whereNull('community_memberships.suspended_at');
            })
            ->where('community_channels.community_space_id', $spaceId)
            ->select([
                'users.name as member_name',
                'community_channels.name as channel_name',
                'community_presence.state',
                'community_presence.last_seen_at',
                'community_presence.typing_until',
            ])
            ->get()
            ->map(function (object $presence) use ($status, $now): array {
                $row = (array) $presence;
                $resolved = $status->resolve(
                    Carbon::parse((string) $row['last_seen_at']),
                    $row['typing_until'] === null ? null : Carbon::parse((string) $row['typing_until']),
                    $now,
                );

                return [
                    'member_name' => (string) $row['member_name'],
                    'channel_name' => (string) $row['channel_name'],
                    'state' => $resolved['online'] ? (string) $row['state'] : 'offline',
                    'typing' => $resolved['typing'],
                ];
            });
    }
}
