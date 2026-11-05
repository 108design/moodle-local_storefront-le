<?php
/**
 * Edit transactional message rules and their recipient settings.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
/**
 * Edit transactional message rules and their recipient settings.
 */
final class message extends \moodleform {
    /**
     * Define the native form fields and their defaults.
     *
     * @return void
     */
    protected function definition(): void {
        $m = $this->_form;
        $m->addElement(
            'select',
            'language',
            get_string('language', 'local_storefront'),
            [ 'en' => 'English', 'de' => 'Deutsch' ]
        );
        $m->addElement(
            'text',
            'subject',
            get_string('subject', 'local_storefront'),
            [ 'size' => 70, 'style' => 'width:100%' ]
        );
        $m->setType('subject', PARAM_TEXT);
        $m->addRule('subject', null, 'required', null, 'client');
        $m->addElement('textarea', 'body', get_string('body', 'local_storefront'), ['rows' => 12, 'cols' => 80]);
        $m->setType('body', PARAM_RAW);
        $m->addRule('body', null, 'required', null, 'client');
        $m->addElement('static', 'placeholders', '', get_string('mailplaceholders', 'local_storefront'));
        $m->addElement('text', 'delayminutes', get_string('delayminutes', 'local_storefront'));
        $m->setType('delayminutes', PARAM_INT);
        $m->addHelpButton('delayminutes', 'delayminutes', 'local_storefront');
        $m->addElement('textarea', 'adminemails', get_string('adminemails', 'local_storefront'));
        $m->setType('adminemails', PARAM_RAW_TRIMMED);
        $m->addHelpButton('adminemails', 'adminemails', 'local_storefront');
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
        $errors = parent::validation($data, $files);
        if ($data['delayminutes'] < 0 || $data['delayminutes'] > 1440) {
            $errors['delayminutes'] = get_string('invalidparameter');
        }
        foreach (preg_split('/[\s,;]+/', trim($data['adminemails']), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (!validate_email($email)) {
                $errors['adminemails'] = get_string('invalidemail');
            }
        }
        if (\core_text::strlen($data['subject']) > 255) {
            $errors['subject'] = get_string('maximumchars', '', 255);
        }
        return $errors;
    }
}
