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
   <flux:navlist.item icon="chat-bubble-left-right" :href="route('community.index')" :current="request()->routeIs('community.*')" wire:navigate>{{ __('Family Community') }}</flux:navlist.item>
   <flux:navlist.item icon="envelope" :href="route('secure-messages.index')" :current="request()->routeIs('secure-messages.*')" wire:navigate>{{ __('Secure Messages') }}</flux:navlist.item>
   <flux:navlist.item icon="photo" :href="route('archive.index')" :current="request()->routeIs('archive.index', 'archive.photos.*', 'archive.derivatives.*', 'archive.originals.*')" wire:navigate>{{ __('Family Archive') }}</flux:navlist.item>
   @if(auth()->user()?->canContribute())
    <flux:navlist.item icon="arrow-up-tray" :href="route('contributor.index')" :current="request()->routeIs('contributor.*')" wire:navigate>{{ __('Contribute Media') }}</flux:navlist.item>
   @endif
   @if(auth()->user()?->role === 'owner')
    <flux:navlist.item icon="shield-check" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>{{ __('Archive Administration') }}</flux:navlist.item>
    <flux:navlist.item icon="circle-stack" :href="route('admin.archive-schema')" :current="request()->routeIs('admin.archive-schema')" wire:navigate>{{ __('Archive Schema') }}</flux:navlist.item>
    <flux:navlist.item icon="archive-box" :href="route('admin.archive-storage')" :current="request()->routeIs('admin.archive-storage')" wire:navigate>{{ __('Archive Storage') }}</flux:navlist.item>
    <flux:navlist.item icon="sparkles" :href="route('admin.restoration')" :current="request()->routeIs('admin.restoration')" wire:navigate>{{ __('Restoration Workspace') }}</flux:navlist.item>
    <flux:navlist.item icon="shield-check" :href="route('admin.operations')" :current="request()->routeIs('admin.operations')" wire:navigate>{{ __('Integrity Operations') }}</flux:navlist.item>
    <flux:navlist.item icon="check-badge" :href="route('admin.release-acceptance')" :current="request()->routeIs('admin.release-acceptance')" wire:navigate>{{ __('v1.0 Acceptance') }}</flux:navlist.item>
    <flux:navlist.item icon="sparkles" :href="route('admin.media-intelligence')" :current="request()->routeIs('admin.media-intelligence')" wire:navigate>{{ __('Media Intelligence') }}</flux:navlist.item>
    <flux:navlist.item icon="arrow-down-tray" :href="route('admin.cloud-imports')" :current="request()->routeIs('admin.cloud-imports')" wire:navigate>{{ __('Cloud Import') }}</flux:navlist.item>
    <flux:navlist.item icon="globe-alt" :href="route('admin.public-discovery')" :current="request()->routeIs('admin.public-discovery')" wire:navigate>{{ __('Public Discovery') }}</flux:navlist.item>
    <flux:navlist.item icon="user-group" :href="route('admin.community-operations')" :current="request()->routeIs('admin.community-operations')" wire:navigate>{{ __('Community Operations') }}</flux:navlist.item>
    <flux:navlist.item icon="lock-closed" :href="route('admin.secure-communication')" :current="request()->routeIs('admin.secure-communication')" wire:navigate>{{ __('Secure Communication') }}</flux:navlist.item>
    <flux:navlist.item icon="presentation-chart-bar" :href="route('admin.portfolio-showcase')" :current="request()->routeIs('admin.portfolio-showcase')" wire:navigate>{{ __('Portfolio Showcase') }}</flux:navlist.item>
    <flux:navlist.item icon="cloud" :href="route('admin.production-readiness')" :current="request()->routeIs('admin.production-readiness')" wire:navigate>{{ __('Production Readiness') }}</flux:navlist.item>
    <flux:navlist.item icon="key" :href="route('admin.access.index')" :current="request()->routeIs('admin.access.*')" wire:navigate>{{ __('Accounts & Access') }}</flux:navlist.item>
    <flux:navlist.item icon="photo" :href="route('admin.photo-intake.index')" :current="request()->routeIs('admin.photo-intake.*')" wire:navigate>{{ __('Photo Intake') }}</flux:navlist.item>
    <flux:navlist.item icon="magnifying-glass" :href="route('admin.duplicate-candidates.index')" :current="request()->routeIs('admin.duplicate-candidates.*')" wire:navigate>{{ __('Duplicate Candidates') }}</flux:navlist.item>
    <flux:navlist.item icon="check-badge" :href="route('admin.archive-promotions.index')" :current="request()->routeIs('admin.archive-promotions.*')" wire:navigate>{{ __('Archive Acceptance') }}</flux:navlist.item>
    <flux:navlist.item icon="calendar-days" :href="route('archive.events.index')" :current="request()->routeIs('archive.events.*')" wire:navigate>{{ __('Events') }}</flux:navlist.item>
    <flux:navlist.item icon="map-pin" :href="route('archive.locations.index')" :current="request()->routeIs('archive.locations.*')" wire:navigate>{{ __('Locations') }}</flux:navlist.item>
    <flux:navlist.item icon="users" :href="route('archive.people.index')" :current="request()->routeIs('archive.people.*')" wire:navigate>{{ __('People') }}</flux:navlist.item>
    <flux:navlist.item icon="rectangle-group" :href="route('archive.branches.index')" :current="request()->routeIs('archive.branches.*')" wire:navigate>{{ __('Family Branches') }}</flux:navlist.item>
    <flux:navlist.item icon="magnifying-glass" :href="route('archive.knowledge')" :current="request()->routeIs('archive.knowledge')" wire:navigate>{{ __('Archive Knowledge') }}</flux:navlist.item>
    <flux:navlist.item icon="circle-stack" :href="route('archive.sources.index')" :current="request()->routeIs('archive.sources.*')" wire:navigate>{{ __('Source Provenance') }}</flux:navlist.item>
    <flux:navlist.item icon="photo" :href="route('admin.viewing-derivatives.index')" :current="request()->routeIs('admin.viewing-derivatives.*')" wire:navigate>{{ __('Viewing Derivatives') }}</flux:navlist.item>
   @endif
  </flux:navlist.group>
 </flux:navlist>
 <flux:spacer />
 <flux:navlist variant="outline">
  <flux:navlist.item icon="globe-alt" :href="route('home')">{{ __('Public Home') }}</flux:navlist.item>
  <flux:navlist.item icon="map" :href="route('public-discovery.map')">{{ __('Archive Map') }}</flux:navlist.item>
 </flux:navlist>
 <x-desktop-user-menu />
</flux:sidebar>
<flux:header class="lg:hidden"><flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" /><flux:spacer /><x-dropdown-user-menu /></flux:header>
{{ $slot }}
@fluxScripts
</body></html>
