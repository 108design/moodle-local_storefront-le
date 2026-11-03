<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,product_service,category_service,tag_service,course_delivery};

$productid=required_param('productid',PARAM_INT);
page::setup('offers','offers','local/storefront:managecatalogue',['productid'=>$productid]);
$product=$DB->get_record('local_storefront_product',['id'=>$productid],'*',MUST_EXIST);
$PAGE->navbar->add(format_string($product->name),new moodle_url('/local/storefront/product_edit.php',['id'=>$productid]));
page::header('offers',true);
echo \local_storefront\output\ui::toolbar([
    \local_storefront\output\ui::button(new moodle_url('/local/storefront/product_edit.php',['id'=>$productid]),format_string($product->name),'t/left'),
    \local_storefront\output\ui::button(new moodle_url('/local/storefront/offer_edit.php',['productid'=>$productid]),get_string('addoffer','local_storefront'),'t/add','btn btn-primary')]);
$table=new html_table(); $table->head=[get_string('offername','local_storefront'),get_string('amount','local_storefront'),get_string('enabled','local_storefront'),get_string('actions')];
foreach ($DB->get_records('local_storefront_offer',['productid'=>$productid],'sortorder,id') as $offer) {
    $table->data[]=[format_string($offer->displayname),page::amount((float)$offer->amount,$offer->currency),\local_storefront\output\ui::visibility_badge(get_string($offer->enabled?'yes':'no'),(bool)$offer->enabled),
        \local_storefront\output\ui::icon_action(new moodle_url('/local/storefront/offer_edit.php',['id'=>$offer->id,'productid'=>$productid]),get_string('edit'),'t/edit')];
}
echo html_writer::div(html_writer::table($table),'table-responsive'); echo $OUTPUT->footer();
