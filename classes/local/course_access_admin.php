<?php
/**
 * Operator actions for purchased course access.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/** Manage one purchased course access from the order administration page. */
final class course_access_admin {
    /** Check whether the installed enrolment plugin supports manual holds. */
    public static function available(): bool {
        return course_delivery::available()
            && (int) get_config('enrol_storefront', 'version') >= 2026110701
            && method_exists(\enrol_storefront\local\fulfilment_service::class, 'set_manual_suspension');
    }

    /** Apply the submitted action to the purchase belonging to the selected order and buyer. */
    public static function apply(int $purchaseid, \moodle_url $returnurl): void {
        global $DB;
        require_capability('local/storefront:manageorders', \context_system::instance());
        require_sesskey();
        $action = required_param('courseaction', PARAM_ALPHA);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !in_array($action, ['suspend', 'resume'], true)) {
            throw new \invalid_parameter_exception('Invalid course access action.');
        }
        if (!self::available()) {
            throw new \moodle_exception('courseaccessunavailable', 'local_storefront');
        }
        $order = $DB->get_record('local_storefront_order', ['id' => required_param('orderid', PARAM_INT)],
            'id, userid', MUST_EXIST);
        $suspend = $action === 'suspend';
        \enrol_storefront\local\fulfilment_service::set_manual_suspension($purchaseid, (int) $order->id,
            (int) $order->userid, $suspend);
        redirect($returnurl, get_string($suspend ? 'manualcoursesuspended' : 'manualcourseresumed', 'local_storefront'));
    }

    /** Render the action beside the purchased course it affects. */
    public static function button(\stdClass $purchase): string {
        global $OUTPUT, $PAGE;
        $suspended = !empty($purchase->manualsuspended);
        $url = new \moodle_url($PAGE->url, [
            'coursepurchase' => $purchase->id,
            'orderid' => $purchase->orderid,
            'courseaction' => $suspended ? 'resume' : 'suspend',
        ]);
        return $OUTPUT->single_button($url,
            get_string($suspended ? 'manualcourseresume' : 'manualcoursesuspend', 'local_storefront'), 'post');
    }
}
