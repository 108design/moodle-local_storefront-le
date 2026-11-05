<?php
/**
 * Native legal documents entry point for Storefront.
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
use local_storefront\output\ui;

require_login();
$context = context_system::instance();
require_capability('local/storefront:managelegal', $context);
admin_externalpage_setup('local_storefront_legal');
$PAGE->set_context($context);
$PAGE->set_url('/local/storefront/legal_documents.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('legaldocuments', 'local_storefront'));
$PAGE->set_heading(get_string('pluginname', 'local_storefront'));
admin_navigation::add_breadcrumb(
    get_string('legaldocuments', 'local_storefront'),
    new moodle_url('/local/storefront/legal_documents.php')
);
echo $OUTPUT->header();
echo admin_navigation::render('legal');
echo ui::page_header(get_string('legaldocuments', 'local_storefront'), [
    ui::icon_action(
        new moodle_url('/local/storefront/legal_edit.php'),
        get_string('addlegaldocument', 'local_storefront'),
        't/add'
    ),
]);
echo html_writer::div(
    get_string('legaldocumentsintro', 'local_storefront')
    . \local_storefront\local\admin_help::icon('legal'),
    'local-storefront-dev-intro small text-muted mb-4'
);
$documents = legal_service::documents();
if (!$documents) {
    echo $OUTPUT->notification(get_string('nolegaldocuments', 'local_storefront'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-hover w-100 local-storefront-action-table';
    $table->head = [
        get_string('legaldocumenttitle', 'local_storefront'),
         get_string('legalidentifier', 'local_storefront'),

        get_string('legallanguages', 'local_storefront'),
        get_string('checkoutuse', 'local_storefront'), get_string('status'), get_string('actions'),
    ];
    foreach ($documents as $document) {
        $checkout = empty($document->showincheckout)
            ? ui::status_badge(get_string('checkoutnotshown', 'local_storefront'), 'secondary')
            : ui::status_badge(
                get_string(!empty($document->requireconfirmation)
                ? 'checkoutconfirmationrequired' : 'checkoutlinked', 'local_storefront'),
                !empty($document->requireconfirmation) ? 'warning' : 'info'
            );
        $actions = [];
        if (!empty($document->visible)) {
            $actions[] = ui::icon_action(new moodle_url('/local/storefront/legal.php', [
                'document' => $document->identifier,
            ]), get_string('viewlegaldocument', 'local_storefront'), 'fa-globe');
        }
        $actions[] = ui::icon_action(
            new moodle_url('/local/storefront/legal_settings.php', ['id' => $document->id]),
            get_string('legalsettings', 'local_storefront'),
            'fa-cog'
        );
        $actions[] = ui::icon_action(
            new moodle_url('/local/storefront/legal_edit.php', ['id' => $document->id]),
            get_string('edit'),
            't/edit'
        );
        $actions[] = ui::icon_action(
            new moodle_url('/local/storefront/legal_delete.php', ['id' => $document->id]),
            get_string('delete'),
            't/delete',
            'local-storefront-action-danger'
        );
        $languages = [];
        foreach (legal_service::translations((int) $document->id) as $translation) {
            $label = legal_service::language_name($translation->lang);
            if ($translation->lang === $document->defaultlang) {
                $label .= ' (' . get_string('legaldefaultlabel', 'local_storefront') . ')';
            }
            if (empty($translation->published)) {
                $label .= ' (' . get_string('legaldraft', 'local_storefront') . ')';
            }
            $languages[] = html_writer::link(new moodle_url('/local/storefront/legal_edit.php', [
                'id' => $document->id, 'editlang' => $translation->lang,
            ]), s($label));
        }
        $table->data[] = [html_writer::link(
            new moodle_url('/local/storefront/legal_edit.php', ['id' => $document->id]),
            format_string($document->title)
        ), s($document->identifier), implode('<br>', $languages), $checkout,
            ui::visibility_badge(get_string(!empty($document->visible)
                ? 'legaldocumentvisible' : 'legaldocumenthidden', 'local_storefront'), !empty($document->visible)),
            ui::table_actions($actions)];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
