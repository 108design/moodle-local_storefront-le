<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');

/** Native forms for course catalogue operations. */
final class editor extends \moodleform {
    protected function definition(): void {
        $m=$this->_form;
        $mode=$this->_customdata['mode'];
        foreach (['id','productid'] as $name) { $m->addElement('hidden',$name,0); $m->setType($name,PARAM_INT); }
        $text=function($name,$label,$type=PARAM_TEXT,$required=false) use ($m) {
            $m->addElement('text',$name,get_string($label,'local_storefront'));
            $m->setType($name,$type);
            if ($required) { $m->addRule($name,null,'required',null,'client'); }
        };
        if ($mode === 'product') {
            $m->addElement('header','contentheader',get_string('productcontent','local_storefront'));
            $text('identifier','identifier',PARAM_ALPHANUMEXT,true);
            if (!empty($this->_customdata['existing'])) { $m->hardFreeze('identifier'); }
            $m->addHelpButton('identifier','identifier','local_storefront');
            $text('name','productname',PARAM_TEXT,true);
            $m->addElement('editor','shortdescription_editor',get_string('summary','local_storefront'),null,
                \local_storefront\local\product_content::editor_options());
            $m->setType('shortdescription_editor',PARAM_RAW);
            $m->addElement('editor','description_editor',get_string('description'),null,
                \local_storefront\local\product_content::editor_options());
            $m->setType('description_editor',PARAM_RAW);
            $m->addElement('header','presentationheader',get_string('productpresentation','local_storefront'));
            $m->addElement('filemanager','productimage',get_string('productimage','local_storefront'),null,
                \local_storefront\local\product_image::options());
            $m->addHelpButton('productimage','productimage','local_storefront');
            foreach (['categories','tags'] as $field) {
                $service='\\local_storefront\\local\\'.($field==='categories'?'category':'tag').'_service';
                $m->addElement('autocomplete',$field,get_string($field,'local_storefront'),$service::choices(),['multiple'=>true]);
                $m->setType($field,PARAM_INT);
            }
        } else if ($mode === 'offer') {
            $text('identifier','identifier',PARAM_ALPHANUMEXT,true);
            $text('displayname','offername',PARAM_TEXT,true);
            $text('amount','amount',PARAM_RAW_TRIMMED,true); $m->addHelpButton('amount','amount','local_storefront');
            $text('currency','currency',PARAM_ALPHA,true); $m->setDefault('currency','EUR');
            $m->addHelpButton('currency','currency','local_storefront');
            $m->addElement('advcheckbox','enabled',get_string('enabled','local_storefront')); $m->setDefault('enabled',1);
        } else if (in_array($mode,['category','tag'],true)) {
            $text('identifier','identifier',PARAM_ALPHANUMEXT,true);
            $text('name','name',PARAM_TEXT,true);
            $text('badgecolor','badgecolor',PARAM_TEXT); $m->addHelpButton('badgecolor','badgecolor','local_storefront');
        } else if ($mode === 'settings') {
            $m->addElement('select','storevisibility',get_string('storevisibility','local_storefront'),[
                'public'=>get_string('audiencepublic','local_storefront'),'authenticated'=>get_string('audienceauthenticated','local_storefront'),
                'editors'=>get_string('audienceeditors','local_storefront')]);
            $m->addHelpButton('storevisibility','storevisibility','local_storefront');
            $accounts=(array)($this->_customdata['paymentaccounts']??[]);
            $m->addElement('select','moodlepaymentaccountid',get_string('paymentaccount','local_storefront'),[0=>get_string('choosedots')]+$accounts);
            $m->setType('moodlepaymentaccountid',PARAM_INT);
            if(!$accounts){$m->addElement('static','paymentaccountnotice','',get_string('moodlepaymentnoaccounts','local_storefront'));} $m->addHelpButton('moodlepaymentaccountid','paymentaccount','local_storefront');
            $m->addElement('advcheckbox','magiclinkenabled',get_string('magiclinkenabled','local_storefront'));
            $m->addHelpButton('magiclinkenabled','magiclinkenabled','local_storefront');
        }
        if (in_array($mode,['product','category','tag'],true)) {
            $m->addElement('header','publicationheader',get_string('publication','local_storefront'));
            $m->addElement('advcheckbox','visible',get_string('visible','local_storefront')); $m->setDefault('visible',0);
        }
        if ($mode !== 'settings') { $text('sortorder','sortorder',PARAM_INT); $m->setDefault('sortorder',0); $m->addHelpButton('sortorder','sortorder','local_storefront'); }
        $this->add_action_buttons(true);
    }
    public function validation($data,$files): array {
        $errors=parent::validation($data,$files);
        if ($this->_customdata['mode']==='settings' && !array_key_exists((int)($data['moodlepaymentaccountid']??0),(array)($this->_customdata['paymentaccounts']??[]))) {
            $errors['moodlepaymentaccountid']=get_string('paymentaccountrequired','local_storefront');
        }
        if ($this->_customdata['mode']==='offer' && (!preg_match('/^\d{1,13}(?:\.\d{1,6})?$/D',$data['amount']) || (float)$data['amount']<=0)) {
            $errors['amount']=get_string('invalidoffer','local_storefront');
        }
        return $errors;
    }
}
