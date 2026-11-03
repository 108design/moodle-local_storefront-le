<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,catalogue,course_delivery};
$identifier=required_param('product',PARAM_ALPHANUMEXT);
page::setup('product','product',null,['product'=>$identifier]);
$product=$DB->get_record('local_storefront_product',['identifier'=>$identifier,'visible'=>1,'producttype'=>'course'],'*',MUST_EXIST);
page::header('product');
echo $OUTPUT->heading(format_string($product->name),3);
if ($image=\local_storefront\local\product_image::url((int)$product->id)) {
    echo html_writer::empty_tag('img',['src'=>$image->out(false),'alt'=>'','class'=>'img-fluid mb-4','style'=>'max-height:24rem']);
}
echo \local_storefront\local\product_content::format($product,'description');
foreach (course_delivery::public_mappings((int)$product->id) as $mapping) {
    $duration=$mapping->enrolperiod ? format_time($mapping->enrolperiod) : get_string('unlimitedaccess','local_storefront');
    echo html_writer::tag('p',format_string($mapping->coursename).' · '.s($duration));
}
echo html_writer::tag('p',get_string('durationnote','local_storefront'));
foreach (catalogue::offers((int)$product->id) as $offer) {
    echo html_writer::start_div('card card-body mb-3');
    echo html_writer::tag('h4',format_string($offer->displayname ?: $product->name));
    echo html_writer::tag('p',page::amount((float)$offer->amount,$offer->currency));
    echo page::post('cart_action',get_string('addtocart','local_storefront'),['action'=>'add','offerid'=>$offer->id]);
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
