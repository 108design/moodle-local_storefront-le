<?php
/**
 * Route customer help links to the supported contextual help topics.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();
/**
 * Route customer help links to the supported contextual help topics.
 */
final class customer_help {
    /**
     * Return the customer help output supported by this view.
     *
     * @return string
     */
    public static function render(): string {
        return '';
    }
    /**
     * Render a native Moodle icon.
     *
     * @param string $topic Registered contextual help topic.
     * @return string
     */
    public static function icon(string $topic): string {
        return admin_help::icon($topic === 'legaltexts' ? 'legal' : $topic);
    }
}
