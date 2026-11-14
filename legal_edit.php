<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Native legal edit entry point for Storefront.
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
require_once($CFG->libdir . '/formslib.php');

use local_storefront\form\legal_document_form;
use local_storefront\local\admin_navigation;
use local_storefront\local\legal_service;
use local_storefront\output\ui;

$id = optional_param('id', 0, PARAM_INT);
require_login();
$context = context_system::instance();
require_capability('local/storefront:managelegal', $context);
admin_externalpage_setup('local_storefront_legal');
$title = get_string($id ? 'editlegaldocument' : 'addlegaldocument', 'local_storefront');
$url = new moodle_url('/local/storefront/legal_edit.php', $id ? ['id' => $id] : []);
$returnurl = new moodle_url('/local/storefront/legal_documents.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->set_heading(get_string('pluginname', 'local_storefront'));
admin_navigation::add_breadcrumb(get_string('legaldocuments', 'local_storefront'), $returnurl, $title);
$language = optional_param('editlang', null, PARAM_SAFEDIR);
$editdata = legal_service::edit_data($id, $language);
$url->param('editlang', $editdata->originlang);
$PAGE->set_url($url);
$form = new legal_document_form($url, [
    'language' => $editdata->originlang, 'format' => $editdata->contentformat, 'documentid' => $id,
]);
$form->set_data($editdata);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    $data->originlang = $editdata->originlang;
    legal_service::save($data, (int) $USER->id);
    redirect(
        $returnurl,
        get_string('legaldocumentsaved', 'local_storefront'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$PAGE->requires->css(new moodle_url('/local/storefront/styles/legal_editor.css'));
$PAGE->requires->js_call_amd('local_storefront/legal_editor', 'init', [[
    'url' => (new moodle_url('/local/storefront/legal_preview.php'))->out(false),
    'edit' => get_string('legaleditmode', 'local_storefront'),
    'preview' => get_string('legalpreviewmode', 'local_storefront'),
    'loading' => get_string('loading', 'moodle'),
    'error' => get_string('legalpreviewfailed', 'local_storefront'),
]]);
echo $OUTPUT->header();
echo admin_navigation::render('legal');
echo html_writer::start_div('local-storefront-content-narrow');
echo ui::page_header($title);
if ($id) {
    echo html_writer::div(ui::badge(get_string('legaldocument', 'local_storefront'), 'primary', ui::ICON_LEGAL)
        . ui::actionbar([
            ui::icon_action(
                new moodle_url('/local/storefront/legal_settings.php', ['id' => $id]),
                get_string('legalsettings', 'local_storefront'),
                'fa-cog'
            ),
            ui::icon_action($returnurl, get_string('legaldocuments', 'local_storefront'), ui::ICON_LEGAL),
            ui::icon_action(
                new moodle_url('/local/storefront/legal.php', ['document' => $editdata->identifier]),
                get_string('viewlegaldocument', 'local_storefront'),
                'fa-globe'
            ),
        ]), 'local-storefront-entity-toolbar d-flex align-items-center justify-content-between '
            . 'local-storefront-gap-3 mb-4');
}
if ($id) {
    echo \local_storefront\output\legal_language_menu::admin($editdata, $editdata->originlang);
}
echo html_writer::div(get_string($id ? 'legaleditingintro' : 'legalcreatingintro', 'local_storefront')
    . \local_storefront\local\admin_help::icon('legal'), 'small text-muted mb-4');
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
