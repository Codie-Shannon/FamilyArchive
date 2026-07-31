@php
    $user = auth()->user();
    $isApprovedMember = $user?->account_state === 'approved';
    $isOwner = $isApprovedMember && $user?->role === 'owner';
    $placesRoute = $isOwner ? route('archive.locations.index') : route('public-discovery.map');

    $items = array_filter([
        $isApprovedMember ? [
            'label' => 'Photos',
            'href' => route('archive.index'),
            'active' => request()->routeIs('archive.index', 'archive.photos.*'),
        ] : null,
        [
            'label' => 'Places & map',
            'href' => $placesRoute,
            'active' => request()->routeIs('public-discovery.map', 'archive.locations.*'),
        ],
        $isOwner ? [
            'label' => 'People',
            'href' => route('archive.people.index'),
            'active' => request()->routeIs('archive.people.*'),
        ] : null,
        $isOwner ? [
            'label' => 'Events',
            'href' => route('archive.events.index'),
            'active' => request()->routeIs('archive.events.*'),
        ] : null,
        $isOwner ? [
            'label' => 'Branches',
            'href' => route('archive.branches.index'),
            'active' => request()->routeIs('archive.branches.*'),
        ] : null,
        $isOwner ? [
            'label' => 'Search',
            'href' => route('archive.knowledge'),
            'active' => request()->routeIs('archive.knowledge'),
        ] : null,
    ]);
@endphp

<nav aria-label="Explore archive" class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-xl border border-zinc-200 bg-white p-1 text-sm dark:border-zinc-700 dark:bg-zinc-900">
    @foreach($items as $item)
        <a
            href="{{ $item['href'] }}"
            @if($item['active']) aria-current="page" @endif
            class="whitespace-nowrap rounded-lg px-4 py-2 font-medium {{ $item['active'] ? 'bg-emerald-500 font-semibold text-zinc-950' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
