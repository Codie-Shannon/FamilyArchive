<?php

namespace Database\Seeders;

use App\Domain\Communication\Models\FamilyMessage;
use App\Domain\Communication\Models\FamilyMessageParticipantSetting;
use App\Domain\Communication\Models\FamilyMessageThread;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class ScreenshotGroup31DemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG31 dataset is local-only.');

        $mary = $this->user('sg31-mary@example.test', 'Aunty Mary', 'contributor');
        $jordan = $this->user('sg31-jordan@example.test', 'Jordan Vale', 'owner');
        $morgan = $this->user('sg31-morgan@example.test', 'Morgan Harbour', 'viewer');
        $casey = $this->user('sg31-casey@example.test', 'Casey Harbour', 'admin');
        $this->user('sg31-evelyn@example.test', 'Evelyn Shore', 'trusted_contributor');

        $jordanThread = $this->thread(
            '31000000-0000-4000-8000-000000000001',
            $mary,
            $jordan,
            $mary,
            now()->subMinutes(4),
        );
        $this->message($jordanThread, '31000000-0000-4000-8000-000000000002', $jordan, 'I added the harbour picnic dates to the anniversary album.', now()->subMinutes(18));
        $this->message($jordanThread, '31000000-0000-4000-8000-000000000003', $mary, 'Perfect. I found another photograph from that afternoon.', now()->subMinutes(11));
        $this->message($jordanThread, '31000000-0000-4000-8000-000000000004', $jordan, 'Send it through when you are ready and I will add the caption.', now()->subMinutes(4));
        $reported = FamilyMessage::query()->where('message_id', '31000000-0000-4000-8000-000000000002')->firstOrFail();
        $reported->forceFill([
            'state' => 'reported',
            'reported_by_user_id' => $mary->id,
            'reported_at' => now()->subMinutes(14),
        ])->save();

        $morganThread = $this->thread(
            '31000000-0000-4000-8000-000000000005',
            $mary,
            $morgan,
            $morgan,
            now()->subMinutes(22),
        );
        $this->message($morganThread, '31000000-0000-4000-8000-000000000006', $morgan, 'Do you remember which summer the wharf photograph was taken?', now()->subMinutes(22));

        $caseyThread = $this->thread(
            '31000000-0000-4000-8000-000000000007',
            $mary,
            $casey,
            $mary,
            now()->subHours(2),
        );
        $this->message($caseyThread, '31000000-0000-4000-8000-000000000008', $mary, 'The new album labels are much easier to follow.', now()->subHours(2));
        FamilyMessageParticipantSetting::query()
            ->where('thread_id', $caseyThread->id)
            ->where('user_id', $mary->id)
            ->update(['muted_at' => now()->subHour()]);

        FamilyMessageParticipantSetting::query()
            ->where('thread_id', $jordanThread->id)
            ->where('user_id', $mary->id)
            ->update(['last_read_at' => now()->subMinutes(5)]);
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'username' => 'sg31.'.str($name)->lower()->replace(' ', '.')->toString(),
            'password' => Hash::make('SG31Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG31 family member.',
        ])->save();

        return $user;
    }

    private function thread(string $threadId, User $one, User $two, User $starter, DateTimeInterface $lastMessageAt): FamilyMessageThread
    {
        [$firstId, $secondId] = collect([$one->id, $two->id])->sort()->values()->all();
        $thread = FamilyMessageThread::query()->updateOrCreate(
            ['thread_id' => $threadId],
            [
                'user_one_id' => $firstId,
                'user_two_id' => $secondId,
                'started_by_user_id' => $starter->id,
                'last_message_at' => $lastMessageAt,
            ],
        );

        foreach ([$one, $two] as $participant) {
            FamilyMessageParticipantSetting::query()->firstOrCreate([
                'thread_id' => $thread->id,
                'user_id' => $participant->id,
            ]);
        }

        return $thread;
    }

    private function message(FamilyMessageThread $thread, string $messageId, User $sender, string $body, DateTimeInterface $sentAt): void
    {
        FamilyMessage::query()->updateOrCreate(
            ['message_id' => $messageId],
            [
                'thread_id' => $thread->id,
                'sender_user_id' => $sender->id,
                'body' => $body,
                'state' => 'visible',
                'reported_by_user_id' => null,
                'reported_at' => null,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ],
        );
    }
}
