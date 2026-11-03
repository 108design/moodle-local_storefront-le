<?php
/**
 * File documentation for classes/local/course_delivery.php.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */
// This file is part of a 108design source-available software product.
//
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>
//
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Narrow optional boundary to the separately installable Storefront enrolment plugin. */
final class course_delivery {
    private const SERVICE = '\\enrol_storefront\\local\\fulfilment_service';

    public static function available(): bool {
        return class_exists(self::SERVICE)
            && function_exists('enrol_is_enabled')
            && enrol_is_enabled('storefront');
    }

    /** Return a public course mapping or null when course delivery must fail closed. */
    public static function public_mapping(int $productid): ?\stdClass {
        $mappings = self::public_mappings($productid);
        return $mappings ? reset($mappings) : null;
    }

    /** @return array<int, \stdClass> */
    public static function public_mappings(int $productid): array {
        if (!self::available() || !method_exists(self::SERVICE, 'mappings_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'mappings_for_product'], $productid, true);
    }

    /** Return any administrative mapping, including a disabled or hidden target. */
    public static function mapping(int $productid): ?\stdClass {
        $mappings = self::mappings($productid);
        return $mappings ? reset($mappings) : null;
    }

    /** @return array<int, \stdClass> */
    public static function mappings(int $productid): array {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'mappings_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'mappings_for_product'], $productid, false);
    }

    /** Immutable course-delivery values captured before payment. */
    public static function snapshot(int $productid): array {
        $snapshots = self::snapshots($productid);
        if (!$snapshots) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        return reset($snapshots);
    }

    /** @return array<int, array<string, mixed>> */
    public static function snapshots(int $productid): array {
        if (!self::available() || !method_exists(self::SERVICE, 'snapshots_for_product')) {
            return [];
        }
        return call_user_func([self::SERVICE, 'snapshots_for_product'], $productid);
    }

    /** Deliver one paid course order line to its claimed Moodle user. */
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
        call_user_func([self::SERVICE, 'fulfil'], $orderid, $orderlineid, $userid, $snapshot,
            $billingtype, $subscriptionreference, $subscriptionexpiry);
    }

    /** Extend current course access after a verified recurring payment. */
    public static function renew_order(int $orderid, int $validuntil): void {
        if (self::available() && method_exists(self::SERVICE, 'renew_order')) {
            call_user_func([self::SERVICE, 'renew_order'], $orderid, $validuntil);
        }
    }

    public static function suspend_order(int $orderid): void {
        if (self::available() && method_exists(self::SERVICE, 'suspend_order')) {
            call_user_func([self::SERVICE, 'suspend_order'], $orderid);
        }
    }

    public static function resume_order(int $orderid): void {
        if (self::available() && method_exists(self::SERVICE, 'resume_order')) {
            call_user_func([self::SERVICE, 'resume_order'], $orderid);
        }
    }

    public static function renew_line(int $orderlineid, int $validuntil): void {
        if (self::available() && method_exists(self::SERVICE, 'renew_line')) {
            call_user_func([self::SERVICE, 'renew_line'], $orderlineid, $validuntil);
        }
    }

    public static function suspend_line(int $orderlineid): void {
        if (self::available() && method_exists(self::SERVICE, 'suspend_line')) {
            call_user_func([self::SERVICE, 'suspend_line'], $orderlineid);
        }
    }

    public static function resume_line(int $orderlineid): void {
        if (self::available() && method_exists(self::SERVICE, 'resume_line')) {
            call_user_func([self::SERVICE, 'resume_line'], $orderlineid);
        }
    }

    public static function product_in_use(int $productid): bool {
        return self::mappings($productid) !== [];
    }

    public static function purchase_count(int $productid): int {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'purchase_count')) {
            return self::mapping($productid) ? 1 : 0;
        }
        return (int) call_user_func([self::SERVICE, 'purchase_count'], $productid);
    }

    public static function purge_test_orders(array $orderids, int $productid): void {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'purge_test_orders')) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        call_user_func([self::SERVICE, 'purge_test_orders'], $orderids, $productid);
    }

    public static function unmap_product(int $productid): void {
        if (!class_exists(self::SERVICE) || !method_exists(self::SERVICE, 'unmap_product')) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        call_user_func([self::SERVICE, 'unmap_product'], $productid);
    }
}
