<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();

/** Session selection contains offer IDs only; prices and delivery are always resolved again. */
final class product_selection {
    public static function snapshot(): array {
        global $SESSION;
        return array_values(array_map('intval', $SESSION->local_storefront_selection ?? []));
    }
    public static function restore(array $ids): void {
        global $SESSION;
        $validated = [];
        foreach ($ids as $id) {
            $offer = catalogue::offer((int)$id);
            $validated[(int)$offer->productid] = (int)$offer->id;
        }
        $SESSION->local_storefront_selection = array_values($validated);
    }
    public static function add(int $id): void {
        self::restore(array_merge(self::snapshot(), [$id]));
    }
    public static function remove(int $id): void {
        global $SESSION;
        $SESSION->local_storefront_selection = array_values(array_diff(self::snapshot(), [$id]));
    }
    public static function clear(): void {
        global $SESSION;
        $SESSION->local_storefront_selection = [];
    }
    public static function items(): array {
        return array_map([catalogue::class, 'offer'], self::snapshot());
    }
    public static function fingerprint(array $items, ?array $courses = null): string {
        return hash('sha256',json_encode(array_map(static fn($i)=>[
            $i->id,$i->amount,$i->currency,$i->productname,
            $courses[$i->id] ?? course_delivery::snapshots((int)$i->productid),
        ],array_values($items)),JSON_THROW_ON_ERROR));
    }
}
