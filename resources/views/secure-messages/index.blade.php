<x-layouts::app title="Secure Messages">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Private and consent-first</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Messages</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">Review conversation requests and private attachments without exposing archive originals.</p>
            </div>
            <div class="space-y-3 text-right">
                <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-100">
                    Your private inbox
                </div>
                <nav class="flex justify-end gap-2 text-sm">
                    <a href="{{ route('secure-messages.index') }}" class="rounded-lg border px-3 py-2 {{ $activeView === 'consent' ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 text-zinc-400' }}">Requests</a>
                    <a href="{{ route('secure-messages.index', ['view' => 'attachments']) }}" class="rounded-lg border px-3 py-2 {{ $activeView === 'attachments' ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 text-zinc-400' }}">Attachments</a>
                </nav>
            </div>
        </header>

        <section class="grid gap-5 {{ $activeView === 'consent' ? 'xl:grid-cols-[0.9fr_1.1fr]' : '' }}">
            @if($activeView === 'consent')
            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">Anonymous public identities</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">Direct-message consent</h2>
                        <p class="mt-2 text-sm text-zinc-400">The recipient decides who may contact them. Routine private conversations do not require owner approval.</p>
                    </div>
                    <span class="text-sm text-zinc-500">{{ $threads->count() }} requests</span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse($threads as $thread)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ $thread['alias_name'] }}</p>
                                <span class="text-xs font-semibold uppercase tracking-wide {{ $thread['state'] === 'accepted' ? 'text-emerald-300' : ($thread['state'] === 'pending' ? 'text-amber-300' : 'text-zinc-500') }}">{{ $thread['state'] }}</span>
                            </div>
                            <p class="mt-2 text-sm text-zinc-500">
                                {{ $thread['state'] === 'pending' ? 'No message content is available until you explicitly consent.' : 'Consent state recorded without revealing a real identity.' }}
                            </p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400">No public DM requests.</p>
                    @endforelse
                </div>
            </article>
            @endif

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">Versioned envelopes</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">Encrypted message state</h2>
                    </div>
                    <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-300">
                        {{ $encryptionEnabled ? 'Runtime enabled' : 'Runtime setup required' }}
                    </span>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse($envelopes as $envelope)
                        <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-white">{{ $envelope['sender_label'] }}</p>
                                <span class="text-xs uppercase tracking-wide text-emerald-300">Protocol v{{ $envelope['protocol_version'] }}</span>
                            </div>
                            <div class="mt-3 flex items-center gap-3 text-sm text-zinc-500">
                                <span class="grid size-8 place-items-center rounded-full bg-emerald-950 text-emerald-300">◆</span>
                                <span>Encrypted payload retained · plaintext never rendered</span>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400">No accepted encrypted envelopes.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section id="attachment-states" class="rounded-xl border {{ $activeView === 'attachments' ? 'border-emerald-900' : 'border-zinc-700' }} bg-zinc-900 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-emerald-300">Private attachment pipeline</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Attachment scan states</h2>
                </div>
                <p class="text-sm text-zinc-500">Authorized accepted threads only</p>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @forelse($attachments as $attachment)
                    <article class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-wide {{ $attachment['scan_state'] === 'clean' ? 'text-emerald-300' : ($attachment['scan_state'] === 'pending' ? 'text-amber-300' : 'text-red-300') }}">{{ $attachment['scan_state'] }}</span>
                            <span class="text-xs text-zinc-600">{{ number_format($attachment['bytes'] / 1024, 1) }} KB</span>
                        </div>
                        <p class="mt-4 font-semibold text-white">{{ $attachment['original_name'] }}</p>
                        <p class="mt-2 text-sm text-zinc-500">{{ $attachment['mime_type'] }}</p>
                        <p class="mt-4 text-xs text-zinc-600">
                            {{ $attachment['scan_state'] === 'clean' ? 'Available inside the authorized thread.' : 'Unavailable until the scan boundary permits access.' }}
                        </p>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400 md:col-span-3">No attachment records.</p>
                @endforelse
            </div>
        </section>

        <aside class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 text-zinc-300">
            Ciphertext, wrapped keys, digests, storage paths, moderation fingerprints and plaintext private messages are excluded from this view.
        </aside>
    </main>
</x-layouts::app>
