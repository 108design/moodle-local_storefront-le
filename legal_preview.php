<?php
/**
 * Native legal preview entry point for Storefront.
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
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/storefront:managelegal', context_system::instance());
require_sesskey();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidparameter');
}
$content = required_param('content', PARAM_RAW);
if (strlen($content) > 1048576) {
    throw new moodle_exception('invalidparameter');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(
    ['html' => \local_storefront\local\legal_service::render_content($content, FORMAT_MARKDOWN)],
    JSON_THROW_ON_ERROR
);
