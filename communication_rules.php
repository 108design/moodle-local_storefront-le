<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Manage transactional message rules and recipients.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Central page::setup() enforces the store audience or the requested administration capability.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\page;
page::setup('communication_rules', 'communication', 'local/storefront:manageorders');
$language = optional_param('language', 'en', PARAM_ALPHA);
if (!in_array($language, ['en', 'de'], true)) {
    throw new moodle_exception('invalidparameter');
}
$retry = optional_param('retry', 0, PARAM_INT);
if ($retry) {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidparameter');
    }
    $row = $DB->get_record('local_storefront_comqueue', ['id' => $retry, 'status' => 'failed'], '*', MUST_EXIST);
    if (empty($row->payload)) {
        throw new moodle_exception('invalidparameter');
    }
    $DB->update_record(
        'local_storefront_comqueue',
        (object)[
        'id' => $retry,
         'status' => 'pending',
         'attempts' => 0,
         'availableat' => time(),
         'timemodified' => time(),
        ]
    );
    redirect($PAGE->url);
}
$data = $DB->get_record(
    'local_storefront_comrule',
    [ 'eventtype' => 'order_ready', 'language' => $language ]
) ?: (object)[

    'language' => $language,
         'subject' => get_string_manager()->get_string(
             'orderreadysubject',
             'local_storefront',
             null,
             $language
         ),

    'body' => get_string_manager()->get_string('orderreadybody', 'local_storefront', null, $language),
         'delayminutes' => 0,
         'adminemails' => '',
    ];
$form = new \local_storefront\form\message($PAGE->url);
$form->set_data($data);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/storefront/orders.php'));
}
if ($submitted = $form->get_data()) {
    if (!in_array($submitted->language, ['en', 'de'], true)) {
        throw new moodle_exception('invalidparameter');
    }
    $existing = $DB->get_record(
        'local_storefront_comrule',
        [ 'eventtype' => 'order_ready', 'language' => $submitted->language ]
    );
    $record = (object)[
        'eventtype' => 'order_ready',
         'language' => $submitted->language,
         'subject' => $submitted->subject,

        'body' => \local_storefront\local\communication_service::clean_template($submitted->body),
         'adminemails' => $submitted->adminemails,

        'delayminutes' => (int)$submitted->delayminutes, 'modifiedby' => $USER->id, 'timemodified' => time(),
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('local_storefront_comrule', $record);
    } else {
        $record->timecreated = time();
        $DB->insert_record('local_storefront_comrule', $record);
    }
    redirect(new moodle_url('/local/storefront/communication_rules.php', ['language' => $submitted->language]));
}
page::header('communication', true);
echo html_writer::tag('p', get_string('communicationintro', 'local_storefront'));
echo html_writer::link(
    new moodle_url($PAGE->url, [ 'language' => $language === 'en' ? 'de' : 'en' ]),
    $language === 'en' ? 'Deutsch' : 'English',
    [ 'class' => 'btn btn-link mb-3' ]
);
$form->display();
$table = new html_table();
$table->head = [
        get_string('ordernumber', 'local_storefront'),
         get_string('mailstatus', 'local_storefront'),
         get_string('attempts', 'local_storefront'),
         get_string('actions'),
    ];
foreach ($DB->get_records('local_storefront_comqueue', null, 'id DESC', '*', 0, 50) as $row) {
    $table->data[] = [
        (int)$row->orderid,
         \local_storefront\output\ui::status_badge(
             get_string('state_' . $row->status, 'local_storefront'),
             $row->status === 'failed' ? 'danger' : (
             $row->status === 'sent' ? 'success' : 'secondary'
             )
         ),
         (int)$row->attempts,

        $row->status === 'failed' ? page::post(
            'communication_rules',
            get_string('retrymail', 'local_storefront'),
            [ 'retry' => $row->id ],
            'btn btn-secondary',
            'i/reload',
            true
        ) : '',
    ];
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
