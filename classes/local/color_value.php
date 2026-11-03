<?php
/**
 * File documentation for classes/local/color_value.php.
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

use invalid_parameter_exception;

/** Safe editable colour values shared by Storefront presentation surfaces. */
final class color_value {
    public const DEFAULT_NEUTRAL = 'oklch(94.165% 0.00518 247.88)';
    public const TEXT_DARK = 'oklch(26.209% 0.00949 248.19)';
    public const TEXT_LIGHT = 'oklch(100% 0 0)';

    public static function normalise(string $value): string {
        $value = strtolower(trim($value));
        if (preg_match('/^#[0-9a-f]{3}$/', $value)) {
            return strtoupper('#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]);
        }
        $number = '[+-]?(?:\d+(?:\.\d+)?|\.\d+)';
        $oklch = '/^oklch\(\s*' . $number . '%?\s+' . $number . '\s+' . $number
            . '(?:deg|grad|rad|turn)?(?:\s*\/\s*' . $number . '%?)?\s*\)$/';
        if ($value === '' || strlen($value) > 120
                || (!preg_match('/^#[0-9a-f]{6}$/', $value) && !preg_match($oklch, $value))) {
            throw new invalid_parameter_exception('Enter a six-digit hexadecimal or OKLCH colour value.');
        }
        $value = preg_replace('/\s+/', ' ', $value);
        return str_starts_with($value, '#') ? strtoupper($value) : $value;
    }

    public static function contrasting_text_colour(string $value): string {
        $value = self::normalise($value);
        if (str_starts_with($value, '#')) {
            $red = hexdec(substr($value, 1, 2));
            $green = hexdec(substr($value, 3, 2));
            $blue = hexdec(substr($value, 5, 2));
            return (($red * 299 + $green * 587 + $blue * 114) / 1000) >= 150
                ? self::TEXT_DARK : self::TEXT_LIGHT;
        }
        preg_match('/^oklch\(\s*([0-9.]+)(%)?/', $value, $matches);
        $lightness = (float) ($matches[1] ?? 0);
        if (!empty($matches[2])) {
            $lightness /= 100;
        }
        return $lightness >= 0.68 ? self::TEXT_DARK : self::TEXT_LIGHT;
    }
}
