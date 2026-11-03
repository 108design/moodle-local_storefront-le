<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\local;
defined('MOODLE_INTERNAL') || die();

/** Shared native page shell and capability-filtered navigation. */
final class page {
    public static function setup(string $route, string $title, ?string $capability = null, array $params = []): void {
        global $PAGE;
        if ($capability) { require_login(); }
        else { store_access::require_access(); }
        $context = \context_system::instance();
        if ($capability) { require_capability($capability,$context); }
        else { store_access::require_catalogue_capability(); }
        $PAGE->set_context($context);
        $PAGE->set_url('/local/storefront/'.$route.'.php', $params);
        $management=$capability && (str_contains($capability,':manage') || $capability==='moodle/site:config');
        $PAGE->set_pagelayout($management ? 'admin' : 'standard');
        $PAGE->set_title(get_string($title,'local_storefront'));
        $PAGE->set_heading(get_string('pluginname','local_storefront'));
        admin_help::prepare();
    }
    public static function header(string $title, bool $admin = false): void {
        global $OUTPUT;
        echo $OUTPUT->header();
        echo self::navigation($admin);
        if ($title!=='storefront') { echo \local_storefront\output\ui::page_header(get_string($title,'local_storefront')); }
    }
    public static function navigation(bool $admin = false): string {
        global $PAGE;
        $route=basename($PAGE->url->get_path(),'.php');
        $current=match($route){'product','selection'=>'index','checkout','moodle_payment'=>'cart',
            'product_edit','offers','offer_edit','course_mapping'=>'products',
            'ordering'=>match($PAGE->url->get_param('kind')){'category'=>'categories','tag'=>'tags',default=>'products'},
            'category_edit','category_to_tag'=>'categories','tag_edit'=>'tags',
            'legal_edit','legal_settings','legal_delete','legal_preview'=>'legal_documents',default=>$route};
        $public=[];
        foreach(['index'=>'storefront','cart'=>'cart','my'=>'mypurchases'] as $target=>$label){
            $public[]=new \tabobject($target,new \moodle_url('/local/storefront/'.$target.'.php'),get_string($label,'local_storefront'),'',true);
        }
        $html=\html_writer::div(print_tabs([$public],$current,null,null,true),'local-storefront-public-tabs');
        $links=[];$context=\context_system::instance();
        if(has_capability('local/storefront:managecatalogue',$context)){$links+=['products'=>'manageproducts','categories'=>'categories','tags'=>'tags'];}
        if(has_capability('local/storefront:manageorders',$context)){$links+=['orders'=>'ordersaccess','communication_rules'=>'communication'];}
        if(has_capability('local/storefront:managelegal',$context)){$links['legal_documents']='legaldocuments';}
        if(has_capability('moodle/site:config',$context)){$links['configuration']='settings';}
        if($links){
            $icons=['products'=>'i/course','categories'=>'i/folder','tags'=>'fa-tags','orders'=>'i/payment','communication_rules'=>'t/message','legal_documents'=>\local_storefront\output\ui::ICON_LEGAL,'configuration'=>'i/settings'];
            $tabs=[];foreach($links as $target=>$label){$tabs[]=new \tabobject($target,new \moodle_url('/local/storefront/'.$target.'.php'),\local_storefront\output\ui::icon($icons[$target]).get_string($label,'local_storefront'),get_string($label,'local_storefront'),true);}
            $html.=\html_writer::div(\html_writer::div(get_string('administration'),'local-storefront-navigation-label')
                .print_tabs([$tabs],$current,null,null,true),'local-storefront-admin-tabs');
        }
        return $html;
    }
    public static function post(string $route, string $label, array $params = [], string $class = 'btn btn-secondary', string $icon = '', bool $compact = false): string {
        $url = new \moodle_url('/local/storefront/'.$route.'.php');
        return \html_writer::tag('form', \html_writer::input_hidden_params(new \moodle_url($url, $params+['sesskey'=>sesskey()]))
            .\html_writer::tag('button',($icon?\local_storefront\output\ui::icon($icon):'').\html_writer::span(s($label),$compact?'sr-only':''),['type'=>'submit','class'=>$compact?'local-storefront-icon-button':$class,'title'=>$label,'aria-label'=>$label]),
            ['method'=>'post','action'=>$url->out(false),'class'=>$compact?'local-storefront-icon-form':'local-storefront-post-form']);
    }
    public static function amount(float $amount, string $currency): string {
        return \core_payment\helper::get_cost_as_string($amount,$currency);
    }
}
