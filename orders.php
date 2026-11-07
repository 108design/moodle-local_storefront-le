<?php
/**
 * Inspect verified orders and retry incomplete course delivery.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Central page::setup() enforces the store audience or the requested administration capability.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{admin_help, course_access_admin, page, order_service, order_view};
page::setup('orders', 'ordersaccess', 'local/storefront:manageorders');
$retry = optional_param('retry', 0, PARAM_INT);
if ($retry) {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidparameter');
    }
    order_service::retry($retry);
    redirect($PAGE->url);
}
$status = optional_param('status', '', PARAM_ALPHA);
$conditions = $status !== '' ? ['status' => $status] : [];
$pagenum = max(0, optional_param('page', 0, PARAM_INT));
$returnurl = new moodle_url($PAGE->url, ['status' => $status, 'page' => $pagenum]);
$PAGE->set_url($returnurl);
$purchaseid = optional_param('coursepurchase', 0, PARAM_INT);
if ($purchaseid) {
    course_access_admin::apply($purchaseid, $returnurl);
}
page::header('storefront', true);
echo \local_storefront\output\ui::page_header(get_string('ordersaccess', 'local_storefront') . ' ' . admin_help::icon('operations'));
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'local-storefront-filterbar mb-4']);
echo html_writer::start_div('local-storefront-filter-field');
echo html_writer::tag(
    'label',
    get_string('orderstatus', 'local_storefront'),
    [ 'for' => 'orderstatus', 'class' => 'mb-1' ]
);
echo html_writer::select(
    ['' => get_string('all'), 'pending' => get_string('state_pending', 'local_storefront'),
    'paid' => get_string('state_paid', 'local_storefront'),
         'partial' => get_string('state_partial', 'local_storefront'),

    'completed' => get_string('state_completed', 'local_storefront')],
    'status',
    $status,
    null,
    [ 'id' => 'orderstatus', 'class' => 'custom-select form-select' ]
);
echo html_writer::end_div();
echo html_writer::tag(
    'button',
    \local_storefront\output\ui::icon('i/filter') . get_string('filter'),
    [ 'type' => 'submit', 'class' => 'btn btn-secondary' ]
);
echo html_writer::end_tag('form');
$orders = $DB->get_records('local_storefront_order', $conditions, 'timecreated DESC,id DESC', '*', $pagenum * 25, 25);
echo $orders ? order_view::render($orders, true) : $OUTPUT->notification(get_string('noorders', 'local_storefront'), 'info');
echo $OUTPUT->paging_bar(
    $DB->count_records('local_storefront_order', $conditions),
    $pagenum,
    25,
    new moodle_url($PAGE->url, [ 'status' => $status ])
);
echo $OUTPUT->footer();
