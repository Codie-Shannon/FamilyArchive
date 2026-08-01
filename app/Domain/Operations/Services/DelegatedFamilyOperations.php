<?php

namespace App\Domain\Operations\Services;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DelegatedFamilyOperations
{
    private const ROUTINE_ROLES = ['viewer', 'contributor'];

    public function decideRoutineAccount(User $actor, User $member, string $decision, ?int $branchId, string $reason): void
    {
        $this->requireOperator($actor);

        if (! in_array($member->role, self::ROUTINE_ROLES, true) || $member->account_state !== 'pending') {
            throw ValidationException::withMessages([
                'member' => 'Only pending viewer and contributor accounts belong in delegated review.',
            ]);
        }

        $state = match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']),
        };
        $before = $member->only(['role', 'account_state', 'family_branch_id', 'family_connection']);

        DB::transaction(function () use ($actor, $member, $branchId, $reason, $state, $before): void {
            $member->forceFill([
                'account_state' => $state,
                'family_branch_id' => $branchId,
            ])->save();

            AccountAccessEvent::query()->create([
                'user_id' => $member->id,
                'actor_id' => $actor->id,
                'event_type' => 'routine_account_decided',
                'previous_values' => $before,
                'new_values' => $member->only(['role', 'account_state', 'family_branch_id', 'family_connection']),
                'reason' => trim($reason),
            ]);
        });
    }

    public function decideVoice(User $actor, int $messageId, string $decision): void
    {
        $this->requireOperator($actor);
        $state = match ($decision) {
            'allow' => 'allowed',
            'block' => 'blocked',
            default => throw ValidationException::withMessages(['decision' => 'Choose allow or block.']),
        };

        $updated = DB::table('voice_messages')
            ->where('id', $messageId)
            ->where('moderation_state', 'pending')
            ->update(['moderation_state' => $state, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => 'This voice item is no longer awaiting review.']);
        }
    }

    public function decideReportedConversation(User $actor, int $messageId, string $decision): void
    {
        $this->requireOperator($actor);
        $state = match ($decision) {
            'restore' => 'visible',
            'hide' => 'hidden',
            default => throw ValidationException::withMessages(['decision' => 'Choose restore or hide.']),
        };

        $updated = DB::table('conversation_messages')
            ->where('id', $messageId)
            ->where('moderation_state', 'reported')
            ->update(['moderation_state' => $state, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => 'This report has already been resolved.']);
        }
    }

    public function decideAnonymousContact(User $actor, int $messageId, string $decision): void
    {
        $this->requireOperator($actor);

        if (! in_array($decision, ['accepted', 'spam', 'blocked'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose accept, spam or block.']);
        }

        $updated = DB::table('anonymous_messages')
            ->where('id', $messageId)
            ->where('moderation_state', 'pending')
            ->update(['moderation_state' => $decision, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => 'This anonymous contact has already been resolved.']);
        }
    }

    public function decideDirectMessage(User $recipient, int $threadId, string $decision): void
    {
        $state = match ($decision) {
            'accept' => 'accepted',
            'block' => 'blocked',
            default => throw ValidationException::withMessages(['decision' => 'Choose accept or block.']),
        };

        $updated = DB::table('public_direct_threads')
            ->where('id', $threadId)
            ->where('recipient_user_id', $recipient->id)
            ->where('state', 'pending')
            ->update(['state' => $state, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['thread' => 'This request is unavailable or already decided.']);
        }
    }

    public function reportConversation(User $member, int $messageId): void
    {
        abort_unless($member->account_state === 'approved', 403);

        $updated = DB::table('conversation_messages')
            ->where('id', $messageId)
            ->where('author_id', '!=', $member->id)
            ->where('moderation_state', 'visible')
            ->update(['moderation_state' => 'reported', 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['message' => 'This message is unavailable or already reported.']);
        }
    }

    private function requireOperator(User $actor): void
    {
        abort_unless($actor->canManageFamilyOperations(), 403);
    }
}
