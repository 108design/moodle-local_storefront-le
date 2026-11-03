<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();
final class admin_navigation {
    public static function add_breadcrumb_path(array $items): void {
        global $PAGE;
        foreach ($items as [$label,$url]) { $PAGE->navbar->add($label,$url); }
    }
    public static function add_breadcrumb(string $label, \moodle_url $url, string $child = ''): void {
        global $PAGE;
        $PAGE->navbar->add($label,$url);
        if ($child !== '') { $PAGE->navbar->add($child); }
    }
    public static function render(string $active): string { return page::navigation(true); }
}
