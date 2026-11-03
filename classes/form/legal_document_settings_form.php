<?php
/**
 * File documentation for classes/form/legal_document_settings_form.php.
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
namespace local_storefront\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

use local_storefront\local\legal_service;

/** Settings shared by every language version of an existing document. */
final class legal_document_settings_form extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        foreach (['id', 'expectedversion'] as $name) {
            $mform->addElement('hidden', $name, 0);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement('text', 'identifier', get_string('legalidentifier', 'local_storefront'), ['size' => 40]);
        $mform->setType('identifier', PARAM_ALPHANUMEXT);
        $mform->addRule('identifier', null, 'required', null, 'client');
        $mform->addHelpButton('identifier', 'legalidentifier', 'local_storefront');
        $mform->addElement('select', 'defaultlang', get_string('legaldefaultlanguage', 'local_storefront'),
            $this->_customdata['languages']);
        $mform->addHelpButton('defaultlang', 'legaldefaultlanguage', 'local_storefront');
        foreach (['visible' => 'legalvisible', 'showincheckout' => 'showincheckout',
            'requireconfirmation' => 'requireconfirmation'] as $name => $key) {
            $mform->addElement('advcheckbox', $name,
                get_string($name === 'visible' ? 'visible' : $key, $name === 'visible' ? 'moodle' : 'local_storefront'));
            $mform->addHelpButton($name, $key, 'local_storefront');
        }
        $mform->hideIf('requireconfirmation', 'showincheckout', 'notchecked');
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_storefront'), ['size' => 8]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->addHelpButton('sortorder', 'legalsortorder', 'local_storefront');
        $this->add_action_buttons(true);
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if ($DB->record_exists_select('local_storefront_legaldoc', 'identifier = :identifier AND id <> :id',
                ['identifier' => $data['identifier'], 'id' => (int) $data['id']])) {
            $errors['identifier'] = get_string('legalidentifierexists', 'local_storefront');
        }
        $translations = legal_service::translations((int) $data['id']);
        if (!isset($translations[$data['defaultlang']])) {
            $errors['defaultlang'] = get_string('required');
        } else if (!empty($data['visible']) && empty($translations[$data['defaultlang']]->published)) {
            $errors['defaultlang'] = get_string('legaldefaultrequired', 'local_storefront');
        }
        return $errors;
    }
}
