<x-layouts::auth :title="__('Family access code')">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[.2em] text-emerald-400">Simple family access</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Enter your access code</h1>
            <p class="mt-3 text-base leading-7 text-zinc-400">Use the 12-character code on the card your family administrator gave you. Spaces and dashes are optional.</p>
        </div>
        <form method="POST" action="{{ route('access-code.find') }}" class="space-y-5">
            @csrf
            <label class="block text-base font-medium text-zinc-200">Access code
                <input name="code" value="{{ old('code') }}" required autofocus autocomplete="one-time-code" placeholder="ABCD-EFGH-JKLM" class="mt-2 w-full rounded-xl border border-zinc-700 bg-zinc-900 p-4 text-center font-mono text-xl uppercase tracking-[.14em] text-white">
            </label>
            @error('code')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
            <button class="w-full rounded-xl bg-emerald-500 px-5 py-4 text-lg font-semibold text-zinc-950">Continue</button>
        </form>
        <a href="{{ route('login') }}" class="text-center text-sm text-zinc-400 underline">I already have a password</a>
    </div>
</x-layouts::auth>
