<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Remove provider associations after the owner's privacy erasure.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Call mutations only while holding the existing order lock and transaction. */
final class order_privacy {
    /** An erased owner has neither an account/contact nor an outstanding claim/recovery token. */
    public static function is_anonymised(int $orderid): bool {
        global $DB;
        $order = $DB->get_record('local_storefront_order', ['id' => $orderid],
            'id,userid,email,claimtokenhash,recoverytokenhash', MUST_EXIST);
        return $order->userid === null && $order->email === null
            && $order->claimtokenhash === null && $order->recoverytokenhash === null;
    }

    /** A late checkout response must not restore erased provider/customer associations. */
    public static function require_available(int $orderid): void {
        if (self::is_anonymised($orderid)) {
            throw new \moodle_exception('privacyordererased', 'local_storefront');
        }
    }

    /** Preserve purchase rows, values and relationships; remove external identity lookup keys. */
    public static function clear_references(int $orderid): void {
        global $DB;
        $DB->update_record('local_storefront_order', (object)[
            'id' => $orderid, 'providercheckoutid' => null, 'providerpaymentid' => null,
            'providersubscriptionid' => null,
        ]);
        // The LE schema has no provider subscription/operation ledger. Full retains
        // its financial rows with new local-only tombstones, never hashes of the old IDs.
        $manager = $DB->get_manager();
        if ($manager->table_exists('local_storefront_billctr')) {
            foreach ($DB->get_records('local_storefront_billctr', ['orderid' => $orderid]) as $contract) {
                $DB->update_record('local_storefront_billctr', (object)[
                    'id' => $contract->id, 'providersubscriptionid' => 'erased:' . $contract->id,
                    'providersubscriptionitemid' => null, 'providercustomerid' => null,
                    'latestinvoiceid' => null, 'iscurrent' => 0,
                ]);
            }
        }
        if ($manager->table_exists('local_storefront_payop')) {
            foreach ($DB->get_records('local_storefront_payop', ['orderid' => $orderid]) as $operation) {
                $DB->update_record('local_storefront_payop', (object)[
                    'id' => $operation->id, 'providerobjectid' => 'erased:' . $operation->id,
                    'idempotencykey' => 'erased:' . $operation->id,
                ]);
            }
        }
        $lineids = array_keys($DB->get_records('local_storefront_orderline', ['orderid' => $orderid], '', 'id'));
        if ($lineids) {
            [$insql, $params] = $DB->get_in_or_equal($lineids, SQL_PARAMS_NAMED, 'erasedline');
            $DB->set_field_select('local_storefront_line_state', 'pendingeffects', null, 'orderlineid ' . $insql, $params);
        }
    }
}
