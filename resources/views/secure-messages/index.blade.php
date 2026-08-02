<x-layouts::app title="Contact requests">
    <main class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>
        @endif

        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Public contact inbox</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Contact requests</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">Choose whether people contacting you through the public site may connect, and check files attached to accepted requests.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" data-open-family-chat class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Open family chat</button>
                <a href="{{ route('dashboard') }}" class="w-fit rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-zinc-200">← Back to Home</a>
            </div>
        </header>

        <section class="rounded-2xl border border-emerald-800 bg-emerald-950/20 p-5 md:p-6">
            <p class="text-sm font-semibold text-emerald-300">Two clear boundaries</p>
            <h2 class="mt-1 text-xl font-semibold text-white">Family chat stays in the chat panel</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-300">Approved family members message one another immediately through Family chat. This page is only for people who contacted you from the public site and therefore need your consent.</p>
        </section>

        @php
            $pendingRequests = $threads->where('state', 'pending');
        @endphp

        <nav aria-label="Message sections">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('contact-requests.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold {{ $activeView === 'requests' ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 text-zinc-400' }}">
                    Requests @if($pendingRequests->isNotEmpty())<span class="ml-1 rounded-full bg-amber-400 px-2 py-0.5 text-xs text-zinc-950">{{ $pendingRequests->count() }}</span>@endif
                </a>
                <a href="{{ route('contact-requests.index', ['view' => 'attachments']) }}" class="rounded-lg border px-4 py-2 text-sm font-semibold {{ $activeView === 'attachments' ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200' : 'border-zinc-700 text-zinc-400' }}">Shared files</a>
            </div>
        </nav>

        @if($activeView === 'requests')
            <section class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">You decide</p>
                        <h2 class="mt-1 text-xl font-semibold text-white">Message requests</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-400">A request cannot reveal a private message until you accept it. The Owner does not decide for you.</p>
                    </div>
                    <span class="text-sm text-zinc-500">{{ $pendingRequests->count() }} waiting</span>
                </div>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @forelse($pendingRequests as $thread)
                        <article class="rounded-xl border border-zinc-700 bg-zinc-950 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">Would like to contact you</p>
                            <h3 class="mt-2 text-lg font-semibold text-white">{{ $thread['alias_name'] }}</h3>
                            <p class="mt-2 text-sm text-zinc-400">Accept to open a private conversation, or block the request. You can make this choice yourself.</p>
                            <form method="POST" action="{{ route('contact-requests.consent', $thread['internal_id']) }}" class="mt-5 grid gap-2 sm:grid-cols-2">
                                @csrf @method('PATCH')
                                <button name="decision" value="accept" class="rounded-lg bg-emerald-500 px-4 py-3 text-sm font-semibold text-zinc-950">Accept request</button>
                                <button name="decision" value="block" class="rounded-lg border border-zinc-700 px-4 py-3 text-sm font-semibold text-zinc-300">Block</button>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-700 p-8 text-center lg:col-span-2">
                            <p class="font-semibold text-white">You are all caught up</p>
                            <p class="mt-2 text-sm text-zinc-400">There are no message requests waiting for you.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-emerald-900 bg-zinc-900 p-5 md:p-6">
                <p class="text-sm font-semibold text-emerald-300">Accepted conversations only</p>
                <h2 class="mt-1 text-xl font-semibold text-white">Shared files</h2>
                <p class="mt-2 text-sm text-zinc-400">Files are checked before they become available in a private conversation.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($attachments as $attachment)
                        @php($fileState = match($attachment['scan_state']) { 'clean' => ['Ready', 'text-emerald-300'], 'pending' => ['Checking', 'text-amber-300'], default => ['Unavailable', 'text-red-300'] })
                        <article class="rounded-xl border border-zinc-700 bg-zinc-950 p-5">
                            <div class="flex items-center justify-between gap-3"><span class="text-xs font-semibold uppercase tracking-wide {{ $fileState[1] }}">{{ $fileState[0] }}</span><span class="text-xs text-zinc-600">{{ number_format($attachment['bytes'] / 1024, 1) }} KB</span></div>
                            <p class="mt-4 break-words font-semibold text-white">{{ $attachment['original_name'] }}</p>
                            <p class="mt-2 text-sm text-zinc-500">{{ $fileState[0] === 'Ready' ? 'Available in its accepted conversation.' : 'This file is not available while checks are incomplete.' }}</p>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-zinc-700 p-8 text-center text-zinc-400 md:col-span-2 xl:col-span-3">No files have been shared with you.</p>
                    @endforelse
                </div>
            </section>
        @endif

        <aside class="rounded-xl border border-emerald-900 bg-emerald-950/20 p-5">
            <div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300">✓</span><div><h2 class="font-semibold text-white">Consent by design</h2><p class="mt-1 text-sm leading-6 text-zinc-300">You decide whether a public contact may connect. Approved family conversations remain separate and never enter an Owner approval queue.</p></div></div>
        </aside>

        @if($showOperationalDetails)
            <details class="group rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4"><span><span class="block font-semibold text-white">Security and audit details</span><span class="mt-1 block text-sm text-zinc-400">Operational evidence for administrators and the Owner.</span></span><span class="text-xl text-emerald-300 transition group-open:rotate-45">+</span></summary>
                <div class="mt-5 grid gap-4 border-t border-zinc-800 pt-5 md:grid-cols-3">
                    <div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Runtime</p><p class="mt-2 font-semibold {{ $encryptionEnabled ? 'text-emerald-300' : 'text-amber-300' }}">{{ $encryptionEnabled ? 'Enabled' : 'Setup required' }}</p></div>
                    <div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Envelope version</p><p class="mt-2 font-semibold text-white">Protocol v{{ $protocolVersion }}</p></div>
                    <div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Protected records</p><p class="mt-2 font-semibold text-white">{{ $envelopes->count() }}</p></div>
                </div>
                <p class="mt-4 text-sm text-zinc-500">Ciphertext, wrapped keys, digests, storage paths, moderation fingerprints and plaintext remain excluded from this page.</p>
            </details>
        @endif
    </main>
</x-layouts::app>
