<?php
// This file is part of Moodle - https://moodle.org/
/**
 * One ordering contract for the native store and presentation consumers.
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
 * One ordering contract for the native store and presentation consumers.
 */
final class catalogue_order {
    /**
     * Apply the supported catalogue ordering to the supplied records.
     *
     * @param array $products Product records.
     * @param string $scope Requested scope.
     * @param int $groupid Native group ID.
     * @return array
     */
    public static function apply(array $products, string $scope, int $groupid): array {
        global $DB;
        $positions = $DB->get_records_menu(
            'local_storefront_sort',
            ['scope' => $scope, 'groupid' => $groupid],
            '',
            'productid,sortorder'
        );
        if (!$positions) {
            return $products;
        }
        $fallback = array_flip(array_map(static fn($p) => (int)$p->id, $products));
        usort($products, static fn($a, $b) =>
            [(int)($positions[$a->id] ?? PHP_INT_MAX), $fallback[$a->id]]
            <=> [(int)($positions[$b->id] ?? PHP_INT_MAX), $fallback[$b->id]]);
        return $products;
    }

    /**
     * All members, including hidden rows and rows outside a current search.
     *
     * @param string $kind Requested object or operation kind.
     * @param string $scope Requested scope.
     * @param int $groupid Native group ID.
     * @return array
     */
    public static function members(string $kind, string $scope = 'global', int $groupid = 0): array {
        global $DB;
        if (
            !in_array($kind, ['product', 'category', 'tag'], true)
                || !in_array($scope, ['global', 'category', 'tag'], true)
                || ($kind !== 'product' && $scope !== 'global')
        ) {
            throw new \invalid_parameter_exception('Invalid ordering context');
        }
        if ($kind !== 'product' || $scope === 'global') {
            return array_values($DB->get_records('local_storefront_' . $kind, null, 'sortorder ASC,name ASC,id ASC'));
        }
        $DB->get_record('local_storefront_' . $scope, ['id' => $groupid], '*', MUST_EXIST);
        $join = $scope === 'category' ? 'productcat' : 'producttag';
        $members = array_values($DB->get_records_sql("SELECT p.* FROM {local_storefront_product} p
            JOIN {local_storefront_{$join}} a ON a.productid = p.id WHERE a.{$scope}id = :groupid
            ORDER BY p.sortorder ASC,p.name ASC,p.id ASC", ['groupid' => $groupid]));
        return self::apply($members, $scope, $groupid);
    }

    /**
     * Hash the current selection or ordering state for a stale-state check.
     *
     * @param array $members Members.
     * @return string
     */
    public static function fingerprint(array $members): string {
        return hash('sha256', json_encode(array_map(static fn($m) => [(int)$m->id, (int)$m->sortorder,
            (string)$m->name], $members)));
    }

    /**
     * Move the selected item after validating the expected ordering state.
     *
     * @param string $kind Requested object or operation kind.
     * @param int $id Record ID.
     * @param string $direction Requested movement direction.
     * @param int $target Destination for the operation.
     * @param string $scope Requested scope.
     * @param int $groupid Native group ID.
     * @param string $expected Expected.
     * @return void
     */
    public static function move(
        string $kind,
        int $id,
        string $direction,
        int $target,
        string $scope,
        int $groupid,
        string $expected
    ): void {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront_catalogue')->get_lock('ordering', 10);
        if (!$lock) {
            throw new \moodle_exception('orderingbusy', 'local_storefront');
        }
        try {
            $tx = $DB->start_delegated_transaction();
            $members = self::members($kind, $scope, $groupid);
            if (!hash_equals(self::fingerprint($members), $expected)) {
                throw new \moodle_exception('orderingchanged', 'local_storefront');
            }
            $ids = array_map(static fn($m) => (int)$m->id, $members);
            $index = array_search($id, $ids, true);
            if ($index === false) {
                throw new \invalid_parameter_exception('Missing ordering member');
            }
            if ($direction === 'reset' && $kind === 'product' && $scope !== 'global') {
                $DB->delete_records('local_storefront_sort', ['scope' => $scope, 'groupid' => $groupid]);
            } else {
                if (!in_array($direction, ['first', 'up', 'down', 'last', 'before', 'after'], true)) {
                    throw new \invalid_parameter_exception('Invalid move');
                }
                if (
                    in_array($direction, [ 'before', 'after' ], true)
                    && (
                    $target === $id || !in_array($target, $ids, true)
                    )
                ) {
                    throw new \invalid_parameter_exception('Invalid move target');
                }
                array_splice($ids, $index, 1);
                $position = match ($direction) {
                    'first' => 0, 'up' => max(0, $index - 1), 'down' => min(count($ids), $index + 1),
                    'last' => count($ids),
                    'before' => array_search($target, $ids, true),
                    'after' => array_search($target, $ids, true) + 1,
                };
                array_splice($ids, $position, 0, [$id]);
                if ($kind === 'product' && $scope !== 'global') {
                    $DB->delete_records('local_storefront_sort', ['scope' => $scope, 'groupid' => $groupid]);
                    foreach ($ids as $i => $memberid) {
                        $DB->insert_record('local_storefront_sort', (object)['scope' => $scope,
                            'groupid' => $groupid, 'productid' => $memberid, 'sortorder' => $i]);
                    }
                } else {
                    foreach ($ids as $i => $memberid) {
                        $DB->update_record('local_storefront_' . $kind, (object)['id' => $memberid,
                            'sortorder' => $i * 10, 'timemodified' => time()]);
                    }
                }
            }
            $tx->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Sections repeat shared products deliberately; callers count distinct products.
     *
     * @param array $products Product records.
     * @param array $filters Filtering criteria.
     * @return array
     */
    public static function sections(array $products, array $filters): array {
        $filters = catalogue_filter::normalise($filters);
        $scope = $filters['group'];
        if ($scope === '') {
            return [['name' => '', 'products' => $products]];
        }
        $service = $scope === 'category' ? category_service::class : tag_service::class;
        $field = $scope === 'category' ? 'categories' : 'tags';
        $sections = [];
        $assigned = [];
        foreach ($service::all(true) as $group) {
            // A selected facet narrows the displayed section headings as well as the products.
            if ($scope === 'category' && $filters['category'] !== '' && $group->identifier !== $filters['category']) {
                continue;
            }
            if ($scope === 'tag' && $filters['tags'] && !in_array($group->identifier, $filters['tags'], true)) {
                continue;
            }
            $members = array_values(array_filter($products, static fn($p) =>
                in_array((int)$group->id, array_map(static fn($g) => (int)$g->id, $p->{$field} ?? []), true)));
            if (!$members) {
                continue;
            }
            foreach ($members as $p) {
                $assigned[$p->id] = true;
            }
            $sections[] = ['name' => $group->name, 'products' => $filters['sort'] === 'name'
                ? $members : self::apply($members, $scope, (int)$group->id)];
        }
        $remaining = array_values(array_filter($products, static fn($p) => !isset($assigned[$p->id])));
        if ($remaining) {
            $sections[] = ['name' => get_string('otherproducts', 'local_storefront'), 'products' => $remaining];
        }
        return $sections;
    }
}
