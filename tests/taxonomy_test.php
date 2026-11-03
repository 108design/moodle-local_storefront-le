<?php
/**
 * File documentation for tests/taxonomy_test.php.
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
namespace local_storefront;
defined('MOODLE_INTERNAL') || die();
use local_storefront\local\{catalogue, catalogue_filter, catalogue_order, category_service, tag_service, taxonomy};

/** Editorial membership, visibility and ordering across public catalogue consumers. */
final class taxonomy_test extends \advanced_testcase {
    private function product(string $name, int $order = 0): int {
        global $DB;
        $id = $DB->insert_record('local_storefront_product', (object)['identifier' => strtolower($name), 'name' => $name,
            'producttype' => 'course', 'visible' => 1, 'sortorder' => $order, 'timecreated' => time(), 'timemodified' => time()]);
        $DB->insert_record('local_storefront_offer', (object)['productid' => $id, 'identifier' => 'single',
            'displayname' => 'Course', 'channel' => 'direct', 'billingtype' => 'oneoff', 'currency' => 'EUR', 'amount' => 10,
            'entitlementsjson' => '[]', 'enabled' => 1, 'stripesourcemode' => 'moodle',
            'timecreated' => time(), 'timemodified' => time()]);
        $course=$this->getDataGenerator()->create_course(['visible'=>1]);
        $role=$DB->get_field('role','id',['shortname'=>'student']);
        enrol_get_plugin('storefront')->add_instance($course,['customint1'=>$id,'roleid'=>$role,'status'=>ENROL_INSTANCE_ENABLED]);
        return (int)$id;
    }
    private function setup_catalogue(): void {
        $this->resetAfterTest(); $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,storefront');
    }
    private function ids(array $products): array { return array_map(static fn($p) => (int)$p->id, $products); }

    public function test_tags_match_any_and_hidden_tags_do_not_leak_through_search(): void {
        $this->setup_catalogue();
        $a = $this->product('Alpha'); $b = $this->product('Beta');
        $advanced = taxonomy::create_tag('Advanced Courses');
        $standalone = taxonomy::create_tag('Practical Courses');
        taxonomy::assign('tag', [$a], $advanced, true);
        taxonomy::assign('tag', [$a, $b], $standalone, true);
        $f = tag_service::get($advanced); $s = tag_service::get($standalone);
        $this->assertSame([$a, $b], $this->ids(catalogue::get_public_products(['tags' => [$f->identifier, $s->identifier]])));
        $this->assertSame([$a], $this->ids(catalogue::get_public_products(['q' => 'Advanced Courses'])));
        $f->visible = 0; tag_service::save($f);
        $this->assertSame([], catalogue::get_public_products(['tags' => [$f->identifier]]));
        $this->assertSame([], catalogue::get_public_products(['q' => 'Advanced Courses']));
        $this->assertStringNotContainsString('Advanced Courses', catalogue::searchable_content(catalogue::get_public_product('alpha')));
    }

    public function test_global_and_group_order_include_hidden_members_and_reset(): void {
        global $DB;
        $this->setup_catalogue();
        $a = $this->product('Alpha', 0); $b = $this->product('Beta', 10); $c = $this->product('Gamma', 20);
        $DB->set_field('local_storefront_product', 'visible', 0, ['id' => $b]);
        $tag = taxonomy::create_tag('Standalone');
        taxonomy::assign('tag', [$a, $b, $c], $tag, true);
        $members = catalogue_order::members('product', 'tag', $tag);
        catalogue_order::move('product', $c, 'first', 0, 'tag', $tag, catalogue_order::fingerprint($members));
        $this->assertSame([$c, $a, $b], $this->ids(catalogue_order::members('product', 'tag', $tag)));
        $this->assertSame([$a, $b, $c], $this->ids(catalogue_order::members('product')));
        $identifier = tag_service::get($tag)->identifier;
        $this->assertSame([$c, $a], $this->ids(catalogue::get_public_products(['tags' => [$identifier]])));
        $this->assertSame([$a, $c], $this->ids(catalogue::get_public_products(['tags' => [$identifier], 'sort' => 'name'])));
        catalogue_order::move('product', $c, 'reset', 0, 'tag', $tag,
            catalogue_order::fingerprint(catalogue_order::members('product', 'tag', $tag)));
        $this->assertSame([$a, $b, $c], $this->ids(catalogue_order::members('product', 'tag', $tag)));
        catalogue_order::move('product', $c, 'before', $b, 'global', 0,
            catalogue_order::fingerprint(catalogue_order::members('product')));
        $this->assertSame([$a, $c, $b], $this->ids(catalogue_order::members('product')));
    }

    public function test_stale_move_is_rejected_without_changing_order(): void {
        $this->setup_catalogue();
        $a = $this->product('Alpha'); $b = $this->product('Beta');
        $hash = catalogue_order::fingerprint(catalogue_order::members('product'));
        catalogue_order::move('product', $b, 'first', 0, 'global', 0, $hash);
        $this->expectException(\moodle_exception::class);
        catalogue_order::move('product', $a, 'first', 0, 'global', 0, $hash);
    }

    public function test_grouping_repeats_product_only_across_sections_and_returns_unassigned(): void {
        $this->setup_catalogue();
        $a = $this->product('Alpha'); $b = $this->product('Beta');
        $t1 = taxonomy::create_tag('Extensions'); $t2 = taxonomy::create_tag('Standalone');
        taxonomy::assign('tag', [$a], $t1, true); taxonomy::assign('tag', [$a], $t2, true);
        $products = catalogue::get_public_products();
        $this->assertCount(2, $products);
        $sections = catalogue_order::sections($products, ['group' => 'tag']);
        $this->assertCount(3, $sections);
        $this->assertSame([$a], $this->ids($sections[0]['products']));
        $this->assertSame([$a], $this->ids($sections[1]['products']));
        $this->assertSame([$b], $this->ids($sections[2]['products']));
    }

    public function test_patch_undo_and_conversion_preserve_unrelated_assignments(): void {
        global $DB;
        $this->setup_catalogue();
        $a = $this->product('Alpha'); $b = $this->product('Beta');
        $t1 = taxonomy::create_tag('Standalone'); $t2 = taxonomy::create_tag('Another');
        taxonomy::assign('tag', [$a], $t1, true); taxonomy::assign('tag', [$a], $t2, true);
        $changed = taxonomy::assign('tag', [$a, $b], $t1, true);
        $this->assertSame([$b], $changed);
        taxonomy::assign('tag', $changed, $t1, false);
        $this->assertEqualsCanonicalizing([$t1, $t2], tag_service::ids_for_product($a));
        $this->assertSame([], tag_service::ids_for_product($b));
        $category = category_service::save((object)['identifier' => 'standalone', 'name' => 'Standalone', 'visible' => 1, 'sortorder' => 0]);
        category_service::sync_product($b, [$category]);
        $this->assertSame($t1, taxonomy::convert($category));
        $this->assertFalse($DB->record_exists('local_storefront_category', ['id' => $category]));
        $this->assertSame([$t1], tag_service::ids_for_product($b));
        $this->assertSame($t1, taxonomy::create_tag('STANDALONE'));
    }

    public function test_grouping_and_sorting_are_not_filters(): void {
        $this->assertFalse(catalogue_filter::active(['group' => 'tag', 'sort' => 'name']));
        $this->assertTrue(catalogue_filter::active(['tags' => ['standalone']]));
        $this->assertSame(['a', 'b'], catalogue_filter::normalise(['tags' => 'a,b,a,<bad>'])['tags']);
    }
}
