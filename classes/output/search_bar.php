<?php
/**
 * Independent consumer of the canonical native search controls.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared native search bar; the consumer supplies fields, labels and result markup.
 */
final class search_bar {
    /**
     * Render native GET controls for a caller-owned authorised collection.
     *
     * @param \moodle_url $base Native fallback destination.
     * @param array $fields Native field descriptors and current values.
     * @param string $scope Unique search instance identifier.
     * @return string
     */
    public static function controls(\moodle_url $base, array $fields, string $scope): string {
        $values = array_map(static fn(array $field) => $field['value'], $fields);
        $groups = ['search' => ['label' => get_string('search'), 'fields' => []],
            'filter' => ['label' => get_string('searchbarfilters', 'local_storefront'), 'fields' => []],
            'display' => ['label' => get_string('searchbardisplay', 'local_storefront'), 'fields' => []]];
        $active = false;
        foreach ($fields as $name => $field) {
            $id = $scope . '-' . $name;
            $label = $field['label'];
            $default = $field['default'] ?? '';
            if (($field['type'] ?? '') === 'checkbox') {
                $control = \html_writer::tag(
                    'label',
                    \html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name, 'id' => $id,
                        'value' => '1', 'checked' => $field['value'] ? 'checked' : null,
                    'class' => 'mr-2 me-2']) . s($label),
                    ['for' => $id, 'class' => 'mb-0']
                );
                $text = $label;
                $group = $field['group'] ?? 'filter';
            } else if (isset($field['options'])) {
                $options = $field['options'];
                $control = self::labelled_control(\html_writer::select(
                    $options,
                    $name,
                    $field['value'],
                    false,
                    ['id' => $id, 'class' => 'custom-select form-select', 'aria-label' => $label]
                ), $label, $id);
                $text = $options[$field['value']] ?? '';
                $group = $field['group'] ?? 'filter';
            } else {
                $control = \html_writer::label($label, $id, false, ['class' => 'sr-only visually-hidden'])
                    . \html_writer::empty_tag('input', ['type' => 'search', 'name' => $name, 'id' => $id,
                        'value' => $field['value'], 'placeholder' => $field['placeholder'] ?? $label,
                        'class' => 'form-control']);
                $text = $field['value'];
                $group = 'search';
            }
            $chips = '';
            $selected = (string)$field['value'] !== (string)$default;
            $active = $active || $selected;
            if ($selected && ($field['chip'] ?? true)) {
                $remove = $values;
                $remove[$name] = $default;
                $url = new \moodle_url($base, $remove);
                $colour = $field['colours'][$field['value']] ?? [];
                $style = !empty($colour['background']) && !empty($colour['foreground'])
                    ? '--search-bar-chip-bg: ' . $colour['background'] . '; --search-bar-chip-fg: ' . $colour['foreground'] : '';
                $chips = \html_writer::link(
                    $url,
                    \html_writer::span(s(trim(strip_tags((string)$text))), 'local-plugin-search-bar-chip-label')
                    . \html_writer::span('×', 'local-plugin-search-bar-chip-remove', ['aria-hidden' => 'true']),
                    ['class' => 'badge', 'style' => $style, 'data-search-bar-remove' => $name,
                        'data-search-bar-default' => $default,
                    'aria-label' => get_string(
                        'searchbarremove',
                        'local_storefront',
                        $label . ': ' . strip_tags((string)$text)
                    )]
                );
            }
            if (!empty($field['compact'])) {
                $groups[$group]['compact'][] = self::field($control, $name, $chips, $label);
            } else {
                $groups[$group]['fields'][] = self::field($control, $name, $chips, $label);
            }
        }
        $hidden = '';
        foreach ($base->params() as $name => $value) {
            if (!array_key_exists($name, $fields)) {
                $hidden .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
        }
        $actions = \html_writer::tag('button', get_string('search'), ['type' => 'submit',
            'class' => 'btn btn-secondary', 'data-search-bar-submit' => '1'])
            . \html_writer::link(
                $base,
                get_string('searchbarreset', 'local_storefront'),
                ['class' => 'btn btn-secondary', 'data-search-bar-reset' => '1', 'hidden' => $active ? null : 'hidden']
            );
        return self::render($groups, ['method' => 'get', 'action' => $base->out_omit_querystring(),
            'role' => 'search', 'data-search-bar' => '1', 'class' => 'local-plugin-search-bar-manager',
            'data-search-bar-loading' => get_string('searchbarloading', 'local_storefront'),
            'data-search-bar-error' => get_string('searchbarerror', 'local_storefront')], $actions, $hidden)
            . \html_writer::div('', 'local-plugin-search-bar-status small', ['data-search-bar-status' => '1',
                'role' => 'status', 'aria-live' => 'polite']);
    }

    /** A persistent inset label, without replacing the native select/disclosure. */
    /**
     * Render an inset label with its native control.
     *
     * @param string $control Native control markup.
     * @param string $label Translated label.
     * @param string $id Control identifier.
     * @param bool $native Whether to use a native label element.
     * @return string
     */
    public static function labelled_control(string $control, string $label, string $id, bool $native = true): string {
        return \html_writer::div(\html_writer::tag($native ? 'label' : 'span', $label, [
            'class' => 'local-plugin-search-bar-inset-label', 'id' => $id . '-label',
            'for' => $native ? $id : null,
        ]) . $control, 'local-plugin-search-bar-labelled-control');
    }

    /**
     * Keep each selection with its native control, including empty update targets.
     *
     * @param string $control Native control markup.
     * @param string $key Stable field key.
     * @param string $chips Removable selection markup.
     * @param string $label Accessible group label.
     * @return string
     */
    public static function field(string $control, string $key, string $chips = '', string $label = ''): string {
        return \html_writer::div($control . \html_writer::div($chips, 'local-plugin-search-bar-chips', [
            'data-search-bar-chips' => $key, 'role' => 'group', 'aria-label' => $label,
        ]), 'local-plugin-search-bar-field', ['data-search-bar-field' => $key]);
    }

    /**
     * Render the grouped native form.
     *
     * @param array $groups Labelled native field groups.
     * @param array $attributes Form attributes.
     * @param string $actions Native action markup.
     * @param string $hidden Preserved hidden context.
     * @param string $title Optional form title.
     * @return string
     */
    public static function render(
        array $groups,
        array $attributes,
        string $actions,
        string $hidden = '',
        string $title = ''
    ): string {
        $content = $hidden;
        if ($title !== '') {
            $content .= \html_writer::tag('h2', $title, ['class' => 'h5 mb-3']);
        }
        $sections = '';
        foreach ($groups as $key => $group) {
            if (!$group['fields'] && empty($group['compact'])) {
                continue;
            }
            $sections .= \html_writer::tag(
                'fieldset',
                \html_writer::tag('legend', $group['label'], ['class' => 'local-plugin-search-bar-heading'])
                . \html_writer::div(implode('', $group['fields']), 'local-plugin-search-bar-fields')
                . (!empty($group['compact']) ? \html_writer::div(
                    implode('', $group['compact']),
                    'local-plugin-search-bar-options'
                ) : ''),
                ['class' => 'local-plugin-search-bar-group local-plugin-search-bar-' . $key]
            );
        }
        $content .= \html_writer::div($sections, 'local-plugin-search-bar-groups')
            . \html_writer::div($actions, 'local-plugin-search-bar-actions');
        $attributes['class'] = trim('local-plugin-search-bar ' . ($attributes['class'] ?? ''));
        return \html_writer::tag('form', $content, $attributes);
    }
}
