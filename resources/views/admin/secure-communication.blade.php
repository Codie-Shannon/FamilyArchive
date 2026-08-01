<x-layouts::app title="Secure Communication">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Private family communication</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Secure and federated communication</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">Operational boundaries for encrypted envelopes, constrained guidance and official business messaging APIs.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                'DMs awaiting consent' => $pendingThreads,
                'Quarantined deliveries' => $quarantined,
                'Guidance interactions' => $botInteractions,
                'Private archive violations' => $privateArchiveViolations,
            ] as $label => $count)
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold text-white">{{ $count }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="space-y-5">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-semibold text-emerald-300">Guidance boundary</p>
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <h2 class="text-xl font-semibold text-white">Site-guidance bot</h2>
                        <span class="rounded-full bg-amber-950 px-3 py-1 text-xs uppercase tracking-wide text-amber-300">{{ $botEnabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <span class="text-zinc-300">Private archive access</span>
                            <span class="text-emerald-300">{{ $botMayAccessPrivateArchive ? 'Permitted' : 'Prohibited' }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                            <span class="text-zinc-300">Recorded violations</span>
                            <span class="{{ $privateArchiveViolations === 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $privateArchiveViolations }}</span>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-zinc-500">Only redacted prompts and responses may be retained. The bot cannot read private archive records.</p>
                </article>

                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-semibold text-emerald-300">Envelope runtime</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Encryption readiness</h2>
                    <div class="mt-4 flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                        <span class="text-zinc-300">Protocol version {{ $protocolVersion }}</span>
                        <span class="text-amber-300">{{ $encryptionEnabled ? 'Runtime enabled' : 'Runtime setup required' }}</span>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-zinc-500">Envelope validation is implemented. No plaintext, ciphertext, wrapped key or digest is rendered here.</p>
                </article>
            </div>

            <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                <p class="text-sm font-semibold text-emerald-300">Official integrations only</p>
                <h2 class="mt-1 text-xl font-semibold text-white">Business messaging bridges</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach($bridges as $provider => $bridge)
                        <div class="rounded-xl border border-zinc-700 bg-zinc-950 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-lg font-semibold text-white">{{ str($provider)->title() }}</p>
                                <span class="text-xs uppercase tracking-wide {{ $bridge['configured'] ? 'text-emerald-300' : 'text-amber-300' }}">
                                    {{ $bridge['configured'] ? 'Configured' : 'Credentials required' }}
                                </span>
                            </div>
                            <p class="mt-4 text-sm text-zinc-400">{{ str($bridge['mode'])->replace('_', ' ')->title() }}</p>
                            <p class="mt-2 text-sm text-zinc-500">Personal-chat federation is not supported.</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    <h3 class="font-semibold text-white">Sanitized delivery state</h3>
                    <div class="mt-3 space-y-2">
                        @forelse($deliveries as $delivery)
                            <div class="flex items-center justify-between rounded-lg border border-zinc-700 bg-zinc-950 px-4 py-3">
                                <span class="text-zinc-300">{{ str($delivery->provider)->title() }} · {{ str($delivery->state)->headline() }}</span>
                                <span class="text-zinc-500">{{ $delivery->delivery_count }}</span>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-zinc-700 p-4 text-zinc-400">No bridge delivery records.</p>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>

        <aside class="rounded-xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
            These are official WhatsApp Business Cloud API and Messenger Platform bridges only. They do not provide access to arbitrary personal chats.
        </aside>
    </main>
</x-layouts::app>
