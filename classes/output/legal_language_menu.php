<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Real stored versions determine the reader menu; editors may add any Moodle language.
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
namespace local_storefront\output;

defined('MOODLE_INTERNAL') || die();

use local_storefront\local\legal_service;

/**
 * Real stored versions determine the reader menu; editors may add any Moodle language.
 */
final class legal_language_menu {
    /**
     * Render the language choices available to the legal-text reader.
     *
     * @param \stdClass $document Validated document data.
     * @param string $language Language code.
     * @return string
     */
    public static function reader(\stdClass $document, string $language): string {
        $choices = ['auto' => get_string('legalautomaticlanguage', 'local_storefront')];
        foreach (legal_service::translations((int) $document->id, true) as $translation) {
            $choices[$translation->lang] = legal_service::language_name($translation->lang);
        }
        return self::form(
            new \moodle_url('/local/storefront/legal.php'),
            [ 'document' => $document->identifier ],
            'doclang',
            $choices,
            $language,
            'reader',
            \local_storefront\local\customer_help::icon('legaltexts')
        );
    }

    /**
     * Render the administration language choices for the document.
     *
     * @param \stdClass $document Validated document data.
     * @param string $language Language code.
     * @return string
     */
    public static function admin(\stdClass $document, string $language): string {
        $links = [];
        foreach (legal_service::translations((int) $document->id) as $translation) {
            $label = legal_service::language_name($translation->lang)
                . (empty($translation->published) ? ' (' . get_string('legaldraft', 'local_storefront') . ')' : '');
            $links[] = \html_writer::link(new \moodle_url('/local/storefront/legal_edit.php', [
                'id' => $document->id, 'editlang' => $translation->lang,
            ]), s($label), ['class' => $language === $translation->lang ? 'font-weight-bold fw-bold' : '']);
        }
        return \html_writer::div(implode(' · ', $links), 'mb-3')
            . self::form(
                new \moodle_url('/local/storefront/legal_edit.php'),
                ['id' => $document->id],
                'editlang',
                ['' => get_string('legaladdlanguage', 'local_storefront')] + legal_service::languages(),
                '',
                'admin'
            );
    }

    /**
     * Render the document language control for the native editing form.
     *
     * @param \moodle_url $url Local or validated destination URL.
     * @param array $hidden Whether the element is hidden.
     * @param string $name Display name.
     * @param array $choices Choices.
     * @param string $selected Selected.
     * @param string $suffix Suffix.
     * @param string $info Info.
     * @return string
     */
    private static function form(
        \moodle_url $url,
        array $hidden,
        string $name,
        array $choices,
        string $selected,
        string $suffix,
        string $info = ''
    ): string {
        $id = 'local-storefront-legal-language-' . $suffix;
        $html = \html_writer::tag('label', get_string('legallanguage', 'local_storefront'), ['for' => $id])
            . \html_writer::select($choices, $name, $selected, null, [
                'id' => $id, 'class' => 'form-select custom-select',
            ]) . $info
            . \html_writer::input_hidden_params(new \moodle_url($url, $hidden))
            . \html_writer::tag('button', get_string('go'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
        return \html_writer::tag('form', $html, [
            'method' => 'get', 'action' => $url->out(false),
            'class' => 'local-storefront-legal-language-menu mb-4',
        ]);
    }
}
