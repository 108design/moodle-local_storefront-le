<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Storefront-side convenience form for the Moodle enrolment instance.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.

namespace local_storefront\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Storefront-side convenience form for the Moodle enrolment instance.
 */
final class course_mapping_form extends \moodleform {
    /**
     * Define the native form fields and their defaults.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'productid');
        $mform->setType('productid', PARAM_INT);
        $mform->addElement(
            'autocomplete',
            'courseids',
            get_string('moodlecourses', 'local_storefront'),
            $this->_customdata['courses'],
            ['multiple' => true]
        );
        $mform->setType('courseids', PARAM_INT);
        $mform->addHelpButton('courseids', 'moodlecourses', 'local_storefront');
        if (!empty($this->_customdata['lockedcourseids'])) {
            $mform->addElement(
                'static',
                'mappinglockednote',
                '',
                get_string('coursemappinglocked', 'local_storefront')
            );
        }
        $mform->addElement('select', 'status', get_string('coursepluginenabled', 'local_storefront'), [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ]);
        $mform->addElement('select', 'roleid', get_string('role'), $this->_customdata['roles']);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol'), [
            'optional' => true, 'defaultunit' => DAYSECS,
        ]);
        $mform->addHelpButton('enrolperiod', 'courseduration', 'local_storefront');
        $mform->addElement('static', 'mappingnote', '', get_string('coursemappingnote', 'local_storefront'));
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
        $productid = (int) ($data['productid'] ?? 0);
        $courseids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['courseids'] ?? [])))));
        $locked = array_map('intval', (array) ($this->_customdata['lockedcourseids'] ?? []));
        if (array_diff($locked, $courseids)) {
            $errors['courseids'] = get_string('coursemappinglocked', 'local_storefront');
        }
        foreach ($courseids as $courseid) {
            if (
                !$DB->record_exists_select('course', 'id = :id AND id <> :siteid', [
                    'id' => $courseid, 'siteid' => SITEID,
                ])
            ) {
                $errors['courseids'] = get_string('coursemissing', 'local_storefront');
                break;
            }
            $context = \context_course::instance($courseid);
            if (
                !has_capability('moodle/course:enrolconfig', $context)
                    || !has_capability('enrol/storefront:config', $context)
            ) {
                $errors['courseids'] = get_string('coursemappingnopermission', 'local_storefront');
                break;
            }
            // Only a real saved mapping may retain a legacy role outside the native course-role list.
            $existingrole = (int)$DB->get_field('enrol', 'roleid', [
                'enrol' => 'storefront', 'courseid' => $courseid, 'customint1' => $productid,
            ]);
            if (!array_key_exists((int) ($data['roleid'] ?? 0), get_default_enrol_roles($context, $existingrole))) {
                $errors['roleid'] = get_string('courseroleinvalid', 'local_storefront');
                break;
            }
        }
        $product = $DB->get_record('local_storefront_product', ['id' => $productid], 'id,producttype');
        if ($product && (string) $product->producttype === 'course' && !$courseids) {
            $errors['courseids'] = get_string('coursebundlerequired', 'local_storefront');
        }
        return $errors;
    }
}
