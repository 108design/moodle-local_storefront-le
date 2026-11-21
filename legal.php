<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Display the selected published legal document.
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
// Central store_access enforces the configured public or authenticated audience.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_storefront\local\legal_service;
use local_storefront\local\public_navigation;

$identifier = optional_param('document', '', PARAM_ALPHANUMEXT);
$revisionid = optional_param('revision', 0, PARAM_INT);
$documentid = optional_param('documentid', 0, PARAM_INT);
$context = context_system::instance();
\local_storefront\local\store_access::require_access();
\local_storefront\local\store_access::require_catalogue_capability();
$document = legal_service::public_document($identifier, $documentid, $revisionid);
if (!$document) {
    throw new moodle_exception('invalidparameter');
}
$preferred = legal_service::preferred_language();
$revision = legal_service::public_revision($document, $revisionid);
$identifier = (string) $document->identifier;
$url = new moodle_url('/local/storefront/legal.php', ['document' => $identifier]);
if ($revisionid) {
    $url->param('revision', $revisionid);
    $url->param('documentid', $document->id);
}
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($revision->title));
$PAGE->requires->css(new moodle_url('/local/storefront/styles/legal_editor.css'));
$PAGE->set_heading(get_string('storefront', 'local_storefront'));
public_navigation::add('legal');
public_navigation::breadcrumb([[format_string($revision->title), null]]);
echo $OUTPUT->header();
echo \local_storefront\local\customer_help::render();
echo html_writer::start_div('local-storefront-content-reading');
echo $OUTPUT->heading(format_string($revision->title));
echo \local_storefront\output\legal_language_menu::reader($document, (string) $revision->lang);
if ($revisionid) {
    echo html_writer::div(
        get_string('legalrevisionnotice', 'local_storefront', $revision->revisionnumber),
        'small text-muted mb-3'
    );
} else if (
    (string) $revision->lang !== $preferred
        && (string) $revision->lang !== explode('_', $preferred)[0]
) {
    echo html_writer::div(get_string(
        'legallanguagefallback',
        'local_storefront',
        s(legal_service::language_name((string) $revision->lang))
    ), 'small text-muted mb-3');
}
echo html_writer::div(legal_service::render_revision($revision), 'local-storefront-legal-content', [
    'lang' => str_replace('_', '-', (string) ($revision->lang ?: current_language())),
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
