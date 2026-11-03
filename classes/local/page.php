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
        if ($title!=='storefront') { echo $OUTPUT->heading(get_string($title,'local_storefront')); }
    }
    public static function navigation(bool $admin = false): string {
        $links = ['index'=>'storefront','cart'=>'cart','my'=>'mypurchases'];
        $context=\context_system::instance();
        if (has_capability('local/storefront:managecatalogue',$context)) {
            $links += ['products'=>'manageproducts','categories'=>'categories','tags'=>'tags'];
        }
        if (has_capability('local/storefront:manageorders',$context)) {
            $links += ['orders'=>'ordersaccess','communication_rules'=>'communication'];
        }
        if (has_capability('local/storefront:managelegal',$context)) { $links['legal_documents']='legaldocuments'; }
        if (has_capability('moodle/site:config',$context)) { $links['configuration']='settings'; }
        $items=[];
        foreach ($links as $route=>$label) {
            $items[]=\html_writer::link(new \moodle_url('/local/storefront/'.$route.'.php'),
                get_string($label,'local_storefront'),['class'=>'btn btn-link']);
        }
        return \html_writer::tag('nav',implode(' ',$items),['class'=>'d-flex flex-wrap mb-4','aria-label'=>get_string('pluginname','local_storefront')]);
    }
    public static function post(string $route, string $label, array $params = []): string {
        $url = new \moodle_url('/local/storefront/'.$route.'.php');
        return \html_writer::tag('form', \html_writer::input_hidden_params(new \moodle_url($url, $params+['sesskey'=>sesskey()]))
            .\html_writer::tag('button',s($label),['type'=>'submit','class'=>'btn btn-secondary']),
            ['method'=>'post','action'=>$url->out(false),'class'=>'d-inline-block me-2 mr-2 mb-2']);
    }
    public static function amount(float $amount, string $currency): string {
        return \core_payment\helper::get_cost_as_string($amount,$currency);
    }
}
