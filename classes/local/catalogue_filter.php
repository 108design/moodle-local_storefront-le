<?php
/**
 * File documentation for classes/local/catalogue_filter.php.
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

/** Canonical catalogue-filter vocabulary for course products. */
final class catalogue_filter {
    public const PRODUCT_TYPES = ['course'];
    public const AVAILABILITY = ['bundle', 'paid'];

    /** Read and normalise filter values from the current request. */
    public static function from_request(): array {
        return self::normalise([
            'q' => optional_param('q', '', PARAM_NOTAGS),
            'type' => optional_param('type', '', PARAM_ALPHA),
            'category' => optional_param('category', '', PARAM_ALPHANUMEXT),
            'availability' => optional_param('availability', '', PARAM_ALPHA),
            'tags' => optional_param_array('tagselect', [], PARAM_ALPHANUMEXT)
                ?: optional_param('tags', '', PARAM_RAW_TRIMMED),
            'group' => optional_param('group', get_config('local_storefront', 'cataloguegroup') ?: '', PARAM_ALPHA),
            'sort' => optional_param('sort', get_config('local_storefront', 'cataloguesort') ?: '', PARAM_ALPHA),
        ]);
    }

    /** Return only supported, trimmed filter values. */
    public static function normalise(array $filters): array {
        $query = trim((string) ($filters['q'] ?? ''));
        $type = (string) ($filters['type'] ?? '');
        $category = trim((string) ($filters['category'] ?? ''));
        $availability = (string) ($filters['availability'] ?? '');
        $tags = $filters['tags'] ?? [];
        $tags = is_array($tags) ? $tags : explode(',', (string)$tags);
        $tags = array_slice(array_values(array_unique(array_filter(array_map('trim', $tags),
            static fn($t) => preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $t)))), 0, 50);
        return [
            'q' => \core_text::substr($query, 0, 255),
            'type' => in_array($type, self::PRODUCT_TYPES, true) ? $type : '',
            'category' => preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $category) ? $category : '',
            'availability' => in_array($availability, self::AVAILABILITY, true) ? $availability : '',
            'tags' => $tags,
            'group' => in_array($filters['group'] ?? '', ['category', 'tag'], true) ? $filters['group'] : '',
            'sort' => ($filters['sort'] ?? '') === 'name' ? 'name' : '',
        ];
    }

    public static function active(array $filters): bool {
        $filters = self::normalise($filters);
        return $filters['q'] !== '' || $filters['type'] !== '' || $filters['category'] !== ''
            || $filters['availability'] !== '' || $filters['tags'] !== [];
    }

    /** @return array<string, string> */
    public static function product_type_choices(): array {
        $choices = [];
        foreach (self::PRODUCT_TYPES as $type) {
            $choices[$type] = get_string('producttype' . $type, 'local_storefront');
        }
        return $choices;
    }

    /** @return array<string, string> */
    public static function availability_choices(): array {
        $choices = [];
        foreach (self::AVAILABILITY as $availability) {
            $choices[$availability] = get_string('availability' . $availability, 'local_storefront');
        }
        return $choices;
    }
}
