<?php
// This file is part of Moodle - https://moodle.org/
/**
 * User-owned purchase data; anonymised order and legal evidence retain their relationships.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\privacy;

defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{contextlist, approved_contextlist, userlist, approved_userlist, writer};

/**
 * User-owned purchase data; anonymised order and legal evidence retain their relationships.
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this component.
     *
     * @param collection $collection Privacy metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_storefront_order', [
            'id' => 'privacy:metadata:local_storefront_order:id',
            'orderkey' => 'privacy:metadata:local_storefront_order:orderkey',
            'userid' => 'privacy:metadata:local_storefront_order:userid',
            'email' => 'privacy:metadata:local_storefront_order:email',
            'customerlang' => 'privacy:metadata:local_storefront_order:customerlang',
            'status' => 'privacy:metadata:local_storefront_order:status',
            'channel' => 'privacy:metadata:local_storefront_order:channel',
            'paymentenvironment' => 'privacy:metadata:local_storefront_order:paymentenvironment',
            'currency' => 'privacy:metadata:local_storefront_order:currency',
            'total' => 'privacy:metadata:local_storefront_order:total',
            'providercheckoutid' => 'privacy:metadata:local_storefront_order:providercheckoutid',
            'providerpaymentid' => 'privacy:metadata:local_storefront_order:providerpaymentid',
            'providersubscriptionid' => 'privacy:metadata:local_storefront_order:providersubscriptionid',
            'provideraccountid' => 'privacy:metadata:local_storefront_order:provideraccountid',
            'accessstatus' => 'privacy:metadata:local_storefront_order:accessstatus',
            'accessreason' => 'privacy:metadata:local_storefront_order:accessreason',
            'accesssuspendat' => 'privacy:metadata:local_storefront_order:accesssuspendat',
            'claimtokenhash' => 'privacy:metadata:local_storefront_order:claimtokenhash',
            'recoverytokenhash' => 'privacy:metadata:local_storefront_order:recoverytokenhash',
            'activationemailsent' => 'privacy:metadata:local_storefront_order:activationemailsent',
            'timecreated' => 'privacy:metadata:local_storefront_order:timecreated',
            'timemodified' => 'privacy:metadata:local_storefront_order:timemodified',
            'timecompleted' => 'privacy:metadata:local_storefront_order:timecompleted',
        ], 'privacy:metadata:local_storefront_order');
        $collection->add_database_table('local_storefront_orderlegal', [
            'revisionid' => 'privacy:legal', 'timeaccepted' => 'privacy:legal'], 'privacy:legal');
        $collection->add_database_table(
            'local_storefront_comqueue',
            [
            'recipientemail' => 'privacy:email',
            'recipientuserid' => 'privacy:userid',
            'payload' => 'privacy:mail'],
            'privacy:mail'
        );
        $collection->add_database_table('local_storefront_line_state', [
            'id' => 'privacy:metadata:local_storefront_line_state:id',
            'orderlineid' => 'privacy:metadata:local_storefront_line_state:orderlineid',
            'paymentstatus' => 'privacy:metadata:local_storefront_line_state:paymentstatus',
            'fulfilmentstatus' => 'privacy:metadata:local_storefront_line_state:fulfilmentstatus',
            'accessstatus' => 'privacy:metadata:local_storefront_line_state:accessstatus',
            'accessreason' => 'privacy:metadata:local_storefront_line_state:accessreason',
            'accesssuspendat' => 'privacy:metadata:local_storefront_line_state:accesssuspendat',
            'currentperiodstart' => 'privacy:metadata:local_storefront_line_state:currentperiodstart',
            'currentperiodend' => 'privacy:metadata:local_storefront_line_state:currentperiodend',
            'cancelatperiodend' => 'privacy:metadata:local_storefront_line_state:cancelatperiodend',
            'timecancelled' => 'privacy:metadata:local_storefront_line_state:timecancelled',
            'lastproviderstate' => 'privacy:metadata:local_storefront_line_state:lastproviderstate',
            'timelastproviderevent' => 'privacy:metadata:local_storefront_line_state:timelastproviderevent',
            'reviewreason' => 'privacy:metadata:local_storefront_line_state:reviewreason',
            'pendingeffects' => 'privacy:metadata:local_storefront_line_state:pendingeffects',
            'timecreated' => 'privacy:metadata:local_storefront_line_state:timecreated',
            'timemodified' => 'privacy:metadata:local_storefront_line_state:timemodified',
        ], 'privacy:metadata:local_storefront_line_state');
        $collection->add_subsystem_link('core_payment', [], 'privacy:payment');
        $collection->add_database_table(
            'local_storefront_legalrev',
            [ 'createdby' => 'privacy:userid' ],
            'privacy:legal'
        );
        $collection->add_database_table('local_storefront_comrule', ['modifiedby' => 'privacy:userid'], 'privacy:mail');
        $collection->add_database_table('local_storefront_orderline', [
            'id' => 'privacy:metadata:local_storefront_orderline:id',
            'orderid' => 'privacy:metadata:local_storefront_orderline:orderid',
            'offerid' => 'privacy:metadata:local_storefront_orderline:offerid',
            'productname' => 'privacy:metadata:local_storefront_orderline:productname',
            'offeringjson' => 'privacy:metadata:local_storefront_orderline:offeringjson',
            'snapshotversion' => 'privacy:metadata:local_storefront_orderline:snapshotversion',
            'snapshothash' => 'privacy:metadata:local_storefront_orderline:snapshothash',
            'amount' => 'privacy:metadata:local_storefront_orderline:amount',
        ], 'privacy:metadata:local_storefront_orderline');
        return $collection;
    }
    /**
     * Find contexts containing records attributed to the user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list = new contextlist();
        if (
            $DB->record_exists('local_storefront_order', ['userid' => $userid])
                || $DB->record_exists('local_storefront_comqueue', ['recipientuserid' => $userid])
                || $DB->record_exists('local_storefront_legalrev', ['createdby' => $userid])
                || $DB->record_exists('local_storefront_comrule', ['modifiedby' => $userid])
        ) {
            $list->add_system_context();
        }
        return $list;
    }
    /**
     * Add the users represented in the requested context to the privacy list.
     *
     * @param userlist $userlist Users represented in the requested context.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_storefront_order} WHERE userid IS NOT NULL', []);
        $userlist->add_from_sql(
            'recipientuserid',
            'SELECT recipientuserid FROM {local_storefront_comqueue} WHERE recipientuserid IS NOT NULL',
            [ ]
        );
        $userlist->add_from_sql(
            'createdby',
            'SELECT createdby FROM {local_storefront_legalrev} WHERE createdby IS NOT NULL',
            [ ]
        );
        $userlist->add_from_sql(
            'modifiedby',
            'SELECT modifiedby FROM {local_storefront_comrule} WHERE modifiedby IS NOT NULL',
            [ ]
        );
    }
    /**
     * Export the records and files allowed by the approved user context list.
     *
     * @param approved_contextlist $contextlist Approved user and context selection.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }
        $userid = (int)$contextlist->get_user()->id;
        $orders = [];
        foreach ($DB->get_records('local_storefront_order', ['userid' => $userid]) as $order) {
            unset($order->claimtokenhash, $order->recoverytokenhash);
            $order->positions = array_values($DB->get_records('local_storefront_orderline', ['orderid' => $order->id]));
            $order->line_states = array_values($DB->get_records_sql(
                'SELECT state.* FROM {local_storefront_line_state} state
                   JOIN {local_storefront_orderline} line ON line.id = state.orderlineid
                  WHERE line.orderid = :orderid ORDER BY state.id',
                ['orderid' => $order->id]
            ));
            $order->legal = array_values($DB->get_records('local_storefront_orderlegal', ['orderid' => $order->id]));
            $orders[] = $order;
        }
        writer::with_context(\context_system::instance())->export_data(
            [ get_string('pluginname', 'local_storefront') ],
            (object)[

            'orders' => $orders,
            'messages' => array_values(
                $DB->get_records('local_storefront_comqueue', [ 'recipientuserid' => $userid ])
            ),

            'legal_authorship' => array_values($DB->get_records('local_storefront_legalrev', ['createdby' => $userid])),
            'communication_authorship' => array_values(
                $DB->get_records('local_storefront_comrule', [ 'modifiedby' => $userid ])
            ),
            ]
        );
    }
    /**
     * Process erasure for all users in the requested context.
     *
     * @param \context $context Native Moodle context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach ($DB->get_fieldset_select('local_storefront_order', 'userid', 'userid IS NOT NULL') as $id) {
            self::anonymise((int)$id);
        }
        foreach (
            $DB->get_fieldset_select(
                'local_storefront_comqueue',
                'recipientuserid',
                'recipientuserid IS NOT NULL'
            ) as $id
        ) {
            self::anonymise((int)$id);
        }
        foreach ($DB->get_fieldset_select('local_storefront_legalrev', 'createdby', 'createdby > 0') as $id) {
            self::anonymise((int)$id);
        }
        foreach ($DB->get_fieldset_select('local_storefront_comrule', 'modifiedby', 'modifiedby IS NOT NULL') as $id) {
            self::anonymise((int)$id);
        }
    }
    /**
     * Process the erasure request for the approved user and contexts.
     *
     * @param approved_contextlist $contextlist Approved user and context selection.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            self::anonymise((int)$contextlist->get_user()->id);
        }
    }
    /**
     * Process erasure for the approved users in the requested context.
     *
     * @param approved_userlist $userlist Users represented in the requested context.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach ($userlist->get_userids() as $id) {
            self::anonymise((int)$id);
        }
    }
    /**
     * Clear the selected personal associations while retaining shared records.
     *
     * @param int $userid User ID.
     * @return void
     */
    private static function anonymise(int $userid): void {
        global $DB;
        foreach ($DB->get_records('local_storefront_order', ['userid' => $userid], 'id', 'id') as $selected) {
            \local_storefront\local\order_lock::with_order((int)$selected->id, static function() use ($DB, $selected, $userid): void {
                // Re-read under the same lock used by payment callbacks and retries.
                $order = $DB->get_record('local_storefront_order', ['id' => $selected->id, 'userid' => $userid]);
                if (!$order) {
                    return;
                }
                $tx = $DB->start_delegated_transaction();
                foreach ($DB->get_records('local_storefront_comqueue', ['orderid' => $order->id]) as $message) {
                    $DB->update_record('local_storefront_comqueue', (object)['id' => $message->id, 'payload' => null,
                        'recipientemail' => null, 'recipientuserid' => null,
                        'recipienthash' => hash('sha256', 'deleted:' . $message->id), 'status' => 'cancelled']);
                }
                $DB->update_record('local_storefront_order', (object)['id' => $order->id, 'userid' => null, 'email' => null,
                    'claimtokenhash' => null, 'recoverytokenhash' => null, 'providercheckoutid' => null,
                    'providerpaymentid' => null, 'providersubscriptionid' => null]);
                \local_storefront\local\order_privacy::clear_references((int)$order->id);
                // Commit before releasing the order lock, so the next callback sees erased ownership.
                $tx->allow_commit();
            });
        }
        $tx = $DB->start_delegated_transaction();
        $DB->set_field('local_storefront_legalrev', 'createdby', 0, ['createdby' => $userid]);
        $DB->set_field('local_storefront_comrule', 'modifiedby', null, ['modifiedby' => $userid]);
        foreach ($DB->get_records('local_storefront_comqueue', ['recipientuserid' => $userid]) as $row) {
            $DB->update_record(
                'local_storefront_comqueue',
                (object)[
                'id' => $row->id,
                'recipientuserid' => null,
                'recipientemail' => null,

                'recipienthash' => hash('sha256', 'deleted:' . $row->id), 'payload' => null, 'status' => 'cancelled',
                ]
            );
        }
        $tx->allow_commit();
    }
}
