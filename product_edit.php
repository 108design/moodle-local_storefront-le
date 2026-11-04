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
page::setup('product_edit','editproduct','local/storefront:managecatalogue',['id'=>$id]);
$data=$id?$DB->get_record('local_storefront_product',['id'=>$id],'*',MUST_EXIST):(object)[];
if ($id) { $data->description_editor=['text'=>$data->description,'format'=>$data->descriptionformat];
    $data->categories=category_service::ids_for_product($id); $data->tags=tag_service::ids_for_product($id); }
$data=\local_storefront\local\product_content::prepare_editors($data,$id);
$draft=file_get_submitted_draft_itemid('productimage');
file_prepare_draft_area($draft,context_system::instance()->id,'local_storefront','productimage',$id,
    \local_storefront\local\product_image::options());
$data->productimage=$draft;
$form=new \local_storefront\form\editor($PAGE->url,['mode'=>'product','existing'=>$id]); $form->set_data($data);
if ($form->is_cancelled()) { redirect(new moodle_url('/local/storefront/products.php')); }
if ($submitted=$form->get_data()) { $id=product_service::save($submitted); redirect(new moodle_url('/local/storefront/product_edit.php',['id'=>$id])); }
page::header('editproduct',true);
if ($id) { echo \local_storefront\output\ui::toolbar([
    \local_storefront\output\ui::button(new moodle_url('/local/storefront/offers.php',['productid'=>$id]),get_string('offers','local_storefront'),'fa-tags'),
    \local_storefront\output\ui::button(new moodle_url('/local/storefront/course_mapping.php',['productid'=>$id]),get_string('delivery','local_storefront'),'fa-box-open')]); }
$form->display(); echo $OUTPUT->footer();
