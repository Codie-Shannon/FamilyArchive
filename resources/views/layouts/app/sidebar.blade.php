<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>@include('partials.head')</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
 <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
 <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate><x-app-logo /></a>
 <flux:navlist variant="outline">
  <flux:navlist.group :heading="__('Your archive')" class="grid">
   <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard', 'community.*')" wire:navigate>{{ __('Home') }}</flux:navlist.item>
   <flux:navlist.item icon="photo" :href="route('archive.index')" :current="request()->routeIs('archive.index', 'archive.photos.*', 'archive.derivatives.*', 'archive.originals.*', 'archive.knowledge', 'archive.events.*', 'archive.locations.*', 'archive.people.*', 'archive.branches.*')" wire:navigate>{{ __('Archive') }}</flux:navlist.item>
   @if(auth()->user()?->canContribute())
    <flux:navlist.item icon="arrow-up-tray" :href="route('contributor.index')" :current="request()->routeIs('contributor.*')" wire:navigate>{{ __('Contribute') }}</flux:navlist.item>
   @endif
   <flux:navlist.item icon="envelope" :href="route('secure-messages.index')" :current="request()->routeIs('secure-messages.*')" wire:navigate>{{ __('Messages') }}</flux:navlist.item>
  </flux:navlist.group>
  @if(auth()->user()?->canAccessWorkHub())
   <flux:navlist.group :heading="__('Operations')" class="grid">
    <flux:navlist.item icon="rectangle-stack" :href="route('work.index')" :current="request()->routeIs('work.*', 'intake.*', 'admin.*')" wire:navigate>{{ __('Work') }}</flux:navlist.item>
   </flux:navlist.group>
  @endif
 </flux:navlist>
 <flux:spacer />
 <x-desktop-user-menu />
</flux:sidebar>
<flux:header class="lg:hidden"><flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" /><flux:spacer /><x-dropdown-user-menu /></flux:header>
{{ $slot }}
@if(auth()->user()?->isApprovedFamilyMember())
 <x-family-chat />
@endif
@fluxScripts
</body></html>
