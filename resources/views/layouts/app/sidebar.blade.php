<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
 <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
 <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate><x-app-logo /></a>
 <flux:navlist variant="outline">
  <flux:navlist.group :heading="__('Platform')" class="grid">
   <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
   @if(auth()->user()?->role === 'owner')
    <flux:navlist.item icon="shield-check" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Archive Administration') }}</flux:navlist.item>
    <flux:navlist.item icon="circle-stack" :href="route('admin.archive-schema')" :current="request()->routeIs('admin.archive-schema')" wire:navigate>{{ __('Archive Schema') }}</flux:navlist.item>
    <flux:navlist.item icon="archive-box" :href="route('admin.archive-storage')" :current="request()->routeIs('admin.archive-storage')" wire:navigate>{{ __('Archive Storage') }}</flux:navlist.item>
    <flux:navlist.item icon="photo" :href="route('admin.photo-intake.index')" :current="request()->routeIs('admin.photo-intake.*')" wire:navigate>{{ __('Photo Intake') }}</flux:navlist.item>
    <flux:navlist.item icon="magnifying-glass" :href="route('admin.duplicate-candidates.index')" :current="request()->routeIs('admin.duplicate-candidates.*')" wire:navigate>{{ __('Duplicate Candidates') }}</flux:navlist.item>
    <flux:navlist.item icon="check-badge" :href="route('admin.archive-promotions.index')" :current="request()->routeIs('admin.archive-promotions.*')" wire:navigate>{{ __('Archive Acceptance') }}</flux:navlist.item>
    <flux:navlist.item icon="photo" :href="route('archive.index')" :current="request()->routeIs('archive.*')" wire:navigate>{{ __('Private Archive') }}</flux:navlist.item>
    <flux:navlist.item icon="circle-stack" :href="route('archive.sources.index')" :current="request()->routeIs('archive.sources.*')" wire:navigate>{{ __('Source Provenance') }}</flux:navlist.item>
    <flux:navlist.item icon="photo" :href="route('admin.viewing-derivatives.index')" :current="request()->routeIs('admin.viewing-derivatives.*')" wire:navigate>{{ __('Viewing Derivatives') }}</flux:navlist.item>
   @endif
  </flux:navlist.group>
 </flux:navlist>
 <flux:spacer />
 <flux:navlist variant="outline"><flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">{{ __('Repository') }}</flux:navlist.item><flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">{{ __('Documentation') }}</flux:navlist.item></flux:navlist>
 <x-desktop-user-menu />
</flux:sidebar>
<flux:header class="lg:hidden"><flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" /><flux:spacer /><x-dropdown-user-menu /></flux:header>
{{ $slot }}
@fluxScripts
</body></html>
