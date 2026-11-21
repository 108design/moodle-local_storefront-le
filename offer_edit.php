<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Edit a one-time course offer.
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
$productid = required_param('productid', PARAM_INT);
page::setup('offer_edit', 'editoffer', 'local/storefront:managecatalogue', ['id' => $id, 'productid' => $productid]);
$return = new moodle_url('/local/storefront/offers.php', ['productid' => $productid]);
$PAGE->navbar->add(get_string('offers', 'local_storefront'), $return);
$data = $id ? $DB->get_record(
    'local_storefront_offer',
    [ 'id' => $id, 'productid' => $productid ],
    '*',
    MUST_EXIST
) : (object)[ 'productid' => $productid ];
$form = new \local_storefront\form\editor($PAGE->url, ['mode' => 'offer']);
$form->set_data($data);
if ($form->is_cancelled()) {
    redirect($return);
}
if ($submitted = $form->get_data()) {
    product_service::save_offer($submitted);
    redirect($return);
}
page::header('editoffer', true);
echo html_writer::tag('p', get_string('durationnote', 'local_storefront'));
$form->display();
echo $OUTPUT->footer();
