<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();

/** Course order view preserves separate payment, position delivery and actual access facts. */
final class order_view {
    public static function render(array $orders,bool $admin=false): string {
        global $DB, $USER;
        $html='';
        foreach ($orders as $order) {
            $html.=\html_writer::start_div('card card-body mb-4');
            $html.=\html_writer::tag('h3',s($order->orderkey),['class'=>'h5']);
            $html.=\html_writer::tag('p',get_string('orderstatus','local_storefront').': '.self::state($order->status)
                .' · '.page::amount((float)$order->total,$order->currency).' · '.userdate($order->timecreated));
            if ($admin) { $html.=\html_writer::tag('p',get_string('account','local_storefront').': '.s($order->email??'').
                ' (#'.(int)$order->userid.')'); }
            $table=new \html_table(); $table->attributes['class']='generaltable mb-4';
            $table->head=[get_string('productname','local_storefront'),get_string('paymentstatus','local_storefront'),
                get_string('deliverystatus','local_storefront'),get_string('courseaccess','local_storefront')];
            foreach ($DB->get_records('local_storefront_orderline',['orderid'=>$order->id],'id') as $line) {
                $state=$DB->get_record('local_storefront_line_state',['orderlineid'=>$line->id],'*',MUST_EXIST);
                $access=[];
                if (course_delivery::available()) {
                    foreach ($DB->get_records('enrol_storefront_purchase',['orderlineid'=>$line->id,'userid'=>$order->userid]) as $purchase) {
                        $course=$DB->get_record('course',['id'=>$purchase->courseid]);
                        $end=$purchase->timeend?userdate($purchase->timeend):get_string('unlimitedaccess','local_storefront');
                        $label=format_string($course->fullname??$line->productname).' · '.self::state($purchase->status).' · '.s($end);
                        $access[]=($course && $course->visible)?\html_writer::link(new \moodle_url('/course/view.php',['id'=>$course->id]),$label):$label;
                    }
                }
                $table->data[]=[format_string($line->productname),self::state($state->paymentstatus),self::state($state->fulfilmentstatus),implode('<br>',$access)];
            }
            $html.=\html_writer::table($table);
            foreach ($DB->get_records('local_storefront_orderlegal',['orderid'=>$order->id]) as $legal) {
                $label=format_string($legal->title).' · '.$legal->lang.' · '.get_string('revision','local_storefront').' #'.$legal->revisionid;
                $html.=\html_writer::tag('p',\html_writer::link(new \moodle_url('/local/storefront/legal.php',[
                    'documentid'=>$legal->sourceid,'revision'=>$legal->revisionid,'document'=>$legal->identifier]),$label)
                    .($legal->timeaccepted?' · '.get_string('acceptedat','local_storefront').' '.userdate($legal->timeaccepted):''),['class'=>'small']);
            }
            if ($order->status==='pending' && (!$admin || (int)$order->userid===(int)$USER->id)) {
                $html.=\html_writer::link(new \moodle_url('/local/storefront/moodle_payment.php',['order'=>$order->orderkey]),
                    get_string('resumepayment','local_storefront'),['class'=>'btn btn-primary']);
            } else if (in_array($order->status,['paid','partial'],true)) {
                $html.=\html_writer::tag('p',get_string('deliverypending','local_storefront'));
                $html.=page::post($admin?'orders':'my',get_string('retrydelivery','local_storefront'),['retry'=>$order->id]);
            }
            $html.=\html_writer::end_div();
        }
        return $html;
    }
    private static function state(string $state): string {
        $key='state_'.$state;
        return get_string_manager()->string_exists($key,'local_storefront')?get_string($key,'local_storefront'):s($state);
    }
}
