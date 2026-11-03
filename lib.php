<?php
/**
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();
function local_storefront_render_navbar_output(\renderer_base $renderer): string {
    return \local_storefront\local\admin_help::navbar($renderer);
}

/** Product files follow the same audience and publication checks as catalogue pages. */
function local_storefront_pluginfile($course,$cm,context $context,string $filearea,array $args,
        bool $forcedownload,array $options=[]): bool {
    global $DB;
    if ($context->contextlevel!==CONTEXT_SYSTEM || !in_array($filearea,['productimage','shortdescription','description'],true)
            || count($args)<2 || !\local_storefront\local\store_access::can_view()) { return false; }
    \local_storefront\local\store_access::require_catalogue_capability();
    $id=(int)array_shift($args);
    $product=$DB->get_record('local_storefront_product',['id'=>$id,'producttype'=>'course']);
    if (!$product || (!$product->visible && !has_capability('local/storefront:managecatalogue',$context))) { return false; }
    $filename=array_pop($args);$filepath='/'.($args?implode('/',$args).'/':'');
    $file=get_file_storage()->get_file($context->id,'local_storefront',$filearea,$id,$filepath,$filename);
    if (!$file || $file->is_directory()) { return false; }
    // Avoid a public cache keeping a now-hidden product file available.
    $options['cacheability']='private';
    $options['dontforcesvgdownload']=false;
    $forcedownload=\local_storefront\local\product_content::requires_download($file->get_mimetype(),$forcedownload);
    send_stored_file($file,0,0,$forcedownload,$options);
    return true;
}
