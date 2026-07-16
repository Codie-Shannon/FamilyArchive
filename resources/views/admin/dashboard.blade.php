<x-layouts::app :title="__('Family Archive Admin')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                Owner administration
            </p>

            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">
                Family Archive
            </h1>

            <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                Archive intake, preservation, review, duplicate handling and integrity control.
            </p>
        </div>

        @php
            $cards = [
                [
                    'title' => 'Incoming uploads',
                    'value' => 0,
                    'description' => 'Media waiting for technical processing.',
                ],
                [
                    'title' => 'Pending review',
                    'value' => 0,
                    'description' => 'Processed media waiting for archive review.',
                ],
                [
                    'title' => 'Possible duplicates',
                    'value' => 0,
                    'description' => 'Possible matches requiring manual review.',
                ],
                [
                    'title' => 'Approved media',
                    'value' => 0,
                    'description' => 'Approved archive records.',
                ],
                [
                    'title' => 'Storage status',
                    'value' => 'Healthy',
                    'description' => 'No storage warnings have been detected.',
                ],
                [
                    'title' => 'Integrity warnings',
                    'value' => 0,
                    'description' => 'Missing or damaged files requiring attention.',
                ],
            ];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                <section
                    class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm
                           dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300">
                        {{ $card['title'] }}
                    </p>

                    <p class="mt-3 text-3xl font-semibold text-zinc-950 dark:text-white">
                        {{ $card['value'] }}
                    </p>

                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $card['description'] }}
                    </p>
                </section>
            @endforeach
        </div>

        <section
            class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm
                   dark:border-zinc-700 dark:bg-zinc-900"
        >
            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">
                Group 1 status
            </h2>

            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <p class="rounded-lg bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                    ✓ Laravel foundation
                </p>

                <p class="rounded-lg bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                    ✓ Owner authentication
                </p>

                <p class="rounded-lg bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                    ✓ Protected administration area
                </p>

                <p class="rounded-lg bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                    ○ Archive schema begins in Group 2
                </p>
            </div>
        </section>
    </div>
</x-layouts::app>