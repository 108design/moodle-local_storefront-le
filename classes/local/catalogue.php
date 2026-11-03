<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();

/** Course catalogue and authoritative purchase-time offer resolution. */
final class catalogue {
    public static function products(array $filters = []): array {
        global $DB;
        $filters=catalogue_filter::normalise($filters);
        $products = array_values($DB->get_records('local_storefront_product',
            ['visible' => 1, 'producttype' => 'course'], 'sortorder,name,id'));
        foreach ($products as $product) {
            $product->categories = category_service::for_product((int)$product->id, true);
            $product->tags = tag_service::for_product((int)$product->id, true);
            $product->offers = self::offers((int)$product->id);
        }
        $products=array_values(array_filter($products, static function($p) use ($filters) {
            if ($filters['q']!=='' && !str_contains(\core_text::strtolower(self::searchable_content($p)),\core_text::strtolower($filters['q']))) { return false; }
            if ($filters['category']!=='' && !in_array($filters['category'],array_column($p->categories,'identifier'),true)) { return false; }
            if ($filters['tags'] && !array_intersect($filters['tags'],array_column($p->tags,'identifier'))) { return false; }
            if ($filters['availability']==='bundle' && count(course_delivery::public_mappings((int)$p->id))<2) { return false; }
            return true;
        }));
        if ($filters['sort']==='name') {
            usort($products,static fn($a,$b)=>[\core_text::strtolower($a->name),(int)$a->id]<=>[\core_text::strtolower($b->name),(int)$b->id]);
        } else if ($filters['category']!=='') {
            $group=$DB->get_record('local_storefront_category',['identifier'=>$filters['category'],'visible'=>1]);
            if ($group) { $products=catalogue_order::apply($products,'category',(int)$group->id); }
        } else if (count($filters['tags'])===1) {
            $group=$DB->get_record('local_storefront_tag',['identifier'=>$filters['tags'][0],'visible'=>1]);
            if ($group) { $products=catalogue_order::apply($products,'tag',(int)$group->id); }
        }
        return $products;
    }
    public static function get_public_products(array $filters=[]): array { return self::products($filters); }
    public static function get_public_product(string $identifier): ?\stdClass {
        foreach (self::products() as $p) { if ($p->identifier===$identifier) { return $p; } }
        return null;
    }
    public static function searchable_content(\stdClass $product): string {
        return $product->name.' '.strip_tags($product->shortdescription??'').' '.strip_tags($product->description??'')
            .' '.implode(' ',array_column(array_merge($product->categories??[],$product->tags??[]),'name'));
    }

    public static function offers(int $productid): array {
        global $DB;
        if (!course_delivery::public_mappings($productid)) { return []; }
        return array_values($DB->get_records('local_storefront_offer', [
            'productid' => $productid, 'enabled' => 1, 'billingtype' => 'oneoff', 'stripesourcemode' => 'moodle',
        ], 'sortorder,id'));
    }

    public static function offer(int $id): \stdClass {
        global $DB;
        $offer = $DB->get_record('local_storefront_offer', ['id' => $id, 'enabled' => 1], '*', MUST_EXIST);
        $product = $DB->get_record('local_storefront_product', ['id' => $offer->productid,
            'producttype' => 'course', 'visible' => 1], '*', MUST_EXIST);
        if ($offer->billingtype !== 'oneoff' || $offer->stripesourcemode !== 'moodle'
                || !course_delivery::public_mappings((int)$product->id) || (float)$offer->amount <= 0) {
            throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront');
        }
        $rounded=\core_payment\helper::get_rounded_cost((float)$offer->amount,$offer->currency);
        if ($rounded<=0 || abs($rounded-(float)$offer->amount)>0.000001) {
            throw new \moodle_exception('invalidoffer','local_storefront');
        }
        $offer->productname = $product->name;
        $offer->productidentifier = $product->identifier;
        $offer->producttype = 'course';
        $offer->quantity = 1;
        $offer->linetotal = $offer->amount;
        return $offer;
    }
}
