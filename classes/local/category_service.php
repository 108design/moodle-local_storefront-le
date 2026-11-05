<?php
/**
 * CRUD and product assignment service for freely defined thematic categories.
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
 * CRUD and product assignment service for freely defined thematic categories.
 */
final class category_service {
    /**
     * Default badge colour.
     */
    public const DEFAULT_BADGE_COLOUR = color_value::DEFAULT_NEUTRAL;

    /**
     * Normalise a trusted category badge colour to the stored CSS-safe format.
     *
     * @param string $colour Supported CSS colour value.
     * @return string
     */
    public static function normalise_badge_colour(string $colour): string {
        try {
            return color_value::normalise($colour);
        } catch (\invalid_parameter_exception) {
            return self::DEFAULT_BADGE_COLOUR;
        }
    }

    /**
     * Return the stored taxonomy records in display order.
     *
     * @return array<int, \stdClass>
     *
     * @param bool $visibleonly Whether to omit entries hidden from the viewer.
     */
    public static function all(bool $visibleonly = false): array {
        global $DB;

        $conditions = $visibleonly ? ['visible' => 1] : null;
        return array_values($DB->get_records(
            'local_storefront_category',
            $conditions,
            'sortorder ASC, name ASC, id ASC'
        ));
    }

    /**
     * Return the menu choices available to the current editor.
     *
     * @return array<int, string>
     *
     * @param bool $visibleonly Whether to omit entries hidden from the viewer.
     */
    public static function choices(bool $visibleonly = false): array {
        $choices = [];
        foreach (self::all($visibleonly) as $category) {
            $choices[(int) $category->id] = format_string($category->name);
        }
        return $choices;
    }

    /**
     * Load the requested category.
     *
     * @param int $categoryid Category ID.
     * @return \stdClass
     */
    public static function get(int $categoryid): \stdClass {
        global $DB;

        return $DB->get_record('local_storefront_category', ['id' => $categoryid], '*', MUST_EXIST);
    }

    /**
     * Return the taxonomy terms assigned to the product.
     *
     * @return array<int, \stdClass>
     *
     * @param int $productid Product ID.
     * @param bool $visibleonly Whether to omit entries hidden from the viewer.
     */
    public static function for_product(int $productid, bool $visibleonly = false): array {
        global $DB;

        $visibilitysql = $visibleonly ? ' AND c.visible = 1' : '';
        $sql = "SELECT c.*
                  FROM {local_storefront_category} c
                  JOIN {local_storefront_productcat} pc ON pc.categoryid = c.id
                 WHERE pc.productid = :productid{$visibilitysql}
              ORDER BY c.sortorder ASC, c.name ASC, c.id ASC";
        return array_values($DB->get_records_sql($sql, ['productid' => $productid]));
    }

    /**
     * Return the IDs of the taxonomy terms assigned to the product.
     *
     * @return array<int, int>
     *
     * @param int $productid Product ID.
     */
    public static function ids_for_product(int $productid): array {
        global $DB;

        return array_map('intval', array_values($DB->get_fieldset_select(
            'local_storefront_productcat',
            'categoryid',
            'productid = :productid',
            ['productid' => $productid]
        )));
    }

    /**
     * Replace a product's category set with validated category IDs.
     *
     * @param int $productid Product ID.
     * @param array $categoryids Categoryids.
     * @return void
     */
    public static function sync_product(int $productid, array $categoryids): void {
        global $DB;

        $categoryids = array_values(array_unique(array_filter(array_map('intval', $categoryids))));
        if ($categoryids) {
            [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'category');
            $validids = array_map('intval', $DB->get_fieldset_select(
                'local_storefront_category',
                'id',
                "id {$insql}",
                $params
            ));
            sort($categoryids);
            sort($validids);
            if ($categoryids !== $validids) {
                throw new \moodle_exception('categoryinvalid', 'local_storefront');
            }
        }

        $currentids = self::ids_for_product($productid);
        foreach (array_diff($currentids, $categoryids) as $categoryid) {
            $DB->delete_records('local_storefront_productcat', [
                'productid' => $productid,
                'categoryid' => $categoryid,
            ]);
            $DB->delete_records(
                'local_storefront_sort',
                [ 'scope' => 'category', 'groupid' => $categoryid, 'productid' => $productid ]
            );
        }
        foreach (array_diff($categoryids, $currentids) as $categoryid) {
            $DB->insert_record('local_storefront_productcat', (object) [
                'productid' => $productid,
                'categoryid' => $categoryid,
            ]);
        }
    }

    /**
     * Validate and save the category.
     *
     * @param \stdClass $data Submitted or stored data.
     * @return int
     */
    public static function save(\stdClass $data): int {
        global $DB;

        $now = time();
        $record = (object) [
            'identifier' => trim((string) $data->identifier),
            'name' => trim((string) $data->name),
            'badgecolor' => self::normalise_badge_colour((string) ($data->badgecolor ?? '')),
            'visible' => empty($data->visible) ? 0 : 1,
            'sortorder' => (int) $data->sortorder,
            'timemodified' => $now,
        ];
        if (!empty($data->id)) {
            $record->id = (int) $data->id;
            $DB->update_record('local_storefront_category', $record);
            $categoryid = $record->id;
        } else {
            $record->timecreated = $now;
            $categoryid = (int) $DB->insert_record('local_storefront_category', $record);
        }

        $productids = $DB->get_fieldset_select(
            'local_storefront_productcat',
            'productid',
            'categoryid = :categoryid',
            ['categoryid' => $categoryid]
        );
        if ($productids) {
            [$insql, $params] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'product');
            $params['timemodified'] = $now;
            $DB->execute("UPDATE {local_storefront_product}
                             SET timemodified = :timemodified
                           WHERE id {$insql}", $params);
        }
        return $categoryid;
    }

    /**
     * Delete the record subject to its retained references.
     *
     * @param int $categoryid Category ID.
     * @return void
     */
    public static function delete(int $categoryid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_storefront_sort', ['scope' => 'category', 'groupid' => $categoryid]);
        $productids = $DB->get_fieldset_select(
            'local_storefront_productcat',
            'productid',
            'categoryid = :categoryid',
            ['categoryid' => $categoryid]
        );
        $DB->delete_records('local_storefront_productcat', ['categoryid' => $categoryid]);
        $DB->delete_records('local_storefront_category', ['id' => $categoryid]);
        if ($productids) {
            [$insql, $params] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'product');
            $params['timemodified'] = time();
            $DB->execute("UPDATE {local_storefront_product}
                             SET timemodified = :timemodified
                           WHERE id {$insql}", $params);
        }
        $transaction->allow_commit();
    }
}
