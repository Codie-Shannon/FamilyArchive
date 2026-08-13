const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const requestJson = async (url, method, payload) => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(payload),
    });
    if (!response.ok) throw new Error(`Archive selection request failed (${response.status}).`);
    return response.json();
};

const initializeGallery = gallery => {
    const context = gallery.dataset.context;
    const hidden = gallery.dataset.hiddenGallery === '1';
    const storageKey = `familyarchive:gallery:${context}:state`;
    const toolbar = gallery.querySelector('[data-selection-toolbar]');
    const editToggle = gallery.querySelector('[data-edit-toggle]');
    const countNode = gallery.querySelector('[data-selected-count]');
    const pagesNode = gallery.querySelector('[data-selected-pages]');
    const selectors = [...gallery.querySelectorAll('[data-photo-selector]')];
    const checkboxes = [...gallery.querySelectorAll('[data-photo-checkbox]')];
    const singleHide = gallery.querySelector('[data-single-hide-action]');
    const batchHide = gallery.querySelector('[data-batch-hide-form]');
    const editSelected = gallery.querySelector('[data-edit-selected]');
    const restore = gallery.querySelector('[data-restore-action]');
    let selectedCount = Number(gallery.dataset.initialSelectedCount || 0);
    let selectedPages = Number(gallery.dataset.initialSelectedPages || 0);
    let selectedIds = JSON.parse(gallery.dataset.initialSelectedIds || '[]').map(String);
    let editMode = hidden;

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
        if (!hidden) editMode = saved.editMode === true;
        if (saved.returning === true) {
            requestAnimationFrame(() => window.scrollTo({ top: Number(saved.scrollY || 0), behavior: 'instant' }));
            sessionStorage.setItem(storageKey, JSON.stringify({ ...saved, returning: false }));
        }
    } catch { /* A corrupt browser draft must not block the archive. */ }

    const saveView = extra => {
        let current = {};
        try { current = JSON.parse(sessionStorage.getItem(storageKey) || '{}'); } catch { current = {}; }
        sessionStorage.setItem(storageKey, JSON.stringify({ ...current, editMode, scrollY: window.scrollY, ...extra }));
    };

    const render = () => {
        selectors.forEach(node => node.classList.toggle('hidden', !editMode));
        toolbar?.classList.toggle('hidden', !(hidden || editMode));
        if (editToggle) editToggle.textContent = editMode ? 'Exit edit mode' : 'Edit photos';
        if (countNode) countNode.textContent = String(selectedCount);
        if (pagesNode) pagesNode.textContent = String(selectedPages);
        if (restore) restore.disabled = selectedCount === 0;
        if (singleHide) {
            singleHide.classList.toggle('hidden', selectedCount !== 1);
            if (selectedCount === 1) {
                const selectedId = selectedIds[0];
                if (selectedId) singleHide.href = gallery.dataset.singleHideTemplate.replace('__PHOTO__', selectedId);
            }
        }
        batchHide?.classList.toggle('hidden', selectedCount < 2);
        editSelected?.classList.toggle('hidden', selectedCount === 0);
        saveView();
    };

    const updateSelection = async (box, selected) => {
        box.disabled = true;
        try {
            const result = await requestJson(
                gallery.dataset.selectionUrlTemplate.replace('__PHOTO__', box.value),
                'PUT',
                { context, selected, source_page: Number(gallery.dataset.currentPage || 1) },
            );
            selectedCount = Number(result.selected_count || 0);
            selectedPages = Number(result.selected_page_count || 0);
            selectedIds = (result.selected_ids || []).map(String);
            box.checked = selected;
            box.closest('[data-photo-card]')?.classList.toggle('ring-2', selected);
            box.closest('[data-photo-card]')?.classList.toggle('ring-emerald-400', selected);
            render();
        } catch (error) {
            box.checked = !selected;
            window.alert(error.message);
        } finally { box.disabled = false; }
    };

    checkboxes.forEach(box => box.addEventListener('change', () => updateSelection(box, box.checked)));
    editToggle?.addEventListener('click', async () => {
        if (editMode && selectedCount > 0 && !window.confirm(`Exit edit mode and clear ${selectedCount} selected photos?`)) return;
        if (editMode && selectedCount > 0) {
            await requestJson(gallery.dataset.clearUrl, 'DELETE', { context });
            selectedCount = 0;
            selectedPages = 0;
            selectedIds = [];
            checkboxes.forEach(box => { box.checked = false; });
        }
        editMode = !editMode;
        render();
    });
    gallery.querySelector('[data-exit-edit]')?.addEventListener('click', () => editToggle?.click());
    gallery.querySelector('[data-clear-selection]')?.addEventListener('click', async () => {
        await requestJson(gallery.dataset.clearUrl, 'DELETE', { context });
        selectedCount = 0;
        selectedPages = 0;
        selectedIds = [];
        checkboxes.forEach(box => { box.checked = false; box.closest('[data-photo-card]')?.classList.remove('ring-2', 'ring-emerald-400'); });
        render();
    });
    gallery.querySelector('[data-select-page]')?.addEventListener('click', async () => {
        for (const box of checkboxes.filter(candidate => !candidate.checked)) await updateSelection(box, true);
    });
    gallery.querySelectorAll('[data-photo-link]').forEach(link => link.addEventListener('click', () => saveView({ returning: true })));
    gallery.querySelectorAll('a[href*="page="]').forEach(link => link.addEventListener('click', () => saveView()));
    gallery.querySelectorAll('[data-processing-form]').forEach(form => form.addEventListener('submit', () => {
        form.querySelector('button')?.setAttribute('disabled', 'disabled');
        toolbar?.querySelector('[data-processing-message]')?.classList.remove('hidden');
    }));
    window.addEventListener('beforeunload', () => saveView());
    render();
};

const initialize = () => document.querySelectorAll('[data-archive-gallery]').forEach(initializeGallery);
document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
