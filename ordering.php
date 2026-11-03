<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{page,catalogue_order,category_service,tag_service};
$kind=optional_param('kind','product',PARAM_ALPHA);$scope=optional_param('scope','global',PARAM_ALPHA);$groupid=optional_param('groupid',0,PARAM_INT);
page::setup('ordering','ordering','local/storefront:managecatalogue',['kind'=>$kind,'scope'=>$scope,'groupid'=>$groupid]);
if (data_submitted()) {
    require_sesskey();
    catalogue_order::move($kind,required_param('id',PARAM_INT),required_param('direction',PARAM_ALPHA),optional_param('target',0,PARAM_INT),
        $scope,$groupid,required_param('expected',PARAM_ALPHANUM));
    redirect($PAGE->url);
}
$members=catalogue_order::members($kind,$scope,$groupid);$expected=catalogue_order::fingerprint($members);
page::header('ordering',true);
echo html_writer::tag('p',get_string('orderingintro','local_storefront'));
echo html_writer::start_div('local-storefront-toolbar mb-4');
echo html_writer::link(new moodle_url('/local/storefront/ordering.php',['kind'=>$kind]),get_string('globalorder','local_storefront'),['class'=>'btn btn-link']);
if ($kind==='product') {
    foreach (['category'=>category_service::all(),'tag'=>tag_service::all()] as $type=>$groups) {
        foreach ($groups as $group) {
            echo html_writer::link(new moodle_url('/local/storefront/ordering.php',['kind'=>'product','scope'=>$type,'groupid'=>$group->id]),
                format_string($group->name),['class'=>'btn btn-link']);
        }
    }
}
echo html_writer::end_div();
$table=new html_table();$table->head=[get_string('name','local_storefront'),get_string('actions')];
foreach ($members as $member) {
    $actions='';
    foreach (['first','up','down','last'] as $direction) {
        $actions.=page::post('ordering',get_string('move_'.$direction,'local_storefront'),[
            'kind'=>$kind,'scope'=>$scope,'groupid'=>$groupid,'id'=>$member->id,'direction'=>$direction,'expected'=>$expected],'btn btn-secondary',match($direction){'first'=>'t/angles-up','up'=>'t/up','down'=>'t/down','last'=>'t/angles-down'},true);
    }
    if ($kind==='product' && $scope!=='global') { $actions.=page::post('ordering',get_string('resetorder','local_storefront'),[
        'kind'=>$kind,'scope'=>$scope,'groupid'=>$groupid,'id'=>$member->id,'direction'=>'reset','expected'=>$expected],'btn btn-secondary','i/reload',true); }
    $table->data[]=[format_string($member->name),html_writer::div($actions,'local-storefront-table-actions')];
}
echo html_writer::div(html_writer::table($table),'table-responsive');echo $OUTPUT->footer();
