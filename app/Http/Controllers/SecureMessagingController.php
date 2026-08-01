<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Services\DelegatedFamilyOperations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SecureMessagingController extends Controller
{
    public function __invoke(): View
    {
        $recipientId = (int) request()->user()->getAuthIdentifier();
        $threads = $this->threads($recipientId);
        $acceptedThreadIds = $threads
            ->where('state', 'accepted')
            ->pluck('internal_id')
            ->all();

        return view('secure-messages.index', [
            'threads' => $threads,
            'envelopes' => $this->envelopes($acceptedThreadIds),
            'attachments' => $this->attachments($acceptedThreadIds),
            'encryptionEnabled' => (bool) config('communication_bridges.end_to_end_encryption.enabled'),
            'protocolVersion' => (int) config('communication_bridges.end_to_end_encryption.protocol_version'),
            'activeView' => request()->string('view')->toString() === 'attachments' ? 'attachments' : 'consent',
        ]);
    }

    public function consent(Request $request, int $thread, DelegatedFamilyOperations $operations): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'block'])],
        ]);
        $operations->decideDirectMessage($request->user(), $thread, $validated['decision']);

        return back()->with('status', 'Your message-request decision was recorded.');
    }

    /** @return Collection<int, array{internal_id: int, alias_name: string, state: string}> */
    private function threads(int $recipientId): Collection
    {
        return DB::table('public_direct_threads')
            ->join('public_identity_aliases', 'public_identity_aliases.id', '=', 'public_direct_threads.initiator_alias_id')
            ->where('public_direct_threads.recipient_user_id', $recipientId)
            ->select([
                'public_direct_threads.id as internal_id',
                'public_identity_aliases.display_name as alias_name',
                'public_direct_threads.state',
            ])
            ->latest('public_direct_threads.created_at')
            ->get()
            ->map(function (object $thread): array {
                $row = (array) $thread;

                return [
                    'internal_id' => (int) $row['internal_id'],
                    'alias_name' => (string) $row['alias_name'],
                    'state' => (string) $row['state'],
                ];
            });
    }

    /**
     * @param  array<int, int>  $threadIds
     * @return Collection<int, array{protocol_version: int, sender_label: string}>
     */
    private function envelopes(array $threadIds): Collection
    {
        if ($threadIds === []) {
            return collect();
        }

        return DB::table('encrypted_message_envelopes')
            ->leftJoin('users', 'users.id', '=', 'encrypted_message_envelopes.sender_user_id')
            ->leftJoin('public_identity_aliases', 'public_identity_aliases.id', '=', 'encrypted_message_envelopes.sender_alias_id')
            ->where('encrypted_message_envelopes.conversation_type', 'public_direct_thread')
            ->whereIn('encrypted_message_envelopes.conversation_id', $threadIds)
            ->select([
                'encrypted_message_envelopes.protocol_version',
                'users.name as sender_user_name',
                'public_identity_aliases.display_name as sender_alias_name',
            ])
            ->latest('encrypted_message_envelopes.created_at')
            ->get()
            ->map(function (object $envelope): array {
                $row = (array) $envelope;

                return [
                    'protocol_version' => (int) $row['protocol_version'],
                    'sender_label' => (string) ($row['sender_user_name'] ?? $row['sender_alias_name'] ?? 'Anonymous sender'),
                ];
            });
    }

    /**
     * @param  array<int, int>  $threadIds
     * @return Collection<int, array{original_name: string, mime_type: string, bytes: int, scan_state: string}>
     */
    private function attachments(array $threadIds): Collection
    {
        if ($threadIds === []) {
            return collect();
        }

        return DB::table('message_attachments')
            ->join('encrypted_message_envelopes', 'encrypted_message_envelopes.id', '=', 'message_attachments.encrypted_message_envelope_id')
            ->where('encrypted_message_envelopes.conversation_type', 'public_direct_thread')
            ->whereIn('encrypted_message_envelopes.conversation_id', $threadIds)
            ->select([
                'message_attachments.original_name',
                'message_attachments.mime_type',
                'message_attachments.bytes',
                'message_attachments.scan_state',
            ])
            ->latest('message_attachments.created_at')
            ->get()
            ->map(function (object $attachment): array {
                $row = (array) $attachment;

                return [
                    'original_name' => (string) $row['original_name'],
                    'mime_type' => (string) $row['mime_type'],
                    'bytes' => (int) $row['bytes'],
                    'scan_state' => (string) $row['scan_state'],
                ];
            });
    }
}
