<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Render capability-filtered administration navigation.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();
/**
 * Render capability-filtered administration navigation.
 */
final class admin_navigation {
    /**
     * Append the supplied entries to the native breadcrumb trail.
     *
     * @param array $items Validated items to process.
     * @return void
     */
    public static function add_breadcrumb_path(array $items): void {
        global $PAGE;
        foreach ($items as [$label, $url]) {
            $PAGE->navbar->add($label, $url);
        }
    }
    /**
     * Append an administration entry to the native breadcrumb trail.
     *
     * @param string $label Accessible control label.
     * @param \moodle_url $url Local or validated destination URL.
     * @param string $child Child.
     * @return void
     */
    public static function add_breadcrumb(string $label, \moodle_url $url, string $child = ''): void {
        global $PAGE;
        $PAGE->navbar->add($label, $url);
        if ($child !== '') {
            $PAGE->navbar->add($child);
        }
    }
    /**
     * Render the administration navigation permitted for the current user.
     *
     * @param string $active Active route or navigation key.
     * @return string
     */
    public static function render(string $active): string {
        return page::navigation(true);
    }
}
