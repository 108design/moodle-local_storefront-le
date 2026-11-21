<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Document rules are shared; title, format and content belong to one language.
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
namespace local_storefront\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

use local_storefront\local\legal_service;

/**
 * Document rules are shared; title, format and content belong to one language.
 */
final class legal_document_form extends \moodleform {
    /**
     * Define the native form fields and their defaults.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        foreach (['id', 'expectedversion'] as $name) {
            $mform->addElement('hidden', $name, 0);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement('hidden', 'originlang', $this->_customdata['language'] ?? '');
        $mform->setType('originlang', PARAM_SAFEDIR);
        $mform->addElement(
            'select',
            'documentlanguage',
            get_string('legallanguage', 'local_storefront'),
            ['' => get_string('choosedots')] + legal_service::languages()
        );
        $mform->addHelpButton('documentlanguage', 'legallanguage', 'local_storefront');
        if (!empty($this->_customdata['language']) && $this->_customdata['language'] !== 'und') {
            $mform->setDefault('documentlanguage', $this->_customdata['language']);
            $mform->hardFreeze('documentlanguage');
        }
        $mform->addElement('text', 'title', get_string('legaldocumenttitle', 'local_storefront'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addHelpButton('title', 'legaldocumenttitle', 'local_storefront');
        $formats = [FORMAT_HTML => get_string('legaleditorformat', 'local_storefront'),
            FORMAT_MARKDOWN => 'Markdown'];
        // Preserve older native formats without silently converting their source.
        $oldformat = (int) ($this->_customdata['format'] ?? FORMAT_HTML);
        if (!isset($formats[$oldformat])) {
            $formats[$oldformat] = format_text_menu()[$oldformat];
        }
        $mform->addElement('select', 'contentformat', get_string('legalinputformat', 'local_storefront'), $formats);
        $mform->addHelpButton('contentformat', 'legalinputformat', 'local_storefront');
        $mform->addElement(
            'editor',
            'content_editor',
            get_string('legaldocumentcontent', 'local_storefront'),
            ['rows' => 18],
            ['maxfiles' => 0, 'maxbytes' => 0, 'trusttext' => false]
        );
        $mform->addHelpButton('content_editor', 'legaldocumentcontent', 'local_storefront');
        $mform->hideIf('content_editor', 'contentformat', 'eq', FORMAT_MARKDOWN);
        $mform->addElement(
            'textarea',
            'content_markdown',
            get_string('legaldocumentcontent', 'local_storefront'),
            ['rows' => 24, 'cols' => 80, 'class' => 'local-storefront-markdown-source', 'spellcheck' => 'true']
        );
        $mform->setType('content_markdown', PARAM_RAW);
        $mform->addHelpButton('content_markdown', 'legaldocumentcontent', 'local_storefront');
        $mform->hideIf('content_markdown', 'contentformat', 'neq', FORMAT_MARKDOWN);
        $mform->addElement('advcheckbox', 'published', get_string('legalpublished', 'local_storefront'));
        $mform->addHelpButton('published', 'legalpublished', 'local_storefront');
        // Shared settings are entered once at creation; existing documents use their own settings page.
        if (empty($this->_customdata['documentid'])) {
            $mform->addElement('header', 'documentsettings', get_string('legalsettings', 'local_storefront'));
            $mform->setExpanded('documentsettings');
            $mform->addElement('text', 'identifier', get_string('legalidentifier', 'local_storefront'), ['size' => 40]);
            $mform->setType('identifier', PARAM_ALPHANUMEXT);
            $mform->addRule('identifier', null, 'required', null, 'client');
            $mform->addHelpButton('identifier', 'legalidentifier', 'local_storefront');
            foreach (
                ['visible' => 'legalvisible', 'showincheckout' => 'showincheckout',
                'requireconfirmation' => 'requireconfirmation'] as $name => $key
            ) {
                $mform->addElement(
                    'advcheckbox',
                    $name,
                    get_string(
                        $name === 'visible' ? 'visible' : $key,
                        $name === 'visible' ? 'moodle' : 'local_storefront'
                    )
                );
                $mform->addHelpButton($name, $key, 'local_storefront');
            }
            $mform->setDefault('visible', 1);
            $mform->hideIf('requireconfirmation', 'showincheckout', 'notchecked');
            $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_storefront'), ['size' => 8]);
            $mform->setType('sortorder', PARAM_INT);
            $mform->setDefault('sortorder', 0);
            $mform->addHelpButton('sortorder', 'legalsortorder', 'local_storefront');
        }
        $this->add_action_buttons(true);
    }

    /**
     * Validate submitted values and return errors keyed by field name.
     *
     * @param mixed $data Submitted or stored data.
     * @param mixed $files Submitted file data.
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (
            empty($data['id'])
            && $DB->record_exists_select(
                'local_storefront_legaldoc',
                'identifier = :identifier AND id <> :id',
                [ 'identifier' => $data['identifier'], 'id' => (int) ($data['id'] ?? 0) ]
            )
        ) {
            $errors['identifier'] = get_string('legalidentifierexists', 'local_storefront');
        }
        if (!isset(legal_service::languages()[$data['documentlanguage']])) {
            $errors['documentlanguage'] = get_string('required');
        }
        $text = (int) $data['contentformat'] === (int) FORMAT_MARKDOWN ? $data['content_markdown']
            : ($data['content_editor']['text'] ?? '');
        if (trim($text) === '') {
            $errors[(int) $data['contentformat'] === (int) FORMAT_MARKDOWN ? 'content_markdown' : 'content_editor']
                = get_string('required');
        }
        if (empty($data['id']) && !empty($data['visible']) && empty($data['published'])) {
            $errors['published'] = get_string('legaldefaultrequired', 'local_storefront');
        }
        return $errors;
    }
}
