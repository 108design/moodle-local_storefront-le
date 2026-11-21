<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Display purchases belonging to the signed-in buyer.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Central page::setup() enforces the store audience or the requested administration capability.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{page, order_service, order_view};
page::setup('my', 'mypurchases', 'local/storefront:viewownorders');
\local_storefront\local\store_access::require_access();
$retry = optional_param('retry', 0, PARAM_INT);
if ($retry) {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidparameter');
    }
    $DB->get_record('local_storefront_order', ['id' => $retry, 'userid' => $USER->id], 'id', MUST_EXIST);
    order_service::retry($retry);
    redirect($PAGE->url);
}
page::header('mypurchases');
$orders = $DB->get_records('local_storefront_order', ['userid' => $USER->id], 'timecreated DESC,id DESC');
echo order_view::render($orders);
if (!$orders) {
    echo $OUTPUT->notification(get_string('noorders', 'local_storefront'), 'info');
}
echo $OUTPUT->footer();
