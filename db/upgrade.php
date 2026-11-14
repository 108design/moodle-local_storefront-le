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
    return true;
}
