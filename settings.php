<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig || has_any_capability(['local/storefront:managecatalogue','local/storefront:managelegal','local/storefront:manageorders'],context_system::instance())) {
    foreach (['local_storefront'=>['products','pluginname','local/storefront:managecatalogue'],
        'local_storefront_legal'=>['legal_documents','legaldocuments','local/storefront:managelegal'],
        'local_storefront_orders'=>['orders','ordersaccess','local/storefront:manageorders'],
        'local_storefront_settings'=>['configuration','settings','moodle/site:config']] as $name=>[$route,$label,$cap]) {
        $ADMIN->add('localplugins',new admin_externalpage($name,get_string($label,'local_storefront'),
            new moodle_url('/local/storefront/'.$route.'.php'),$cap));
    }
}
