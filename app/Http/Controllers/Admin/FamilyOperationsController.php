<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Services\MemberAccessService;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Operations\Services\DelegatedFamilyOperations;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class FamilyOperationsController extends Controller
{
    public function index(Request $request): View
    {
        $recoverableAccounts = User::query()
            ->whereIn('account_state', ['approved', 'pending', 'suspended']);

        if ($request->user()->role !== 'owner') {
            $recoverableAccounts->whereIn('role', ['viewer', 'contributor']);
        }

        return view('admin.family-operations', [
            'routineAccounts' => User::query()
                ->where('account_state', 'pending')
                ->whereIn('role', ['viewer', 'contributor'])
                ->orderBy('name')
                ->get(),
            'ownerExceptions' => User::query()
                ->where('account_state', 'pending')
                ->whereIn('role', ['trusted_contributor', 'admin', 'owner'])
                ->orderBy('name')
                ->get(),
            'branches' => FamilyBranch::query()->orderBy('name')->get(),
            'recoverableAccounts' => $recoverableAccounts->orderBy('name')->get(),
            'reportedMessages' => DB::table('conversation_messages')
                ->join('conversation_threads', 'conversation_threads.id', '=', 'conversation_messages.conversation_thread_id')
                ->join('users', 'users.id', '=', 'conversation_messages.author_id')
                ->where('conversation_messages.moderation_state', 'reported')
                ->select(['conversation_messages.id', 'conversation_messages.body', 'conversation_threads.subject', 'users.name as author_name'])
                ->latest('conversation_messages.updated_at')
                ->get(),
            'voiceMessages' => DB::table('voice_messages')
                ->join('users', 'users.id', '=', 'voice_messages.user_id')
                ->join('community_channels', 'community_channels.id', '=', 'voice_messages.community_channel_id')
                ->where('voice_messages.moderation_state', 'pending')
                ->select(['voice_messages.id', 'voice_messages.duration_seconds', 'voice_messages.mime_type', 'users.name as member_name', 'community_channels.name as channel_name'])
                ->latest('voice_messages.created_at')
                ->get(),
            'anonymousMessages' => DB::table('anonymous_messages')
                ->where('moderation_state', 'pending')
                ->select(['id', 'subject', 'body', 'created_at'])
                ->latest()
                ->get(),
        ]);
    }

    public function invite(Request $request, MemberAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'username' => ['nullable', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'role' => ['required', Rule::in(['viewer', 'contributor'])],
            'family_branch_id' => ['nullable', 'exists:family_branches,id'],
        ]);
        $result = $access->createSetup($request->user(), $validated);

        return back()->with('access_card', $result['card']);
    }

    public function recovery(Request $request, User $user, MemberAccessService $access): RedirectResponse
    {
        abort_if($request->user()->role !== 'owner' && ! in_array($user->role, ['viewer', 'contributor'], true), 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $result = $access->createRecovery($request->user(), $user, $validated['reason']);

        return back()->with('access_card', $result['card']);
    }

    public function account(Request $request, User $user, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'family_branch_id' => ['nullable', 'exists:family_branches,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $operations->decideRoutineAccount($request->user(), $user, $validated['decision'], $validated['family_branch_id'] ?? null, $validated['reason']);

        return back()->with('status', 'Routine account decision recorded.');
    }

    public function voice(Request $request, int $message, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $validated = $request->validate(['decision' => ['required', Rule::in(['allow', 'block'])]]);
        $operations->decideVoice($request->user(), $message, $validated['decision']);

        return back()->with('status', 'Voice moderation decision recorded.');
    }

    public function conversation(Request $request, int $message, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $validated = $request->validate(['decision' => ['required', Rule::in(['restore', 'hide'])]]);
        $operations->decideReportedConversation($request->user(), $message, $validated['decision']);

        return back()->with('status', 'Conversation report resolved.');
    }

    public function anonymous(Request $request, int $message, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $validated = $request->validate(['decision' => ['required', Rule::in(['accepted', 'spam', 'blocked'])]]);
        $operations->decideAnonymousContact($request->user(), $message, $validated['decision']);

        return back()->with('status', 'Anonymous contact decision recorded.');
    }
}
