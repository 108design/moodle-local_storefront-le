// This file is part of Moodle - https://moodle.org/
/**
 * Enhance the LE catalogue's native search and tag disclosure.
 *
 * @module local_storefront/catalogue_controls
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
import {init as initSearchBar} from 'local_storefront/search_bar';

export const init = () => {
    const form = document.querySelector('form.local-storefront-catalogue-filters');
    if (!form || form.dataset.catalogueControlsReady === '1') return;
    form.dataset.catalogueControlsReady = '1';
    initSearchBar(form);

    const tags = form.querySelector('.local-plugin-search-bar-tags');
    if (!tags) return;
    const count = tags.querySelector('[data-search-bar-tag-count]');
    const updateCount = () => {
        const selected = tags.querySelectorAll('input:checked').length;
        count.textContent = selected ? count.dataset.searchBarTagCount.replace('{count}', selected)
            : count.dataset.searchBarTagEmpty;
    };
    form.addEventListener('change', updateCount);
    form.addEventListener('search-bar-updated', updateCount);
    document.addEventListener('click', event => { if (!tags.contains(event.target)) tags.open = false; });
    tags.addEventListener('keydown', event => {
        if (event.key === 'Escape' && tags.open) {
            event.preventDefault();
            tags.open = false;
            tags.querySelector('summary').focus();
        }
    });
};
