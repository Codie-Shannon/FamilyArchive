<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    @include('partials.head', ['title' => 'Family Conversations'])
</head>
<body class="min-h-screen bg-zinc-950 text-white">
    <main class="mx-auto max-w-5xl space-y-7 p-6 sm:p-10">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-300">Screenshot Group 02 · Build Groups 21–28</p>
                <h1 class="mt-1 text-3xl font-semibold text-white">Family conversations</h1>
                <p class="mt-2 max-w-2xl text-zinc-400">Moderated public discussion is separated from private archive knowledge.</p>
            </div>
            <div class="rounded-xl border border-emerald-800 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-100">
                v{{ \App\Support\Release::version() }} · {{ \App\Support\Release::name() }}
                <span class="mt-1 block text-emerald-200">
                    @auth
                        Signed in: {{ auth()->user()->name }} · {{ auth()->user()->account_state }}
                    @else
                        Public access · no archive access
                    @endauth
                </span>
            </div>
        </header>

        <aside class="rounded-xl border border-sky-900 bg-sky-950/30 p-5 text-sm text-sky-100">
            Public conversations never reveal private archive records, living-person details, original files or access grants.
        </aside>

        @if(session('status'))
            <p class="rounded-lg border border-emerald-700 bg-emerald-950/30 p-4 text-emerald-200">{{ session('status') }}</p>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-rose-800 bg-rose-950/30 p-4 text-rose-100">
                <p class="font-semibold">Request denied</p>
                @foreach($errors->all() as $error)
                    <p class="mt-1">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs uppercase text-zinc-500">Private archive</p><p class="mt-1 text-sm text-zinc-200">Verified Owner only</p></article>
            <article class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs uppercase text-zinc-500">Member posting</p><p class="mt-1 text-sm text-zinc-200">Approved account required</p></article>
            <article class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs uppercase text-zinc-500">Locked threads</p><p class="mt-1 text-sm text-zinc-200">New posts rejected</p></article>
            <article class="rounded-lg border border-zinc-800 bg-zinc-900 p-4"><p class="text-xs uppercase text-zinc-500">Anonymous contact</p><p class="mt-1 text-sm text-zinc-200">Rate-limited moderation</p></article>
        </section>

        <section class="grid gap-4">
            @forelse($threads as $thread)
                <article class="rounded-xl border border-zinc-700 bg-zinc-900 p-5">
                    <p class="text-xs uppercase tracking-wide text-emerald-300">Moderated public thread</p>
                    <h2 class="mt-1 text-xl font-semibold text-white">{{ $thread->subject }}</h2>
                    @if($thread->is_locked)
                        <p class="mt-2 text-sm font-medium text-amber-300">Thread locked · new posts are disabled</p>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse($messages->get($thread->id, collect()) as $message)
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                                <p class="text-sm font-semibold text-zinc-200">{{ $message->author_name }}</p>
                                <p class="mt-1 text-zinc-300">{{ $message->body }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">No approved posts yet.</p>
                        @endforelse
                    </div>

                    @auth
                        @if(! $thread->is_locked)
                        <form method="POST" action="{{ route('public-chat.message') }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <input type="hidden" name="thread_id" value="{{ $thread->id }}">
                            <input name="body" maxlength="4000" required class="min-w-0 flex-1 rounded-lg bg-zinc-950 p-3 text-white" placeholder="Add a respectful message">
                            <button class="rounded-lg bg-emerald-500 px-4 py-3 font-semibold text-black">Post</button>
                        </form>
                        @endif
                    @endauth
                </article>
            @empty
                <p class="text-zinc-400">No public conversations are open.</p>
            @endforelse
        </section>

        <section class="rounded-xl border border-zinc-700 bg-zinc-900 p-6">
            <h2 class="text-xl font-semibold text-white">Send an anonymous message</h2>
            <p class="mt-1 text-sm text-zinc-400">Messages enter moderation and never create an account or grant archive access.</p>
            <form method="POST" action="{{ route('anonymous-message.store') }}" class="mt-4 grid gap-3">
                @csrf
                <input name="subject" required maxlength="120" class="rounded-lg bg-zinc-950 p-3 text-white" placeholder="Subject">
                <input name="reply_email" type="email" class="rounded-lg bg-zinc-950 p-3 text-white" placeholder="Reply email (optional)">
                <textarea name="body" required minlength="10" maxlength="4000" class="min-h-32 rounded-lg bg-zinc-950 p-3 text-white" placeholder="Message"></textarea>
                <button class="w-fit rounded-lg bg-emerald-500 px-5 py-3 font-semibold text-black">Send to moderation</button>
            </form>
        </section>
    </main>
</body>
</html>
