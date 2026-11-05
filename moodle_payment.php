<?php
/**
 * Native moodle payment entry point for Storefront.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
require_once(__DIR__ . '/../../config.php');

use core\output\single_button;
use local_storefront\local\order_service;
use local_storefront\local\public_navigation;
use local_storefront\output\ui;
use local_storefront\payment\service_provider;

$orderkey = required_param('order', PARAM_ALPHANUMEXT);
require_login();
$context = context_system::instance();
\local_storefront\local\store_access::require_access();
require_capability('local/storefront:viewcatalogue', $context);
$order = $DB->get_record('local_storefront_order', [
    'orderkey' => $orderkey,
    'channel' => 'moodle',
], '*', MUST_EXIST);
if ((int) $order->userid !== (int) $USER->id) {
    throw new moodle_exception('moodlepaymentinvalid', 'local_storefront');
}
$url = new moodle_url('/local/storefront/moodle_payment.php', ['order' => $orderkey]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('moodlepaymenttitle', 'local_storefront'));
$PAGE->set_heading(get_string('storefront', 'local_storefront'));
public_navigation::add('cart');
public_navigation::breadcrumb([
    [get_string('cart', 'local_storefront'), new moodle_url('/local/storefront/cart.php')],
    [get_string('checkout', 'local_storefront'), new moodle_url('/local/storefront/checkout.php')],
    [get_string('moodlepaymenttitle', 'local_storefront'), null],
]);

echo $OUTPUT->header();
echo public_navigation::render_tabs('cart');
echo html_writer::start_div('local-storefront-content-reading');
echo ui::page_header(get_string('moodlepaymenttitle', 'local_storefront'));
if ($order->status === 'completed') {
    echo $OUTPUT->notification(get_string('moodlepaymentcomplete', 'local_storefront'), 'success');
    foreach (order_service::course_links((int) $order->id) as $course) {
        echo html_writer::div(html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course['id']]),
            format_string($course['name']),
            ['class' => 'btn btn-primary']
        ), 'mb-2');
    }
} else if ($order->status === 'pending') {
    $gateways = \core_payment\helper::get_available_gateways(
        'local_storefront',
        service_provider::AREA_ORDER,
        (int) $order->id
    );
    if (!$gateways) {
        echo $OUTPUT->notification(get_string('moodlepaymentnogateway', 'local_storefront'), 'error');
    } else {
        $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
        $button = new single_button(
            $url,
            get_string('paywithmoodlegateway', 'local_storefront'),
            'post',
            single_button::BUTTON_PRIMARY,
            [
                'data-action' => 'core_payment/triggerPayment',
                'data-component' => 'local_storefront',
                'data-paymentarea' => service_provider::AREA_ORDER,
                'data-itemid' => $order->id,
                'data-cost' => \core_payment\helper::get_cost_as_string(
                    (float) $order->total,
                    (string) $order->currency
                ),
                'data-successurl' => service_provider::get_success_url(
                    service_provider::AREA_ORDER,
                    (int) $order->id
                )->out(false),
                'data-description' => get_string('moodlepaymentdescription', 'local_storefront', $order->orderkey),
            ]
        );
        echo html_writer::tag('p', get_string('moodlepaymentchoosegateway', 'local_storefront'));
        echo $OUTPUT->render($button);
    }
} else {
    echo html_writer::link(
        new moodle_url('/local/storefront/my.php'),
        get_string('deliverypending', 'local_storefront')
    );
    echo $OUTPUT->notification(get_string('moodlepaymentinvalid', 'local_storefront'), 'error');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
