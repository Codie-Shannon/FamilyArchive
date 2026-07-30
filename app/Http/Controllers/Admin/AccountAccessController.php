<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\OriginalAccessGrant;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Domain\Media\Models\MediaItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AccountAccessController extends Controller
{
    private const ROLES = ['viewer', 'contributor', 'trusted_contributor', 'admin', 'owner'];

    private const STATES = ['pending', 'approved', 'rejected', 'suspended'];

    public function index(): View
    {
        return view('admin.access.index', [
            'users' => User::query()->orderByRaw("case account_state when 'pending' then 0 when 'approved' then 1 else 2 end")->orderBy('name')->get(),
            'invitations' => UserInvitation::query()->latest()->limit(20)->get(),
            'branches' => FamilyBranch::query()->orderBy('name')->get(),
            'events' => AccountAccessEvent::query()->latest('created_at')->limit(20)->get(),
            'grants' => OriginalAccessGrant::query()->latest()->limit(20)->get(),
            'mediaItems' => MediaItem::query()->whereNotNull('approved_at')->orderBy('archive_id')->get(['id', 'archive_id', 'title']),
            'submissions' => ContributorSubmission::query()->with(['contributor', 'session', 'incomingUpload'])->latest()->limit(30)->get(),
            'roles' => self::ROLES,
            'states' => self::STATES,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'family_branch_id' => ['nullable', 'exists:family_branches,id'],
        ]);
        $token = Str::random(64);
        $invitation = UserInvitation::query()->create([
            ...$validated,
            'invitation_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('invitation_url', route('invitation.show', [$invitation->invitation_id, $token]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'account_state' => ['required', Rule::in(self::STATES)],
            'family_branch_id' => ['nullable', 'exists:family_branches,id'],
            'family_connection' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $before = $user->only(['role', 'account_state', 'family_branch_id', 'family_connection']);
        $after = [
            'role' => $validated['role'],
            'account_state' => $validated['account_state'],
            'family_branch_id' => $validated['family_branch_id'] ?? null,
            'family_connection' => $validated['family_connection'] ?? null,
        ];

        abort_if(
            $user->role === 'owner'
            && $user->account_state === 'approved'
            && ($after['role'] !== 'owner' || $after['account_state'] !== 'approved')
            && User::query()->where('role', 'owner')->where('account_state', 'approved')->count() <= 1,
            422,
            'The last approved owner cannot be demoted or suspended.'
        );

        DB::transaction(function () use ($request, $user, $before, $after, $validated): void {
            $user->forceFill($after)->save();
            AccountAccessEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'account_access_updated',
                'previous_values' => $before,
                'new_values' => $after,
                'reason' => $validated['reason'],
            ]);
        });

        return back()->with('status', 'Account access updated.');
    }

    public function grant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'media_item_id' => ['required', 'exists:media_items,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        OriginalAccessGrant::query()->create([
            ...$validated,
            'granted_by' => $request->user()->id,
            'effective_at' => now(),
        ]);

        return back()->with('status', 'Original access granted.');
    }

    public function revoke(Request $request, OriginalAccessGrant $grant): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        abort_if($grant->revoked_at !== null, 422, 'This grant is already revoked.');
        $grant->forceFill(['revoked_at' => now(), 'revocation_reason' => $validated['reason']])->save();

        return back()->with('status', 'Original access revoked.');
    }
}
