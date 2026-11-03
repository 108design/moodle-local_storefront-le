<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\output;
defined('MOODLE_INTERNAL') || die();
final class ui {
    public const ICON_LEGAL = 'i/info';
    public static function page_header(string $title, array $actions = []): string {
        return \html_writer::tag('h2',$title).self::actionbar($actions);
    }
    public static function icon_action(\moodle_url $url,string $label,string $icon='',string $class=''): string {
        return \html_writer::link($url,$label,['class'=>'btn btn-secondary btn-sm '.$class]);
    }
    public static function actionbar(array $actions): string { return \html_writer::div(implode(' ', $actions),'d-flex flex-wrap gap-2 mb-4'); }
    public static function table_actions(array $actions): string { return self::actionbar($actions); }
    public static function badge(string $label,string $style='',string $icon=''): string { return \html_writer::span($label,'badge bg-secondary'); }
    public static function status_badge(string $label,string $style=''): string { return s($label); }
    public static function visibility_badge(string $label,bool $visible): string { return s($label); }
}
