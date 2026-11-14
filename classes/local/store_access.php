<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Global audience gate for customer-facing Storefront content and routes.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
//
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
//
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Global audience gate for customer-facing Storefront content and routes.
 */
final class store_access {
    /**
     * Public.
     */
    public const PUBLIC = 'public';
    /**
     * Authenticated.
     */
    public const AUTHENTICATED = 'authenticated';
    /**
     * Editors.
     */
    public const EDITORS = 'editors';

    /**
     * Anonymous browsing uses the native guest role without signing the visitor in.
     *
     * @return void
     */
    public static function require_catalogue_capability(): void {
        global $USER, $CFG;
        $context = \context_system::instance();
        $viewer = isloggedin() ? $USER : guest_user();
        if (
            (!isloggedin() && !empty($CFG->forcelogin)) || !$viewer
                || !has_capability('local/storefront:viewcatalogue', $context, $viewer)
        ) {
            throw new \required_capability_exception($context, 'local/storefront:viewcatalogue', 'nopermissions', '');
        }
    }

    /**
     * The configured audience, failing closed to editors while the Store is under development.
     *
     * @return string
     */
    public static function mode(): string {
        $mode = (string) get_config('local_storefront', 'storevisibility');
        return in_array($mode, [self::PUBLIC, self::AUTHENTICATED, self::EDITORS], true)
            ? $mode : self::EDITORS;
    }

    /**
     * Whether the current viewer belongs to the configured Store audience.
     *
     * @return bool
     */
    public static function can_view(): bool {
        global $CFG;
        if (!isloggedin() && !empty($CFG->forcelogin)) {
            return false;
        }
        if (self::is_editor()) {
            return true;
        }
        return match (self::mode()) {
            self::PUBLIC => true,
            self::AUTHENTICATED => isloggedin() && !isguestuser(),
            default => false,
        };
    }

    /**
     * Enforce the global audience before a route performs its more specific capability checks.
     *
     * @return void
     */
    public static function require_access(): void {
        global $CFG;
        if (!isloggedin() && !empty($CFG->forcelogin)) {
            require_login();
        }
        $mode = self::mode();
        if ($mode === self::AUTHENTICATED && (!isloggedin() || isguestuser())) {
            require_login();
        }
        if ($mode === self::EDITORS) {
            if (!isloggedin() || isguestuser()) {
                require_login();
            }
            $context = \context_system::instance();
            if (!self::is_editor()) {
                throw new \core\exception\required_capability_exception(
                    $context,
                    'local/storefront:managecatalogue',
                    'nopermissions',
                    ''
                );
            }
        }
    }

    /**
     * Storefront editors include every role with an operational Store write permission.
     *
     * @return bool
     */
    public static function is_editor(): bool {
        $context = \context_system::instance();
        return has_any_capability([
            'local/storefront:managecatalogue',
            'local/storefront:manageorders',
            'local/storefront:managelegal',
            'moodle/site:config',
        ], $context);
    }
}
