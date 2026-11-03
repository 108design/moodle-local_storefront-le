<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,product_service,category_service,tag_service,course_delivery};

page::setup('tags','tags','local/storefront:managecatalogue');
page::header('tags',true);
echo html_writer::link(new moodle_url('/local/storefront/ordering.php',['kind'=>'tag']),get_string('ordering','local_storefront'),['class'=>'btn btn-secondary mb-4']);
echo html_writer::link(new moodle_url('/local/storefront/tag_edit.php'),get_string('addtaxonomy','local_storefront'),['class'=>'btn btn-primary mb-4']);
$table=new html_table(); $table->head=[get_string('name','local_storefront'),get_string('sortorder','local_storefront'),get_string('actions')];
foreach (tag_service::all() as $term) {
    $table->data[]=[format_string($term->name),$term->sortorder,html_writer::link(new moodle_url('/local/storefront/tag_edit.php',['id'=>$term->id]),get_string('edit'))];
}
echo html_writer::table($table); echo $OUTPUT->footer();
