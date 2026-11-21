<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Upgrade callbacks for Storefront.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();
/**
 * Apply supported schema upgrades for Storefront.
 *
 * @param mixed $oldversion Previously installed numeric plugin build.
 * @return mixed
 */
function xmldb_local_storefront_upgrade($oldversion) {
    // This protects migration data only; the installer may already have replaced files.
    if ($oldversion > 2026093099 || get_config('local_storefront', 'distribution') !== 'course') {
        throw new moodle_exception('distributionmismatch', 'local_storefront');
    }
    if ($oldversion < 2026010114) {
        global $DB;
        // LE only creates orders for authenticated users. These NULL associations
        // identify earlier privacy erasure, whose old provider retained the payment ID.
        $select = "userid IS NULL AND email IS NULL AND channel = :channel AND providerpaymentid LIKE :payment";
        $params = ['channel' => 'moodle', 'payment' => 'moodle:%'];
        foreach ($DB->get_records_select('local_storefront_order', $select, $params, 'id', 'id') as $order) {
            \local_storefront\local\order_lock::with_order((int)$order->id, static function() use ($DB, $order, $select, $params): void {
                $DB->set_field_select('local_storefront_order', 'providerpaymentid', null,
                    'id = :orderid AND ' . $select, ['orderid' => $order->id] + $params);
            });
        }
        upgrade_plugin_savepoint(true, 2026010114, 'local', 'storefront');
    }
    return true;
}
