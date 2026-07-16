<x-layouts::app :title="__('Archive Schema')">
    <div class="flex w-full flex-1 flex-col gap-5">
        <header class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-400">Owner-only read-only overview</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-white">Archive Schema</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-300">
                    Group 02 archive records, intake records and original/derived file versions.
                    This surface contains no upload, replacement, deletion or storage mutation actions.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full border border-emerald-700 bg-emerald-950/70 px-3 py-1.5 text-emerald-300">
                    Owner allowed
                </span>
                <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-zinc-300">
                    Non-owner 403
                </span>
                <span class="rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-zinc-300">
                    Guest login redirect
                </span>
            </div>
        </header>

        <nav class="flex flex-wrap gap-2" aria-label="Archive schema views">
            @foreach ([
                'overview' => 'Overview',
                'media-item' => 'Media item',
                'incoming-upload' => 'Incoming upload',
                'file-versions' => 'File versions',
                'contracts' => 'Status contracts',
                'access-boundary' => 'Access boundary',
            ] as $viewKey => $viewLabel)
                <a
                    href="{{ route('admin.archive-schema', ['view' => $viewKey]) }}"
                    @class([
                        'rounded-lg border px-3 py-2 text-sm font-medium transition',
                        'border-white bg-white text-zinc-950' => $activeView === $viewKey,
                        'border-zinc-700 bg-zinc-900 text-zinc-300 hover:border-zinc-500 hover:text-white' => $activeView !== $viewKey,
                    ])
                >
                    {{ $viewLabel }}
                </a>
            @endforeach
        </nav>

        @if ($activeView === 'overview')
            <section class="grid gap-4 lg:grid-cols-3">
                @foreach ($tables as $table)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-zinc-400">{{ $table['name'] }}</p>
                                <h2 class="mt-1 text-xl font-semibold text-white">{{ $table['label'] }}</h2>
                            </div>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-emerald-950 text-emerald-300' => $table['healthy'],
                                'bg-amber-950 text-amber-300' => ! $table['healthy'],
                            ])>
                                {{ $table['healthy'] ? 'Healthy' : 'Needs migration' }}
                            </span>
                        </div>
                        <p class="mt-6 text-4xl font-semibold text-white">{{ $table['count'] }}</p>
                        <p class="mt-1 text-sm text-zinc-400">Fictional records</p>
                    </article>
                @endforeach
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-zinc-400">Migration health</p>
                            <h2 class="mt-1 text-xl font-semibold text-white">Core archive schema</h2>
                        </div>
                        <span class="text-sm font-semibold text-emerald-300">
                            {{ $appliedMigrationCount }}/{{ $expectedMigrationCount }} applied
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-zinc-800 px-4 py-3 text-sm text-zinc-200">MediaItem metadata only</div>
                        <div class="rounded-lg bg-zinc-800 px-4 py-3 text-sm text-zinc-200">IncomingUpload remains separate</div>
                        <div class="rounded-lg bg-zinc-800 px-4 py-3 text-sm text-zinc-200">Versions preserve lineage</div>
                    </div>
                </article>

                <article class="rounded-xl border border-emerald-800 bg-emerald-950/40 p-5">
                    <p class="text-sm font-medium text-emerald-300">Preservation boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Originals stay separate</h2>
                    <p class="mt-3 text-sm leading-6 text-zinc-300">
                        MediaItem stores archive metadata. File versions store original and derivative records.
                        Restrictive foreign keys prevent cascade deletion of original versions.
                    </p>
                </article>
            </section>
        @elseif ($activeView === 'media-item')
            <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-zinc-400">Fictional MediaItem</p>
                            <h2 class="mt-1 text-2xl font-semibold text-white">
                                {{ $mediaItem?->title ?? 'Demo record not seeded' }}
                            </h2>
                        </div>
                        <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-semibold text-emerald-300">
                            {{ $mediaItem?->review_status?->value ?? 'not_available' }}
                        </span>
                    </div>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Archive ID</dt><dd class="mt-1 font-mono text-sm text-white">{{ $mediaItem?->archive_id ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Media type</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->media_type?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Visibility</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->visibility?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Review status</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->review_status?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Date confidence</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->date_confidence?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Estimated decade</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->estimated_decade ?? 'Unknown' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Sensitivity</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->sensitivity_status?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Related versions</dt><dd class="mt-1 text-sm text-white">{{ $mediaItem?->fileVersions->count() ?? 0 }}</dd></div>
                    </dl>

                    <div class="mt-6 rounded-lg bg-zinc-800 p-4">
                        <p class="text-xs uppercase tracking-wide text-zinc-500">Description</p>
                        <p class="mt-2 text-sm leading-6 text-zinc-200">{{ $mediaItem?->description ?? 'No fictional demo record is available.' }}</p>
                    </div>
                </article>

                <article class="rounded-xl border border-emerald-800 bg-emerald-950/40 p-6">
                    <p class="text-sm font-medium text-emerald-300">MediaItem boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">No file paths on the archive record</h2>
                    <ul class="mt-5 space-y-3 text-sm text-zinc-200">
                        <li class="rounded-lg bg-zinc-950/50 px-4 py-3">Original path: not stored on MediaItem</li>
                        <li class="rounded-lg bg-zinc-950/50 px-4 py-3">Web path: not stored on MediaItem</li>
                        <li class="rounded-lg bg-zinc-950/50 px-4 py-3">Thumbnail path: not stored on MediaItem</li>
                        <li class="rounded-lg bg-zinc-950/50 px-4 py-3">Metadata and workflow state only</li>
                    </ul>
                </article>
            </section>
        @elseif ($activeView === 'incoming-upload')
            <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-medium text-zinc-400">Fictional IncomingUpload</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">{{ $incomingUpload?->upload_id ?? 'Demo record not seeded' }}</h2>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Original filename</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload?->original_filename ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">MIME type</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload?->mime_type ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">File size</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload ? number_format($incomingUpload->file_size_bytes) . ' bytes' : 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Processing</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload?->processing_status?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Review</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload?->review_status?->value ?? 'Not available' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-zinc-500">Duplicate state</dt><dd class="mt-1 text-sm text-white">{{ $incomingUpload?->duplicate_status?->value ?? 'Not available' }}</dd></div>
                    </dl>
                </article>

                <article class="rounded-xl border border-amber-800 bg-amber-950/30 p-6">
                    <p class="text-sm font-medium text-amber-300">Intake boundary</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Separate until approval</h2>
                    <div class="mt-5 space-y-3 text-sm">
                        <div class="rounded-lg bg-zinc-950/50 px-4 py-3 text-zinc-200">
                            Intake record: {{ $incomingUpload?->upload_id ?? 'Not available' }}
                        </div>
                        <div class="rounded-lg bg-zinc-950/50 px-4 py-3 text-zinc-200">
                            Approved archive link: {{ $incomingUpload?->mediaItem?->archive_id ?? 'None' }}
                        </div>
                        <div class="rounded-lg bg-zinc-950/50 px-4 py-3 text-zinc-200">
                            Source retained: {{ $incomingUpload?->source_file_retained ? 'Yes' : 'No' }}
                        </div>
                    </div>
                </article>
            </section>
        @elseif ($activeView === 'file-versions')
            <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-400">Fictional derivative lineage</p>
                        <h2 class="mt-1 text-2xl font-semibold text-white">Original and viewing versions are separate records</h2>
                    </div>
                    <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-semibold text-emerald-300">No cascade delete</span>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-3">
                    @forelse ($versions as $version)
                        <article @class([
                            'rounded-xl border p-5',
                            'border-emerald-700 bg-emerald-950/30' => $version->version_type->value === 'original',
                            'border-zinc-700 bg-zinc-800' => $version->version_type->value !== 'original',
                        ])>
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-mono text-xs text-zinc-400">Version #{{ $version->id }}</p>
                                <span class="rounded-full bg-zinc-950 px-2.5 py-1 text-xs font-semibold text-zinc-200">
                                    {{ $version->version_type->value }}
                                </span>
                            </div>
                            <dl class="mt-5 space-y-3 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-zinc-400">Parent</dt><dd class="text-right text-white">{{ $version->parentVersion?->version_type?->value ?? 'Root original' }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-400">MIME</dt><dd class="text-right text-white">{{ $version->mime_type }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-400">Dimensions</dt><dd class="text-right text-white">{{ $version->width }} x {{ $version->height }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-400">Generation</dt><dd class="text-right text-white">{{ $version->generation_status->value }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-400">Preferred</dt><dd class="text-right text-white">{{ $version->is_preferred ? 'Yes' : 'No' }}</dd></div>
                            </dl>
                        </article>
                    @empty
                        <p class="text-sm text-zinc-400">No fictional file versions are available.</p>
                    @endforelse
                </div>
            </section>
        @elseif ($activeView === 'contracts')
            <section class="grid gap-3 lg:grid-cols-2">
                @foreach ($enumContracts as $contractName => $values)
                    <article class="rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="text-sm font-semibold text-white">{{ $contractName }}</h2>
                            <span class="text-xs font-medium text-zinc-500">{{ count($values) }} values</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($values as $value)
                                <span class="rounded-md bg-zinc-800 px-2 py-1 font-mono text-[11px] text-zinc-200">{{ $value }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
                    <p class="text-sm font-medium text-zinc-400">Authorization response matrix</p>
                    <h2 class="mt-1 text-2xl font-semibold text-white">Owner access is enforced by the route</h2>

                    <div class="mt-6 overflow-hidden rounded-xl border border-zinc-700">
                        @foreach ($accessBoundary as $boundary)
                            <div class="grid gap-2 border-b border-zinc-700 bg-zinc-800 px-4 py-4 last:border-b-0 sm:grid-cols-[1fr_0.55fr_1.45fr] sm:items-center">
                                <p class="font-medium text-white">{{ $boundary['actor'] }}</p>
                                <p @class([
                                    'text-sm font-semibold',
                                    'text-emerald-300' => $boundary['allowed'],
                                    'text-amber-300' => ! $boundary['allowed'],
                                ])>{{ $boundary['result'] }}</p>
                                <p class="text-sm text-zinc-300">{{ $boundary['reason'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="rounded-xl border border-emerald-800 bg-emerald-950/40 p-6">
                    <p class="text-sm font-medium text-emerald-300">Active route middleware</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">Read-only Owner boundary</h2>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($routeMiddleware as $middleware)
                            <span class="rounded-md border border-emerald-800 bg-zinc-950/50 px-3 py-2 font-mono text-xs text-zinc-200">{{ $middleware }}</span>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm leading-6 text-zinc-300">
                        Feature tests execute all three access states. No upload, delete, replacement or storage mutation route is registered here.
                    </p>
                </article>
            </section>
        @endif
    </div>
</x-layouts::app>
