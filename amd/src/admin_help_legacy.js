// This file is part of Moodle - https://moodle.org/
/**
 * This file is part of a 108design source-available software product.
 *
 * Use and modification are permitted only under the Software License included
 * with this distribution. See LICENSE.md for the full terms.
 *
 * File documentation for amd/src/admin_help_legacy.js.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen
 * @license   See LICENSE.md for the full terms.
 */
// Copyright (C) 2026 Andreas Giesen. See LICENSE.md.
import {publish, subscribe} from 'core/pubsub';
import DrawerEvents from 'core/drawer_events';
import Drawers from 'theme_boost/drawers';
import jQuery from 'jquery';

/** Nonmodal contextual help; use native exclusive drawer coordination when available. */
export const init = config => {
    const panel = document.getElementById('local-storefront-context-help');
    if (!panel || panel.dataset.initialised) return;
    panel.dataset.initialised = '1';
    const navigation = document.querySelector('#usernavigation');
    const helpEntry = navigation?.querySelector('.local-storefront-help-navbar');
    if (helpEntry) {
        const anchor = navigation.querySelector('.local-storefront-cart-nav')
            || navigation.querySelector(':scope > .divider, :scope > .usermenu-container');
        if (anchor && anchor.parentElement === navigation) navigation.insertBefore(helpEntry, anchor);
    }

    document.body.append(panel);
    const heading = panel.querySelector('h2');
    const content = panel.querySelector('.local-storefront-help-content');
    const choice = panel.querySelector('select');
    const helpUrl = new URL(panel.dataset.storefrontHelpUrl, location.href);
    const hasTopic = topic => [...choice.options].some(option => option.value === topic);
    let opener = null;
    let activeTopic = '';
    let ownsSpace = false;
    const exclusiveDrawers = Boolean(DrawerEvents.DRAWER_EXCLUSIVE_REQUESTED && DrawerEvents.DRAWER_EXCLUSIVE_RELEASED);
    document.body.classList.toggle('local-storefront-help-legacy-drawers', !exclusiveDrawers);

    const position = () => {
        const nav = document.querySelector('.navbar.fixed-top');
        const top = nav ? Math.max(0, nav.getBoundingClientRect().bottom) + 12 : 12;
        panel.style.setProperty('--local-storefront-help-top', `${top}px`);
    };
    const hide = (returnFocus = true, releaseSpace = true) => {
        if (panel.hidden) return;
        panel.hidden = true;
        document.body.classList.remove('local-storefront-help-open');
        document.querySelectorAll('[data-storefront-help-open]').forEach(button => button.setAttribute('aria-expanded', 'false'));
        if (ownsSpace) {
            ownsSpace = false;
            if (releaseSpace) publish(DrawerEvents.DRAWER_EXCLUSIVE_RELEASED, {region: 'right'});
        }
        if (returnFocus && opener?.isConnected) opener.focus();
    };
    const showTopic = topic => {
        const article = [...panel.querySelectorAll('[data-storefront-help-article]')]
            .find(node => node.dataset.storefrontHelpArticle === topic);
        if (!article) return;
        panel.querySelectorAll('article').forEach(node => { node.hidden = node !== article; });
        choice.value = topic;
        if (activeTopic !== topic) content.scrollTop = 0;
        activeTopic = topic;
    };
    const show = (topic, trigger) => {
        if (topic && !hasTopic(topic)) return;
        if (trigger) opener = trigger;
        showTopic(topic || activeTopic || choice.value);
        position();
        if (panel.hidden) {
            if (exclusiveDrawers) {
                publish(DrawerEvents.DRAWER_EXCLUSIVE_REQUESTED, {region: 'right'});
                ownsSpace = true;
            }
            panel.hidden = false;
            document.body.classList.add('local-storefront-help-open');
        }
        document.querySelectorAll('[data-storefront-help-open]').forEach(button => button.setAttribute('aria-expanded', 'true'));
        (panel.querySelector('article:not([hidden]) h3') || heading).focus();
    };
    document.addEventListener('click', event => {
        // Native information controls inside labels/disclosures must not change their host control.
        if (event.target.closest('.local-storefront-context-help-icon')?.closest('label, summary')) {
            event.preventDefault();
        }
        const close = event.target.closest('[data-storefront-help-close]');
        if (close) { hide(); return; }
        const button = event.target.closest('[data-storefront-help-open]');
        const link = event.target.closest('a[href]');
        let topic = '';
        if (link) {
            const url = new URL(link.href, window.location.href);
            if (url.origin === location.origin && url.pathname === helpUrl.pathname) {
                const requested = url.searchParams.get('storefronthelp') || '';
                if (hasTopic(requested)) topic = requested;
            }
        }
        if (!button && !topic) return;
        event.preventDefault();
        if (button && !panel.hidden) { hide(); return; }
        const popover = link?.closest('.popover');
        // Moodle adapters may describe the outer popover or its body; match the actual trigger.
        const describedIds = [popover?.id, popover?.querySelector('.popover-body')?.id].filter(Boolean);
        const icon = [...document.querySelectorAll('.help-icon[aria-describedby]')]
            .find(candidate => candidate.getAttribute('aria-describedby').split(/\s+/)
                .some(id => describedIds.includes(id)));
        const trigger = button || icon || document.querySelector('[data-storefront-help-open]');
        if (topic) {
            jQuery('.local-storefront-context-help-icon').popover('hide');
        }
        show(topic, trigger);
    });
    choice.addEventListener('change', () => showTopic(choice.value));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || panel.hidden || document.querySelector('.modal.show, dialog[open]')) return;
        event.preventDefault();
        hide();
    });
    // Native tools win when explicitly reopened. Their DOM and input state are never replaced.
    document.addEventListener(Drawers.eventTypes.drawerShow, event => {
        if (event.target.classList.contains('drawer-right') && !panel.hidden) hide(false);
    });
    // A second overlay now owns the native exclusive request; do not release its claim.
    if (exclusiveDrawers) subscribe(DrawerEvents.DRAWER_EXCLUSIVE_REQUESTED, ({region} = {}) => {
        if (region === 'right' && ownsSpace) hide(false, false);
    });
    document.addEventListener('show.bs.modal', () => hide(false));
    jQuery(document).on('show.bs.modal', () => hide(false));
    window.addEventListener('resize', position);
    if (config.topic) show(config.topic);
};
