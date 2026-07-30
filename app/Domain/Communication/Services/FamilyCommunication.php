<?php

namespace App\Domain\Communication\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FamilyCommunication
{
    public function post(User $author, int $threadId, string $body, ?int $parentId = null): int
    {
        $thread = DB::table('conversation_threads')->where('id', $threadId)->lockForUpdate()->first();

        if (! $thread || $thread->is_locked) {
            throw ValidationException::withMessages(['thread' => 'This conversation is unavailable or locked.']);
        }

        if ($author->account_state !== 'approved' || ! in_array($author->role, ['viewer', 'contributor', 'trusted_contributor', 'admin', 'owner'], true)) {
            throw ValidationException::withMessages(['author' => 'An approved family account is required.']);
        }

        $clean = trim($body);
        if (mb_strlen($clean) < 2 || mb_strlen($clean) > 4000) {
            throw ValidationException::withMessages(['body' => 'Messages must contain between 2 and 4000 characters.']);
        }

        return DB::table('conversation_messages')->insertGetId([
            'conversation_thread_id' => $threadId,
            'author_id' => $author->id,
            'parent_id' => $parentId,
            'body' => $clean,
            'moderation_state' => 'visible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function acceptAnonymous(string $subject, string $body, ?string $email, string $networkToken): string
    {
        $subject = trim($subject);
        $body = trim($body);

        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 120 || mb_strlen($body) < 10 || mb_strlen($body) > 4000) {
            throw ValidationException::withMessages(['message' => 'Provide a valid subject and message.']);
        }

        $messageId = (string) Str::uuid();
        DB::table('anonymous_messages')->insert([
            'message_id' => $messageId,
            'correlation_token' => hash_hmac('sha256', $networkToken, (string) config('app.key')),
            'reply_email' => filled($email) ? mb_strtolower(trim((string) $email)) : null,
            'subject' => $subject,
            'body' => $body,
            'moderation_state' => 'pending',
            'source_fingerprint' => hash('sha256', $networkToken),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $messageId;
    }
}
