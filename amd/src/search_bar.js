// This file is part of Moodle - https://moodle.org/
// @copyright 2026 Andreas Giesen <andreas@108design.com>
// @license 108design source-available software license; see LICENSE.md
// Copyright (C) 2026 Andreas Giesen. See the consuming plugin's LICENSE.md.
// Shared progressive enhancement. A consumer may supply a local filter adapter instead of the GET adapter.
const remoteControllers = new Set();

export const init = (form, adapter = {}) => {
    if (form.dataset.searchBarReady === '1') return;
    if (form.method.toLowerCase() !== 'get' || new URL(form.action).origin !== window.location.origin) return;
    const scope = form.closest('[data-search-bar-scope]');
    const results = scope?.querySelector('[data-search-bar-results]');
    if (!results) return; // Navigation-only search forms keep native submit behaviour.
    form.dataset.searchBarReady = '1';
    form.classList.add('local-plugin-search-bar-live');
    const status = scope.querySelector('[data-search-bar-status]');
    const scopeId = scope.dataset.searchBarScope;
    let timer;
    let controller;
    let generation = 0;
    let composing = false;
    let lastUrl = window.location.href;
    const invalidate = () => {
        ++generation;
        controller?.abort();
        window.clearTimeout(timer);
        results.removeAttribute('aria-busy');
    };
    const self = {scope, scopeId, invalidate, sync: null};
    if (!adapter.update) remoteControllers.add(self);
    const invalidatePeers = () => {
        for (const peer of remoteControllers) {
            if (peer !== self && peer.scope.isConnected) peer.invalidate();
        }
    };
    const urlFromForm = () => {
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        return url;
    };
    const setStatus = message => { if (status) status.textContent = message; };
    const restoreFields = next => {
        for (const field of form.elements) {
            if (!field.name || field.type === 'hidden') continue;
            const match = [...next.elements].find(candidate => candidate.name === field.name
                && (field.type !== 'checkbox' || candidate.value === field.value));
            if (!match) continue;
            if (field.type === 'checkbox') field.checked = match.checked;
            else if (field.matches('input, select')) field.value = match.value;
        }
    };
    const apply = (nextScope, restore, focus = true) => {
        const nextResults = nextScope.querySelector('[data-search-bar-results]');
        const nextForm = nextScope.querySelector('form[data-search-bar]');
        if (!nextResults || !nextForm) throw new Error('Missing search results');
        results.replaceChildren(...nextResults.childNodes);
        // Independent bars on one page carry each other's current filters as hidden fields.
        for (const field of form.elements) {
            if (field.type !== 'hidden' || !field.name) continue;
            const match = [...nextForm.elements].find(candidate => candidate.name === field.name && candidate.type === 'hidden');
            if (match) field.value = match.value;
        }
        const nextChips = [...nextScope.querySelectorAll('[data-search-bar-chips]')];
        for (const chips of scope.querySelectorAll('[data-search-bar-chips]')) {
            const match = nextChips.find(node => node.dataset.searchBarChips === chips.dataset.searchBarChips);
            chips.replaceChildren(...(match?.childNodes || []));
        }
        const reset = form.querySelector('[data-search-bar-reset]');
        const nextReset = nextForm.querySelector('[data-search-bar-reset]');
        if (reset && nextReset) {
            reset.href = nextReset.href;
            reset.hidden = nextReset.hidden;
        }
        if (restore) {
            restoreFields(nextForm);
            if (focus) form.querySelector('input[type="search"]')?.focus({preventScroll: true});
        }
        form.dispatchEvent(new CustomEvent('search-bar-updated'));
    };
    self.sync = (nextScope, url) => {
        invalidate();
        apply(nextScope, true, false);
        lastUrl = url.href;
        setStatus('');
        form.classList.add('local-plugin-search-bar-live');
    };
    const request = async(url = urlFromForm(), restore = false, history = true) => {
        if (!adapter.update) invalidatePeers();
        window.clearTimeout(timer);
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;
        const current = ++generation;
        results.setAttribute('aria-busy', 'true');
        setStatus(form.dataset.searchBarLoading || '');
        try {
            if (adapter.update) {
                await adapter.update(new FormData(form), {signal, isCurrent: () => current === generation});
            } else {
                const response = await fetch(url, {credentials: 'same-origin', signal});
                if (!response.ok || response.redirected) throw new Error('Search unavailable');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextScope = [...page.querySelectorAll('[data-search-bar-scope]')]
                    .find(node => node.dataset.searchBarScope === scopeId);
                if (!nextScope) throw new Error('Search unavailable');
                if (current !== generation) return;
                apply(nextScope, restore);
                for (const peer of remoteControllers) {
                    if (peer === self || !peer.scope.isConnected) continue;
                    const nextPeer = [...page.querySelectorAll('[data-search-bar-scope]')]
                        .find(node => node.dataset.searchBarScope === peer.scopeId);
                    if (nextPeer) peer.sync(nextPeer, url);
                }
                if (history && url.href !== lastUrl) window.history.pushState(null, '', url.href);
                lastUrl = url.href;
            }
            if (current === generation) {
                form.classList.add('local-plugin-search-bar-live');
                setStatus('');
            }
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) {
                window.console.warn('Search bar update failed:', error.message);
                setStatus(form.dataset.searchBarError || '');
                // Keep native submit available for retry; never discard the query or old results.
                form.classList.remove('local-plugin-search-bar-live');
            }
        } finally {
            if (current === generation) results.removeAttribute('aria-busy');
        }
    };
    const schedule = delay => {
        if (!adapter.update) invalidatePeers();
        window.clearTimeout(timer);
        // Invalidate immediately, including responses arriving during the typing pause.
        ++generation;
        controller?.abort();
        results.removeAttribute('aria-busy');
        timer = window.setTimeout(() => request(), delay);
    };
    form.addEventListener('input', event => {
        if (event.target.matches('input[type="search"]') && !composing) schedule(adapter.delay ?? 300);
    });
    form.addEventListener('compositionstart', () => {
        composing = true;
        if (!adapter.update) invalidatePeers();
        invalidate();
        setStatus('');
    });
    form.addEventListener('compositionend', () => { composing = false; schedule(adapter.delay ?? 300); });
    form.addEventListener('change', event => {
        if (event.target.matches('select, input[type="checkbox"]')) schedule(0);
    });
    form.addEventListener('submit', event => {
        // Once a network error exposes the fallback button, submit uses normal navigation.
        if (!form.classList.contains('local-plugin-search-bar-live')) return;
        event.preventDefault();
        request();
    });
    scope.addEventListener('click', event => {
        const link = event.target.closest('[data-search-bar-reset], [data-search-bar-chips] a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const url = new URL(link.href);
        if (link.dataset.searchBarRemove) {
            // Remove only this facet, retaining input/select changes still awaiting a response.
            const current = urlFromForm();
            current.searchParams.set(link.dataset.searchBarRemove, link.dataset.searchBarDefault || '');
            request(current, true);
        } else request(url, true);
    });
    window.addEventListener('popstate', () => {
        if (!adapter.update && [...remoteControllers].find(peer => peer.scope.isConnected) !== self) return;
        request(new URL(window.location.href), true, false);
    });
};

/** Wrap an already live local picker input. Never create a nested form. */
export const enhanceInput = input => {
    if (input.dataset.searchBarLocalReady === '1') return input.closest('.local-plugin-search-bar');
    input.dataset.searchBarLocalReady = '1';
    const bar = document.createElement('div');
    bar.className = 'local-plugin-search-bar local-plugin-search-bar-manager';
    const fields = document.createElement('div');
    fields.className = 'local-plugin-search-bar-fields';
    const field = document.createElement('div');
    field.className = 'local-plugin-search-bar-field';
    const chips = document.createElement('div');
    chips.className = 'local-plugin-search-bar-chips';
    input.before(bar);
    field.append(input, chips); fields.append(field); bar.append(fields);
    input.classList.remove('mb-2', 'mb-3');
    const refresh = () => {
        chips.replaceChildren();
        if (!input.value.trim()) return;
        const chip = document.createElement('button');
        chip.type = 'button'; chip.className = 'badge';
        chip.setAttribute('aria-label', `${input.getAttribute('aria-label') || input.placeholder}: ${input.value} ×`);
        const label = document.createElement('span'); label.className = 'local-plugin-search-bar-chip-label';
        label.textContent = input.value;
        const remove = document.createElement('span'); remove.className = 'local-plugin-search-bar-chip-remove';
        remove.textContent = '×'; remove.setAttribute('aria-hidden', 'true'); chip.append(label, remove);
        chip.addEventListener('click', () => {
            input.value = ''; input.dispatchEvent(new Event('input', {bubbles: true})); input.focus();
        });
        chips.append(chip);
    };
    input.addEventListener('input', refresh);
    input.addEventListener('change', refresh);
    input.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    refresh();
    return bar;
};
