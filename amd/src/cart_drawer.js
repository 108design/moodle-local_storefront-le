/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
define([], function() {
    'use strict';
    var initialized = false;
    return {init: function(config) {
        if (initialized || typeof HTMLDialogElement === 'undefined') return;
        var dialog = document.getElementById('local-storefront-cart-drawer');
        if (!dialog) return;
        // Moodle's native footer may be collapsed; the modal belongs outside that hidden subtree.
        document.body.append(dialog);
        initialized = true;
        var opener, queue = Promise.resolve();
        var status = dialog.querySelector('[data-storefront-cart-status]');
        var open = function(source) {
            if (!dialog.open) opener = source || document.activeElement;
            var mode = document.documentElement.dataset.bsTheme || document.body.dataset.bsTheme;
            dialog.dataset.theme = mode === 'dark' ? 'dark' : 'light';
            if (!dialog.open) dialog.showModal();
            document.documentElement.classList.add('local-storefront-selection-open');
            dialog.querySelector('[data-storefront-close]').focus();
        };
        var update = function(result) {
            dialog.querySelector('[data-storefront-cart-body]').innerHTML = result.html;
            document.querySelectorAll('[data-storefront-open]').forEach(function(link) {
                link.setAttribute('aria-label', result.label);
                link.title = result.label;
                link.querySelector('.local-storefront-cart-badge').textContent = String(result.count);
            });
        };
        dialog.querySelector('[data-storefront-close]').addEventListener('click', function() {dialog.close();});
        dialog.addEventListener('close', function() {
            document.documentElement.classList.remove('local-storefront-selection-open');
            if (opener && opener.isConnected) opener.focus();
        });
        dialog.addEventListener('click', function(event) {if (event.target === dialog) {
            var box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        }});
        document.addEventListener('click', function(event) {
            var link = event.target.closest('[data-storefront-open]');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault(); open(link);
        });
        document.addEventListener('submit', function(event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || new URL(form.getAttribute('action') || location.href, location.href).href !== config.endpoint) return;
            // These pages must refresh their complete order review after a selection change.
            if (/\/local\/storefront\/(cart|checkout|moodle_payment)\.php$/.test(location.pathname)) return;
            event.preventDefault();
            var data = new FormData(form); data.set('ajax', '1');
            var source = event.submitter || document.activeElement;
            queue = queue.then(async function() {
                var buttons = Array.from(form.querySelectorAll('button')).filter(function(button) {return !button.disabled;});
                buttons.forEach(function(button) {button.disabled = true;});
                var controller = new AbortController();
                var timer = setTimeout(function() {controller.abort();}, 30000);
                try {
                    var response = await fetch(config.endpoint, {method:'POST', body:data, credentials:'same-origin', signal:controller.signal});
                    if (!response.ok) throw new Error(config.error);
                    var result = await response.json();
                    if (typeof result.html !== 'string' || !Number.isInteger(result.count) || typeof result.label !== 'string') throw new Error(config.error);
                    status.textContent = ''; update(result); open(source);
                } catch (error) {status.textContent = config.error; open(source);}
                finally {clearTimeout(timer);buttons.filter(function(button) {return button.isConnected;}).forEach(function(button) {button.disabled = false;});}
            });
        });
    }};
});
