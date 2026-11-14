<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Native legal delete entry point for Storefront.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
//
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
//
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_storefront\local\admin_navigation;
use local_storefront\local\legal_service;

$id = required_param('id', PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/storefront:managelegal', $context);
admin_externalpage_setup('local_storefront_legal');
$document = legal_service::get($id);
$url = new moodle_url('/local/storefront/legal_delete.php', ['id' => $id]);
$returnurl = new moodle_url('/local/storefront/legal_documents.php');
if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_sesskey();
    legal_service::delete($id);
    redirect(
        $returnurl,
        get_string('legaldocumentdeleted', 'local_storefront'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('deletelegaldocument', 'local_storefront'));
$PAGE->set_heading(get_string('pluginname', 'local_storefront'));
admin_navigation::add_breadcrumb(
    get_string('legaldocuments', 'local_storefront'),
    $returnurl,
    get_string('deletelegaldocument', 'local_storefront')
);
echo $OUTPUT->header();
echo admin_navigation::render('legal');
echo $OUTPUT->confirm(
    get_string('deletelegaldocumentconfirm', 'local_storefront', format_string($document->title)),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $returnurl
);
echo $OUTPUT->footer();
