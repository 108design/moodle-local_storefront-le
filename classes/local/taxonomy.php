<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Bounded editorial mutations; never grants rights or changes delivery.
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
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Bounded editorial mutations; never grants rights or changes delivery.
 */
final class taxonomy {
    /**
     * Resolve the category or tag service for the requested taxonomy kind.
     *
     * @param string $kind Requested object or operation kind.
     * @return string
     */
    public static function service(string $kind): string {
        if (!in_array($kind, ['category', 'tag'], true)) {
            throw new \invalid_parameter_exception('Invalid taxonomy');
        }
        return $kind === 'category' ? category_service::class : tag_service::class;
    }

    /**
     * Apply the requested product taxonomy assignments.
     *
     * @param string $kind Requested object or operation kind.
     * @param array $products Product records.
     * @param int $id Record ID.
     * @param bool $add Add.
     * @return array
     */
    public static function assign(string $kind, array $products, int $id, bool $add): array {
        global $DB;
        $service = self::service($kind);
        $service::get($id);
        $products = array_values(array_unique(array_map('intval', $products)));
        if (!$products || count($products) > 200) {
            throw new \invalid_parameter_exception('Invalid selection');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront_catalogue')->get_lock('assignment', 10);
        if (!$lock) {
            throw new \moodle_exception('orderingbusy', 'local_storefront');
        }
        try {
            $tx = $DB->start_delegated_transaction();
            $changed = [];
            foreach ($products as $productid) {
                $DB->get_record('local_storefront_product', ['id' => $productid], 'id', MUST_EXIST);
                $ids = $service::ids_for_product($productid);
                $exists = in_array($id, $ids, true);
                if ($exists === $add) {
                    continue;
                }
                $service::sync_product($productid, $add ? array_merge($ids, [$id]) : array_diff($ids, [$id]));
                if (!$add) {
                    $DB->delete_records(
                        'local_storefront_sort',
                        [ 'scope' => $kind, 'groupid' => $id, 'productid' => $productid ]
                    );
                }
                $DB->set_field('local_storefront_product', 'timemodified', time(), ['id' => $productid]);
                $changed[] = $productid;
            }
            $tx->allow_commit();
            return $changed;
        } finally {
            $lock->release();
        }
    }

    /**
     * Explicit creation with a stable URL identifier and a controlled vocabulary.
     *
     * @param string $name Display name.
     * @return int
     */
    public static function create_tag(string $name): int {
        $name = trim(clean_param($name, PARAM_TEXT));
        if ($name === '' || \core_text::strlen($name) > 255) {
            throw new \invalid_parameter_exception('Invalid tag name');
        }
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront_catalogue')->get_lock('create-tag', 10);
        if (!$lock) {
            throw new \moodle_exception('orderingbusy', 'local_storefront');
        }
        try {
            foreach (tag_service::all() as $tag) {
                if (\core_text::strtolower($tag->name) === \core_text::strtolower($name)) {
                    return (int)$tag->id;
                }
            }
            $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)), '-');
            $slug = substr($slug ?: 'tag', 0, 75) . '-' . substr(bin2hex(random_bytes(6)), 0, 12);
            return tag_service::save((object)['name' => $name, 'identifier' => $slug, 'visible' => 1,
                'sortorder' => count(tag_service::all()) * 10]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Explicit conversion preserves the membership, with no inference from product names.
     *
     * @param int $categoryid Category ID.
     * @return int
     */
    public static function convert(int $categoryid): int {
        global $DB;
        $category = category_service::get($categoryid);
        $tx = $DB->start_delegated_transaction();
        $existing = false;
        foreach (tag_service::all() as $tag) {
            if (\core_text::strtolower($tag->name) === \core_text::strtolower($category->name)) {
                $existing = true;
                break;
            }
        }
        $tagid = self::create_tag($category->name);
        $products = $DB->get_fieldset_select(
            'local_storefront_productcat',
            'productid',
            'categoryid = :id',
            [ 'id' => $categoryid ]
        );
        foreach ($products as $id) {
            tag_service::sync_product((int)$id, array_merge(tag_service::ids_for_product((int)$id), [$tagid]));
        }
        $tag = tag_service::get($tagid);
        if (!$existing) {
            $tag->visible = $category->visible;
            $tag->badgecolor = $category->badgecolor;
            $tag->sortorder = $category->sortorder;
            tag_service::save($tag);
            foreach (
                $DB->get_records(
                    'local_storefront_sort',
                    [ 'scope' => 'category', 'groupid' => $categoryid ]
                ) as $position
            ) {
                unset($position->id);
                $position->scope = 'tag';
                $position->groupid = $tagid;
                $DB->insert_record('local_storefront_sort', $position);
            }
        }
        category_service::delete($categoryid);
        $tx->allow_commit();
        return $tagid;
    }
}
