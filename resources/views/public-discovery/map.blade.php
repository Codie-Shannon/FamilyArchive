@php
    $layout = auth()->user()?->account_state === 'approved'
        ? 'layouts::app'
        : 'layouts.public-discovery';
    $mapPoints = $points->values();
@endphp

<x-dynamic-component :component="$layout" title="Archive Map">
    <x-archive-shell>
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-300">Privacy-safe geography</p>
                <h1 class="mt-2 text-3xl font-semibold text-white sm:text-4xl">Archive map</h1>
                <p class="mt-2 max-w-3xl text-base leading-7 text-zinc-400 sm:text-lg">Explore reviewed family places on a real map. Markers are deliberately reduced to neighbourhood, town or region precision before they reach this page.</p>
            </div>
            <div class="rounded-xl border border-emerald-900 bg-emerald-950/30 px-5 py-3 text-sm text-emerald-200">
                {{ $points->count() }} approved public {{ Str::plural('location', $points->count()) }}
            </div>
        </header>

        <section class="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1.75fr)_minmax(20rem,0.7fr)]">
            <div class="min-w-0 self-start overflow-hidden rounded-3xl border border-zinc-700 bg-zinc-900 shadow-xl shadow-black/20">
                @if($googleMapsKey !== '')
                    <div
                        id="archive-map"
                        class="min-h-[18rem] w-full sm:min-h-[28rem] xl:min-h-[34rem]"
                        role="region"
                        aria-label="Interactive map of privacy-reviewed archive places"
                    ></div>
                    <noscript>
                        <p class="p-6 text-zinc-300">JavaScript is required to use the interactive archive map. The reviewed place list remains available alongside it.</p>
                    </noscript>
                @else
                    <div class="grid min-h-[18rem] place-items-center p-8 text-center sm:min-h-[28rem] xl:min-h-[34rem]">
                        <div class="max-w-lg">
                            <div class="mx-auto grid size-16 place-items-center rounded-2xl border border-emerald-800 bg-emerald-950/40 text-3xl">⌖</div>
                            <h2 class="mt-5 text-2xl font-semibold text-white">Interactive map configuration pending</h2>
                            <p class="mt-3 text-zinc-400">The reviewed place list is available now. Add the restricted Google Maps browser key to render map tiles and interactive markers.</p>
                        </div>
                    </div>
                @endif
                <div class="border-t border-zinc-700 bg-zinc-950/90 px-5 py-4 text-sm text-zinc-400">
                    Google Maps · privacy-reduced markers only · exact archive coordinates withheld
                </div>
            </div>

            <aside class="min-w-0 space-y-3 xl:max-h-[38.75rem] xl:overflow-y-auto xl:pr-2" aria-label="Reviewed map places">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white">Reviewed places</h2>
                    <span class="text-xs uppercase tracking-wide text-zinc-500">Select a marker or card</span>
                </div>
                @forelse($points as $index => $point)
                    <button
                        type="button"
                        data-map-point="{{ $index }}"
                        aria-pressed="false"
                        class="map-point-card w-full rounded-xl border border-zinc-700 bg-zinc-900 p-3.5 text-left transition hover:border-emerald-700 hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-emerald-400"
                    >
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full border border-emerald-200 bg-emerald-500 text-xs font-bold text-zinc-950">{{ $index + 1 }}</span>
                                <span class="truncate font-semibold text-white">{{ $point['title'] }}</span>
                            </span>
                            <span class="text-xs uppercase tracking-wide text-emerald-300">{{ $point['precision'] }}</span>
                        </span>
                        <span class="mt-2 block pl-10 text-sm text-zinc-400">{{ $point['place'] }}</span>
                        <span class="mt-2 block pl-10 text-xs text-zinc-500">Privacy reviewed · exact location withheld</span>
                    </button>
                @empty
                    <p class="rounded-xl border border-dashed border-zinc-700 p-4 text-zinc-400">No approved map points.</p>
                @endforelse
            </aside>
        </section>

        <aside class="rounded-2xl border border-amber-900 bg-amber-950/20 p-5 text-amber-100">
            The map receives only published, privacy-reviewed points. Private records, unreviewed locations and exact coordinates never enter its browser payload.
        </aside>
    </x-archive-shell>

    @if($googleMapsKey !== '')
        <script>
            (() => {
                const points = @json($mapPoints);
                const mapStyle = [
                    { elementType: 'geometry', stylers: [{ color: '#18181b' }] },
                    { elementType: 'labels.text.stroke', stylers: [{ color: '#18181b' }] },
                    { elementType: 'labels.text.fill', stylers: [{ color: '#a1a1aa' }] },
                    { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#3f3f46' }] },
                    { featureType: 'landscape', elementType: 'geometry', stylers: [{ color: '#202024' }] },
                    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
                    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#3f3f46' }] },
                    { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#d4d4d8' }] },
                    { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0f3d46' }] },
                    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#67e8f9' }] },
                ];

                window.initArchiveMap = () => {
                    const map = new google.maps.Map(document.getElementById('archive-map'), {
                        center: points.length ? { lat: points[0].latitude, lng: points[0].longitude } : { lat: -41.2865, lng: 174.7762 },
                        zoom: points.length ? 7 : 5,
                        clickableIcons: false,
                        fullscreenControl: true,
                        mapTypeControl: false,
                        streetViewControl: false,
                        styles: mapStyle,
                    });
                    const bounds = new google.maps.LatLngBounds();
                    const infoWindow = new google.maps.InfoWindow();
                    const markers = points.map((point, index) => {
                        const position = { lat: point.latitude, lng: point.longitude };
                        const marker = new google.maps.Marker({
                            map,
                            position,
                            title: point.place,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 16,
                                fillColor: '#10b981',
                                fillOpacity: 1,
                                strokeColor: '#d1fae5',
                                strokeOpacity: 1,
                                strokeWeight: 2,
                            },
                            label: { text: String(index + 1), color: '#18181b', fontSize: '12px', fontWeight: '800' },
                            zIndex: 100 + index,
                        });
                        bounds.extend(position);
                        marker.addListener('click', () => {
                            infoWindow.setContent(`
                                <article style="min-width:220px;max-width:280px;padding:6px 4px 4px;color:#18181b;font-family:system-ui,sans-serif">
                                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                                        <span style="display:grid;width:30px;height:30px;flex:0 0 30px;place-items:center;border-radius:999px;background:#10b981;color:#18181b;font-size:12px;font-weight:800">${index + 1}</span>
                                        <strong style="font-size:15px;line-height:1.25">${escapeMapText(point.title)}</strong>
                                    </div>
                                    <div style="font-size:14px;font-weight:600">${escapeMapText(point.place)}</div>
                                    <div style="margin-top:8px;display:inline-block;border-radius:999px;background:#d1fae5;padding:4px 8px;color:#065f46;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase">${escapeMapText(point.precision)} precision</div>
                                    <div style="margin-top:9px;color:#52525b;font-size:11px">Privacy reviewed · exact location withheld</div>
                                </article>
                            `);
                            infoWindow.open({ map, anchor: marker });
                            highlightCard(index);
                        });

                        return marker;
                    });

                    if (markers.length > 1) {
                        map.fitBounds(bounds, 64);
                    }

                    document.querySelectorAll('[data-map-point]').forEach((card) => {
                        card.addEventListener('click', () => {
                            const index = Number(card.dataset.mapPoint);
                            const marker = markers[index];
                            if (! marker) return;
                            map.panTo(marker.getPosition());
                            map.setZoom(Math.max(map.getZoom() ?? 7, 9));
                            google.maps.event.trigger(marker, 'click');
                        });
                    });
                };

                const highlightCard = (selectedIndex) => {
                    document.querySelectorAll('[data-map-point]').forEach((card, index) => {
                        card.classList.toggle('border-emerald-500', index === selectedIndex);
                        card.classList.toggle('bg-emerald-950/20', index === selectedIndex);
                        card.setAttribute('aria-pressed', index === selectedIndex ? 'true' : 'false');
                    });
                };

                const escapeMapText = (value) => String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            })();
        </script>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ rawurlencode($googleMapsKey) }}&callback=initArchiveMap&v=weekly"></script>
    @endif
</x-dynamic-component>
