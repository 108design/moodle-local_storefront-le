<?php
/**
 * Validate the signed-in buyer's selection and legal confirmations.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{page, product_selection, legal_service, order_service, moodle_payment_config};
require_login();
page::setup('checkout', 'checkout', 'local/storefront:viewcatalogue');
\local_storefront\local\store_access::require_access();
if (isguestuser() || empty($USER->confirmed) || !empty($USER->suspended)) {
    throw new moodle_exception('moodlepaymentinvalid', 'local_storefront');
}
try {
    $items = product_selection::items();
} catch (moodle_exception $error) {
    redirect(new moodle_url('/local/storefront/cart.php'));
}
if (!$items) {
    redirect(new moodle_url('/local/storefront/cart.php'));
}
// A separate intent is bound to the exact reviewed selection and prevents double submission.
$fingerprint = product_selection::fingerprint($items);
if (data_submitted()) {
    require_sesskey();
    $intent = required_param('intent', PARAM_ALPHANUM);
    $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock(
        'checkout:' . $USER->id . ':' . $intent,
        10
    );
    if (!$lock) {
        throw new moodle_exception('locktimeout');
    }
    try {
        if (!empty($SESSION->local_storefront_checkoutorders[$intent])) {
            redirect(
                new moodle_url(
                    '/local/storefront/moodle_payment.php',
                    [ 'order' => $SESSION->local_storefront_checkoutorders[$intent] ]
                )
            );
        }
        if (
            empty($SESSION->local_storefront_checkoutintent)
            || !hash_equals($SESSION->local_storefront_checkoutintent, $intent)
                || !hash_equals($SESSION->local_storefront_checkoutfingerprint ?? '', $fingerprint)
        ) {
            throw new moodle_exception('selectionchanged', 'local_storefront');
        }
        $result = order_service::create(
            $items,
            $USER->email,
            (int)$USER->id,
            optional_param_array('accepted', [], PARAM_RAW),
            'moodle',
            moodle_payment_config::account_id(),
            optional_param_array('presented', [], PARAM_RAW),
            $fingerprint
        );
        $SESSION->local_storefront_checkoutorders[$intent] = $result['order']->orderkey;
        unset($SESSION->local_storefront_checkoutintent);
    } finally {
        $lock->release();
    }
    product_selection::clear();
    redirect(new moodle_url('/local/storefront/moodle_payment.php', ['order' => $result['order']->orderkey]));
}
$SESSION->local_storefront_checkoutintent = bin2hex(random_bytes(24));
$SESSION->local_storefront_checkoutfingerprint = $fingerprint;
page::header('checkout');
echo html_writer::tag('p', get_string('accountpurchase', 'local_storefront', fullname($USER)));
echo html_writer::tag('p', get_string('durationnote', 'local_storefront'));
echo html_writer::start_div('local-storefront-checkout-layout');
echo html_writer::start_div();
echo $OUTPUT->heading(get_string('orderreview', 'local_storefront'), 3);
echo \local_storefront\output\catalogue_view::summary($items);
echo html_writer::div(\local_storefront\output\catalogue_view::totals($items), 'local-storefront-cart-summary');
foreach ($items as $item) {
    echo html_writer::tag('h4', format_string($item->productname), ['class' => 'h6']);
    echo html_writer::start_tag('ul', ['class' => 'small']);
    foreach (\local_storefront\local\course_delivery::snapshots((int)$item->productid) as $course) {
        echo html_writer::tag(
            'li',
            format_string($course['coursefullname']) . ' · '
            . s(
                $course['enrolperiod'] ? format_time($course['enrolperiod']) : get_string('unlimitedaccess', 'local_storefront')
            )
        );
    }
    echo html_writer::end_tag('ul');
}
echo html_writer::end_div();
echo html_writer::start_div('local-storefront-checkout-legal');
echo $OUTPUT->heading(get_string('checkoutlegal', 'local_storefront'), 3);
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::input_hidden_params(
    new moodle_url(
        '/local/storefront/checkout.php',
        [ 'sesskey' => sesskey(), 'intent' => $SESSION->local_storefront_checkoutintent ]
    )
);
foreach (legal_service::checkout_documents() as $document) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'presented[]', 'value' => $document->token]);
    $link = html_writer::link(
        $document->url,
        format_string($document->title),
        [ 'target' => '_blank', 'rel' => 'noopener' ]
    );
    if ($document->required) {
        $id = 'legal-' . $document->sourceid;
        echo html_writer::div(
            html_writer::empty_tag(
                'input',
                [
                'type' => 'checkbox',
                'name' => 'accepted[]',
                'value' => $document->token,
                'id' => $id,
                'required' => 'required',
                ]
            )

            . ' '
            . html_writer::tag(
                'label',
                get_string('acceptdocument', 'local_storefront', $link),
                [ 'for' => $id ]
            ),
            'mb-3'
        );
    } else {
        echo html_writer::tag('p', $link);
    }
}
echo html_writer::tag(
    'button',
    get_string('createpayment', 'local_storefront'),
    [ 'type' => 'submit', 'class' => 'btn btn-primary' ]
);
echo html_writer::end_tag('form');
echo html_writer::end_div() . html_writer::end_div();
echo $OUTPUT->footer();
