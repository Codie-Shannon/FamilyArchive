<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\UserInvitation;
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

    public function accept(Request $request, string $invitationId, string $token): RedirectResponse
    {
        $invitation = $this->invitation($invitationId, $token);
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated): User {
            $locked = UserInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($locked->isUsable(), 410, 'This invitation is no longer available.');
            abort_if(User::query()->where('email', $locked->email)->exists(), 422, 'An account already uses this email address.');

            $user = User::query()->create([
                'name' => $locked->name,
                'email' => $locked->email,
                'password' => $validated['password'],
                'role' => $locked->role,
                'account_state' => 'pending',
                'family_branch_id' => $locked->family_branch_id,
            ]);
            $locked->forceFill(['accepted_at' => now()])->save();
            AccountAccessEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $locked->invited_by,
                'event_type' => 'invitation_accepted',
                'new_values' => ['role' => $user->role, 'account_state' => 'pending', 'family_branch_id' => $user->family_branch_id],
                'reason' => 'Invitation accepted; email verification and owner approval remain required.',
            ]);

            return $user;
        });

        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
    }

    private function invitation(string $invitationId, string $token): UserInvitation
    {
        $invitation = UserInvitation::query()->where('invitation_id', $invitationId)->firstOrFail();
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 404);
        abort_unless($invitation->isUsable(), 410, 'This invitation is no longer available.');

        return $invitation;
    }
}
