<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Services\ProductionReadiness;
use App\Domain\Storage\Services\ArchiveProviderConfiguration;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class OwnerCommandCentreController extends Controller
{
    public function __invoke(
        Request $request,
        ProductionReadiness $readiness,
        ArchiveProviderConfiguration $providers,
    ): View {
        $requestedSection = $request->string('view')->toString();
        $section = in_array($requestedSection, ['overview', 'queue', 'family', 'system'], true)
            ? $requestedSection
            : 'overview';

        $queue = [
            'accounts' => User::query()
                ->where('account_state', 'pending')
                ->whereIn('role', ['trusted_contributor', 'admin', 'owner'])
                ->count(),
            'invitations' => DB::table('user_invitations')
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->count(),
            'intake_batches' => DB::table('cloud_import_sessions')
                ->where('provider', 'manual_export')
                ->whereIn('review_state', ['not_ready', 'preparing', 'ready', 'needs_attention'])
                ->count(),
            'intake_exceptions' => (int) DB::table('cloud_import_sessions')
                ->where('provider', 'manual_export')
                ->whereIn('review_state', ['preparing', 'ready', 'needs_attention'])
                ->sum('attention_count'),
            'duplicates' => DB::table('duplicate_candidates')
                ->where('review_state', 'pending_review')
                ->count(),
            'restoration' => DB::table('restoration_candidates')
                ->where('review_state', 'pending')
                ->count(),
            'repairs' => DB::table('repair_cases')
                ->whereNotIn('state', ['closed'])
                ->count(),
        ];

        $report = $readiness->report();
        $passedGates = collect($report['gates'])->filter(static fn (bool $passed): bool => $passed)->count();

        return view('admin.dashboard', [
            'section' => $section,
            'queue' => $queue,
            'queueTotal' => collect($queue)->except('invitations')->sum(),
            'approvedMembers' => User::query()->where('account_state', 'approved')->count(),
            'archiveRecords' => DB::table('media_items')->whereNotNull('approved_at')->count(),
            'integrityWarnings' => DB::table('integrity_checks')->where('result', '!=', 'verified')->count(),
            'failedJobs' => DB::table('processing_jobs')->where('state', 'failed')->count(),
            'storage' => $providers->status(),
            'readiness' => $report,
            'passedGates' => $passedGates,
            'gateTotal' => count($report['gates']),
        ]);
    }
}
