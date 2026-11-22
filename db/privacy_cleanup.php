<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Reconcile provider references left by earlier completed privacy erasures.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
defined('MOODLE_INTERNAL') || die();

/** Owned orders, guest contacts and still-claimable orders are never selected. */
function xmldb_local_storefront_cleanup_provider_references(): void {
    global $DB;
    $select = 'userid IS NULL AND email IS NULL AND claimtokenhash IS NULL AND recoverytokenhash IS NULL';
    foreach ($DB->get_records_select('local_storefront_order', $select, [], 'id', 'id') as $selected) {
        \local_storefront\local\order_lock::with_order((int)$selected->id, static function() use ($selected, $DB): void {
            if (!\local_storefront\local\order_privacy::is_anonymised((int)$selected->id)) {
                return;
            }
            $transaction = $DB->start_delegated_transaction();
            \local_storefront\local\order_privacy::clear_references((int)$selected->id);
            $transaction->allow_commit();
        });
    }
}
