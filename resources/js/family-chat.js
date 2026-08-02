const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

window.familyChat = () => ({
    open: false,
    loading: false,
    view: 'threads',
    contacts: [],
    contactSearch: '',
    threads: [],
    conversation: null,
    draft: '',
    error: '',
    actionsOpen: false,
    longPressTimer: null,

    init() {
        window.addEventListener('family-chat:open', () => this.show());
        window.setInterval(() => { if (this.open) this.refresh(false); }, 15000);
    },

    async request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token(), ...(options.headers ?? {}) },
            ...options,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(validation ?? data.message ?? 'The message service could not complete that action.');
        }
        return data;
    },

    async show() {
        this.open = true;
        await this.refresh(true);
    },

    async refresh(showLoading = true) {
        if (showLoading) this.loading = true;
        this.error = '';
        try {
            const data = await this.request('/family-messages');
            this.contacts = data.contacts;
            this.threads = data.threads;
            if (this.conversation) await this.openThread(this.conversation.id, false);
        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    async start(recipientId) {
        this.loading = true;
        this.error = '';
        try {
            this.conversation = await this.request('/family-messages/threads', { method: 'POST', body: JSON.stringify({ recipient_id: recipientId }) });
            this.view = 'conversation';
            await this.refresh(false);
            this.scrollToEnd();
        } catch (error) { this.error = error.message; } finally { this.loading = false; }
    },

    async openThread(id, loading = true) {
        if (loading) this.loading = true;
        this.error = '';
        try {
            this.conversation = await this.request(`/family-messages/threads/${id}`);
            this.view = 'conversation';
            this.scrollToEnd();
        } catch (error) { this.error = error.message; } finally { this.loading = false; }
    },

    async send() {
        const message = this.draft.trim();
        if (!message || !this.conversation) return;
        this.draft = '';
        this.error = '';
        try {
            this.conversation = await this.request(`/family-messages/threads/${this.conversation.id}/messages`, { method: 'POST', body: JSON.stringify({ message }) });
            await this.refresh(false);
            this.scrollToEnd();
        } catch (error) { this.draft = message; this.error = error.message; }
    },

    async action(action) {
        if (!this.conversation) return;
        this.actionsOpen = false;
        this.error = '';
        try {
            const state = await this.request(`/family-messages/threads/${this.conversation.id}/setting`, { method: 'PATCH', body: JSON.stringify({ action }) });
            Object.assign(this.conversation, state);
            if (action === 'archive') { this.view = 'threads'; this.conversation = null; }
            await this.refresh(false);
        } catch (error) { this.error = error.message; }
    },

    async report(message) {
        if (!window.confirm('Report this message to a family administrator?')) return;
        try {
            await this.request(`/family-messages/messages/${message.id}/report`, { method: 'PATCH', body: '{}' });
            message.state = 'reported';
            message.body = 'This message is unavailable.';
        } catch (error) { this.error = error.message; }
    },

    back() { this.view = 'threads'; this.conversation = null; this.actionsOpen = false; this.refresh(false); },
    beginLongPress() { this.cancelLongPress(); this.longPressTimer = window.setTimeout(() => { this.actionsOpen = true; }, 600); },
    cancelLongPress() { if (this.longPressTimer) window.clearTimeout(this.longPressTimer); this.longPressTimer = null; },
    scrollToEnd() { this.$nextTick(() => { const list = this.$refs.messages; if (list) list.scrollTop = list.scrollHeight; }); },
    roleLabel(role) { return ({ trusted_contributor: 'Trusted contributor', contributor: 'Contributor', viewer: 'Family member', admin: 'Administrator', owner: 'Owner' })[role] ?? 'Family member'; },
    time(value) { if (!value) return ''; return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value)); },
    visibleThreads() { return this.threads.filter(thread => !thread.archived); },
    visibleContacts() {
        const query = this.contactSearch.trim().toLocaleLowerCase();

        if (!query) return this.contacts;

        return this.contacts.filter(contact => `${contact.name} ${this.roleLabel(contact.role)}`.toLocaleLowerCase().includes(query));
    },
    totalUnread() { return this.threads.reduce((sum, thread) => sum + Number(thread.unread || 0), 0); },
});
