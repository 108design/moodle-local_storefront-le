<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Course cart presentation shared by the native header and the side panel.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\output;

use html_writer as h;
use moodle_url;
use local_storefront\local\{store_access, product_selection, catalogue, page};

defined('MOODLE_INTERNAL') || die();
/**
 * Course cart presentation shared by the native header and the side panel.
 */
final class cart_drawer {
    /**
     * Check whether the cart drawer can be shown in the current context.
     *
     * @return bool
     */
    public static function allowed(): bool {
        global $DB, $USER;
        if (
            defined('CLI_SCRIPT') && CLI_SCRIPT || !($DB instanceof \moodle_database)
            || during_initial_install()
            || !store_access::can_view()
        ) {
            return false;
        }
        $viewer = isloggedin() ? $USER : guest_user();
        return $viewer && has_capability('local/storefront:viewcatalogue', \context_system::instance(), $viewer);
    }
    /**
     * Return the cart label including its current selection count.
     *
     * @return string
     */
    public static function label(): string {
        return get_string('viewcartcount', 'local_storefront', count(product_selection::snapshot()));
    }
    /**
     * Render the component control in the native navigation bar.
     *
     * @return string
     */
    public static function navbar(): string {
        if (!self::allowed()) {
            return '';
        }
        $label = self::label();
        return h::div(
            h::link(
                new moodle_url('/local/storefront/cart.php'),
                h::tag('i', '', ['class' => 'fa fa-shopping-cart', 'aria-hidden' => 'true'])

                . h::span(
                    (string)count(product_selection::snapshot()),
                    'local-storefront-cart-badge',
                    [ 'aria-hidden' => 'true' ]
                ),
                [
                'class' => 'local-storefront-cart-link nav-link position-relative icon-no-margin',
                'aria-label' => $label,

                'title' => $label,
                'data-storefront-open' => '1',
                ]
            ),
            'local-storefront-cart-nav d-flex align-items-center',
            ['data-region' => 'local-storefront-cart']
        );
    }
    /**
     * Render the current cart items and their action controls.
     *
     * @return string
     */
    public static function body(): string {
        $html = '';
        $items = [];
        foreach (product_selection::snapshot() as $id) {
            try {
                $item = catalogue::offer($id);
                $items[] = $item;
                $content = h::tag(
                    'h3',
                    h::link(
                        new moodle_url(
                            '/local/storefront/product.php',
                            [ 'product' => $item->productidentifier ]
                        ),
                        format_string($item->productname)
                    )
                );
                $content .= h::tag('p', format_string($item->displayname ?: $item->productname), ['class' => 'small']);
                $content .= h::tag(
                    'p',
                    page::amount((float)$item->amount, $item->currency),
                    [ 'class' => 'local-storefront-selection-cost' ]
                );
            } catch (\moodle_exception $e) {
                $content = h::tag('p', get_string('selectionunavailable', 'local_storefront'));
            }
            $content .= page::post(
                'cart_action',
                get_string('remove'),
                [ 'action' => 'remove', 'offerid' => $id ],
                'btn btn-outline-secondary btn-sm',
                't/delete'
            );
            $html .= h::div($content, 'local-storefront-selection-row');
        }
        if (!$html) {
            return h::tag('p', get_string('cartempty', 'local_storefront'), ['class' => 'my-4']);
        }
        $footer = catalogue_view::totals($items);
        $footer .= h::link(
            new moodle_url('/local/storefront/cart.php'),
            get_string('viewcart', 'local_storefront'),
            [ 'class' => 'btn btn-outline-secondary mt-3 me-2 mr-2' ]
        );
        if ($items && count($items) === count(product_selection::snapshot())) {
            $footer .= h::link(
                new moodle_url(
                    isloggedin() && !isguestuser() ? '/local/storefront/checkout.php' : '/local/storefront/cart.php'
                ),
                get_string(
                    isloggedin() && !isguestuser() ? 'checkout' : 'logincheckout',
                    'local_storefront'
                ),
                [ 'class' => 'btn btn-primary mt-3' ]
            );
        }
        return $html . h::div($footer, 'local-storefront-selection-footer');
    }
    /**
     * Return the component output appended to the native page footer.
     *
     * @param \core\hook\output\before_standard_footer_html_generation $hook Native Moodle output hook.
     * @return void
     */
    public static function footer(\core\hook\output\before_standard_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::allowed()) {
            return;
        }
        $heading = h::tag('h2', get_string('cart', 'local_storefront'), ['id' => 'local-storefront-cart-title']);
        $close = h::tag(
            'button',
            h::tag('i', '', ['class' => 'fa fa-times', 'aria-hidden' => 'true']),
            ['type' => 'button', 'data-storefront-close' => '1', 'aria-label' => get_string('closebuttontitle')]
        );
        $html = h::tag(
            'dialog',
            h::div($heading . $close, 'local-storefront-selection-heading')
            . h::div('', '', ['role' => 'status', 'data-storefront-cart-status' => '1'])
            . h::div(self::body(), '', ['data-storefront-cart-body' => '1']),
            [
            'id' => 'local-storefront-cart-drawer',
            'class' => 'local-storefront-selection-dialog',
            'aria-labelledby' => 'local-storefront-cart-title',
            ]
        );
        $hook->add_html($html);
        $PAGE->requires->js_call_amd('local_storefront/cart_drawer', 'init', [[
            'endpoint' => (new moodle_url('/local/storefront/cart_action.php'))->out(false),
            'error' => get_string('cartupdatefailed', 'local_storefront')]]);
    }
}
