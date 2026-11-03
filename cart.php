<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,product_selection,selection_resume};
page::setup('cart','cart');
$resume=optional_param('resume','',PARAM_ALPHANUM);
if ($resume!=='') {
    require_login();
    redirect(new moodle_url(selection_resume::consume($resume)));
}
$items=[]; $unavailable=[];
foreach (product_selection::snapshot() as $id) {
    try { $items[]=\local_storefront\local\catalogue::offer($id); }
    catch (moodle_exception $error) { $unavailable[]=$id; }
}
page::header('cart');
foreach ($unavailable as $id) {
    echo $OUTPUT->notification(get_string('selectionunavailable','local_storefront'),'warning');
    echo page::post('cart_action',get_string('remove'),['action'=>'remove','offerid'=>$id]);
}
if($items){echo \local_storefront\output\catalogue_view::summary($items,true);
    echo html_writer::div(\local_storefront\output\catalogue_view::totals($items),'local-storefront-cart-summary');}
if (!$items) { echo $OUTPUT->notification(get_string('cartempty','local_storefront'),'info'); }
else if (!$unavailable) {
    echo page::post('cart_action',get_string('clearcart','local_storefront'),['action'=>'clear']);
    if (isloggedin() && !isguestuser()) {
        echo html_writer::link(new moodle_url('/local/storefront/checkout.php'),get_string('checkout','local_storefront'),['class'=>'btn btn-primary']);
    } else {
        $token=selection_resume::create('/local/storefront/cart.php');
        $return=new moodle_url('/local/storefront/cart.php',['resume'=>$token]);
        $SESSION->wantsurl=$return->out(false);
        echo html_writer::link(get_login_url(),get_string('login'),['class'=>'btn btn-primary mr-2 me-2']);
        if (get_config('local_storefront','magiclinkenabled') && is_enabled_auth('magiclink') && class_exists('auth_magiclink\\local\\magic_link_service')) {
            echo html_writer::link(new moodle_url('/auth/magiclink/login.php',['returnurl'=>$return->out_as_local_url(false)]),
                get_string('magiclinklogin','local_storefront'),['class'=>'btn btn-secondary']);
        }
    }
}
echo $OUTPUT->footer();
