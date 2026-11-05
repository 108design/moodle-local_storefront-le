<?php
/**
 * Narrow optional boundary to the separately installable Storefront enrolment plugin.
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
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Narrow optional boundary to the separately installable Storefront enrolment plugin.
 */
final class course_delivery {
    /**
     * Service.
     */
    private const SERVICE = '\\enrol_storefront\\local\\fulfilment_service';

    /**
     * Report whether the required component service is available.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(self::SERVICE) && function_exists('enrol_is_enabled')
            && enrol_is_enabled('storefront');
    }

    /**
     * Return a public course mapping or null when course delivery must fail closed.
     *
     * @param int $productid Product ID.
     * @return \stdClass|null
     */
    public static function public_mapping(int $productid): ?\stdClass {
        $mappings = self::public_mappings($productid);
        return $mappings ? reset($mappings) : null;
    }

    /**
     * Return the product's visible and enabled course mappings.
     *
     * @return array<int, \stdClass>
     *
     * @param int $productid Product ID.
     */
    public static function public_mappings(int $productid): array {
        if (!self::available() || !method_exists(self::SERVICE, 'mappings_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'mappings_for_product'], $productid, true);
    }

    /**
     * Return any administrative mapping, including a disabled or hidden target.
     *
     * @param int $productid Product ID.
     * @return \stdClass|null
     */
    public static function mapping(int $productid): ?\stdClass {
        $mappings = self::mappings($productid);
        return $mappings ? reset($mappings) : null;
    }

    /**
     * Return the product's native Storefront course mappings.
     *
     * @return array<int, \stdClass>
     *
     * @param int $productid Product ID.
     */
    public static function mappings(int $productid): array {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'mappings_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'mappings_for_product'], $productid, false);
    }

    /**
     * Immutable course-delivery values captured before payment.
     *
     * @param int $productid Product ID.
     * @return array
     */
    public static function snapshot(int $productid): array {
        $snapshots = self::snapshots($productid);
        if (!$snapshots) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        return reset($snapshots);
    }

    /**
     * Capture the course mappings used by a new immutable order.
     *
     * @return array<int, array<string, mixed>>
     *
     * @param int $productid Product ID.
     */
    public static function snapshots(int $productid): array {
        if (!self::available() || !method_exists(self::SERVICE, 'snapshots_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'snapshots_for_product'], $productid);
    }

    /**
     * Deliver one paid course order line to its claimed Moodle user.
     *
     * @param int $orderid Order ID.
     * @param int $orderlineid Order-line ID.
     * @param int $userid User ID.
     * @param array $snapshot Immutable delivery or selection snapshot.
     * @param string $billingtype Billing mode recorded for the purchase.
     * @param string|null $subscriptionreference Gateway subscription reference.
     * @param int|null $subscriptionexpiry Subscription expiry timestamp.
     * @return void
     */
    public static function fulfil(
        int $orderid,
        int $orderlineid,
        int $userid,
        array $snapshot,
        string $billingtype,
        ?string $subscriptionreference,
        ?int $subscriptionexpiry
    ): void {
        if (!self::available() || !method_exists(self::SERVICE, 'fulfil')) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        call_user_func(
            [self::SERVICE, 'fulfil'],
            $orderid,
            $orderlineid,
            $userid,
            $snapshot,
            $billingtype,
            $subscriptionreference,
            $subscriptionexpiry
        );
    }

    /**
     * Extend current course access after a verified recurring payment.
     *
     * @param int $orderid Order ID.
     * @param int $validuntil Expiry timestamp.
     * @return void
     */
    public static function renew_order(int $orderid, int $validuntil): void {
        if (self::available() && method_exists(self::SERVICE, 'renew_order')) {
            call_user_func([self::SERVICE, 'renew_order'], $orderid, $validuntil);
        }
    }

    /**
     * Suspend delivered purchases belonging to the selected order.
     *
     * @param int $orderid Order ID.
     * @return void
     */
    public static function suspend_order(int $orderid): void {
        if (self::available() && method_exists(self::SERVICE, 'suspend_order')) {
            call_user_func([self::SERVICE, 'suspend_order'], $orderid);
        }
    }

    /**
     * Resume delivered purchases belonging to the selected order.
     *
     * @param int $orderid Order ID.
     * @return void
     */
    public static function resume_order(int $orderid): void {
        if (self::available() && method_exists(self::SERVICE, 'resume_order')) {
            call_user_func([self::SERVICE, 'resume_order'], $orderid);
        }
    }

    /**
     * Extend the purchases belonging to the verified subscription line.
     *
     * @param int $orderlineid Order-line ID.
     * @param int $validuntil Expiry timestamp.
     * @return void
     */
    public static function renew_line(int $orderlineid, int $validuntil): void {
        if (self::available() && method_exists(self::SERVICE, 'renew_line')) {
            call_user_func([self::SERVICE, 'renew_line'], $orderlineid, $validuntil);
        }
    }

    /**
     * Suspend the purchases belonging to one order line.
     *
     * @param int $orderlineid Order-line ID.
     * @return void
     */
    public static function suspend_line(int $orderlineid): void {
        if (self::available() && method_exists(self::SERVICE, 'suspend_line')) {
            call_user_func([self::SERVICE, 'suspend_line'], $orderlineid);
        }
    }

    /**
     * Resume the purchases belonging to one order line.
     *
     * @param int $orderlineid Order-line ID.
     * @return void
     */
    public static function resume_line(int $orderlineid): void {
        if (self::available() && method_exists(self::SERVICE, 'resume_line')) {
            call_user_func([self::SERVICE, 'resume_line'], $orderlineid);
        }
    }

    /**
     * Check whether stored purchases still refer to the product.
     *
     * @param int $productid Product ID.
     * @return bool
     */
    public static function product_in_use(int $productid): bool {
        return self::mappings($productid) !== [];
    }

    /**
     * Count the delivered purchase records for the product.
     *
     * @param int $productid Product ID.
     * @return int
     */
    public static function purchase_count(int $productid): int {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'purchase_count')) {
            return self::mapping($productid) ? 1 : 0;
        }
        return (int) call_user_func([self::SERVICE, 'purchase_count'], $productid);
    }

    /**
     * Remove delivery records for the explicitly verified test orders.
     *
     * @param array $orderids Order IDs to process.
     * @param int $productid Product ID.
     * @return void
     */
    public static function purge_test_orders(array $orderids, int $productid): void {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'purge_test_orders')) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        call_user_func([self::SERVICE, 'purge_test_orders'], $orderids, $productid);
    }

    /**
     * Remove native course mappings once no purchased access remains.
     *
     * @param int $productid Product ID.
     * @return void
     */
    public static function unmap_product(int $productid): void {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'unmap_product')) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        call_user_func([self::SERVICE, 'unmap_product'], $productid);
    }
}
