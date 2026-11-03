<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{contextlist,approved_contextlist,userlist,approved_userlist,writer};

/** User-owned purchase data; anonymised order and legal evidence retain their relationships. */
final class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_storefront_order',[
            'userid'=>'privacy:userid','email'=>'privacy:email','customerlang'=>'privacy:language',
            'providerpaymentid'=>'privacy:payment','total'=>'privacy:purchase'],'privacy:orders');
        $collection->add_database_table('local_storefront_orderlegal',[
            'revisionid'=>'privacy:legal','timeaccepted'=>'privacy:legal'],'privacy:legal');
        $collection->add_database_table('local_storefront_comqueue',[
            'recipientemail'=>'privacy:email','recipientuserid'=>'privacy:userid','payload'=>'privacy:mail'],'privacy:mail');
        $collection->add_subsystem_link('core_payment',[],'privacy:payment');
        $collection->add_database_table('local_storefront_legalrev',['createdby'=>'privacy:userid'],'privacy:legal');
        $collection->add_database_table('local_storefront_comrule',['modifiedby'=>'privacy:userid'],'privacy:mail');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $list=new contextlist();
        if ($DB->record_exists('local_storefront_order',['userid'=>$userid])
                || $DB->record_exists('local_storefront_comqueue',['recipientuserid'=>$userid])
                || $DB->record_exists('local_storefront_legalrev',['createdby'=>$userid])
                || $DB->record_exists('local_storefront_comrule',['modifiedby'=>$userid])) { $list->add_system_context(); }
        return $list;
    }
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel!==CONTEXT_SYSTEM) { return; }
        $userlist->add_from_sql('userid','SELECT userid FROM {local_storefront_order} WHERE userid IS NOT NULL',[]);
        $userlist->add_from_sql('recipientuserid','SELECT recipientuserid FROM {local_storefront_comqueue} WHERE recipientuserid IS NOT NULL',[]);
        $userlist->add_from_sql('createdby','SELECT createdby FROM {local_storefront_legalrev} WHERE createdby IS NOT NULL',[]);
        $userlist->add_from_sql('modifiedby','SELECT modifiedby FROM {local_storefront_comrule} WHERE modifiedby IS NOT NULL',[]);
    }
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id,$contextlist->get_contextids())) { return; }
        $userid=(int)$contextlist->get_user()->id;
        $orders=[];
        foreach ($DB->get_records('local_storefront_order',['userid'=>$userid]) as $order) {
            unset($order->claimtokenhash,$order->recoverytokenhash);
            $order->positions=array_values($DB->get_records('local_storefront_orderline',['orderid'=>$order->id]));
            $order->legal=array_values($DB->get_records('local_storefront_orderlegal',['orderid'=>$order->id]));
            $orders[]=$order;
        }
        writer::with_context(\context_system::instance())->export_data([get_string('pluginname','local_storefront')],(object)[
            'orders'=>$orders,'messages'=>array_values($DB->get_records('local_storefront_comqueue',['recipientuserid'=>$userid])),
            'legal_authorship'=>array_values($DB->get_records('local_storefront_legalrev',['createdby'=>$userid])),
            'communication_authorship'=>array_values($DB->get_records('local_storefront_comrule',['modifiedby'=>$userid]))]);
    }
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel!==CONTEXT_SYSTEM) { return; }
        foreach ($DB->get_fieldset_select('local_storefront_order','userid','userid IS NOT NULL') as $id) { self::anonymise((int)$id); }
        foreach ($DB->get_fieldset_select('local_storefront_comqueue','recipientuserid','recipientuserid IS NOT NULL') as $id) { self::anonymise((int)$id); }
        foreach ($DB->get_fieldset_select('local_storefront_legalrev','createdby','createdby > 0') as $id) { self::anonymise((int)$id); }
        foreach ($DB->get_fieldset_select('local_storefront_comrule','modifiedby','modifiedby IS NOT NULL') as $id) { self::anonymise((int)$id); }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (in_array(\context_system::instance()->id,$contextlist->get_contextids())) { self::anonymise((int)$contextlist->get_user()->id); }
    }
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel!==CONTEXT_SYSTEM) { return; }
        foreach ($userlist->get_userids() as $id) { self::anonymise((int)$id); }
    }
    private static function anonymise(int $userid): void {
        global $DB;
        $tx=$DB->start_delegated_transaction();
        foreach ($DB->get_records('local_storefront_order',['userid'=>$userid]) as $order) {
            foreach ($DB->get_records('local_storefront_comqueue',['orderid'=>$order->id]) as $message) {
                $DB->update_record('local_storefront_comqueue',(object)['id'=>$message->id,'payload'=>null,
                    'recipientemail'=>null,'recipienthash'=>hash('sha256','deleted:'.$message->id),'status'=>'cancelled']);
            }
            $DB->update_record('local_storefront_order',(object)['id'=>$order->id,'userid'=>null,'email'=>null,
                'claimtokenhash'=>null,'recoverytokenhash'=>null]);
        }
        $DB->set_field('local_storefront_legalrev','createdby',0,['createdby'=>$userid]);
        $DB->set_field('local_storefront_comrule','modifiedby',null,['modifiedby'=>$userid]);
        foreach ($DB->get_records('local_storefront_comqueue',['recipientuserid'=>$userid]) as $row) {
            $DB->update_record('local_storefront_comqueue',(object)['id'=>$row->id,'recipientuserid'=>null,'recipientemail'=>null,
                'recipienthash'=>hash('sha256','deleted:'.$row->id),'payload'=>null,'status'=>'cancelled']);
        }
        $tx->allow_commit();
    }
}
