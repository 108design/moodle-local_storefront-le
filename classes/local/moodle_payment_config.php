<?php
/**
 * Resolve the enabled Moodle payment account and supported currencies.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();
/**
 * Resolve the enabled Moodle payment account and supported currencies.
 */
final class moodle_payment_config {
    /**
     * Return the configured Moodle payment account ID.
     *
     * @return int
     */
    public static function account_id(): int {
        return max(0, (int)get_config('local_storefront', 'moodlepaymentaccountid'));
    }
    /**
     * Check whether the Moodle payment account and gateway are usable.
     *
     * @return bool
     */
    public static function configured(): bool {
        global $DB;
        return self::account_id() > 0
        && $DB->record_exists('payment_accounts', [ 'id' => self::account_id(), 'enabled' => 1 ]);
    }
    /**
     * Check whether an enabled gateway supports the requested currency.
     *
     * @param string $currency Currency code.
     * @return bool
     */
    public static function supports_currency(string $currency): bool {
        if (!self::configured()) {
            return false;
        }
        $account = new \core_payment\account(self::account_id());
        foreach ($account->get_gateways() as $name => $gateway) {
            if (
                $gateway->get('enabled') && in_array(
                    $currency,
                    component_class_callback('\\paygw_' . $name . '\\gateway', 'get_supported_currencies', [], []),
                    true
                )
            ) {
                return true;
            }
        }
        return false;
    }
}
