<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Moodle editor and formatted-content helpers for products.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Moodle editor and formatted-content helpers for products.
 */
final class product_content {
    /**
     * Active standalone documents must not execute under the Moodle origin.
     *
     * @param string $mimetype Mimetype.
     * @param bool $requested Requested.
     * @return bool
     */
    public static function requires_download(string $mimetype, bool $requested): bool {
        return $requested || in_array(strtolower($mimetype), [
            'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        ], true);
    }
    /**
     * Options shared by both Tiny-compatible Moodle editor fields.
     *
     * @return array
     */
    public static function editor_options(): array {

        return [
            'context' => \context_system::instance(),
         'maxfiles' => 20,
         'maxbytes' => 0,
         'subdirs' => true,
         // Follow Moodle's normal capability- and site-policy-driven trusted-content contract.
            'trusttext' => true, 'removeorphaneddrafts' => true,
        ];
    }

    /**
     * Prepare stored product text and embedded files for the edit form.
     *
     * @param \stdClass $data Submitted or stored data.
     * @param int $productid Product ID.
     * @return \stdClass
     */
    public static function prepare_editors(\stdClass $data, int $productid): \stdClass {

        $context = \context_system::instance();
        foreach (['shortdescription', 'description'] as $field) {
            $data = file_prepare_standard_editor(
                $data,
                $field,
                self::editor_options(),
                $context,
                'local_storefront',
                $field,
                $productid
            );
        }
        return $data;
    }

    /**
     * Move submitted editor files into their permanent areas and return final text data.
     *
     * @param \stdClass $data Submitted or stored data.
     * @param int $productid Product ID.
     * @return \stdClass
     */
    public static function postupdate_editors(\stdClass $data, int $productid): \stdClass {

        $context = \context_system::instance();
        foreach (['shortdescription', 'description'] as $field) {
            $data = file_postupdate_standard_editor(
                $data,
                $field,
                self::editor_options(),
                $context,
                'local_storefront',
                $field,
                $productid
            );
        }
        return $data;
    }

    /**
     * Render a stored product editor field with its Moodle format and embedded files.
     *
     * @param \stdClass $product Course product record.
     * @param string $field Field name.
     * @return string
     */
    public static function format(\stdClass $product, string $field): string {

        $context = \context_system::instance();
        $text = file_rewrite_pluginfile_urls(
            (string) ($product->{$field} ?? ''),
            'pluginfile.php',
            $context->id,
            'local_storefront',
            $field,
            (int) $product->id
        );
        $trusted = !empty($product->{$field . 'trust'});
        return format_text($text, (int) ($product->{$field . 'format'} ?? FORMAT_HTML), [
            'context' => $context,
         'trusted' => $trusted,
         'noclean' => $trusted,
         'allowid' => $trusted,
         'overflowdiv' => true,

        ]);
    }
}
