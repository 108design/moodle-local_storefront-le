<?php
/**
 * Redirect the legacy selection entry point to the cart.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Redirect only; cart.php enforces the store access policy.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
redirect(new moodle_url('/local/storefront/cart.php'));
