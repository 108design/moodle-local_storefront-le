<?php
/**
 * Manage course products.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
// Central page::setup() enforces the store audience or the requested administration capability.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{page, product_service, category_service, tag_service, course_delivery};

page::setup('products', 'manageproducts', 'local/storefront:managecatalogue');
page::header('manageproducts', true);
echo \local_storefront\output\ui::toolbar([
    \local_storefront\output\ui::button(
        new moodle_url('/local/storefront/product_edit.php'),
        get_string('addproduct', 'local_storefront'),
        't/add',
        'btn btn-primary'
    ),

    \local_storefront\output\ui::button(
        new moodle_url('/local/storefront/ordering.php'),
        get_string('ordering', 'local_storefront'),
        't/sort'
    )]);
$table = new html_table();
$table->attributes['class'] = 'generaltable mb-4';
$table->head = [
        get_string('productname', 'local_storefront'),
         get_string('visible', 'local_storefront'),
         get_string('sortorder', 'local_storefront'),
         get_string('actions'),
    ];
foreach ($DB->get_records('local_storefront_product', null, 'sortorder,name,id') as $product) {
    $actions = [];
    foreach (
        [
        'product_edit' => [ 'edit', 'id' ],
         'offers' => [ 'offers', 'productid' ],
         'course_mapping' => [ 'delivery', 'productid' ],
        ] as $route => [ $label, $param ]
    ) {
        $icons = ['product_edit' => 't/edit', 'offers' => 'fa-tags', 'course_mapping' => 'fa-box-open'];
        $actions[] = \local_storefront\output\ui::icon_action(
            new moodle_url('/local/storefront/' . $route . '.php', [ $param => $product->id ]),
            get_string($label, 'local_storefront'),
            $icons[$route]
        );
    }
    $table->data[] = [
        html_writer::link(
            new moodle_url('/local/storefront/product_edit.php', [ 'id' => $product->id ]),
            format_string($product->name)
        ),
         \local_storefront\output\ui::visibility_badge(
             get_string($product->visible ? 'yes' : 'no'),
             (bool)$product->visible
         ),
         $product->sortorder,
         \local_storefront\output\ui::table_actions($actions),
    ];
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
