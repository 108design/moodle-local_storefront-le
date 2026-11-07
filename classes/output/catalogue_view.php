<?php
/**
 * Course-only presentation. Purchase authority remains in the existing services.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\output;

use html_writer;
use moodle_url;
use local_storefront\local\page;

defined('MOODLE_INTERNAL') || die();
/**
 * Course-only presentation. Purchase authority remains in the existing services.
 */
final class catalogue_view {
    /**
     * Render a product card for the public catalogue.
     *
     * @param \stdClass $product Course product record.
     * @return string
     */
    public static function card(\stdClass $product): string {
        $url = new moodle_url('/local/storefront/product.php', ['product' => $product->identifier]);
        $image = \local_storefront\local\product_image::url((int)$product->id);
        $cover = $image ? html_writer::link($url, html_writer::empty_tag('img', [
            'src' => $image->out(false), 'alt' => '', 'loading' => 'lazy',
            'class' => 'local-storefront-product-card-image',
        ]), ['aria-label' => html_to_text(format_string($product->name), 0, false)]) : '';
        if (!$image) {
            $cover = html_writer::span(
                ui::icon('i/course') . get_string('producttypecourse', 'local_storefront'),
                'local-storefront-card-kind'
            );
        }
        $cover .= html_writer::div(html_writer::link($url, ui::icon('fa-arrow-right'), [
            'class' => 'btn btn-primary local-storefront-product-cta is-compact',
            'aria-label' => get_string('viewproduct', 'local_storefront') . ': '
                . html_to_text(format_string($product->name), 0, false),
            'title' => get_string('viewproduct', 'local_storefront'),
        ]), 'local-storefront-card-action');
        $cover = html_writer::div($cover, 'local-storefront-card-cover' . ($image ? '' : ' without-image'));
        $content = html_writer::tag(
            'h3',
            html_writer::link($url, format_string($product->name)),
            ['class' => 'card-title local-storefront-card-title']
        );
        $badges = '';
        foreach (array_merge($product->categories, $product->tags) as $term) {
            $colour = \local_storefront\local\color_value::normalise($term->badgecolor);
            $badges .= html_writer::span(format_string($term->name), 'badge', [
                'style' => 'background:' . $colour . ';color:'
                    . \local_storefront\local\color_value::contrasting_text_colour($colour),
            ]);
        }
        if ($badges !== '') {
            $content .= html_writer::div($badges, 'local-storefront-taxonomy mb-3');
        }
        $content .= \local_storefront\local\product_content::format($product, 'shortdescription');
        $price = '';
        $currencies = array_unique(array_column($product->offers, 'currency'));
        if (count($currencies) === 1) {
            $amount = min(array_map(static fn($offer)=>(float)$offer->amount, $product->offers));
            $price = page::amount($amount, reset($currencies));
            if (count($product->offers) > 1) {
                $price = get_string('pricefrom', 'local_storefront', $price);
            }
        }
        if (!$product->offers) {
            $price = get_string('coursefulfilmentunavailable', 'local_storefront');
        }
        $content .= html_writer::div(
            html_writer::span($price, 'local-storefront-card-price font-weight-bold fw-semibold')
            . html_writer::span(get_string('oneoffpurchase', 'local_storefront'), 'small text-muted'),
            'local-storefront-product-card-footer'
        );
        return html_writer::tag(
            'article',
            $cover . html_writer::div($content, 'card-body'),
            ['class' => 'card h-100 local-storefront-product-card']
        );
    }
    /**
     * Render the selected offers and their available cart actions.
     *
     * @param array $items Validated items to process.
     * @param bool $remove Remove.
     * @return string
     */
    public static function summary(array $items, bool $remove = false): string {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable local-storefront-cart-table';
        $table->head = [
        get_string('productname', 'local_storefront'),
         get_string('offername', 'local_storefront'),
         get_string('amount', 'local_storefront'),
        ];
        if ($remove) {
            $table->head[] = get_string('actions');
        }
        foreach ($items as $item) {
            $row = [
            html_writer::link(
                new moodle_url(
                    '/local/storefront/product.php',
                    [ 'product' => $item->productidentifier ]
                ),
                format_string($item->productname)
            ),

                format_string($item->displayname ?: $item->productname),
            page::amount((float)$item->amount, $item->currency),
            ];
            if ($remove) {
                $row[] = page::post(
                    'cart_action',
                    get_string('remove'),
                    [ 'action' => 'remove', 'offerid' => $item->id ],
                    'btn btn-outline-secondary btn-sm',
                    't/delete',
                    true
                );
            }
            $table->data[] = $row;
        }
        return html_writer::div(html_writer::table($table), 'table-responsive');
    }
    /**
     * Render the totals for the current validated selection.
     *
     * @param array $items Validated items to process.
     * @return string
     */
    public static function totals(array $items): string {
        $totals = [];
        foreach ($items as $item) {
            $totals[$item->currency] = ($totals[$item->currency] ?? 0) + (float)$item->amount;
        }
        $out = '';
        foreach ($totals as $currency => $amount) {
            $out .= html_writer::div(
                s(get_string('ordertotal', 'local_storefront')) . ': '
                . page::amount($amount, $currency),
                'local-storefront-cart-total'
            );
        }
        return $out;
    }
}
