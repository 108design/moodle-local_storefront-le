<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();

/** Immutable course orders, verified Moodle payments, and retryable position delivery. */
final class order_service {
    public static function course_links(int $orderid): array {
        global $DB;
        $links=[];
        foreach ($DB->get_records('enrol_storefront_purchase',['orderid'=>$orderid,'status'=>'active']) as $purchase) {
            if ($purchase->timeend && $purchase->timeend<=time()) { continue; }
            $course=$DB->get_record('course',['id'=>$purchase->courseid,'visible'=>1]);
            if ($course) { $links[$course->id]=['id'=>(int)$course->id,'name'=>$course->fullname]; }
        }
        return array_values($links);
    }
    public static function create(array $items, string $email, ?int $userid, array $accepted,
            string $provider = 'moodle', ?int $accountid = null, ?array $presented = null,
            ?string $expectedselection = null): array {
        global $DB;
        $user = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) : false;
        if (!$user || isguestuser($user) || !$items || $provider !== 'moodle'
                || $accountid !== moodle_payment_config::account_id() || !moodle_payment_config::configured()) {
            throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
        }
        $resolved = []; $snapshots = [];
        foreach ($items as $item) {
            $offer = catalogue::offer((int)$item->id);
            if ((string)$offer->amount !== (string)$item->amount || $offer->currency !== $item->currency) {
                throw new \moodle_exception('selectionchanged', 'local_storefront');
            }
            if (isset($resolved[$offer->productid])) { throw new \moodle_exception('invalidoffer', 'local_storefront'); }
            $resolved[$offer->productid] = $offer;
            $snapshots[$offer->id] = course_delivery::snapshots((int)$offer->productid);
            if (!$snapshots[$offer->id]) { throw new \moodle_exception('coursefulfilmentunavailable', 'local_storefront'); }
        }
        $currencies = array_unique(array_column($resolved, 'currency'));
        if ($expectedselection !== null && !hash_equals($expectedselection,product_selection::fingerprint($resolved,$snapshots))) {
            throw new \moodle_exception('selectionchanged','local_storefront');
        }
        if (count($currencies) !== 1) { throw new \moodle_exception('checkoutmixedcurrency', 'local_storefront'); }
        $total = array_sum(array_map(static fn($i) => (float)$i->amount, $resolved));
        $currency = reset($currencies);
        if (!moodle_payment_config::supports_currency($currency)) { throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront'); }
        $hex = bin2hex(random_bytes(16));
        $key = substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
        $tx = $DB->start_delegated_transaction();
        try {
        $order = (object)['orderkey' => $key, 'userid' => $userid, 'email' => $user->email,
            'customerlang' => current_language(), 'status' => 'pending', 'channel' => 'moodle',
            'currency' => $currency, 'total' => $total, 'provideraccountid' => $accountid,
            'timecreated' => time(), 'timemodified' => time()];
        $order->id = (int)$DB->insert_record('local_storefront_order', $order);
        foreach ($resolved as $offer) {
            $snapshot = ['productid' => (int)$offer->productid, 'productidentifier' => $offer->productidentifier,
                'producttype' => 'course', 'identifier' => $offer->identifier, 'billingtype' => 'oneoff',
                'billinginterval' => null, 'billingintervalcount' => 1, 'currency' => $offer->currency,
                'unitamount' => (float)$offer->amount, 'quantity' => 1, 'paymentsource' => 'moodle',
                'courses' => $snapshots[$offer->id], 'downloads' => [], 'entitlements' => [], 'entitlementrecipe' => []];
            $json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $lineid = $DB->insert_record('local_storefront_orderline', (object)['orderid' => $order->id,
                'offerid' => $offer->id, 'productname' => $offer->productname, 'offeringjson' => $json,
                'snapshotversion' => 3, 'snapshothash' => hash('sha256', $json), 'amount' => $offer->amount]);
            $DB->insert_record('local_storefront_line_state', (object)['orderlineid' => $lineid,
                'timecreated' => time(), 'timemodified' => time()]);
        }
        legal_service::record_order_documents($order->id, $accepted, $presented);
        $tx->allow_commit();
        return ['order' => $order];
        } catch (\Throwable $error) {
            $tx->rollback($error);
            throw $error;
        }
    }

    /** Core gateway has verified the transaction; bind its persisted payment to this exact order. */
    public static function complete_moodle_payment(int $orderid, int $paymentid, int $userid): bool {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock('payment-order:'.$orderid, 10);
        if (!$lock) { throw new \moodle_exception('locktimeout'); }
        try {
            $order = $DB->get_record('local_storefront_order', ['id' => $orderid, 'userid' => $userid,
                'channel' => 'moodle'], '*', MUST_EXIST);
            $payment = $DB->get_record('payments', ['id' => $paymentid, 'component' => 'local_storefront',
                'paymentarea' => 'courseorder', 'itemid' => $orderid, 'userid' => $userid,
                'accountid' => $order->provideraccountid], '*', MUST_EXIST);
            // Core may include a gateway surcharge in payments.amount.
            $fee = \core_payment\helper::get_gateway_surcharge($payment->gateway);
            $expected = \core_payment\helper::get_rounded_cost((float)$order->total, $order->currency, $fee);
            if ($payment->currency !== $order->currency || (empty($order->providerpaymentid) && abs((float)$payment->amount - $expected) > 0.000001)
                    || (!empty($order->providerpaymentid) && $order->providerpaymentid !== 'moodle:'.$paymentid)) {
                throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
            }
            if (!in_array($order->status, ['pending','paid','partial','completed'], true)) {
                throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
            }
            if (empty($order->providerpaymentid)) {
                $tx = $DB->start_delegated_transaction();
                $DB->update_record('local_storefront_order', (object)['id' => $orderid, 'status' => 'paid',
                    'providerpaymentid' => 'moodle:'.$paymentid, 'timecompleted' => time(), 'timemodified' => time()]);
                foreach ($DB->get_records('local_storefront_orderline', ['orderid' => $orderid]) as $line) {
                    $state = $DB->get_record('local_storefront_line_state', ['orderlineid' => $line->id], '*', MUST_EXIST);
                    $DB->update_record('local_storefront_line_state', (object)['id' => $state->id,
                        'paymentstatus' => 'paid', 'lastproviderstate' => 'moodle_payment_complete',
                        'timelastproviderevent' => time(), 'timemodified' => time()]);
                }
                $tx->allow_commit();
            }
        } finally { $lock->release(); }
        self::retry($orderid);
        return true;
    }

    /** Retry delivery only against an already recorded, revalidated payment. */
    public static function retry(int $orderid): void {
        global $DB;
        $order = $DB->get_record('local_storefront_order', ['id' => $orderid, 'channel' => 'moodle'], '*', MUST_EXIST);
        if (empty($order->providerpaymentid) || !in_array($order->status, ['paid','partial','completed'], true)) {
            throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront');
        }
        $user = $DB->get_record('user', ['id' => $order->userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1], '*', MUST_EXIST);
        $failed = false;
        foreach ($DB->get_records('local_storefront_orderline', ['orderid' => $orderid], 'id') as $line) {
            position_lock::with_lines([(int)$line->id], static function() use ($DB, $order, $user, $line, &$failed) {
                $state = $DB->get_record('local_storefront_line_state', ['orderlineid' => $line->id], '*', MUST_EXIST);
                if ($state->fulfilmentstatus === 'fulfilled') { return; }
                if ($state->paymentstatus !== 'paid') { throw new \moodle_exception('moodlepaymentinvalid', 'local_storefront'); }
                try {
                    if (!hash_equals($line->snapshothash, hash('sha256', $line->offeringjson))) {
                        throw new \coding_exception('Order snapshot integrity failure');
                    }
                    $snapshot = json_decode($line->offeringjson, true, 512, JSON_THROW_ON_ERROR);
                    if (($snapshot['producttype'] ?? '') !== 'course' || ($snapshot['billingtype'] ?? '') !== 'oneoff'
                            || empty($snapshot['courses'])) { throw new \coding_exception('Invalid course order snapshot'); }
                    foreach ($snapshot['courses'] as $course) {
                        course_delivery::fulfil((int)$order->id, (int)$line->id, (int)$user->id, $course, 'oneoff', null, null);
                    }
                    $DB->update_record('local_storefront_line_state', (object)['id' => $state->id,
                        'fulfilmentstatus' => 'fulfilled', 'accessstatus' => 'active', 'accessreason' => 'payment_verified',
                        'reviewreason' => null, 'timemodified' => time()]);
                } catch (\Throwable $error) {
                    $failed = true;
                    // Detailed exception stays in server logs, not customer-visible records.
                    debugging($error->getMessage(), DEBUG_DEVELOPER);
                    $DB->update_record('local_storefront_line_state', (object)['id' => $state->id,
                        'fulfilmentstatus' => 'failed', 'reviewreason' => 'course_delivery_failed', 'timemodified' => time()]);
                }
            });
        }
        $unfinished=$DB->record_exists_sql("SELECT s.id FROM {local_storefront_line_state} s
            JOIN {local_storefront_orderline} l ON l.id=s.orderlineid
            WHERE l.orderid=:orderid AND s.fulfilmentstatus<>:fulfilled",['orderid'=>$orderid,'fulfilled'=>'fulfilled']);
        $DB->update_record('local_storefront_order', (object)['id' => $orderid,
            'status' => $unfinished ? 'partial' : 'completed', 'timemodified' => time()]);
        if (!$unfinished) { communication_service::enqueue_completed($orderid); }
    }
}
