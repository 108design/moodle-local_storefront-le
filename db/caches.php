<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Cache definitions for Storefront.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();
$definitions = [
        'selection_resume' => [
        'mode' => cache_store::MODE_APPLICATION,
         'simplekeys' => true,
         'simpledata' => true,
         'ttl' => 3600,
        ],
    ];
