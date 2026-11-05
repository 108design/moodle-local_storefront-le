<?php
/**
 * Moodle Payment API consumer for authenticated one-off Storefront product bundles.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
namespace local_storefront\payment;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle Payment API consumer for authenticated one-off Storefront product bundles.
 */
final class service_provider implements \core_payment\local\callback\service_provider {
    /**
     * Area order.
     */
    public const AREA_ORDER = 'courseorder';
    /** @deprecated Kept stable for already-created Moodle payment records. */
    public const AREA_COURSE_ORDER = 'courseorder';

    /**
     * Return the cost, currency and account for the authenticated buyer's order.
     *
     * @param string $paymentarea Core payment service area.
     * @param int $itemid Service-specific item ID.
     * @return \core_payment\local\entities\payable
     */
    public static function get_payable(string $paymentarea, int $itemid): \core_payment\local\entities\payable {
        global $USER;
        $order = self::order($paymentarea, $itemid);
        if (!isloggedin() || isguestuser() || (int)$USER->id !== (int)$order->userid) {
            throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
        }
        return new \core_payment\local\entities\payable(
            (float) $order->total,
            (string) $order->currency,
            (int) $order->provideraccountid
        );
    }

    /**
     * Return the post-payment destination without granting course access.
     *
     * @param string $paymentarea Core payment service area.
     * @param int $itemid Service-specific item ID.
     * @return \moodle_url
     */
    public static function get_success_url(string $paymentarea, int $itemid): \moodle_url {
        $order = self::order($paymentarea, $itemid, true);
        return new \moodle_url('/local/storefront/moodle_payment.php', ['order' => $order->orderkey]);
    }

    /**
     * Deliver the order only after binding the verified Core payment to its buyer.
     *
     * @param string $paymentarea Core payment service area.
     * @param int $itemid Service-specific item ID.
     * @param int $paymentid Verified Core payment ID.
     * @param int $userid User ID.
     * @return bool
     */
    public static function deliver_order(string $paymentarea, int $itemid, int $paymentid, int $userid): bool {
        $order = self::order($paymentarea, $itemid, true);
        return \local_storefront\local\order_service::complete_moodle_payment(
            (int) $order->id,
            $paymentid,
            $userid
        );
    }

    /**
     * Load the order belonging to the authenticated buyer and payment area.
     *
     * @param string $paymentarea Core payment service area.
     * @param int $itemid Service-specific item ID.
     * @param bool $allowcompleted Allowcompleted.
     * @return \stdClass
     */
    private static function order(string $paymentarea, int $itemid, bool $allowcompleted = false): \stdClass {
        global $DB;
        if ($paymentarea !== self::AREA_ORDER) {
            throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
        }
        $order = $DB->get_record('local_storefront_order', [
            'id' => $itemid,
            'channel' => 'moodle',
        ], '*', MUST_EXIST);
        if (
            empty($order->userid) || empty($order->provideraccountid)

            || (
            !in_array(
                (string) $order->status,
                $allowcompleted ? [ 'pending', 'paid', 'partial', 'completed' ] : [ 'pending' ],
                true
            )
            )
        ) {
            throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
        }
        return $order;
    }
}
