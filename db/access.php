<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Capability definitions for Storefront.
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

$capabilities = [
    'local/storefront:viewcatalogue' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [ 'guest' => CAP_ALLOW, 'user' => CAP_ALLOW ],
    ],
    'local/storefront:viewownorders' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [ 'user' => CAP_ALLOW ],
    ],
    'local/storefront:managecatalogue' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [ 'manager' => CAP_ALLOW ],
    ],
    'local/storefront:managelegal' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [ 'manager' => CAP_ALLOW ],
    ],
    'local/storefront:manageorders' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [ 'manager' => CAP_ALLOW ],
    ],
];
