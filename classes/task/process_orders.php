<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\task;
defined('MOODLE_INTERNAL') || die();
final class process_orders extends \core\task\scheduled_task {
    public function get_name(): string { return get_string('processorders','local_storefront'); }
    public function execute(): void {
        global $DB;
        foreach ($DB->get_records_select('local_storefront_order','channel = :channel AND status IN (:paid,:partial)',
                ['channel'=>'moodle','paid'=>'paid','partial'=>'partial'],'id','id',0,50) as $order) {
            try { \local_storefront\local\order_service::retry((int)$order->id); }
            catch (\Throwable $error) { mtrace('Storefront order '.$order->id.': '.$error->getMessage()); }
        }
        \local_storefront\local\communication_service::dispatch();
    }
}
