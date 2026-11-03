<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,category_service,taxonomy};
$id=required_param('id',PARAM_INT);
page::setup('category_to_tag','converttotag','local/storefront:managecatalogue',['id'=>$id]);
$category=category_service::get($id);
if (data_submitted()) { require_sesskey(); taxonomy::convert($id); redirect(new moodle_url('/local/storefront/tags.php')); }
page::header('converttotag',true);
echo html_writer::tag('p',get_string('conversionintro','local_storefront',format_string($category->name)));
$members=$DB->get_records_sql('SELECT p.* FROM {local_storefront_product} p JOIN {local_storefront_productcat} c ON c.productid=p.id WHERE c.categoryid=?',[$id]);
foreach ($members as $product) { echo html_writer::tag('p',format_string($product->name)); }
echo page::post('category_to_tag',get_string('confirm'),['id'=>$id]);
echo $OUTPUT->footer();
