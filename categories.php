<?php
/**
 * Manage the product categories.
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

page::setup('categories', 'categories', 'local/storefront:managecatalogue');
page::header('categories', true);
echo \local_storefront\output\ui::toolbar([
    \local_storefront\output\ui::button(
        new moodle_url('/local/storefront/category_edit.php'),
        get_string('addtaxonomy', 'local_storefront'),
        't/add',
        'btn btn-primary'
    ),

    \local_storefront\output\ui::button(
        new moodle_url('/local/storefront/ordering.php', [ 'kind' => 'category' ]),
        get_string('ordering', 'local_storefront'),
        't/sort'
    )]);
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
        get_string('name', 'local_storefront'),
         get_string('visible', 'local_storefront'),
         get_string('sortorder', 'local_storefront'),
         get_string('actions'),
    ];
foreach (category_service::all() as $term) {
    $actions = [
        \local_storefront\output\ui::icon_action(
            new moodle_url('/local/storefront/category_edit.php', [ 'id' => $term->id ]),
            get_string('edit'),
            't/edit'
        ),
    ];
    $actions[] = \local_storefront\output\ui::icon_action(
        new moodle_url('/local/storefront/category_to_tag.php', [ 'id' => $term->id ]),
        get_string('converttotag', 'local_storefront'),
        'fa-tag'
    );
    $table->data[] = [
        format_string($term->name),
         \local_storefront\output\ui::visibility_badge(
             get_string($term->visible ? 'yes' : 'no'),
             (bool)$term->visible
         ),
         $term->sortorder,
         \local_storefront\output\ui::table_actions($actions),
    ];
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
