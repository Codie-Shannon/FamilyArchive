<x-layouts::app :title="__('Accounts & Access')">
<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-8">
    <header><p class="text-sm font-medium text-emerald-300">Policy and original-access control</p><h1 class="mt-1 text-3xl font-semibold text-white">Accounts, branches & original grants</h1><p class="mt-2 max-w-3xl text-zinc-400">Routine viewer and contributor decisions stay with administrators. Elevated roles, scoped original access and append-only access history remain here.</p></header>
    @if(session('status'))<div class="rounded-xl border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
    @if(session('invitation_url'))<div class="rounded-xl border border-cyan-700 bg-cyan-950/30 p-4 text-cyan-100"><strong>One-time invitation URL</strong><p class="mt-2 break-all font-mono text-xs">{{ session('invitation_url') }}</p></div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-700 bg-red-950/30 p-4 text-red-100">{{ $errors->first() }}</div>@endif

    <section class="grid gap-5 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.access.invite') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <h2 class="text-xl font-semibold text-white">Invite a family member</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm text-zinc-300">Name<input name="name" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="text-sm text-zinc-300">Email<input name="email" type="email" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
                <label class="text-sm text-zinc-300">Role<select name="role" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3">@foreach($roles as $role)<option value="{{ $role }}">{{ str_replace('_',' ',$role) }}</option>@endforeach</select></label>
                <label class="text-sm text-zinc-300">Family branch<select name="family_branch_id" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"><option value="">Whole family</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
            </div>
            <button class="mt-5 rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white">Create seven-day invitation</button>
        </form>
        <form method="POST" action="{{ route('admin.access.grants.store') }}" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
            @csrf
            <h2 class="text-xl font-semibold text-white">Grant verified original access</h2>
            <div class="mt-5 grid gap-4">
                <label class="text-sm text-zinc-300">Member<select name="user_id" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->role }}</option>@endforeach</select></label>
                <label class="text-sm text-zinc-300">Archive record<select name="media_item_id" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3">@foreach($mediaItems as $item)<option value="{{ $item->id }}">{{ $item->archive_id }} · {{ $item->title }}</option>@endforeach</select></label>
                <label class="text-sm text-zinc-300">Reason<textarea name="reason" required class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></textarea></label>
                <label class="text-sm text-zinc-300">Expiry (optional)<input name="expires_at" type="datetime-local" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-950 p-3"></label>
            </div>
            <button class="mt-5 rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white">Grant original access</button>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
        <h2 class="text-xl font-semibold text-white">Member approval & branch scope</h2>
        <div class="mt-5 grid gap-4">
            @foreach($users as $user)
            <form method="POST" action="{{ route('admin.access.users.update', $user) }}" class="grid gap-3 rounded-xl border border-zinc-700 bg-zinc-950/50 p-4 lg:grid-cols-[1.4fr_.8fr_.8fr_1fr_1.5fr_auto] lg:items-end">
                @csrf @method('PATCH')
                <div><strong class="text-white">{{ $user->name }}</strong><p class="text-xs text-zinc-500">{{ $user->email }} · {{ $user->email_verified_at ? 'verified' : 'unverified' }}</p></div>
                <label class="text-xs text-zinc-400">Role<select name="role" class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2">@foreach($roles as $role)<option value="{{ $role }}" @selected($user->role===$role)>{{ str_replace('_',' ',$role) }}</option>@endforeach</select></label>
                <label class="text-xs text-zinc-400">State<select name="account_state" class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2">@foreach($states as $state)<option value="{{ $state }}" @selected($user->account_state===$state)>{{ $state }}</option>@endforeach</select></label>
                <label class="text-xs text-zinc-400">Branch<select name="family_branch_id" class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2"><option value="">Whole family</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($user->family_branch_id===$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <input name="family_connection" value="{{ $user->family_connection }}" placeholder="Family connection" class="rounded border border-zinc-700 bg-zinc-900 p-2 text-sm">
                <div><input name="reason" required placeholder="Reason" class="w-full rounded border border-zinc-700 bg-zinc-900 p-2 text-sm"><button class="mt-2 rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Save</button></div>
            </form>
            @endforeach
        </div>
    </section>

    <section id="contributor-moderation" class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-sm font-medium text-emerald-300">Retained source → delegated intake review → archive exceptions</p><h2 class="mt-1 text-xl font-semibold text-white">Contributor submissions</h2></div><span class="rounded-full border border-zinc-700 px-3 py-1 text-sm text-zinc-300">{{ $submissions->count() }} recent files</span></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse($submissions as $submission)
            <form method="POST" action="{{ route('admin.contributor-submissions.review',$submission) }}" class="rounded-xl border border-zinc-700 bg-zinc-950/60 p-4">
                @csrf @method('PATCH')
                <div class="flex items-start justify-between gap-4"><div><strong class="text-white">{{ $submission->original_name }}</strong><p class="mt-1 text-xs text-zinc-500">{{ $submission->submission_id }} · {{ $submission->contributor?->name }} · {{ $submission->incomingUpload?->source_file_retained ? 'source retained' : 'retention warning' }}</p></div><span class="rounded-full border border-emerald-900 px-2 py-1 text-xs text-emerald-300">{{ str_replace('_',' ',$submission->status) }}</span></div>
                <p class="mt-3 text-sm text-zinc-400">{{ $submission->source_context }}</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-[.8fr_1.2fr_auto] sm:items-end"><label class="text-xs text-zinc-400">Decision<select name="status" class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2"><option value="needs_info">Needs information</option><option value="possible_duplicate">Possible duplicate</option><option value="accepted">Accepted for archive review</option><option value="rejected">Rejected</option></select></label><label class="text-xs text-zinc-400">Reviewer note<input name="reviewer_note" required class="mt-1 w-full rounded border border-zinc-700 bg-zinc-900 p-2"></label><button class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Record</button></div>
            </form>
            @empty<p class="text-zinc-500">No contributor files await moderation.</p>@endforelse
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Invitation register</h2><div class="mt-4 space-y-3 text-sm">@forelse($invitations as $invite)<div class="rounded-lg bg-zinc-950 p-3"><strong class="text-white">{{ $invite->name }}</strong><p class="text-zinc-400">{{ $invite->email }} · {{ $invite->accepted_at ? 'accepted' : ($invite->revoked_at ? 'revoked' : 'open') }} · expires {{ $invite->expires_at->format('j M Y') }}</p></div>@empty<p class="text-zinc-500">No invitations.</p>@endforelse</div></div>
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-6"><h2 class="text-xl font-semibold text-white">Immutable access history</h2><div class="mt-4 space-y-3 text-sm">@forelse($events as $event)<div class="rounded-lg bg-zinc-950 p-3"><strong class="text-white">{{ str_replace('_',' ',$event->event_type) }}</strong><p class="text-zinc-400">Member #{{ $event->user_id }} · actor #{{ $event->actor_id ?? 'system' }} · {{ $event->created_at->format('j M Y H:i') }}</p><p class="mt-1 text-zinc-500">{{ $event->reason }}</p></div>@empty<p class="text-zinc-500">No access changes recorded.</p>@endforelse</div></div>
    </section>
</div>
</x-layouts::app>
