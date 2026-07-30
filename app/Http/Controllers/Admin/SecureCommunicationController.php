<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Communication\Services\SecureCommunicationPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SecureCommunicationController extends Controller
{
    public function __invoke(SecureCommunicationPolicy $policy): View
    {
        return view('admin.secure-communication', [
            'bridges' => $policy->bridgeReadiness(),
            'pendingThreads' => DB::table('public_direct_threads')->where('state', 'pending')->count(),
            'quarantined' => DB::table('messaging_bridge_deliveries')->where('state', 'quarantined')->count(),
            'botEnabled' => (bool) config('communication_bridges.guidance_bot.enabled'),
            'botMayAccessPrivateArchive' => (bool) config('communication_bridges.guidance_bot.may_access_private_archive'),
            'botInteractions' => DB::table('guidance_bot_interactions')->count(),
            'privateArchiveViolations' => DB::table('guidance_bot_interactions')->where('private_archive_accessed', true)->count(),
            'encryptionEnabled' => (bool) config('communication_bridges.end_to_end_encryption.enabled'),
            'protocolVersion' => (int) config('communication_bridges.end_to_end_encryption.protocol_version'),
            'deliveries' => DB::table('messaging_bridge_deliveries')
                ->select(['provider', 'state', DB::raw('count(*) as delivery_count')])
                ->groupBy('provider', 'state')
                ->orderBy('provider')
                ->orderBy('state')
                ->get(),
        ]);
    }
}
