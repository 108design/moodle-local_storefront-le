<?php
/**
 * File documentation for classes/local/product_image.php.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */
// This file is part of a 108design source-available software product.
//
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>
//
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Product image file-area helpers. */
final class product_image {
    public static function options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];
    }

    public static function url(int $productid): ?\moodle_url {
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_storefront',
            'productimage',
            $productid,
            'sortorder, id',
            false
        );
        $file = reset($files);
        if (!$file) {
            return null;
        }
        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_storefront',
            'productimage',
            $productid,
            $file->get_filepath(),
            $file->get_filename()
        );
    }
}
