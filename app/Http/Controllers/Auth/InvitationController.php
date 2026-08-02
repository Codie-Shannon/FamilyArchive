<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\UserInvitation;
use App\Domain\Access\Services\MemberAccessService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

final class InvitationController extends Controller
{
    public function show(string $invitationId, string $token): View
    {
        $invitation = $this->invitation($invitationId, $token);

        return view('auth.invitation', compact('invitation', 'token'));
    }

    public function code(): View
    {
        return view('auth.access-code');
    }

    public function find(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $code = MemberAccessService::normalizeCode($validated['code']);
        $invitation = UserInvitation::query()->where('token_hash', hash('sha256', $code))->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            return back()->withErrors(['code' => 'That access code is invalid, expired or already used.'])->withInput();
        }

        return redirect()->route('invitation.show', [$invitation->invitation_id, $code]);
    }

    public function accept(Request $request, string $invitationId, string $token): RedirectResponse
    {
        $invitation = $this->invitation($invitationId, $token);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $locked = UserInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($locked->isUsable(), 410, 'This invitation is no longer available.');

            if ($locked->purpose === 'recovery') {
                $user = User::query()->lockForUpdate()->findOrFail($locked->target_user_id);
                $user->forceFill(['password' => $validated['password']])->save();
                $locked->forceFill(['accepted_at' => now()])->save();
                AccountAccessEvent::query()->create([
                    'user_id' => $user->id,
                    'actor_id' => $locked->invited_by,
                    'event_type' => 'recovery_access_used',
                    'reason' => 'A one-time assisted recovery code was used.',
                ]);

                return $user;
            }

            abort_if($locked->email !== null && User::query()->where('email', $locked->email)->exists(), 422, 'An account already uses this email address.');
            abort_if($locked->username !== null && User::query()->where('username', $locked->username)->exists(), 422, 'An account already uses this member name.');

            $user = User::query()->create([
                'name' => $locked->name,
                'username' => $locked->username,
                'email' => $locked->email,
                'password' => $validated['password'],
                'role' => $locked->role,
                'account_state' => 'pending',
                'family_branch_id' => $locked->family_branch_id,
            ]);
            if ($locked->email === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $locked->forceFill(['accepted_at' => now()])->save();
            AccountAccessEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $locked->invited_by,
                'event_type' => 'invitation_accepted',
                'new_values' => ['role' => $user->role, 'account_state' => 'pending', 'family_branch_id' => $user->family_branch_id],
                'reason' => $locked->email === null
                    ? 'Guided family access accepted; administrator approval remains required.'
                    : 'Invitation accepted; email verification and administrator approval remain required.',
            ]);

            return $user;
        });

        Auth::login($user);

        if ($invitation->purpose === 'recovery') {
            return redirect()->route($user->account_state === 'approved' ? 'dashboard' : 'account.waiting')
                ->with('status', 'Password updated. This access code cannot be used again.');
        }

        if ($user->email !== null) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        return redirect()->route('account.waiting')->with('status', 'Your account is ready for administrator approval.');
    }

    private function invitation(string $invitationId, string $token): UserInvitation
    {
        $invitation = UserInvitation::query()->where('invitation_id', $invitationId)->firstOrFail();
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 404);
        abort_unless($invitation->isUsable(), 410, 'This invitation is no longer available.');

        return $invitation;
    }
}
