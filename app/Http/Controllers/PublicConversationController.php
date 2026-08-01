<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Services\FamilyCommunication;
use App\Domain\Operations\Services\DelegatedFamilyOperations;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PublicConversationController extends Controller
{
    public function index(): View
    {
        return view('public-chat', [
            'threads' => DB::table('conversation_threads')
                ->where('scope', 'public')
                ->latest()
                ->limit(20)
                ->get(),
            'messages' => DB::table('conversation_messages')
                ->join('users', 'users.id', '=', 'conversation_messages.author_id')
                ->where('conversation_messages.moderation_state', 'visible')
                ->select([
                    'conversation_messages.id',
                    'conversation_messages.author_id',
                    'conversation_messages.conversation_thread_id',
                    'conversation_messages.body',
                    'conversation_messages.created_at',
                    'users.name as author_name',
                ])
                ->oldest('conversation_messages.created_at')
                ->get()
                ->groupBy('conversation_thread_id'),
        ]);
    }

    public function message(Request $request, FamilyCommunication $communication): RedirectResponse
    {
        $data = $request->validate([
            'thread_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        /** @var User $author */
        $author = $request->user();
        $communication->post($author, (int) $data['thread_id'], $data['body']);

        return back()->with('status', 'Message posted to the family conversation.');
    }

    public function anonymous(Request $request, FamilyCommunication $communication): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'reply_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $communication->acceptAnonymous(
            $data['subject'],
            $data['body'],
            $data['reply_email'] ?? null,
            (string) $request->ip(),
        );

        return back()->with('status', 'Anonymous message entered moderation. No archive access was granted.');
    }

    public function report(Request $request, int $message, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $operations->reportConversation($request->user(), $message);

        return back()->with('status', 'Message reported for administrator review.');
    }
}
