<?php
/**
 * Regression tests for course shop.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront;

defined('MOODLE_INTERNAL') || die();
use local_storefront\local\{catalogue,
         course_delivery,
         legal_service,
         order_service,
         product_selection,
         selection_resume};
use local_storefront\payment\service_provider;

/**
 * Native state coverage for the complete course purchase boundary.
 */
final class course_shop_test extends \advanced_testcase {
    /**
     * Create the synthetic product, offer and course mappings.
     *
     * @param int $count Count.
     * @return array
     */
    private function shop(int $count = 1): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enrol_plugins_enabled', 'manual,storefront');
        set_config('paygw_plugins_sortorder', 'paypal');
        $account = \core_payment\helper::save_payment_account(
            (object)[
            'name' => 'Course test',
            'idnumber' => 'course-test',
            'enabled' => 1,
            'contextid' => \context_system::instance()->id,
            ]
        );
        $accountid = (int)$account->get('id');
        $DB->insert_record(
            'payment_gateways',
            (object)[
            'accountid' => $accountid,
            'gateway' => 'paypal',
            'enabled' => 1,
            'config' => '{}',
            'timecreated' => time(),
            'timemodified' => time(),
            ]
        );
        set_config('moodlepaymentaccountid', $accountid, 'local_storefront');
        set_config('storevisibility', 'public', 'local_storefront');
        $productid = (int)$DB->insert_record(
            'local_storefront_product',
            (object)[
            'identifier' => 'course-test',
            'name' => 'Course test',

            'producttype' => 'course', 'visible' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]
        );
        $offerid = (int)$DB->insert_record(
            'local_storefront_offer',
            (object)[
            'productid' => $productid,
            'identifier' => 'single',

            'displayname' => 'Course purchase',
            'amount' => '12.50',
            'currency' => 'EUR',
            'stripesourcemode' => 'moodle',

            'entitlementsjson' => '[]', 'timecreated' => time(), 'timemodified' => time(),
            ]
        );
        $user = $this->getDataGenerator()->create_user();
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $courses = [];
        $enrolids = [];
        for ($i = 0; $i < $count; $i++) {
            $course = $this->getDataGenerator()->create_course(['visible' => 1]);
            $courses[] = $course;
            $enrolids[] = (int)enrol_get_plugin('storefront')->add_instance($course, [
                'customint1' => $productid,
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $roleid,
            'enrolperiod' => WEEKSECS]);
        }
        $this->setUser($user);
        return compact('user', 'productid', 'offerid', 'accountid', 'courses', 'enrolids');
    }
    /**
     * Create a synthetic pending order for the test buyer.
     *
     * @param array $shop Shop.
     * @return \stdClass
     */
    private function order(array $shop): \stdClass {
        $item = catalogue::offer($shop['offerid']);
        return order_service::create(
            [$item],
            $shop['user']->email,
            (int)$shop['user']->id,
            [],
            'moodle',
            $shop['accountid'],
            [],
            product_selection::fingerprint([$item])
        )['order'];
    }
    /**
     * Store a synthetic Core payment record for the test order.
     *
     * @param \stdClass $order Order.
     * @param int|null $userid User ID.
     * @param float $amount Amount in the selected currency.
     * @param string $currency Currency code.
     * @return int
     */
    private function payment(
        \stdClass $order,
        ?int $userid = null,
        float $amount = 12.50,
        string $currency = 'EUR'
    ): int {
        return \core_payment\helper::save_payment(
            (int)$order->provideraccountid,
            'local_storefront',
            'courseorder',
            (int)$order->id,
            $userid ?? (int)$order->userid,
            $amount,
            $currency,
            'paypal'
        );
    }
    /**
     * Verify that package snapshot replay and multiple purchase access.
     *
     * @return void
     */
    public function test_package_snapshot_replay_and_multiple_purchase_access(): void {
        global $DB;
        $shop = $this->shop(2);
        $order = $this->order($shop);
        $line = $DB->get_record('local_storefront_orderline', ['orderid' => $order->id], '*', MUST_EXIST);
        // A later mapping edit must not change the purchased duration.
        foreach ($shop['enrolids'] as $id) {
            $DB->set_field('enrol', 'enrolperiod', DAYSECS, ['id' => $id]);
        }
        $paymentid = $this->payment($order);
        $this->assertTrue(
            service_provider::deliver_order(
                'courseorder',
                (int)$order->id,
                $paymentid,
                (int)$shop['user']->id
            )
        );
        $this->assertTrue(
            service_provider::deliver_order(
                'courseorder',
                (int)$order->id,
                $paymentid,
                (int)$shop['user']->id
            )
        );
        $purchases = $DB->get_records('enrol_storefront_purchase', ['orderlineid' => $line->id]);
        $this->assertCount(2, $purchases);
        foreach ($purchases as $purchase) {
            $this->assertEquals(WEEKSECS, (int)$purchase->timeend - (int)$purchase->timestart);
        }
        $this->assertSame(
            $line->offeringjson,
            $DB->get_field('local_storefront_orderline', 'offeringjson', [ 'id' => $line->id ])
        );
        $this->assertEquals(1, $DB->count_records('local_storefront_comqueue', ['orderid' => $order->id]));
        $next = $this->order($shop);
        $nextpayment = $this->payment($next);
        service_provider::deliver_order('courseorder', (int)$next->id, $nextpayment, (int)$shop['user']->id);
        $nextline = $DB->get_record('local_storefront_orderline', ['orderid' => $next->id], '*', MUST_EXIST);
        \enrol_storefront\local\fulfilment_service::suspend_line((int)$line->id);
        foreach ($shop['enrolids'] as $id) {
            $ue = $DB->get_record(
                'user_enrolments',
                [ 'enrolid' => $id, 'userid' => $shop['user']->id ],
                '*',
                MUST_EXIST
            );
            $this->assertEquals(ENROL_USER_ACTIVE, $ue->status);
            $this->assertLessThanOrEqual(DAYSECS + 2, (int)$ue->timeend - time());
        }
        $this->assertEquals(2, $DB->count_records('enrol_storefront_purchase', ['orderlineid' => $nextline->id]));
    }
    /**
     * Verify that failed delivery is retryable with original payment.
     *
     * @return void
     */
    public function test_failed_delivery_is_retryable_with_original_payment(): void {
        global $DB;
        $shop = $this->shop();
        $order = $this->order($shop);
        $id = $shop['enrolids'][0];
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $id]);
        $paymentid = $this->payment($order);
        // The durable failure is expected; native debugging is asserted, not suppressed.
        service_provider::deliver_order('courseorder', (int)$order->id, $paymentid, (int)$shop['user']->id);
        $this->assertDebuggingCalled();
        $this->assertEquals('partial', $DB->get_field('local_storefront_order', 'status', ['id' => $order->id]));
        $this->assertEquals(0, $DB->count_records('enrol_storefront_purchase'));
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $id]);
        order_service::retry((int)$order->id);
        $this->assertEquals('completed', $DB->get_field('local_storefront_order', 'status', ['id' => $order->id]));
        $this->assertEquals(1, $DB->count_records('enrol_storefront_purchase'));
        $this->assertEquals(1, $DB->count_records('payments'));
    }
    /**
     * Verify that redirect or missing payment never delivers.
     *
     * @return void
     */
    public function test_redirect_or_missing_payment_never_delivers(): void {
        global $DB;
        $shop = $this->shop();
        $order = $this->order($shop);
        service_provider::get_success_url('courseorder', (int)$order->id);
        $this->assertEquals(0, $DB->count_records('enrol_storefront_purchase'));
        try {
            order_service::retry((int)$order->id);
            $this->fail('Unpaid retry accepted');
        } catch (\moodle_exception $e) {
            $this->assertEquals('moodlepaymentinvalid', $e->errorcode);
        }
        $this->expectException(\dml_missing_record_exception::class);
        service_provider::deliver_order('courseorder', (int)$order->id, 99999, (int)$shop['user']->id);
    }
    /**
     * Verify that foreign user cannot obtain payable or receive delivery.
     *
     * @return void
     */
    public function test_foreign_user_cannot_obtain_payable_or_receive_delivery(): void {
        $shop = $this->shop();
        $order = $this->order($shop);
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        try {
            service_provider::get_payable('courseorder', (int)$order->id);
            $this->fail('Foreign payable accepted');
        } catch (\moodle_exception $e) {
            $this->assertEquals('moodlepaymentinvalid', $e->errorcode);
        }
        $this->expectException(\dml_missing_record_exception::class);
        service_provider::deliver_order(
            'courseorder',
            (int)$order->id,
            $this->payment($order, (int)$other->id),
            (int)$other->id
        );
    }
    /**
     * Verify that wrong amount does not mark paid.
     *
     * @return void
     */
    public function test_wrong_amount_does_not_mark_paid(): void {
        global $DB;
        $shop = $this->shop();
        $order = $this->order($shop);
        try {
            service_provider::deliver_order(
                'courseorder',
                (int)$order->id,
                $this->payment($order, null, 0.01),
                (int)$shop['user']->id
            );
            $this->fail();
        } catch (\moodle_exception $e) {
            $this->assertEquals('moodlepaymentinvalid', $e->errorcode);
        }
        $this->assertEquals('pending', $DB->get_field('local_storefront_order', 'status', ['id' => $order->id]));
    }
    /**
     * Verify that gateway surcharge and later replay.
     *
     * @return void
     */
    public function test_gateway_surcharge_and_later_replay(): void {
        global $DB;
        $shop = $this->shop();
        $order = $this->order($shop);
        set_config('surcharge', 10, 'paygw_paypal');
        $paymentid = $this->payment($order, null, 13.75);
        service_provider::deliver_order('courseorder', (int)$order->id, $paymentid, (int)$shop['user']->id);
        set_config('surcharge', 20, 'paygw_paypal');
        service_provider::deliver_order('courseorder', (int)$order->id, $paymentid, (int)$shop['user']->id);
        $this->assertEquals(1, $DB->count_records('enrol_storefront_purchase'));
    }
    /**
     * Verify that disabled companion blocks new purchase.
     *
     * @return void
     */
    public function test_disabled_companion_blocks_new_purchase(): void {
        $shop = $this->shop();
        set_config('enrol_plugins_enabled', 'manual');
        $this->expectException(\moodle_exception::class);
        catalogue::offer($shop['offerid']);
    }
    /**
     * Verify that changed duration requires checkout review.
     *
     * @return void
     */
    public function test_changed_duration_requires_checkout_review(): void {
        global $DB;
        $shop = $this->shop();
        $item = catalogue::offer($shop['offerid']);
        $hash = product_selection::fingerprint([$item]);
        $DB->set_field('enrol', 'enrolperiod', 2 * WEEKSECS, ['id' => $shop['enrolids'][0]]);
        $this->expectException(\moodle_exception::class);
        order_service::create(
            [ $item ],
            $shop['user']->email,
            (int)$shop['user']->id,
            [ ],
            'moodle',
            $shop['accountid'],
            [ ],
            $hash
        );
    }
    /**
     * Verify that selection resumes once in another session without order.
     *
     * @return void
     */
    public function test_selection_resumes_once_in_another_session_without_order(): void {
        global $SESSION, $DB;
        $shop = $this->shop();
        $this->setUser(null);
        product_selection::add($shop['offerid']);
        $token = selection_resume::create('/local/storefront/cart.php');
        $SESSION->local_storefront_selection = [];
        unset($SESSION->local_storefront_resumehash);
        $this->setUser($shop['user']);
        $url = selection_resume::consume($token);
        $this->assertStringContainsString('/local/storefront/cart.php', $url);
        $this->assertEquals([$shop['offerid']], product_selection::snapshot());
        $this->assertEquals(0, $DB->count_records('local_storefront_order'));
        $this->assertEquals(0, $DB->count_records('payments'));
        $this->assertEquals(0, $DB->count_records('enrol_storefront_purchase'));
        $this->expectException(\moodle_exception::class);
        selection_resume::consume($token);
    }
    /**
     * Verify that selection cannot resume to checkout or external action.
     *
     * @return void
     */
    public function test_selection_cannot_resume_to_checkout_or_external_action(): void {
        $this->assertStringContainsString(
            '/local/storefront/selection.php',
            selection_resume::local_return('/local/storefront/checkout.php')
        );
        $this->assertStringNotContainsString(
            'action=',
            selection_resume::local_return('/local/storefront/cart.php?action=delete&sesskey=x')
        );
    }
    /**
     * Build the document used by the test.
     *
     * @param string $identifier Stable identifier.
     * @param string $lang Lang.
     * @param int $id Record ID.
     * @return int
     */
    private function document(string $identifier, string $lang = 'en', int $id = 0): int {
        $data = legal_service::edit_data($id, $lang);
        $data->identifier = $identifier;
        $data->title = $identifier . ' ' . $lang;
        $data->documentlanguage = $lang;
        $data->originlang = $lang;
        $data->contentformat = FORMAT_HTML;
        $data->content_editor = ['text' => '<p>Saved legal terms</p>', 'format' => FORMAT_HTML];
        $data->published = 1;
        $data->visible = 1;
        $data->showincheckout = 1;
        $data->requireconfirmation = 1;
        return legal_service::save($data, (int)get_admin()->id);
    }
    /**
     * Verify that two document limit excludes languages and revisions and retains evidence.
     *
     * @return void
     */
    public function test_two_document_limit_excludes_languages_and_revisions_and_retains_evidence(): void {
        global $DB;
        $shop = $this->shop();
        $first = $this->document('terms');
        $second = $this->document('privacy');
        $documents = legal_service::checkout_documents();
        $tokens = array_column($documents, 'token');
        $order = order_service::create(
            [catalogue::offer($shop['offerid'])],
            $shop['user']->email,
            (int)$shop['user']->id,
            $tokens,
            'moodle',
            $shop['accountid'],
            $tokens
        )['order'];
        $before = $DB->get_records('local_storefront_orderlegal', ['orderid' => $order->id]);
        $this->document('terms', 'de', $first);
        $this->document('terms', 'en', $first);
        $this->assertEquals(2, $DB->count_records('local_storefront_legaldoc'));
        $this->assertCount(2, legal_service::translations($first));
        $this->assertEquals($before, $DB->get_records('local_storefront_orderlegal', ['orderid' => $order->id]));
        $DB->set_field('local_storefront_legaldoc', 'visible', 0, ['id' => $first]);
        $evidence = $DB->get_record(
            'local_storefront_orderlegal',
            [ 'orderid' => $order->id, 'sourceid' => $first ],
            '*',
            MUST_EXIST
        );
        $this->assertNotNull(legal_service::public_document('terms', $first, (int)$evidence->revisionid));
        $this->expectException(\moodle_exception::class);
        $this->document('third');
    }
    /**
     * Verify that missing legal acceptance rolls back the order.
     *
     * @return void
     */
    public function test_missing_legal_acceptance_rolls_back_the_order(): void {
        global $DB;
        // PostgreSQL PHPUnit normally wraps the entire test in a delegated transaction.
        // Close that wrapper before the fixture so the real request-level rollback is observable.
        $this->preventResetByRollback();
        $shop = $this->shop();
        $this->document('terms');
        $this->assertFalse($DB->is_transaction_started());
        $this->assertEquals(0, $DB->count_records('local_storefront_order'));
        try {
            $this->order($shop);
            $this->fail();
        } catch (\moodle_exception $e) {
            $this->assertContains($e->errorcode, ['legalconfirmationmissing', 'legalcheckoutchanged']);
        }
        $this->assertEquals(0, $DB->count_records('local_storefront_order'));
        $this->assertFalse($DB->is_transaction_started());
        foreach (
            [
            'local_storefront_orderline',
            'local_storefront_line_state',
            'local_storefront_orderlegal',
            ] as $table
        ) {
            $this->assertEquals(0, $DB->count_records($table));
        }
    }
}
