<x-layouts::app title="Family Community">
    <main class="mx-auto max-w-7xl space-y-7 p-6">
        <header class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Home · Family activity</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Family activity</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">Your family spaces, recent presence and approved voice notes in one place.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-100">
                <a href="{{ route('dashboard') }}" class="font-semibold">← Back to Home</a>
            </div>
        </header>

        @if($selectedSpace === null)
            <section class="rounded-2xl border border-dashed border-zinc-700 p-8 text-zinc-400">
                No active community membership is available.
            </section>
        @else
            <section class="grid gap-5 xl:grid-cols-[0.78fr_1.22fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-emerald-300">Your spaces</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Community directory</h2>
                        </div>
                        <span class="text-sm text-zinc-500">{{ $spaces->count() }} active</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach($spaces as $space)
                            <div class="rounded-lg border {{ $loop->first ? 'border-emerald-800 bg-emerald-950/20' : 'border-zinc-700 bg-zinc-950' }} p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-white">{{ $space['name'] }}</p>
                                    <span class="text-xs uppercase tracking-wide text-emerald-300">{{ $space['role'] }}</span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-500">{{ str($space['visibility'])->headline() }} visibility</p>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-semibold text-emerald-300">Selected space</p>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-2xl font-semibold text-white">{{ $selectedSpace['name'] }}</h2>
                        <span class="rounded-full bg-zinc-800 px-3 py-1 text-xs uppercase tracking-wide text-zinc-300">{{ $selectedSpace['role'] }}</span>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        @foreach($channels as $channel)
                            <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-white"># {{ $channel->name }}</p>
                                    <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $channel->kind }}</span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-500">Membership and channel permissions apply.</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach($roles as $role)
                            <span class="rounded-full border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-300">
                                {{ str($role->role)->headline() }} · {{ $role->member_count }}
                            </span>
                        @endforeach
                    </div>
                </article>
            </section>

            <section id="presence-voice" class="grid gap-5 xl:grid-cols-[1fr_1fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-emerald-300">Expiring signals</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Presence and typing</h2>
                        </div>
                        <span class="text-xs text-zinc-500">90s presence · 8s typing</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($presence as $member)
                            <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <div>
                                    <p class="font-semibold text-white">{{ $member['member_name'] }}</p>
                                    <p class="mt-1 text-sm text-zinc-500"># {{ $member['channel_name'] }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm {{ $member['state'] === 'offline' ? 'text-zinc-500' : 'text-emerald-300' }}">{{ str($member['state'])->headline() }}</p>
                                    @if($member['typing'])
                                        <p class="mt-1 text-xs text-emerald-300">Typing now…</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400">No current presence signals.</p>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-emerald-300">Moderated media</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Approved voice messages</h2>
                        </div>
                        <span class="text-xs text-zinc-500">Maximum 10 minutes</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($voiceMessages as $message)
                            <div class="rounded-lg border border-zinc-700 bg-zinc-950 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-white">{{ $message->member_name }}</p>
                                    <span class="text-xs uppercase tracking-wide text-emerald-300">Allowed</span>
                                </div>
                                <div class="mt-3 flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-full bg-emerald-400 font-semibold text-zinc-950">▶</span>
                                    <div class="h-1.5 flex-1 rounded-full bg-zinc-700">
                                        <div class="h-1.5 w-2/5 rounded-full bg-emerald-400"></div>
                                    </div>
                                    <span class="text-sm text-zinc-400">{{ gmdate('i:s', $message->duration_seconds) }}</span>
                                </div>
                                <p class="mt-3 text-xs text-zinc-600"># {{ $message->channel_name }} · {{ $message->mime_type }}</p>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-zinc-700 p-5 text-zinc-400">No approved voice messages.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <aside class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
                <span>Live voice calls are unavailable until signalling and TURN infrastructure pass interoperability testing.</span>
                <span class="rounded-full bg-amber-950 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-300">Setup required</span>
            </aside>
        @endif
    </main>
</x-layouts::app>
