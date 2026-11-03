<?php
/**
 * File documentation for classes/local/position_lock.php.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */
// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Serialises every state transition and side effect for one or more immutable order lines. */
final class position_lock {
    /** @var array<int, int> Re-entrant lock depth within the current PHP request. */
    private static array $held = [];

    /**
     * Run an operation while holding all requested line locks in deterministic order.
     *
     * @template T
     * @param int[] $orderlineids
     * @param callable():T $operation
     * @return T
     */
    public static function with_lines(array $orderlineids, callable $operation): mixed {
        $orderlineids = array_values(array_unique(array_filter(array_map('intval', $orderlineids),
            static fn(int $id): bool => $id > 0)));
        sort($orderlineids, SORT_NUMERIC);
        if ($orderlineids === []) {
            return $operation();
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_storefront_position');
        $locks = [];
        try {
            foreach ($orderlineids as $orderlineid) {
                if (!empty(self::$held[$orderlineid])) {
                    self::$held[$orderlineid]++;
                    $locks[] = [$orderlineid, null];
                    continue;
                }
                $lock = $factory->get_lock('line:' . $orderlineid, 30);
                if (!$lock) {
                    throw new \moodle_exception('locktimeout', 'error');
                }
                self::$held[$orderlineid] = 1;
                $locks[] = [$orderlineid, $lock];
            }
            return $operation();
        } finally {
            foreach (array_reverse($locks) as [$orderlineid, $lock]) {
                self::$held[$orderlineid]--;
                if (self::$held[$orderlineid] === 0) {
                    unset(self::$held[$orderlineid]);
                    if ($lock !== null) {
                        $lock->release();
                    }
                }
            }
        }
    }
}
