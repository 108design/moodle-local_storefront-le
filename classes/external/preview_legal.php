<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Render an authorised unsaved legal Markdown preview.
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
namespace local_storefront\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

/** Read-only preview of unsaved legal text for authorised administrators. */
final class preview_legal extends external_api {
    /** Describe the raw Markdown input. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'content' => new external_value(PARAM_RAW, 'Unsaved Markdown source'),
        ]);
    }

    /** Validate the system context and render without saving the submitted text. */
    public static function execute(string $content): array {
        $params = self::validate_parameters(self::execute_parameters(), ['content' => $content]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/storefront:managelegal', $context);
        if (strlen($params['content']) > 1048576) {
            throw new \invalid_parameter_exception('Markdown preview exceeds 1 MiB.');
        }
        return ['html' => \local_storefront\local\legal_service::render_content($params['content'], FORMAT_MARKDOWN)];
    }

    /** Describe the sanitised preview HTML. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Sanitised legal preview HTML'),
        ]);
    }
}
