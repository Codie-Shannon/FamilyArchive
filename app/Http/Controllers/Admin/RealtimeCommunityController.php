<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Community\Services\RealtimeStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class RealtimeCommunityController extends Controller
{
    public function __invoke(RealtimeStatus $status): View
    {
        return view('admin.community-operations', [
            'readiness' => $status->callReadiness(),
            'spaces' => DB::table('community_spaces')->count(),
            'memberships' => DB::table('community_memberships')->whereNull('suspended_at')->count(),
            'pendingVoiceMessages' => DB::table('voice_messages')->where('moderation_state', 'pending')->count(),
            'activeCalls' => DB::table('voice_call_sessions')->whereIn('state', ['signalling', 'active'])->count(),
            'recentCalls' => DB::table('voice_call_sessions')
                ->join('community_channels', 'community_channels.id', '=', 'voice_call_sessions.community_channel_id')
                ->join('community_spaces', 'community_spaces.id', '=', 'community_channels.community_space_id')
                ->join('users', 'users.id', '=', 'voice_call_sessions.started_by')
                ->select([
                    'community_spaces.name as space_name',
                    'community_channels.name as channel_name',
                    'users.name as started_by_name',
                    'voice_call_sessions.state',
                ])
                ->latest('voice_call_sessions.created_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
