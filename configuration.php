<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,product_service,category_service,tag_service,course_delivery};

page::setup('configuration','settings','moodle/site:config');
$data=(object)['storevisibility'=>\local_storefront\local\store_access::mode(),
    'moodlepaymentaccountid'=>\local_storefront\local\moodle_payment_config::account_id(),
    'magiclinkenabled'=>get_config('local_storefront','magiclinkenabled')];
$form=new \local_storefront\form\editor($PAGE->url,['mode'=>'settings','paymentaccounts'=>\core_payment\helper::get_payment_accounts_menu(context_system::instance())]); $form->set_data($data);
if ($form->is_cancelled()) { redirect(new moodle_url('/local/storefront/products.php')); }
if ($submitted=$form->get_data()) {
    if (!in_array($submitted->storevisibility,['public','authenticated','editors'],true)
            || !$DB->record_exists('payment_accounts',['id'=>(int)$submitted->moodlepaymentaccountid,'enabled'=>1])) { throw new moodle_exception('moodlepaymentinvalid','local_storefront'); }
    foreach (['storevisibility','moodlepaymentaccountid','magiclinkenabled'] as $name) { set_config($name,$submitted->$name??0,'local_storefront'); }
    redirect($PAGE->url);
}
page::header('settings',true); $form->display(); echo $OUTPUT->footer();
