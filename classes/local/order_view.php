<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Course order view preserves separate payment, position delivery and actual access facts.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Course order view preserves separate payment, position delivery and actual access facts.
 */
final class order_view {
    /**
     * Render the order details available to the current viewer.
     *
     * @param array $orders Orders.
     * @param bool $admin Whether the administration view is requested.
     * @return string
     */
    public static function render(array $orders, bool $admin = false): string {
        global $DB, $USER;
        $canmanageaccess = $admin && course_access_admin::available();
        $html = '';
        foreach ($orders as $order) {
            $buyer = $DB->get_record(
                'user',
                [ 'id' => $order->userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1 ]
            );
            $html .= \html_writer::start_div('card card-body mb-4');
            $html .= \html_writer::tag(
                'h3',
                get_string('orderheading', 'local_storefront', (int)$order->id),
                [ 'class' => 'h5' ]
            );
            $html .= \html_writer::div(s($order->orderkey), 'small text-muted text-break mb-2');
            $html .= \html_writer::tag(
                'p',
                get_string('orderstatus', 'local_storefront') . ': '
                . self::state_badge($order->status)

                . ' · '
                . page::amount((float)$order->total, $order->currency)
                . ' · '
                . userdate($order->timecreated)
            );
            if ($admin) {
                $html .= \html_writer::tag(
                    'p',
                    get_string('account', 'local_storefront') . ': ' . s($order->email ?? '')
                    .
                    ' (#' . (int)$order->userid . ')'
                );
            }
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable mb-4';
            $table->head = [
            get_string('productname', 'local_storefront'),
            get_string('paymentstatus', 'local_storefront'),

                get_string('deliverystatus', 'local_storefront'), get_string('courseaccess', 'local_storefront'),
            ];
            foreach ($DB->get_records('local_storefront_orderline', ['orderid' => $order->id], 'id') as $line) {
                $state = $DB->get_record('local_storefront_line_state', ['orderlineid' => $line->id], '*', MUST_EXIST);
                $access = [];
                if (course_delivery::available()) {
                    foreach (
                        $DB->get_records(
                            'enrol_storefront_purchase',
                            [ 'orderlineid' => $line->id, 'userid' => $order->userid ]
                        ) as $purchase
                    ) {
                        $course = $DB->get_record('course', ['id' => $purchase->courseid]);
                        $end = $purchase->timeend ? userdate($purchase->timeend)
                            : get_string('unlimitedaccess', 'local_storefront');
                        $purchasestatus = empty($purchase->manualsuspended) ? $purchase->status : 'suspended';
                        if ($purchasestatus === 'active' && $purchase->timeend > 0 && $purchase->timeend <= time()) {
                            $purchasestatus = 'expired';
                        }
                        $canaccess = $course && $course->visible && $buyer
                            && can_access_course($course, (int)$buyer->id, '', true);
                        $label = format_string($course->fullname ?? $line->productname);
                        $details = self::state_badge($purchasestatus) . ' '
                        . \html_writer::span(s($end), 'small text-muted');
                        if ($purchasestatus === 'active' && !$canaccess) {
                            $details .= \html_writer::div(
                                get_string('courseaccessunavailable', 'local_storefront'),
                                'small text-muted'
                            );
                        }
                        if ($canmanageaccess && $course && $purchasestatus !== 'expired') {
                            $details .= course_access_admin::button($purchase);
                        }
                        $access[] = \html_writer::div(
                            (
                            $canaccess ? \html_writer::link(
                                new \moodle_url('/course/view.php', [ 'id' => $course->id ]),
                                $label
                            ) : $label
                            )
                            . ' '
                            . $details,
                            'mb-2'
                        );
                    }
                }
                $table->data[] = [
                format_string($line->productname),
                self::state_badge($state->paymentstatus),
                self::state_badge($state->fulfilmentstatus),
                implode('<br>', $access),
                ];
            }
            $html .= \html_writer::div(\html_writer::table($table), 'table-responsive');
            foreach ($DB->get_records('local_storefront_orderlegal', ['orderid' => $order->id]) as $legal) {
                $label = format_string($legal->title) . ' · ' . $legal->lang . ' · '
                . get_string('revision', 'local_storefront')
                . ' #'
                . $legal->revisionid;
                $html .= \html_writer::tag(
                    'p',
                    \html_writer::link(
                        new \moodle_url('/local/storefront/legal.php', [
                        'documentid' => $legal->sourceid,
                        'revision' => $legal->revisionid,
                        'document' => $legal->identifier]),
                        $label
                    )

                    . (
                    $legal->timeaccepted ? ' · ' . get_string('acceptedat', 'local_storefront') . ' '
                    . userdate($legal->timeaccepted) : ''
                    ),
                    [ 'class' => 'small' ]
                );
            }
            if ($order->status === 'pending' && (!$admin || (int)$order->userid === (int)$USER->id)) {
                $html .= \html_writer::link(
                    new \moodle_url('/local/storefront/moodle_payment.php', ['order' => $order->orderkey]),
                    get_string('resumepayment', 'local_storefront'),
                    ['class' => 'btn btn-primary']
                );
            } else if (in_array($order->status, ['paid', 'partial'], true)) {
                $html .= \html_writer::tag('p', get_string('deliverypending', 'local_storefront'));
                $html .= page::post(
                    $admin ? 'orders' : 'my',
                    get_string('retrydelivery', 'local_storefront'),
                    [ 'retry' => $order->id ],
                    'btn btn-secondary',
                    'i/reload'
                );
            }
            $html .= \html_writer::end_div();
        }
        return $html;
    }
    /**
     * Render the order or delivery state as a labelled badge.
     *
     * @param string $state State.
     * @return string
     */
    private static function state_badge(string $state): string {
        $tone = match ($state) {
            'paid',
            'completed',
            'fulfilled',
            'active',
            'sent'=>'success',
            'failed',
            'cancelled',
            'expired',
            'suspended'=>'danger',
            'pending',
            'partial',
            'processing'=>'warning',
            default=>'secondary'
        };
        return \local_storefront\output\ui::status_badge(self::state($state), $tone);
    }
    /**
     * Resolve the order state presented to the current viewer.
     *
     * @param string $state State.
     * @return string
     */
    private static function state(string $state): string {
        $key = 'state_' . $state;
        return get_string_manager()->string_exists($key, 'local_storefront') ? get_string($key, 'local_storefront') : $state;
    }
}
