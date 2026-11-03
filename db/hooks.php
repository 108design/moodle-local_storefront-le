<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();
$callbacks = [
    ['hook' => \core\hook\output\before_http_headers::class,
        'callback' => \local_storefront\local\admin_help::class.'::prepare'],
    ['hook' => \core\hook\output\before_standard_footer_html_generation::class,
        'callback' => \local_storefront\local\admin_help::class.'::footer'],
];

// Product identity on native administration and upgrade surfaces.
$callbacks[] = ['hook'=>\core\hook\output\before_standard_head_html_generation::class,
    'callback'=>[\local_storefront\local\admin_branding::class,'head']];
$callbacks[] = ['hook'=>\core\hook\output\before_http_headers::class,
    'callback'=>[\local_storefront\local\admin_branding::class,'prepare']];

$callbacks[] = ['hook'=>\core\hook\output\before_standard_footer_html_generation::class,
    'callback'=>[\local_storefront\output\cart_drawer::class,'footer']];
