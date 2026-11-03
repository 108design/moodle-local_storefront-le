<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,product_service,category_service,tag_service,course_delivery};

$id=optional_param('id',0,PARAM_INT);
page::setup('category_edit','categories','local/storefront:managecatalogue',['id'=>$id]);
$return=new moodle_url('/local/storefront/categories.php');
$data=$id?category_service::get($id):(object)[];
$form=new \local_storefront\form\editor($PAGE->url,['mode'=>'category']); $form->set_data($data);
if ($form->is_cancelled()) { redirect($return); }
if ($submitted=$form->get_data()) { category_service::save($submitted); redirect($return); }
page::header('categories',true); $form->display(); echo $OUTPUT->footer();
