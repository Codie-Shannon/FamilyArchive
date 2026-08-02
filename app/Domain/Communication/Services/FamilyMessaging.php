<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Models\FamilyMessage;
use App\Domain\Communication\Models\FamilyMessageParticipantSetting;
use App\Domain\Communication\Models\FamilyMessageThread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyMessaging
{
    /** @return Collection<int, User> */
    public function contacts(User $user): Collection
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->where('account_state', 'approved')
            ->whereIn('role', ['owner', 'admin', 'trusted_contributor', 'contributor', 'viewer'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'family_branch_id']);
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     person: array{id: int, name: string, role: string},
     *     preview: string,
     *     last_message_at: string|null,
     *     unread: int<0, max>,
     *     muted: bool,
     *     archived: bool,
     *     blocked: bool
     * }>
     */
    public function threads(User $user): array
    {
        return FamilyMessageThread::query()
            ->with(['userOne:id,name,role', 'userTwo:id,name,role', 'settings', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->where(fn ($query) => $query->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (FamilyMessageThread $thread) use ($user): array {
                $setting = $thread->settings->firstWhere('user_id', $user->id);
                $other = $thread->user_one_id === $user->id ? $thread->userTwo : $thread->userOne;
                $latest = $thread->messages->first();
                $unread = FamilyMessage::query()
                    ->where('thread_id', $thread->id)
                    ->where('sender_user_id', '!=', $user->id)
                    ->where('state', 'visible')
                    ->when($setting?->last_read_at, fn ($query, $date) => $query->where('created_at', '>', $date))
                    ->count();

                return [
                    'id' => $thread->thread_id,
                    'person' => ['id' => $other->id, 'name' => $other->name, 'role' => $other->role],
                    'preview' => $latest?->state === 'visible' ? Str::limit($latest->body, 72) : 'Message unavailable',
                    'last_message_at' => $thread->last_message_at?->toIso8601String(),
                    'unread' => $unread,
                    'muted' => $setting?->muted_at !== null,
                    'archived' => $setting?->archived_at !== null,
                    'blocked' => $setting?->blocked_at !== null,
                ];
            })
            ->values()
            ->all();
    }

    public function start(User $sender, User $recipient): FamilyMessageThread
    {
        $this->ensureEligible($sender);
        $this->ensureEligible($recipient);

        if ($sender->is($recipient)) {
            throw ValidationException::withMessages(['recipient_id' => 'Choose another family member.']);
        }

        [$one, $two] = collect([$sender->id, $recipient->id])->sort()->values()->all();

        return DB::transaction(function () use ($sender, $one, $two): FamilyMessageThread {
            $thread = FamilyMessageThread::query()->firstOrCreate(
                ['user_one_id' => $one, 'user_two_id' => $two],
                ['thread_id' => (string) Str::uuid(), 'started_by_user_id' => $sender->id],
            );

            foreach ([$one, $two] as $userId) {
                FamilyMessageParticipantSetting::query()->firstOrCreate([
                    'thread_id' => $thread->id,
                    'user_id' => $userId,
                ]);
            }

            FamilyMessageParticipantSetting::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $sender->id)
                ->update(['archived_at' => null]);

            return $thread;
        });
    }

    /** @return array<string, mixed> */
    public function conversation(User $user, FamilyMessageThread $thread): array
    {
        $this->ensureParticipant($user, $thread);
        $other = $thread->user_one_id === $user->id ? $thread->userTwo()->firstOrFail() : $thread->userOne()->firstOrFail();
        $setting = $this->setting($user, $thread);
        $setting->update(['last_read_at' => now()]);

        return [
            'id' => $thread->thread_id,
            'person' => ['id' => $other->id, 'name' => $other->name, 'role' => $other->role],
            'muted' => $setting->muted_at !== null,
            'archived' => $setting->archived_at !== null,
            'blocked' => $setting->blocked_at !== null,
            'blocked_by_other' => $thread->settings()->where('user_id', $other->id)->whereNotNull('blocked_at')->exists(),
            'messages' => $thread->messages()->with('sender:id,name')->oldest()->limit(250)->get()->map(fn (FamilyMessage $message): array => [
                'id' => $message->message_id,
                'mine' => $message->sender_user_id === $user->id,
                'sender' => $message->sender->name,
                'body' => $message->state === 'visible' ? $message->body : 'This message is unavailable.',
                'state' => $message->state,
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function send(User $sender, FamilyMessageThread $thread, string $body): FamilyMessage
    {
        $this->ensureParticipant($sender, $thread);
        $this->ensureEligible($sender);

        if ($thread->settings()->whereNotNull('blocked_at')->exists()) {
            throw ValidationException::withMessages(['message' => 'Messages are blocked in this conversation.']);
        }

        return DB::transaction(function () use ($sender, $thread, $body): FamilyMessage {
            $message = $thread->messages()->create([
                'message_id' => (string) Str::uuid(),
                'sender_user_id' => $sender->id,
                'body' => trim($body),
            ]);
            $thread->update(['last_message_at' => $message->created_at]);
            $thread->settings()->where('user_id', $sender->id)->update(['last_read_at' => now(), 'archived_at' => null]);

            return $message;
        });
    }

    public function updateSetting(User $user, FamilyMessageThread $thread, string $action): FamilyMessageParticipantSetting
    {
        $this->ensureParticipant($user, $thread);
        $setting = $this->setting($user, $thread);
        $values = match ($action) {
            'mute' => ['muted_at' => now()],
            'unmute' => ['muted_at' => null],
            'archive' => ['archived_at' => now()],
            'unarchive' => ['archived_at' => null],
            'block' => ['blocked_at' => now()],
            'unblock' => ['blocked_at' => null],
            default => throw ValidationException::withMessages(['action' => 'Choose a valid conversation action.']),
        };
        $setting->update($values);

        return $setting->refresh();
    }

    public function report(User $user, FamilyMessage $message): void
    {
        $this->ensureParticipant($user, $message->thread()->firstOrFail());
        if ($message->sender_user_id === $user->id) {
            throw ValidationException::withMessages(['message' => 'You cannot report your own message.']);
        }
        $message->update(['state' => 'reported', 'reported_by_user_id' => $user->id, 'reported_at' => now()]);
    }

    private function ensureEligible(User $user): void
    {
        if (! $user->isApprovedFamilyMember()) {
            throw new AuthorizationException('This account is not an approved family member.');
        }
    }

    private function ensureParticipant(User $user, FamilyMessageThread $thread): void
    {
        if (! in_array($user->id, [$thread->user_one_id, $thread->user_two_id], true)) {
            throw new AuthorizationException('You are not part of this conversation.');
        }
    }

    private function setting(User $user, FamilyMessageThread $thread): FamilyMessageParticipantSetting
    {
        return FamilyMessageParticipantSetting::query()->firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);
    }
}
