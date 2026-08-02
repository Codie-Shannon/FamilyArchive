<x-layouts::app :title="__('Owner Command Centre')">
    @php
        $tabs = [
            'overview' => 'Overview',
            'queue' => 'Work queue',
            'family' => 'Family & access',
            'system' => 'System & storage',
        ];
        $queueCards = [
            ['label' => 'Accounts awaiting approval', 'value' => $queue['accounts'], 'route' => route('admin.access.index'), 'detail' => 'Confirm identity, role and family connection.'],
            ['label' => 'Intake batches', 'value' => $queue['intake_batches'], 'route' => route('intake.index'), 'detail' => 'Administrators and trusted contributors clear routine photo review.'],
            ['label' => 'Intake exceptions', 'value' => $queue['intake_exceptions'], 'route' => route('intake.index'), 'detail' => 'Escalate only uncertain crops, duplicates and failed processing.'],
            ['label' => 'Possible duplicates', 'value' => $queue['duplicates'], 'route' => route('admin.duplicate-candidates.index'), 'detail' => 'Make a human decision without removing source bytes.'],
            ['label' => 'Restoration candidates', 'value' => $queue['restoration'], 'route' => route('admin.restoration'), 'detail' => 'Compare candidate versions and approve explicitly.'],
            ['label' => 'Open repair cases', 'value' => $queue['repairs'], 'route' => route('admin.operations'), 'detail' => 'Resolve integrity exceptions through controlled repair.'],
        ];
    @endphp

    <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 px-5 py-8 lg:px-8">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-400">Owner-only workspace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">Command Centre</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-300">See what needs attention, then move into the established specialist workflow only when necessary.</p>
            </div>
            <div class="rounded-xl border border-amber-800 bg-amber-950/20 px-5 py-4 text-right">
                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-amber-300">Needs attention</p>
                <p class="mt-1 text-3xl font-semibold text-white">{{ $queueTotal }}</p>
            </div>
        </header>

        <nav aria-label="Owner command centre views" class="flex flex-wrap gap-2 rounded-xl border border-zinc-700 bg-zinc-900 p-2">
            @foreach($tabs as $key => $label)
                <a href="{{ route('admin.dashboard', ['view' => $key]) }}" class="rounded-lg px-4 py-2 text-sm font-semibold {{ $section === $key ? 'bg-emerald-500 text-zinc-950' : 'text-zinc-300 hover:bg-zinc-800' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if($section === 'overview')
            <section aria-label="Owner overview" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('admin.dashboard', ['view' => 'queue']) }}" class="rounded-2xl border border-amber-800 bg-amber-950/20 p-5">
                    <p class="text-sm font-medium text-amber-300">Work queue</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $queueTotal }}</p>
                    <p class="mt-2 text-sm text-amber-100">Review actions across access, intake and preservation.</p>
                </a>
                <a href="{{ route('admin.dashboard', ['view' => 'family']) }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-medium text-emerald-400">Family & access</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $approvedMembers }}</p>
                    <p class="mt-2 text-sm text-zinc-400">Approved accounts with controlled archive access.</p>
                </a>
                <a href="{{ route('archive.index') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-medium text-emerald-400">Archive</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $archiveRecords }}</p>
                    <p class="mt-2 text-sm text-zinc-400">Approved records available through access-filtered browsing.</p>
                </a>
                <a href="{{ route('admin.dashboard', ['view' => 'system']) }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-sm font-medium text-emerald-400">System & storage</p>
                    <p class="mt-3 text-3xl font-semibold text-white">{{ $integrityWarnings + $failedJobs }}</p>
                    <p class="mt-2 text-sm text-zinc-400">Integrity warnings and failed processing jobs.</p>
                </a>
            </section>

            <section class="grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-emerald-400">Priority path</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Start with decisions, not dashboards</h2>
                        </div>
                        <a href="{{ route('admin.dashboard', ['view' => 'queue']) }}" class="text-sm font-semibold text-emerald-300">Open queue →</a>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-zinc-950 p-4"><p class="text-2xl font-semibold text-white">{{ $queue['accounts'] }}</p><p class="mt-1 text-sm text-zinc-400">account decisions</p></div>
                        <div class="rounded-xl bg-zinc-950 p-4"><p class="text-2xl font-semibold text-white">{{ $queue['intake_batches'] }}</p><p class="mt-1 text-sm text-zinc-400">delegated batches</p></div>
                        <div class="rounded-xl bg-zinc-950 p-4"><p class="text-2xl font-semibold text-white">{{ $queue['intake_exceptions'] }}</p><p class="mt-1 text-sm text-zinc-400">intake exceptions</p></div>
                    </div>
                </article>
                <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-medium text-emerald-400">At a glance</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Archive safeguards</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-zinc-400">Storage</dt><dd class="font-semibold text-white">{{ ucfirst($storage['provider']) }} · private</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-400">Readiness gates</dt><dd class="font-semibold text-white">{{ $passedGates }}/{{ $gateTotal }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-zinc-400">Active invitations</dt><dd class="font-semibold text-white">{{ $queue['invitations'] }}</dd></div>
                    </dl>
                </article>
            </section>
        @elseif($section === 'queue')
            <section>
                <p class="text-sm font-medium text-emerald-400">Action-oriented review</p>
                <h2 class="mt-1 text-2xl font-semibold text-white">Owner exception queue</h2>
                <p class="mt-2 text-sm text-zinc-400">Routine review stays delegated. This queue keeps policy, identity and preservation exceptions visible without making the owner a bottleneck.</p>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($queueCards as $card)
                        <a href="{{ $card['route'] }}" class="rounded-2xl border {{ $card['value'] > 0 ? 'border-amber-800 bg-amber-950/20' : 'border-zinc-700 bg-zinc-900' }} p-5">
                            <div class="flex items-start justify-between gap-4">
                                <p class="font-semibold text-white">{{ $card['label'] }}</p>
                                <span class="rounded-full bg-zinc-950 px-3 py-1 text-sm font-semibold text-white">{{ $card['value'] }}</span>
                            </div>
                            <p class="mt-3 text-sm {{ $card['value'] > 0 ? 'text-amber-100' : 'text-zinc-400' }}">{{ $card['detail'] }}</p>
                            <p class="mt-5 text-sm font-semibold text-emerald-300">Review →</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @elseif($section === 'family')
            <section>
                <p class="text-sm font-medium text-emerald-400">People, knowledge and communication</p>
                <h2 class="mt-1 text-2xl font-semibold text-white">Family & access</h2>
                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Accounts & contributors</h3>
                        <p class="mt-2 text-sm text-zinc-400">Invitations, approval, roles, branch scope, original grants and contributor review.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.access.index') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Manage access</a><span class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300">{{ $queue['accounts'] }} pending</span></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Archive knowledge</h3>
                        <p class="mt-2 text-sm text-zinc-400">People, events, places, branches and source provenance remain together.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('archive.knowledge') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Open knowledge</a><a href="{{ route('archive.sources.index') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Sources</a></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Community & privacy</h3>
                        <p class="mt-2 text-sm text-zinc-400">Moderation, presence, voice readiness and consent-first communication.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.community-operations') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Community</a><a href="{{ route('admin.secure-communication') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Communication</a></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Public experience</h3>
                        <p class="mt-2 text-sm text-zinc-400">Review what the public can discover and the evidence presented to clients.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.public-discovery') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Discovery</a><a href="{{ route('admin.portfolio-showcase') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Showcase</a></div>
                    </article>
                </div>
            </section>
        @else
            <section>
                <p class="text-sm font-medium text-emerald-400">Preservation and operations</p>
                <h2 class="mt-1 text-2xl font-semibold text-white">System & storage</h2>
                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Storage & integrity</h3>
                        <p class="mt-2 text-sm text-zinc-400">Private {{ $storage['provider'] }} storage, transfer verification and controlled repair cases.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.archive-storage') }}" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Storage status</a><a href="{{ route('admin.operations') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Integrity</a></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Intake & preservation</h3>
                        <p class="mt-2 text-sm text-zinc-400">Retained intake, duplicate review, archive acceptance and private derivatives.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('intake.index') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Intake & review</a><a href="{{ route('admin.archive-promotions.index') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Exceptions</a><a href="{{ route('admin.viewing-derivatives.index') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Derivatives</a></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Restoration & intelligence</h3>
                        <p class="mt-2 text-sm text-zinc-400">Non-destructive automation, candidate review, metadata intelligence and cloud import planning.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.restoration') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Restoration</a><a href="{{ route('admin.media-intelligence') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Intelligence</a><a href="{{ route('admin.cloud-imports') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Cloud import</a><a href="{{ route('admin.batch-imports') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Large batches</a><a href="{{ route('admin.migration-qualification') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Migration qualification</a></div>
                    </article>
                    <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
                        <h3 class="text-xl font-semibold text-white">Production & release</h3>
                        <p class="mt-2 text-sm text-zinc-400">{{ $passedGates }}/{{ $gateTotal }} readiness gates currently pass in this environment.</p>
                        <div class="mt-5 flex flex-wrap gap-3"><a href="{{ route('admin.production-readiness') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Production readiness</a><a href="{{ route('admin.release-acceptance') }}" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-white">Release acceptance</a></div>
                    </article>
                </div>
            </section>
        @endif
    </main>
</x-layouts::app>
