<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Package replacement compatibility.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Compatibility for an old cached hook registration; intentionally passive. */
final class update_guard {
    /** Allow old cached callbacks to finish without changing the native request. */
    public static function after_config(\core\hook\after_config $hook): void {
    }
}
