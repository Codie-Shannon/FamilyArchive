<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WorkHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $intake = DB::table('cloud_import_sessions')
            ->where('provider', 'manual_export')
            ->whereIn('state', ['paused', 'complete']);

        if (! $user->isArchiveAdministrator()) {
            $intake->where('user_id', $user->id);
        }

        $summary = [
            'intake_batches' => (clone $intake)->count(),
            'intake_attention' => (int) (clone $intake)->sum('attention_count'),
            'routine_accounts' => $this->routineAccountCount($user),
            'family_review' => $this->familyReviewCount($user),
            'owner_exceptions' => $this->ownerExceptionCount($user),
        ];

        return view('work.index', [
            'user' => $user,
            'summary' => $summary,
            'roleLabel' => match ($user->role) {
                'owner' => 'Policy Owner',
                'admin' => 'Archive Administrator',
                default => 'Trusted Contributor',
            },
            'cards' => $this->cards($user, $summary),
        ]);
    }

    private function routineAccountCount(User $user): int
    {
        if (! $user->canManageFamilyOperations()) {
            return 0;
        }

        return User::query()
            ->where('account_state', 'pending')
            ->whereIn('role', ['viewer', 'contributor'])
            ->count();
    }

    private function familyReviewCount(User $user): int
    {
        if (! $user->canManageFamilyOperations()) {
            return 0;
        }

        return DB::table('conversation_messages')->where('moderation_state', 'reported')->count()
            + DB::table('voice_messages')->where('moderation_state', 'pending')->count()
            + DB::table('anonymous_messages')->where('moderation_state', 'pending')->count();
    }

    private function ownerExceptionCount(User $user): int
    {
        if ($user->role !== 'owner') {
            return 0;
        }

        return User::query()
            ->where('account_state', 'pending')
            ->whereIn('role', ['trusted_contributor', 'admin', 'owner'])
            ->count()
            + DB::table('duplicate_candidates')->where('review_state', 'pending_review')->count()
            + DB::table('restoration_candidates')->where('review_state', 'pending')->count()
            + DB::table('repair_cases')->whereNotIn('state', ['closed'])->count();
    }

    /**
     * @param  array<string, int>  $summary
     * @return array<int, array{label: string, count: int, detail: string, route: string, tone: string}>
     */
    private function cards(User $user, array $summary): array
    {
        if ($user->role === 'owner') {
            return [
                ['label' => 'Owner exceptions', 'count' => $summary['owner_exceptions'], 'detail' => 'Elevated roles, preservation exceptions and policy decisions only.', 'route' => route('admin.dashboard', ['view' => 'queue']), 'tone' => 'amber'],
                ['label' => 'Family operations', 'count' => $summary['routine_accounts'] + $summary['family_review'], 'detail' => 'Delegated queues remain visible without becoming Owner prerequisites.', 'route' => route('admin.family-operations.index'), 'tone' => 'emerald'],
                ['label' => 'Intake batches', 'count' => $summary['intake_batches'], 'detail' => 'Trusted contributors and administrators finish routine batch review.', 'route' => route('intake.index'), 'tone' => 'emerald'],
                ['label' => 'Access policy', 'count' => 0, 'detail' => 'Manage invitations, elevated roles and verified original-file grants.', 'route' => route('admin.access.index'), 'tone' => 'sky'],
            ];
        }

        if ($user->role === 'admin') {
            return [
                ['label' => 'Routine accounts', 'count' => $summary['routine_accounts'], 'detail' => 'Approve ordinary viewer and contributor accounts without escalation.', 'route' => route('admin.family-operations.index'), 'tone' => 'emerald'],
                ['label' => 'Reported activity', 'count' => $summary['family_review'], 'detail' => 'Resolve reported posts, voice exceptions and anonymous contact.', 'route' => route('admin.family-operations.index'), 'tone' => 'amber'],
                ['label' => 'Intake batches', 'count' => $summary['intake_batches'], 'detail' => 'Finish routine batch review and isolate only genuine exceptions.', 'route' => route('intake.index'), 'tone' => 'emerald'],
                ['label' => 'Needs attention', 'count' => $summary['intake_attention'], 'detail' => 'Open the shared intake workspace at its exception-first view.', 'route' => route('intake.index'), 'tone' => 'sky'],
            ];
        }

        return [
            ['label' => 'Your batches', 'count' => $summary['intake_batches'], 'detail' => 'Review and complete the photo batches you contributed.', 'route' => route('intake.index'), 'tone' => 'emerald'],
            ['label' => 'Need attention', 'count' => $summary['intake_attention'], 'detail' => 'Resolve uncertain items before completing the batch.', 'route' => route('intake.index'), 'tone' => 'amber'],
            ['label' => 'Add photos', 'count' => 0, 'detail' => 'Start another browser upload with your automation preferences.', 'route' => route('contributor.index'), 'tone' => 'sky'],
            ['label' => 'Archive', 'count' => 0, 'detail' => 'Return to approved family photos and reviewed knowledge.', 'route' => route('archive.index'), 'tone' => 'zinc'],
        ];
    }
}
