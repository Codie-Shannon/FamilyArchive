<x-layouts::auth :title="__('Accept family invitation')">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[.2em] text-emerald-400">{{ $invitation->purpose === 'recovery' ? 'Assisted account recovery' : 'Invitation only' }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-white">{{ $invitation->purpose === 'recovery' ? 'Choose a new password' : 'Join Family Archive' }}</h1>
            <p class="mt-2 text-sm text-zinc-400">{{ $invitation->name }} · {{ $invitation->username ?? $invitation->email }}</p>
        </div>
        <div class="rounded-xl border border-amber-700/60 bg-amber-950/25 p-4 text-sm text-amber-100">
            @if($invitation->purpose === 'recovery') This one-time code restores access only to this account and expires after 24 hours.
            @elseif($invitation->email) After accepting, verify your email. An archive administrator then reviews routine access.
            @else No email is needed. An archive administrator reviews the account before archive access begins.
            @endif
        </div>
        <form method="POST" action="{{ route('invitation.accept', [$invitation->invitation_id, $token]) }}" class="space-y-4">
            @csrf
            <label class="block text-sm text-zinc-300">Password
                <input name="password" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-3 text-white">
            </label>
            <label class="block text-sm text-zinc-300">Confirm password
                <input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 w-full rounded-lg border border-zinc-700 bg-zinc-900 p-3 text-white">
            </label>
            @if($errors->any())<div class="text-sm text-red-300">{{ $errors->first() }}</div>@endif
            <button class="w-full rounded-lg bg-emerald-600 px-4 py-3 font-semibold text-white">{{ $invitation->purpose === 'recovery' ? 'Update password' : 'Finish account setup' }}</button>
        </form>
    </div>
</x-layouts::auth>
