<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\FamilyMessage;
use App\Domain\Communication\Models\FamilyMessageThread;
use App\Domain\Communication\Services\FamilyMessaging;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMessageController extends Controller
{
    public function __construct(private readonly FamilyMessaging $messaging) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'current_user' => ['id' => $user->id, 'name' => $user->name],
            'contacts' => $this->messaging->contacts($user)->map(fn (User $contact): array => [
                'id' => $contact->id,
                'name' => $contact->name,
                'role' => $contact->role,
            ]),
            'threads' => $this->messaging->threads($user),
        ]);
    }

    public function storeThread(Request $request): JsonResponse
    {
        $validated = $request->validate(['recipient_id' => ['required', 'integer', 'exists:users,id']]);
        /** @var User $user */
        $user = $request->user();
        $recipient = User::query()->whereKey((int) $validated['recipient_id'])->firstOrFail();
        $thread = $this->messaging->start($user, $recipient);

        return response()->json($this->messaging->conversation($user, $thread), 201);
    }

    public function show(Request $request, string $threadId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->messaging->conversation($user, $this->thread($threadId)));
    }

    public function storeMessage(Request $request, string $threadId): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        /** @var User $user */
        $user = $request->user();
        $thread = $this->thread($threadId);
        $this->messaging->send($user, $thread, $validated['message']);

        return response()->json($this->messaging->conversation($user, $thread), 201);
    }

    public function setting(Request $request, string $threadId): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'string', 'in:mute,unmute,archive,unarchive,block,unblock']]);
        /** @var User $user */
        $user = $request->user();
        $setting = $this->messaging->updateSetting($user, $this->thread($threadId), $validated['action']);

        return response()->json([
            'muted' => $setting->muted_at !== null,
            'archived' => $setting->archived_at !== null,
            'blocked' => $setting->blocked_at !== null,
        ]);
    }

    public function report(Request $request, string $messageId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $message = FamilyMessage::query()->where('message_id', $messageId)->firstOrFail();
        $this->messaging->report($user, $message);

        return response()->json(['status' => 'reported']);
    }

    private function thread(string $threadId): FamilyMessageThread
    {
        return FamilyMessageThread::query()->where('thread_id', $threadId)->firstOrFail();
    }
}
