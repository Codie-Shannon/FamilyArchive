<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Use your email or the member name from your family access card')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email or managed member name -->
            <flux:input
                name="email"
                :label="__('Email or member name')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="email@example.com or mary.smith"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <flux:button :href="route('access-code.show')" variant="outline" class="w-full">{{ __('Use a family access code') }}</flux:button>
        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">{{ __('Need access? Ask your family administrator for an invitation.') }}</p>
    </div>
</x-layouts::auth>
