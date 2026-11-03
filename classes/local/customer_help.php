<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();
final class customer_help {
    public static function render(): string { return ''; }
    public static function icon(string $topic): string { return admin_help::icon($topic === 'legaltexts' ? 'legal' : $topic); }
}
