<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Edit a product tag.
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

$id = optional_param('id', 0, PARAM_INT);
page::setup('tag_edit', 'tags', 'local/storefront:managecatalogue', ['id' => $id]);
$return = new moodle_url('/local/storefront/tags.php');
$data = $id ? tag_service::get($id) : (object)[];
$form = new \local_storefront\form\editor($PAGE->url, ['mode' => 'tag']);
$form->set_data($data);
if ($form->is_cancelled()) {
    redirect($return);
}
if ($submitted = $form->get_data()) {
    tag_service::save($submitted);
    redirect($return);
}
page::header('tags', true);
$form->display();
echo $OUTPUT->footer();
