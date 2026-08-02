<x-layouts::app title="Family Operations">
<main class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-emerald-300">Delegated family operations</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Routine work, without an Owner bottleneck</h1>
            <p class="mt-2 max-w-3xl text-zinc-400">Administrators handle ordinary membership and reported-content decisions. Members publish routine conversation immediately and decide their own private-message requests. Owner attention is reserved for elevated roles, original access and policy exceptions.</p>
        </div>
        <span class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">v{{ config('release.version') }} · {{ config('release.name') }}</span>
    </header>

    @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    @include('admin.partials.access-card')
    @if($errors->any())<div class="rounded-xl border border-red-700 bg-red-950/30 p-4 text-red-100">{{ $errors->first() }}</div>@endif

    <section class="grid gap-5 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.family-operations.invitations') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-6">
            @csrf
            <p class="text-sm font-semibold text-emerald-300">Guided member setup</p><h2 class="mt-1 text-xl font-semibold text-white">Create a family access card</h2><p class="mt-2 text-sm text-zinc-400">Email is optional. The member uses a simple one-time code, chooses a password, then appears in the routine approval queue.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="text-xs text-zinc-400">Full name<input name="name" required class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-base"></label><label class="text-xs text-zinc-400">Member name (optional)<input name="username" placeholder="Generated automatically" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-base"></label><label class="text-xs text-zinc-400">Email (optional)<input name="email" type="email" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-base"></label><label class="text-xs text-zinc-400">Access level<select name="role" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-base"><option value="viewer">Viewer</option><option value="contributor">Contributor</option></select></label><label class="text-xs text-zinc-400 sm:col-span-2">Family branch<select name="family_branch_id" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3 text-base"><option value="">Whole family</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label></div>
            <button class="mt-4 w-full rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-zinc-950">Create access card</button>
        </form>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-6"><p class="text-sm font-semibold text-cyan-300">Assisted recovery</p><h2 class="mt-1 text-xl font-semibold text-white">Help a member back in</h2><p class="mt-2 text-sm text-zinc-400">Issue a 24-hour one-time code. It changes only the selected member’s password and leaves their role and archive permissions unchanged.</p><div class="mt-4 max-h-72 space-y-3 overflow-y-auto">@foreach($recoverableAccounts as $member)<form method="POST" action="{{ route('admin.family-operations.recovery', $member) }}" class="grid gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf<div><p class="font-semibold text-white">{{ $member->name }}</p><p class="text-xs text-zinc-500">{{ $member->username ?? $member->email }} · {{ $member->account_state }}</p></div><label class="text-xs text-zinc-400">Reason<input name="reason" required value="Member requested assisted recovery" class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2"></label><button class="rounded-lg border border-cyan-700 px-3 py-2 text-sm font-semibold text-cyan-100">Create code</button></form>@endforeach</div></article>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-6">
        @foreach([
            'Routine accounts' => $routineAccounts->count(),
            'Reported posts' => $reportedMessages->count(),
            'Reported messages' => $reportedPrivateMessages->count(),
            'Voice review' => $voiceMessages->count(),
            'Anonymous contact' => $anonymousMessages->count(),
            'Owner exceptions' => $ownerExceptions->count(),
        ] as $label => $count)
        <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-4"><p class="text-xs text-zinc-400 sm:text-sm">{{ $label }}</p><p class="mt-2 text-2xl font-semibold {{ $label === 'Owner exceptions' ? 'text-amber-300' : 'text-white' }}">{{ $count }}</p></article>
        @endforeach
    </section>

    @if($reportedPrivateMessages->isNotEmpty())
    <section class="rounded-2xl border border-rose-900 bg-rose-950/15 p-4 sm:p-6">
        <p class="text-sm font-semibold text-rose-300">Recipient-reported only</p>
        <h2 class="mt-1 text-xl font-semibold text-white">Private-message exceptions</h2>
        <p class="mt-2 text-sm text-zinc-400">Administrators see a private message only after a participant reports it. Ordinary family chat never enters an approval queue.</p>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @foreach($reportedPrivateMessages as $message)
            <form method="POST" action="{{ route('admin.family-operations.private-messages', $message) }}" class="rounded-xl border border-zinc-700 bg-zinc-950 p-4">
                @csrf @method('PATCH')
                <p class="text-xs text-zinc-500">{{ $message->sender->name }} · participant report</p>
                <p class="mt-2 text-sm text-zinc-200">{{ $message->body }}</p>
                <div class="mt-3 flex gap-2"><button name="decision" value="restore" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Restore</button><button name="decision" value="hide" class="rounded border border-zinc-700 px-3 py-2 text-xs text-zinc-300">Hide</button></div>
            </form>
            @endforeach
        </div>
    </section>
    @endif

    <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-sm font-semibold text-emerald-300">Administrator delegated</p><h2 class="mt-1 text-xl font-semibold text-white">Routine member approvals</h2></div><span class="text-xs uppercase tracking-wide text-zinc-500">Viewer + contributor only</span></div>
            <div class="mt-5 space-y-4">
                @forelse($routineAccounts as $member)
                <form method="POST" action="{{ route('admin.family-operations.accounts', $member) }}" class="rounded-xl border border-zinc-700 bg-zinc-950 p-4">
                    @csrf @method('PATCH')
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-semibold text-white">{{ $member->name }}</p><p class="mt-1 text-sm text-zinc-500">{{ $member->username ?? $member->email }} · {{ str($member->role)->headline() }}</p></div><span class="rounded-full bg-amber-950 px-3 py-1 text-xs font-semibold uppercase text-amber-300">Pending</span></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1.2fr_auto] sm:items-end">
                        <label class="text-xs text-zinc-400">Family branch<select name="family_branch_id" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-2"><option value="">Whole family</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                        <label class="text-xs text-zinc-400">Reason<input name="reason" required value="Verified family invitation" class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-2"></label>
                        <div class="flex gap-2"><button name="decision" value="approve" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-zinc-950">Approve</button><button name="decision" value="reject" class="rounded-lg border border-zinc-700 px-4 py-2 text-sm text-zinc-300">Reject</button></div>
                    </div>
                </form>
                @empty<p class="rounded-xl border border-dashed border-zinc-700 p-6 text-zinc-400">No routine accounts await review.</p>@endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-amber-900 bg-amber-950/20 p-4 sm:p-6">
            <p class="text-sm font-semibold text-amber-300">Owner policy boundary</p><h2 class="mt-1 text-xl font-semibold text-white">Elevated-role exceptions</h2>
            <p class="mt-2 text-sm leading-6 text-zinc-400">Trusted contributors, administrators and Owners stay out of routine delegated approval. Original-file grants also remain Owner-only.</p>
            <div class="mt-5 space-y-3">@forelse($ownerExceptions as $member)<div class="rounded-xl border border-amber-900/70 bg-zinc-950/60 p-4"><p class="font-semibold text-white">{{ $member->name }}</p><p class="mt-1 text-sm text-amber-200">{{ str($member->role)->headline() }} · Owner decision required</p></div>@empty<p class="rounded-xl border border-dashed border-amber-900 p-5 text-amber-100">No elevated-role exceptions.</p>@endforelse</div>
        </article>
    </section>

    <section class="grid gap-5 xl:grid-cols-3">
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-5"><p class="text-sm font-semibold text-emerald-300">Report, then review</p><h2 class="mt-1 text-xl font-semibold text-white">Family conversation</h2><div class="mt-4 space-y-3">@forelse($reportedMessages as $message)<form method="POST" action="{{ route('admin.family-operations.conversations', $message->id) }}" class="rounded-xl border border-zinc-700 bg-zinc-950 p-4">@csrf @method('PATCH')<p class="text-xs text-zinc-500">{{ $message->subject }} · {{ $message->author_name }}</p><p class="mt-2 text-sm text-zinc-200">{{ $message->body }}</p><div class="mt-3 flex gap-2"><button name="decision" value="restore" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Restore</button><button name="decision" value="hide" class="rounded border border-zinc-700 px-3 py-2 text-xs text-zinc-300">Hide</button></div></form>@empty<p class="rounded-xl border border-dashed border-zinc-700 p-5 text-zinc-400">No reported posts.</p>@endforelse</div></article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-5"><p class="text-sm font-semibold text-emerald-300">Media exception</p><h2 class="mt-1 text-xl font-semibold text-white">Voice messages</h2><div class="mt-4 space-y-3">@forelse($voiceMessages as $message)<form method="POST" action="{{ route('admin.family-operations.voice', $message->id) }}" class="rounded-xl border border-zinc-700 bg-zinc-950 p-4">@csrf @method('PATCH')<p class="font-semibold text-white">{{ $message->member_name }}</p><p class="mt-1 text-xs text-zinc-500"># {{ $message->channel_name }} · {{ gmdate('i:s', $message->duration_seconds) }} · {{ $message->mime_type }}</p><div class="mt-3 flex gap-2"><button name="decision" value="allow" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Allow</button><button name="decision" value="block" class="rounded border border-zinc-700 px-3 py-2 text-xs text-zinc-300">Block</button></div></form>@empty<p class="rounded-xl border border-dashed border-zinc-700 p-5 text-zinc-400">No voice items await review.</p>@endforelse</div></article>
        <article class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4 sm:p-5"><p class="text-sm font-semibold text-emerald-300">Untrusted boundary</p><h2 class="mt-1 text-xl font-semibold text-white">Anonymous contact</h2><div class="mt-4 space-y-3">@forelse($anonymousMessages as $message)<form method="POST" action="{{ route('admin.family-operations.anonymous', $message->id) }}" class="rounded-xl border border-zinc-700 bg-zinc-950 p-4">@csrf @method('PATCH')<p class="font-semibold text-white">{{ $message->subject }}</p><p class="mt-2 line-clamp-3 text-sm text-zinc-400">{{ $message->body }}</p><div class="mt-3 flex flex-wrap gap-2"><button name="decision" value="accepted" class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Accept</button><button name="decision" value="spam" class="rounded border border-zinc-700 px-3 py-2 text-xs text-zinc-300">Spam</button><button name="decision" value="blocked" class="rounded border border-red-900 px-3 py-2 text-xs text-red-300">Block</button></div></form>@empty<p class="rounded-xl border border-dashed border-zinc-700 p-5 text-zinc-400">No anonymous contact awaits review.</p>@endforelse</div></article>
    </section>

    <aside class="rounded-xl border border-sky-900 bg-sky-950/20 p-5 text-sm leading-6 text-sky-100">Routine family posts publish immediately. Members report problems, recipients decide private contact, administrators resolve ordinary queues, and the Owner sees only elevated access, original-file and policy exceptions.</aside>
</main>
</x-layouts::app>
