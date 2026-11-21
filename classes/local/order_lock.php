<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Serialises every state transition and side effect for one or more immutable order lines.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Serialise payment, delivery retry and erasure before reading the order owner. */
final class order_lock {
    /** Run under the existing payment callback's order lock. */
    public static function with_order(int $orderid, callable $operation): mixed {
        $factory = \core\lock\lock_config::get_lock_factory('local_storefront');
        $lock = $factory->get_lock('payment-order:' . $orderid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'error');
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }
}
