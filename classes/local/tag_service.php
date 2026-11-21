<?php
// This file is part of Moodle - https://moodle.org/
/**
 * CRUD and product assignment service for store-owned product tags.
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
 * CRUD and product assignment service for store-owned product tags.
 */
final class tag_service {
    /**
     * Default badge colour.
     */
    public const DEFAULT_BADGE_COLOUR = color_value::DEFAULT_NEUTRAL;

    /**
     * Normalise a trusted tag badge colour to the stored CSS-safe format.
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
        return array_values($DB->get_records('local_storefront_tag', $conditions, 'sortorder ASC, name ASC, id ASC'));
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
        foreach (self::all($visibleonly) as $tag) {
            $choices[(int) $tag->id] = format_string($tag->name);
        }
        return $choices;
    }

    /**
     * Load the requested tag.
     *
     * @param int $tagid Tagid.
     * @return \stdClass
     */
    public static function get(int $tagid): \stdClass {
        global $DB;

        return $DB->get_record('local_storefront_tag', ['id' => $tagid], '*', MUST_EXIST);
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
                  FROM {local_storefront_tag} c
                  JOIN {local_storefront_producttag} pc ON pc.tagid = c.id
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
            'local_storefront_producttag',
            'tagid',
            'productid = :productid',
            ['productid' => $productid]
        )));
    }

    /**
     * Replace a product's tag set with validated tag IDs.
     *
     * @param int $productid Product ID.
     * @param array $tagids Tagids.
     * @return void
     */
    public static function sync_product(int $productid, array $tagids): void {
        global $DB;

        $tagids = array_values(array_unique(array_filter(array_map('intval', $tagids))));
        if ($tagids) {
            [$insql, $params] = $DB->get_in_or_equal($tagids, SQL_PARAMS_NAMED, 'tag');
            $validids = array_map('intval', $DB->get_fieldset_select(
                'local_storefront_tag',
                'id',
                "id {$insql}",
                $params
            ));
            sort($tagids);
            sort($validids);
            if ($tagids !== $validids) {
                throw new \moodle_exception('taginvalid', 'local_storefront');
            }
        }

        $currentids = self::ids_for_product($productid);
        foreach (array_diff($currentids, $tagids) as $tagid) {
            $DB->delete_records('local_storefront_producttag', [ 'productid' => $productid, 'tagid' => $tagid ]);
            $DB->delete_records(
                'local_storefront_sort',
                [ 'scope' => 'tag', 'groupid' => $tagid, 'productid' => $productid ]
            );
        }
        foreach (array_diff($tagids, $currentids) as $tagid) {
            $DB->insert_record('local_storefront_producttag', (object) [
                'productid' => $productid,
                'tagid' => $tagid,
            ]);
        }
    }

    /**
     * Validate and save the tag.
     *
     * @param \stdClass $data Submitted or stored data.
     * @return int
     */
    public static function save(\stdClass $data): int {
        global $DB;

        $name = trim((string)$data->name);
        $identifier = trim((string)$data->identifier);
        if (
            $name === '' || \core_text::strlen($name) > 255
                || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $identifier)
        ) {
            throw new \invalid_parameter_exception('Invalid tag');
        }
        if (
            $DB->record_exists_select(
                'local_storefront_tag',
                $DB->sql_equal('name', ':name', false) . ' AND id <> :id',
                ['name' => $name, 'id' => (int)($data->id ?? 0)]
            )
        ) {
            throw new \moodle_exception('tagnameexists', 'local_storefront');
        }
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
            $DB->update_record('local_storefront_tag', $record);
            $tagid = $record->id;
        } else {
            $record->timecreated = $now;
            $tagid = (int) $DB->insert_record('local_storefront_tag', $record);
        }

        $productids = $DB->get_fieldset_select(
            'local_storefront_producttag',
            'productid',
            'tagid = :tagid',
            ['tagid' => $tagid]
        );
        if ($productids) {
            [$insql, $params] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'product');
            $params['timemodified'] = $now;
            $DB->execute("UPDATE {local_storefront_product}
                             SET timemodified = :timemodified
                           WHERE id {$insql}", $params);
        }
        return $tagid;
    }

    /**
     * Delete the record subject to its retained references.
     *
     * @param int $tagid Tagid.
     * @return void
     */
    public static function delete(int $tagid): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_storefront_sort', ['scope' => 'tag', 'groupid' => $tagid]);
        $productids = $DB->get_fieldset_select(
            'local_storefront_producttag',
            'productid',
            'tagid = :tagid',
            ['tagid' => $tagid]
        );
        $DB->delete_records('local_storefront_producttag', ['tagid' => $tagid]);
        $DB->delete_records('local_storefront_tag', ['id' => $tagid]);
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
