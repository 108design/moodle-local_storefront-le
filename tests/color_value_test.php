<?php
/**
 * Regression tests for color value.
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
namespace local_storefront;

use advanced_testcase;
use invalid_parameter_exception;
use local_storefront\local\color_value;

/**
 * Tests Storefront colour normalisation and contrast.
 */
final class color_value_test extends advanced_testcase {
    /**
     * Verify that accepts hex and oklch with contrast.
     *
     * @return void
     */
    public function test_accepts_hex_and_oklch_with_contrast(): void {
        $this->assertSame('#AABBCC', color_value::normalise('#abc'));
        $this->assertSame('oklch(62% 0.18 275)', color_value::normalise('OKLCH(62% 0.18 275)'));
        $this->assertSame(
            color_value::TEXT_LIGHT,
            color_value::contrasting_text_colour('oklch(40% 0.18 275)')
        );
        $this->assertSame(
            color_value::TEXT_DARK,
            color_value::contrasting_text_colour('oklch(82% 0.10 95)')
        );
        $this->assertStringStartsWith('oklch(', color_value::DEFAULT_NEUTRAL);
    }

    /**
     * Verify that rejects other CSS colour formats.
     *
     * @return void
     */
    public function test_rejects_other_css_colour_formats(): void {
        $this->expectException(invalid_parameter_exception::class);
        color_value::normalise('rgb(1, 2, 3)');
    }
}
