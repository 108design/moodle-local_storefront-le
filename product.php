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
$PAGE->navbar->add(format_string($product->name));
page::header('storefront');
echo html_writer::start_div('local-storefront-product-detail');
if($image=\local_storefront\local\product_image::url((int)$product->id)){
    echo html_writer::div(html_writer::empty_tag('img',['src'=>$image->out(false),'alt'=>'','class'=>'local-storefront-product-detail-image']),'local-storefront-product-detail-media');
}
echo html_writer::start_div('local-storefront-product-detail-content');
echo $OUTPUT->heading(format_string($product->name),2);
echo \local_storefront\local\product_content::format($product,'description');
echo html_writer::start_div('local-storefront-included');
echo $OUTPUT->heading(get_string('includedcourses','local_storefront'),3);
echo html_writer::start_tag('ul',['class'=>'local-storefront-included-list']);
foreach(course_delivery::public_mappings((int)$product->id) as $mapping){
    $duration=$mapping->enrolperiod?format_time($mapping->enrolperiod):get_string('unlimitedaccess','local_storefront');
    echo html_writer::tag('li',$OUTPUT->pix_icon('i/course','').html_writer::span(format_string($mapping->coursename).' · '.s($duration)));
}
echo html_writer::end_tag('ul');
echo html_writer::tag('p',get_string('durationnote','local_storefront'),['class'=>'small text-muted mt-3']);
echo html_writer::end_div().html_writer::end_div().html_writer::end_div();
$offers=catalogue::offers((int)$product->id);
if($offers){echo $OUTPUT->heading(get_string('offers','local_storefront'),3);echo html_writer::start_div('local-storefront-pricing-grid');}
foreach($offers as $offer){
    echo html_writer::start_div('card local-storefront-pricing-card');echo html_writer::start_div('card-body');
    echo html_writer::tag('h4',format_string($offer->displayname?:$product->name),['class'=>'h5']);
    echo html_writer::tag('p',page::amount((float)$offer->amount,$offer->currency),['class'=>'local-storefront-pricing-amount']);
    echo html_writer::tag('p',get_string('oneoffpurchase','local_storefront'),['class'=>'small text-muted']);
    echo page::post('cart_action',get_string('addtocart','local_storefront'),['action'=>'add','offerid'=>$offer->id],'btn btn-primary');
    echo html_writer::end_div().html_writer::end_div();
}
if($offers){echo html_writer::end_div();}else{echo $OUTPUT->notification(get_string('coursefulfilmentunavailable','local_storefront'),'info');}
echo $OUTPUT->footer();
