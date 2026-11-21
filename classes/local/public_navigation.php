<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Build the public catalogue navigation and breadcrumbs.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();
/**
 * Build the public catalogue navigation and breadcrumbs.
 */
final class public_navigation {
    /**
     * Render the supported public catalogue tabs.
     *
     * @param string $active Active route or navigation key.
     * @return string
     */
    public static function render_tabs(string $active): string {
        return page::navigation();
    }
    /**
     * Add the requested item to the current selection.
     *
     * @param string $active Active route or navigation key.
     * @return void
     */
    public static function add(string $active): void {
    }
    /**
     * Append the supplied entries to the public breadcrumb trail.
     *
     * @param array $items Validated items to process.
     * @return void
     */
    public static function breadcrumb(array $items): void {
        global $PAGE;
        $PAGE->navbar->add(
            get_string('storefront', 'local_storefront'),
            new \moodle_url('/local/storefront/index.php')
        );
        foreach ($items as [$label, $url]) {
            $PAGE->navbar->add($label, $url);
        }
    }
}
