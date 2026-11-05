<?php
/**
 * Native legal settings entry point for Storefront.
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

use local_storefront\form\legal_document_settings_form;
use local_storefront\local\admin_navigation;
use local_storefront\local\legal_service;
use local_storefront\output\ui;

$id = required_param('id', PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/storefront:managelegal', $context);
admin_externalpage_setup('local_storefront_legal');
$document = legal_service::get($id);
$url = new moodle_url('/local/storefront/legal_settings.php', ['id' => $id]);
$returnurl = new moodle_url('/local/storefront/legal_documents.php');
$title = get_string('legalsettings', 'local_storefront');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'local_storefront'));
admin_navigation::add_breadcrumb(get_string('legaldocuments', 'local_storefront'), $returnurl, $title);
$languages = [];
foreach (legal_service::translations($id) as $translation) {
    $languages[$translation->lang] = legal_service::language_name($translation->lang)
        . (empty($translation->published) ? ' (' . get_string('legaldraft', 'local_storefront') . ')' : '');
}
$form = new legal_document_settings_form($url, ['languages' => $languages]);
$document->expectedversion = (int) $document->editversion;
$form->set_data($document);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    legal_service::save_settings($data);
    redirect(
        $returnurl,
        get_string('legalsettingssaved', 'local_storefront'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$PAGE->requires->css(new moodle_url('/local/storefront/styles/legal_editor.css'));
echo $OUTPUT->header();
echo admin_navigation::render('legal');
echo html_writer::start_div('local-storefront-content-narrow');
echo ui::page_header($title);
echo html_writer::div(get_string('legalsettingsintro', 'local_storefront')
    . \local_storefront\local\admin_help::icon('legal'), 'small text-muted mb-4');
echo html_writer::tag('h3', format_string($document->title));
echo \local_storefront\output\legal_language_menu::admin($document, '');
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
