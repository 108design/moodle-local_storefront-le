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
    public const ICON_LEGAL = 'fa-scale-balanced';
    /** Render either a Moodle pix icon or an explicitly requested Font Awesome icon. */
    private static function icon(string $icon): string {
        global $OUTPUT;

        if (str_starts_with($icon, 'fa-')) {
            return \html_writer::tag('i', '', [
                'class' => 'fa ' . $icon . ' icon',
                'aria-hidden' => 'true',
            ]);
        }
        return $OUTPUT->pix_icon($icon, '');
    }

    public static function icon_action(
        \moodle_url $url,
        string $label,
        string $icon,
        string $classes = '',
        array $attributes = []
    ): string {
        return \html_writer::link($url,
            self::icon($icon) . \html_writer::span($label, 'sr-only'), [
                'class' => trim('local-storefront-icon-button ' . $classes),
                'aria-label' => $label,
                'title' => $label,
                'data-toggle' => 'tooltip',
                'data-placement' => 'top',
            ] + $attributes);
    }


    public static function page_header(string $title, array $actions=[]): string {
        return \html_writer::div(\html_writer::tag('h2',$title,['class'=>'mb-0']).($actions?self::actionbar($actions):''),
            'local-storefront-page-header d-flex flex-wrap align-items-center justify-content-between mb-4');
    }
    public static function actionbar(array $actions): string {
        return \html_writer::div(implode('',array_filter($actions)),'local-storefront-actionbar');
    }
    public static function table_actions(array $actions): string {
        return \html_writer::div(implode('',array_filter($actions)),'local-storefront-table-actions');
    }
    public static function badge(string $label,string $tone='secondary',string $icon=''): string {
        $tone=in_array($tone,['primary','secondary','success','danger','warning','info'],true)?$tone:'secondary';
        return \html_writer::span(($icon?self::icon($icon):'').\html_writer::span(s($label)),
            'badge rounded-pill local-storefront-semantic-badge local-storefront-status-badge badge-'.$tone);
    }
    public static function status_badge(string $label,string $tone='secondary'): string {
        return self::badge($label,$tone,match($tone){'success'=>'fa-circle-check','warning'=>'fa-clock',
            'danger'=>'fa-circle-xmark','info','primary'=>'fa-circle-info',default=>'fa-circle-minus'});
    }
    public static function visibility_badge(string $label,bool $visible): string {
        return self::badge($label,$visible?'success':'secondary',$visible?'fa-eye':'fa-eye-slash');
    }
}
