<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\AccountAccessEvent;
use App\Domain\Access\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Str;

final class MemberAccessService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param  array{name:string,email?:string|null,username?:string|null,role:string,family_branch_id?:int|null}  $attributes
     * @return array{invitation:UserInvitation,card:array{purpose:string,name:string,username:string|null,code:string,url:string,expires_at:string}}
     */
    public function createSetup(User $actor, array $attributes): array
    {
        $username = $this->availableUsername($attributes['username'] ?? null, $attributes['name']);
        [$code, $hash] = $this->code();
        $invitation = UserInvitation::query()->create([
            'invitation_id' => (string) Str::uuid(),
            'name' => trim($attributes['name']),
            'username' => $username,
            'email' => filled($attributes['email'] ?? null) ? Str::lower(trim((string) $attributes['email'])) : null,
            'role' => $attributes['role'],
            'purpose' => 'setup',
            'family_branch_id' => $attributes['family_branch_id'] ?? null,
            'token_hash' => $hash,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        return $this->result($invitation, $code);
    }

    /** @return array{invitation:UserInvitation,card:array{purpose:string,name:string,username:string|null,code:string,url:string,expires_at:string}} */
    public function createRecovery(User $actor, User $member, string $reason): array
    {
        UserInvitation::query()
            ->where('purpose', 'recovery')
            ->where('target_user_id', $member->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        [$code, $hash] = $this->code();
        $invitation = UserInvitation::query()->create([
            'invitation_id' => (string) Str::uuid(),
            'name' => $member->name,
            'username' => $member->username,
            'email' => $member->email,
            'role' => $member->role,
            'purpose' => 'recovery',
            'target_user_id' => $member->id,
            'family_branch_id' => $member->family_branch_id,
            'token_hash' => $hash,
            'invited_by' => $actor->id,
            'expires_at' => now()->addDay(),
        ]);
        AccountAccessEvent::query()->create([
            'user_id' => $member->id,
            'actor_id' => $actor->id,
            'event_type' => 'recovery_access_issued',
            'new_values' => ['expires_at' => $invitation->expires_at->toIso8601String()],
            'reason' => trim($reason),
        ]);

        return $this->result($invitation, $code);
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    private function availableUsername(?string $requested, string $name): string
    {
        $base = Str::of($requested ?: $name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '.')->trim('.')->limit(64, '')->toString();
        $base = $base !== '' ? $base : 'family.member';
        $candidate = $base;
        $suffix = 2;

        while (User::query()->where('username', $candidate)->exists()
            || UserInvitation::query()->where('username', $candidate)->whereNull('revoked_at')->whereNull('accepted_at')->exists()) {
            $candidate = $base.'.'.$suffix++;
        }

        return $candidate;
    }

    /** @return array{0:string,1:string} */
    private function code(): array
    {
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $hash = hash('sha256', $code);
        } while (UserInvitation::query()->where('token_hash', $hash)->exists());

        return [$code, $hash];
    }

    /** @return array{invitation:UserInvitation,card:array{purpose:string,name:string,username:string|null,code:string,url:string,expires_at:string}} */
    private function result(UserInvitation $invitation, string $code): array
    {
        return [
            'invitation' => $invitation,
            'card' => [
                'purpose' => $invitation->purpose,
                'name' => $invitation->name,
                'username' => $invitation->username,
                'code' => implode('-', str_split($code, 4)),
                'url' => route('invitation.show', [$invitation->invitation_id, $code]),
                'expires_at' => $invitation->expires_at->format('j M Y, g:i a'),
            ],
        ];
    }
}
