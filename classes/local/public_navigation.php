<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();
final class public_navigation {
    public static function render_tabs(string $active): string { return page::navigation(); }
    public static function add(string $active): void {}
    public static function breadcrumb(array $items): void {
        global $PAGE;
        $PAGE->navbar->add(get_string('storefront','local_storefront'),new \moodle_url('/local/storefront/index.php'));
        foreach ($items as [$label,$url]) { $PAGE->navbar->add($label,$url); }
    }
}
