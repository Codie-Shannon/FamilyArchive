<x-layouts::auth :title="__('Account awaiting approval')">
    <div class="flex flex-col gap-6 text-center">
        <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-950 text-3xl text-emerald-300">✓</div>
        <div>
            <p class="text-sm font-semibold uppercase tracking-[.2em] text-emerald-400">Setup complete</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Your family administrator will approve access</h1>
            <p class="mt-3 text-base leading-7 text-zinc-400">You do not need to repeat setup. Once approved, sign in with <strong class="text-zinc-200">{{ auth()->user()->username ?? auth()->user()->email }}</strong> and your password.</p>
        </div>
        @if(session('status'))<div class="rounded-xl border border-emerald-800 bg-emerald-950/30 p-4 text-emerald-100">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-xl border border-zinc-700 px-5 py-3 font-semibold text-zinc-200">Sign out</button></form>
    </div>
</x-layouts::auth>
