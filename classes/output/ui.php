<?php
/**
 * Render shared native interface controls for the component.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
namespace local_storefront\output;

defined('MOODLE_INTERNAL') || die();
/**
 * Render shared native interface controls for the component.
 */
final class ui {
    /**
     * Icon legal.
     */
    public const ICON_LEGAL = 'fa-scale-balanced';
    /**
     * Render either a Moodle pix icon or an explicitly requested Font Awesome icon.
     *
     * @param string $icon Native icon name.
     * @return string
     */
    public static function icon(string $icon): string {
        global $OUTPUT;

        if (str_starts_with($icon, 'fa-')) {
            return \html_writer::tag('i', '', [ 'class' => 'fa ' . $icon . ' icon', 'aria-hidden' => 'true' ]);
        }
        return $OUTPUT->pix_icon($icon, '');
    }

    /**
     * Render an accessible icon link for a native action.
     *
     * @param \moodle_url $url Local or validated destination URL.
     * @param string $label Accessible control label.
     * @param string $icon Native icon name.
     * @param string $classes CSS classes for the generated element.
     * @param array $attributes HTML attributes for the generated element.
     * @return string
     */
    public static function icon_action(
        \moodle_url $url,
        string $label,
        string $icon,
        string $classes = '',
        array $attributes = []
    ): string {
        return \html_writer::link(
            $url,
            self::icon($icon) . \html_writer::span($label, 'sr-only'),
            [
                'class' => trim('local-storefront-icon-button ' . $classes),
                'aria-label' => $label,
                'title' => $label,
                'data-toggle' => 'tooltip',
                'data-placement' => 'top',
            ] + $attributes
        );
    }


    /**
     * Render a native action button with its label and attributes.
     *
     * @param \moodle_url $url Local or validated destination URL.
     * @param string $label Accessible control label.
     * @param string $icon Native icon name.
     * @param string $class Class.
     * @return string
     */
    public static function button(
        \moodle_url $url,
        string $label,
        string $icon,
        string $class = 'btn btn-outline-secondary'
    ): string {
        return \html_writer::link($url, self::icon($icon) . \html_writer::span(s($label)), ['class' => $class]);
    }
    /**
     * Group the supported controls in the native toolbar.
     *
     * @param array $actions Action definitions for this view.
     * @return string
     */
    public static function toolbar(array $actions): string {
        return \html_writer::div(implode('', array_filter($actions)), 'local-storefront-toolbar mb-4');
    }

    /**
     * Render the component page heading and its action controls.
     *
     * @param string $title Display title.
     * @param array $actions Action definitions for this view.
     * @return string
     */
    public static function page_header(string $title, array $actions = []): string {
        return \html_writer::div(
            \html_writer::tag('h2', $title, ['class' => 'mb-0']) . ($actions ? self::actionbar($actions) : ''),
            'local-storefront-page-header d-flex flex-wrap align-items-center justify-content-between mb-4'
        );
    }
    /**
     * Group related native page actions.
     *
     * @param array $actions Action definitions for this view.
     * @return string
     */
    public static function actionbar(array $actions): string {
        return \html_writer::div(implode('', array_filter($actions)), 'local-storefront-actionbar');
    }
    /**
     * Group the actions for one table row.
     *
     * @param array $actions Action definitions for this view.
     * @return string
     */
    public static function table_actions(array $actions): string {
        return \html_writer::div(implode('', array_filter($actions)), 'local-storefront-table-actions');
    }
    /**
     * Render an accessible labelled state badge.
     *
     * @param string $label Accessible control label.
     * @param string $tone Visual tone of the notice or control.
     * @param string $icon Native icon name.
     * @return string
     */
    public static function badge(string $label, string $tone = 'secondary', string $icon = ''): string {
        $tone = in_array(
            $tone,
            [ 'primary', 'secondary', 'success', 'danger', 'warning', 'info' ],
            true
        ) ? $tone : 'secondary';
        return \html_writer::span(
            ($icon ? self::icon($icon) : '') . \html_writer::span(s($label)),
            'badge rounded-pill local-storefront-semantic-badge local-storefront-status-badge badge-' . $tone
        );
    }
    /**
     * Render the state badge for the record.
     *
     * @param string $label Accessible control label.
     * @param string $tone Visual tone of the notice or control.
     * @return string
     */
    public static function status_badge(string $label, string $tone = 'secondary'): string {
        return self::badge($label, $tone, match ($tone) {
            'success'=>'fa-circle-check', 'warning'=>'fa-triangle-exclamation',
            'danger'=>'fa-circle-xmark', 'info', 'primary'=>'fa-circle-info', default=>'fa-circle-minus'
        });
    }
    /**
     * Render the product's visible or hidden state.
     *
     * @param string $label Accessible control label.
     * @param bool $visible Visible.
     * @return string
     */
    public static function visibility_badge(string $label, bool $visible): string {
        return self::badge($label, $visible ? 'success' : 'secondary', $visible ? 'fa-eye' : 'fa-eye-slash');
    }
}
