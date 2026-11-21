<?php
// This file is part of Moodle - https://moodle.org/
/**
 * External AJAX functions for Storefront.
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
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_storefront_preview_legal' => [
        'classname' => \local_storefront\external\preview_legal::class,
        'methodname' => 'execute',
        'description' => 'Preview unsaved legal Markdown without saving it.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/storefront:managelegal',
        'loginrequired' => true,
    ],
];
