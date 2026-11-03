// Copyright 2026 Andreas Giesen. See LICENSE.md.
import Config from 'core/config';

/** Markdown source and its unsaved preview occupy the same viewport. */
export const init = options => {
    const source = document.querySelector('#id_content_markdown');
    if (!source || source.dataset.previewReady) {
        return;
    }
    source.dataset.previewReady = '1';
    source.classList.add('local-storefront-markdown-source');
    const host = document.createElement('div');
    host.className = 'local-storefront-markdown';
    source.before(host);
    const toolbar = document.createElement('div');
    toolbar.className = 'local-storefront-markdown-toolbar btn-group';
    toolbar.setAttribute('role', 'tablist');
    toolbar.setAttribute('aria-label', options.preview);
    const makeTab = (label, id, selected) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.id = id;
        button.textContent = label;
        button.className = 'btn btn-outline-secondary' + (selected ? ' active' : '');
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-selected', String(selected));
        button.tabIndex = selected ? 0 : -1;
        toolbar.append(button);
        return button;
    };
    const edit = makeTab(options.edit, 'storefront-markdown-edit', true);
    const view = makeTab(options.preview, 'storefront-markdown-view', false);
    const editor = document.createElement('div');
    editor.id = 'storefront-markdown-editor';
    editor.setAttribute('role', 'tabpanel');
    editor.setAttribute('aria-labelledby', edit.id);
    editor.append(source);
    const preview = document.createElement('div');
    preview.id = 'storefront-markdown-preview';
    preview.className = 'local-storefront-markdown-preview';
    preview.setAttribute('role', 'tabpanel');
    preview.setAttribute('aria-labelledby', view.id);
    preview.tabIndex = 0;
    preview.hidden = true;
    edit.setAttribute('aria-controls', editor.id);
    view.setAttribute('aria-controls', preview.id);
    host.append(toolbar, editor, preview);
    let generation = 0;
    let sourceScroll = 0;
    let previewScroll = 0;
    let hasPreview = false;
    let controller;
    const showEdit = () => {
        generation++;
        controller?.abort();
        previewScroll = preview.scrollTop;
        preview.hidden = true;
        editor.hidden = false;
        source.scrollTop = sourceScroll;
        edit.classList.add('active');
        view.classList.remove('active');
        edit.setAttribute('aria-selected', 'true');
        view.setAttribute('aria-selected', 'false');
        edit.tabIndex = 0;
        view.tabIndex = -1;
    };
    const showPreview = async() => {
        const request = ++generation;
        controller?.abort();
        controller = new AbortController();
        sourceScroll = source.scrollTop;
        const sourceProgress = sourceScroll / Math.max(1, source.scrollHeight - source.clientHeight);
        preview.style.height = source.getBoundingClientRect().height + 'px';
        editor.hidden = true;
        preview.hidden = false;
        preview.textContent = options.loading;
        preview.setAttribute('aria-busy', 'true');
        edit.classList.remove('active');
        view.classList.add('active');
        edit.setAttribute('aria-selected', 'false');
        view.setAttribute('aria-selected', 'true');
        edit.tabIndex = -1;
        view.tabIndex = 0;
        try {
            const response = await fetch(options.url, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                body: new URLSearchParams({sesskey: Config.sesskey, content: source.value}),
            });
            const result = await response.json();
            if (!response.ok || typeof result.html !== 'string') {
                throw new Error('Preview unavailable');
            }
            if (request !== generation) {
                return;
            }
            // Server output uses the same Moodle HTML cleaning as the published revision.
            preview.innerHTML = result.html;
            preview.querySelectorAll('a').forEach(link => {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            });
            preview.scrollTop = hasPreview ? previewScroll
                : sourceProgress * Math.max(0, preview.scrollHeight - preview.clientHeight);
            hasPreview = true;
        } catch (error) {
            if (request === generation && error.name !== 'AbortError') {
                preview.textContent = options.error;
            }
        } finally {
            if (request === generation) {
                preview.removeAttribute('aria-busy');
            }
        }
    };
    edit.addEventListener('click', showEdit);
    view.addEventListener('click', showPreview);
    toolbar.addEventListener('keydown', event => {
        if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? edit : event.key === 'End' ? view
                : document.activeElement === edit ? view : edit;
            next.focus();
            next.click();
        }
    });
    // Never leave the source hidden when native form validation needs to focus it.
    source.form?.addEventListener('submit', showEdit);
    source.addEventListener('invalid', showEdit);
};
