<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Native course mapping entry point for Storefront.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_storefront\form\course_mapping_form;
use local_storefront\local\admin_navigation;
use local_storefront\local\course_delivery;
use local_storefront\output\ui;

$productid = required_param('productid', PARAM_INT);
require_login();
$systemcontext = context_system::instance();
require_capability('local/storefront:managecatalogue', $systemcontext);
admin_externalpage_setup('local_storefront');
$product = $DB->get_record('local_storefront_product', ['id' => $productid], '*', MUST_EXIST);
if (!in_array((string) $product->producttype, ['course'], true)) {
    throw new moodle_exception('deliveryconfigurationunavailable', 'local_storefront');
}
$url = new moodle_url('/local/storefront/course_mapping.php', ['productid' => $productid]);
$returnurl = new moodle_url('/local/storefront/products.php');
$producturl = new moodle_url('/local/storefront/product_edit.php', ['id' => $productid]);
$terminal = get_string('managecoursemappingfor', 'local_storefront', format_string($product->name));
$PAGE->set_context($systemcontext);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($terminal);
$PAGE->set_heading(get_string('pluginname', 'local_storefront'));
admin_navigation::add_breadcrumb_path([
    [get_string('manageproducts', 'local_storefront'), $returnurl],
    [format_string($product->name), $producturl],
    [$terminal, null],
]);

$enrolplugin = enrol_get_plugin('storefront');
if (!$enrolplugin) {
    echo $OUTPUT->header();
    echo admin_navigation::render('products');
    echo html_writer::start_div('local-storefront-content-narrow');
    echo ui::page_header($terminal);
    echo $OUTPUT->notification(get_string('coursepluginmissing', 'local_storefront'), 'warning');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}
$mappings = course_delivery::mappings($productid);
$lockedcourseids = [];
foreach ($mappings as $mapping) {
    if ($DB->record_exists('enrol_storefront_purchase', ['enrolid' => $mapping->enrolid])) {
        $lockedcourseids[] = (int) $mapping->courseid;
    }
}
$courses = [];
foreach (
    $DB->get_records_select(
        'course',
        'id <> :siteid',
        ['siteid' => SITEID],
        'fullname ASC',
        'id,fullname,shortname,visible'
    ) as $course
) {
    $label = format_string($course->fullname) . ' · ' . format_string($course->shortname);
    if (empty($course->visible)) {
        $label .= ' (' . get_string('hidden') . ')';
    }
    $courses[$course->id] = $label;
}
$roles = get_default_enrol_roles($systemcontext);
foreach ($mappings as $mapping) {
    $existingroles = get_default_enrol_roles($systemcontext, (int)$mapping->roleid);
    if (isset($existingroles[$mapping->roleid])) {
        $roles[$mapping->roleid] = $existingroles[$mapping->roleid];
    }
}
$studentroles = get_archetype_roles('student');
$studentrole = $studentroles ? (int) reset($studentroles)->id : 0;
$firstmapping = $mappings ? reset($mappings) : null;
$data = (object) [
    'productid' => $productid,
    'courseids' => array_values(array_map(static fn(\stdClass $mapping): int => (int) $mapping->courseid, $mappings)),
    'status' => $firstmapping ? (int) $firstmapping->enrolstatus : ENROL_INSTANCE_ENABLED,
    'roleid' => $firstmapping ? (int) $firstmapping->roleid : $studentrole,
    'enrolperiod' => $firstmapping ? (int) $firstmapping->enrolperiod : 0,
];
$form = new course_mapping_form($url, [
    'courses' => $courses,
    'roles' => $roles,
    'lockedcourseids' => $lockedcourseids,
]);
$form->set_data($data);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($submitted = $form->get_data()) {
    if ((int)$submitted->productid !== $productid) {
        throw new moodle_exception('invalidparameter');
    }
    // Existing mappings that are removed require course authority as well as selected mappings.
    foreach ($mappings as $mapping) {
        $coursecontext = context_course::instance($mapping->courseid);
        require_capability('moodle/course:enrolconfig', $coursecontext);
        require_capability('enrol/storefront:config', $coursecontext);
    }
    $selectedcourseids = array_values(array_unique(array_filter(array_map(
        'intval',
        (array) ($submitted->courseids ?? [])
    ))));
    $transaction = $DB->start_delegated_transaction();
    foreach ($mappings as $mapping) {
        $instance = $DB->get_record('enrol', ['id' => $mapping->enrolid], '*', MUST_EXIST);
        if (!in_array((int) $mapping->courseid, $selectedcourseids, true)) {
            if ($DB->record_exists('enrol_storefront_purchase', ['enrolid' => $mapping->enrolid])) {
                throw new moodle_exception('coursemappinglocked', 'local_storefront');
            }
            $enrolplugin->delete_instance($instance);
            continue;
        }
        $update = clone $submitted;
        $update->customint1 = $productid;
        $enrolplugin->update_instance($instance, $update);
    }
    $existingcourseids = array_map(static fn(\stdClass $mapping): int => (int) $mapping->courseid, $mappings);
    foreach (array_diff($selectedcourseids, $existingcourseids) as $courseid) {
        $targetcourse = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $enrolplugin->add_instance($targetcourse, [
            'status' => (int) $submitted->status,
            'customint1' => $productid,
            'roleid' => (int) $submitted->roleid,
            'enrolperiod' => (int) $submitted->enrolperiod,
        ]);
    }
    $transaction->allow_commit();
    redirect(
        new moodle_url('/local/storefront/offers.php', ['productid' => $productid]),
        get_string('coursemappingsaved', 'local_storefront'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo admin_navigation::render('products');
echo html_writer::start_div('local-storefront-content-narrow');
echo ui::page_header($terminal, [
    $firstmapping ? ui::icon_action(
        new moodle_url('/enrol/instances.php', ['id' => $firstmapping->courseid]),
        get_string('moodleenrolmentmethods', 'local_storefront'),
        'fa-user-graduate'
    ) : '',
]);
echo html_writer::div(
    ui::status_badge(get_string(
        enrol_is_enabled('storefront') ? 'coursepluginenabled' : 'courseplugindisabled',
        'local_storefront'
    ), enrol_is_enabled('storefront') ? 'success' : 'warning'),
    'local-storefront-badge-list mb-3'
);
if (!enrol_is_enabled('storefront')) {
    echo $OUTPUT->notification(get_string('coursepluginenablehelp', 'local_storefront'), 'warning');
}
$form->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
