<?php
/**
 * Regression tests for file delivery.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront;

defined('MOODLE_INTERNAL') || die();

/**
 * File delivery safety is independent of a database or the uploader's role.
 */
final class file_delivery_test extends \basic_testcase {
    /**
     * Verify that active documents download and normal embedded media remains available.
     *
     * @return void
     */
    public function test_active_documents_download_and_normal_embedded_media_remains_available(): void {
        foreach (
            [
            'text/html',
            'application/xhtml+xml',
            'image/svg+xml',
            'text/xml',
            'application/xml',
            'TEXT/HTML',
            ] as $type
        ) {
            $this->assertTrue(\local_storefront\local\product_content::requires_download($type, false), $type);
        }
        foreach (
            [
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
            'audio/mpeg',
            'video/mp4',
            'application/pdf',
            ] as $type
        ) {
            $this->assertFalse(\local_storefront\local\product_content::requires_download($type, false), $type);
            $this->assertTrue(\local_storefront\local\product_content::requires_download($type, true), $type);
        }
    }
}
