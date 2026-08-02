<div x-data="familyChat()" x-init="init()" class="relative z-[70]">
    <button type="button" x-on:click="show()" aria-label="Open family messages"
        class="fixed bottom-5 right-5 grid size-14 place-items-center rounded-full bg-emerald-500 text-zinc-950 shadow-2xl shadow-black/40 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-zinc-900">
        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3h6m-8.25 7.5 2.39-2.39c.28-.28.66-.44 1.06-.44h8.05A2.25 2.25 0 0 0 19 13.67V6.75a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4 6.75v10.94c0 .67.81 1 1.25.56Z"/></svg>
        <span x-cloak x-show="totalUnread() > 0" x-text="totalUnread() > 9 ? '9+' : totalUnread()" class="absolute -right-1 -top-1 min-w-5 rounded-full bg-rose-500 px-1.5 py-0.5 text-center text-xs font-bold text-white"></span>
    </button>

    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 bg-black/45 lg:bg-transparent" x-on:click.self="open = false"></div>
    <section x-cloak x-show="open" x-transition
        class="fixed inset-0 flex flex-col overflow-hidden bg-zinc-950 text-zinc-100 shadow-2xl sm:inset-auto sm:bottom-20 sm:right-5 sm:h-[min(680px,calc(100vh-7rem))] sm:w-[410px] sm:rounded-2xl sm:border sm:border-zinc-700"
        aria-label="Family messages">
        <header class="flex min-h-16 items-center gap-3 border-b border-zinc-800 px-4">
            <button x-show="view !== 'threads'" type="button" x-on:click="back()" class="rounded-lg p-2 text-zinc-300 hover:bg-zinc-800" aria-label="Back to conversations">←</button>
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-white" x-text="view === 'conversation' && conversation ? conversation.person.name : (view === 'new' ? 'New message' : 'Family messages')"></p>
                <p class="truncate text-xs text-zinc-400" x-text="view === 'conversation' && conversation ? roleLabel(conversation.person.role) : 'Approved family members only'"></p>
            </div>
            <div class="relative" x-show="view === 'conversation' && conversation">
                <button type="button" x-on:click="actionsOpen = !actionsOpen" x-on:contextmenu.prevent="actionsOpen = true" x-on:pointerdown="beginLongPress()" x-on:pointerup="cancelLongPress()" x-on:pointerleave="cancelLongPress()" class="rounded-lg p-2 text-xl text-zinc-300 hover:bg-zinc-800" aria-label="Conversation options">•••</button>
                <div x-cloak x-show="actionsOpen" x-on:click.outside="actionsOpen = false" class="absolute right-0 top-11 z-10 w-52 rounded-xl border border-zinc-700 bg-zinc-900 p-2 shadow-xl">
                    <button type="button" x-on:click="action(conversation.muted ? 'unmute' : 'mute')" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-800" x-text="conversation?.muted ? 'Unmute notifications' : 'Mute notifications'"></button>
                    <button type="button" x-on:click="action('archive')" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-zinc-800">Archive conversation</button>
                    <button type="button" x-on:click="action(conversation.blocked ? 'unblock' : 'block')" class="w-full rounded-lg px-3 py-2 text-left text-sm text-rose-300 hover:bg-zinc-800" x-text="conversation?.blocked ? 'Unblock messages' : 'Block messages'"></button>
                </div>
            </div>
            <button type="button" x-on:click="open = false" class="rounded-lg p-2 text-xl text-zinc-300 hover:bg-zinc-800" aria-label="Close family messages">×</button>
        </header>

        <div x-show="error" class="border-b border-rose-900 bg-rose-950/60 px-4 py-3 text-sm text-rose-200" x-text="error"></div>
        <div x-show="loading" class="h-1 overflow-hidden bg-zinc-900"><div class="h-full w-1/2 animate-pulse bg-emerald-400"></div></div>

        <div x-show="view === 'threads'" class="flex min-h-0 flex-1 flex-col">
            <div class="flex items-center justify-between border-b border-zinc-800 px-4 py-3">
                <p class="text-sm text-zinc-400" x-text="visibleThreads().length ? `${visibleThreads().length} conversations` : 'Start your first conversation'"></p>
                <button type="button" x-on:click="view = 'new'" class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-semibold text-zinc-950 hover:bg-emerald-400">New message</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-2">
                <template x-for="thread in visibleThreads()" :key="thread.id">
                    <button type="button" x-on:click="openThread(thread.id)" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-zinc-900">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-500/15 font-semibold text-emerald-300" x-text="thread.person.name.slice(0, 1).toUpperCase()"></span>
                        <span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-3"><span class="truncate font-semibold text-white" x-text="thread.person.name"></span><span class="text-xs text-zinc-500" x-text="time(thread.last_message_at)"></span></span><span class="mt-1 block truncate text-sm text-zinc-400" x-text="thread.preview || 'No messages yet'"></span></span>
                        <span x-show="thread.unread > 0" class="min-w-5 rounded-full bg-emerald-500 px-1.5 py-0.5 text-center text-xs font-bold text-zinc-950" x-text="thread.unread"></span>
                    </button>
                </template>
                <div x-show="!loading && visibleThreads().length === 0" class="grid h-full min-h-56 place-items-center px-8 text-center"><div><p class="font-semibold text-white">Family chat is ready</p><p class="mt-2 text-sm leading-6 text-zinc-400">Choose an approved family member and send a message without waiting for Owner approval.</p></div></div>
            </div>
        </div>

        <div x-show="view === 'new'" class="min-h-0 flex-1 overflow-y-auto p-2">
            <p class="px-3 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Approved family members</p>
            <label class="mx-3 mb-2 block">
                <span class="sr-only">Find a family member</span>
                <input x-model="contactSearch" type="search" placeholder="Find a family member…" class="w-full rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2.5 text-sm text-white placeholder:text-zinc-500 focus:border-emerald-500 focus:outline-none">
            </label>
            <template x-for="contact in visibleContacts()" :key="contact.id">
                <button type="button" x-on:click="start(contact.id)" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left hover:bg-zinc-900">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full bg-zinc-800 font-semibold text-emerald-300" x-text="contact.name.slice(0, 1).toUpperCase()"></span>
                    <span><span class="block font-semibold text-white" x-text="contact.name"></span><span class="text-sm text-zinc-400" x-text="roleLabel(contact.role)"></span></span>
                </button>
            </template>
            <p x-show="!loading && visibleContacts().length === 0" class="p-8 text-center text-sm text-zinc-400" x-text="contactSearch ? 'No approved family members match that search.' : 'No other approved family accounts are available.'"></p>
        </div>

        <div x-show="view === 'conversation'" class="flex min-h-0 flex-1 flex-col">
            <div x-ref="messages" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                <template x-for="message in (conversation?.messages ?? [])" :key="message.id">
                    <div class="group flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[82%]">
                            <div class="rounded-2xl px-4 py-2.5 text-sm leading-5" :class="message.mine ? 'rounded-br-md bg-emerald-500 text-zinc-950' : 'rounded-bl-md bg-zinc-800 text-zinc-100'" x-text="message.body"></div>
                            <div class="mt-1 flex items-center gap-2 px-1 text-[11px] text-zinc-500" :class="message.mine ? 'justify-end' : 'justify-start'"><span x-text="time(message.created_at)"></span><button x-show="!message.mine && message.state === 'visible'" type="button" x-on:click="report(message)" class="opacity-0 hover:text-rose-300 group-hover:opacity-100 focus:opacity-100">Report</button></div>
                        </div>
                    </div>
                </template>
                <p x-show="conversation && conversation.messages.length === 0" class="py-12 text-center text-sm text-zinc-500">No messages yet. Say hello.</p>
            </div>
            <div x-show="conversation?.blocked || conversation?.blocked_by_other" class="border-t border-zinc-800 px-4 py-4 text-center text-sm text-amber-200">Messages are blocked in this conversation. <button x-show="conversation?.blocked" x-on:click="action('unblock')" class="font-semibold underline">Unblock</button></div>
            <form x-show="conversation && !conversation.blocked && !conversation.blocked_by_other" x-on:submit.prevent="send()" class="flex items-end gap-2 border-t border-zinc-800 p-3">
                <textarea x-model="draft" x-on:keydown.enter.exact.prevent="send()" rows="1" maxlength="4000" placeholder="Message your family…" aria-label="Message" class="max-h-28 min-h-11 flex-1 resize-none rounded-xl border border-zinc-700 bg-zinc-900 px-3 py-2.5 text-sm text-white placeholder:text-zinc-500 focus:border-emerald-500 focus:outline-none"></textarea>
                <button type="submit" :disabled="!draft.trim()" class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-500 font-bold text-zinc-950 disabled:cursor-not-allowed disabled:opacity-40">↑</button>
            </form>
        </div>
    </section>
</div>
