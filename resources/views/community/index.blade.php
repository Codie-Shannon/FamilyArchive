<x-layouts::app title="Family activity">
    <main class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Your family</p>
                <h1 class="mt-2 text-3xl font-semibold text-white">Family activity</h1>
                <p class="mt-2 max-w-3xl text-zinc-400">See your family rooms, who is around and the voice stories shared with you.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="w-fit rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-zinc-200">← Back to Home</a>
        </header>

        @if($selectedSpace === null)
            <section class="rounded-2xl border border-dashed border-zinc-700 p-8 text-center"><p class="font-semibold text-white">No family rooms yet</p><p class="mt-2 text-sm text-zinc-400">An administrator can add you to a family room when it is ready.</p></section>
        @else
            <section class="grid gap-5 xl:grid-cols-[0.78fr_1.22fr]">
                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-sm font-semibold text-emerald-300">Rooms you can access</p><h2 class="mt-1 text-xl font-semibold text-white">Your family rooms</h2></div><span class="text-sm text-zinc-500">{{ $spaces->count() }}</span></div>
                    <div class="mt-4 space-y-3">
                        @foreach($spaces as $space)
                            <div class="rounded-xl border {{ $loop->first ? 'border-emerald-800 bg-emerald-950/20' : 'border-zinc-700 bg-zinc-950' }} p-4"><div class="flex items-center justify-between gap-3"><p class="font-semibold text-white">{{ $space['name'] }}</p>@if($space['role'] !== 'member')<span class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ str($space['role'])->headline() }}</span>@endif</div><p class="mt-2 text-sm text-zinc-500">Shared with {{ $space['visibility'] === 'family' ? 'your family' : 'invited members' }}</p></div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
                    <p class="text-sm font-semibold text-emerald-300">Open room</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">{{ $selectedSpace['name'] }}</h2>
                    <p class="mt-2 text-sm text-zinc-400">Choose a conversation to read family updates or share a memory.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach($channels as $channel)
                            <div class="rounded-xl border border-zinc-700 bg-zinc-950 p-4"><p class="font-semibold text-white">{{ str($channel->name)->replace('-', ' ')->headline() }}</p><p class="mt-2 text-sm text-zinc-500">{{ $channel->kind === 'voice' ? 'Voice stories and recordings' : 'Family conversation' }}</p></div>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm text-zinc-500">{{ $roles->sum('member_count') }} active {{ Str::plural('member', $roles->sum('member_count')) }}</p>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
                    <p class="text-sm font-semibold text-emerald-300">Here now</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Family members</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($presence as $member)
                            <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-700 bg-zinc-950 p-4"><div><p class="font-semibold text-white">{{ $member['member_name'] }}</p><p class="mt-1 text-sm text-zinc-500">{{ str($member['channel_name'])->replace('-', ' ')->headline() }}</p></div><div class="text-right"><p class="text-sm {{ $member['state'] === 'offline' ? 'text-zinc-500' : 'text-emerald-300' }}">{{ $member['state'] === 'offline' ? 'Away' : 'Here now' }}</p>@if($member['typing'])<p class="mt-1 text-xs text-emerald-300">Typing now…</p>@endif</div></div>
                        @empty
                            <p class="rounded-xl border border-dashed border-zinc-700 p-6 text-zinc-400">No one is showing as active right now.</p>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5 md:p-6">
                    <p class="text-sm font-semibold text-emerald-300">Listen back</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Voice stories</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($voiceMessages as $message)
                            <div class="rounded-xl border border-zinc-700 bg-zinc-950 p-4"><div class="flex items-center justify-between gap-3"><p class="font-semibold text-white">{{ $message->member_name }}</p><span class="text-sm text-zinc-400">{{ gmdate('i:s', $message->duration_seconds) }}</span></div><div class="mt-3 flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-emerald-400 font-semibold text-zinc-950">▶</span><div><p class="text-sm text-zinc-300">{{ str($message->channel_name)->replace('-', ' ')->headline() }}</p><p class="mt-1 text-xs text-emerald-300">Ready to play</p></div></div></div>
                        @empty
                            <p class="rounded-xl border border-dashed border-zinc-700 p-6 text-zinc-400">No voice stories have been shared yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            @if($showOperationalDetails)
                <details class="group rounded-xl border border-zinc-700 bg-zinc-900 p-5"><summary class="flex cursor-pointer list-none items-center justify-between gap-4"><span><span class="block font-semibold text-white">Community service details</span><span class="mt-1 block text-sm text-zinc-400">Operational readiness for administrators and the Owner.</span></span><span class="text-xl text-emerald-300 transition group-open:rotate-45">+</span></summary><div class="mt-5 grid gap-3 border-t border-zinc-800 pt-5 sm:grid-cols-3"><div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Presence</p><p class="mt-2 text-emerald-300">Temporary signals</p></div><div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Voice notes</p><p class="mt-2 text-emerald-300">Available</p></div><div class="rounded-lg bg-zinc-950 p-4"><p class="text-xs uppercase tracking-wide text-zinc-500">Live calls</p><p class="mt-2 {{ $readiness['calls_enabled'] ? 'text-emerald-300' : 'text-amber-300' }}">{{ $readiness['calls_enabled'] ? 'Available' : 'Not enabled' }}</p></div></div></details>
            @endif
        @endif
    </main>
</x-layouts::app>
