<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
namespace local_storefront;
defined('MOODLE_INTERNAL') || die();
use local_storefront\local\{store_access,product_service,product_content,product_image};

/** Anonymous audience, native files, and local migration protections. */
final class public_access_test extends \advanced_testcase {
    public function test_mapping_cannot_treat_a_requested_system_role_as_an_existing_course_role(): void {
        global $DB,$PAGE;
        $this->resetAfterTest();$this->setAdminUser();$PAGE->set_context(\context_system::instance());
        $course=$this->getDataGenerator()->create_course();
        $productid=product_service::save((object)['identifier'=>'role-boundary','name'=>'Role boundary']);
        $PAGE->set_url('/local/storefront/course_mapping.php',['productid'=>$productid]);
        $systemroles=get_archetype_roles('user');$systemrole=(int)reset($systemroles)->id;
        $context=\context_course::instance($course->id);
        $this->assertArrayNotHasKey($systemrole,get_default_enrol_roles($context));
        $form=new \local_storefront\form\course_mapping_form(null,['courses'=>[$course->id=>$course->fullname],
            'roles'=>role_fix_names(get_all_roles(),\context_system::instance(),ROLENAME_ORIGINAL,true),'lockedcourseids'=>[]]);
        $data=['productid'=>$productid,'courseids'=>[$course->id],'roleid'=>$systemrole,'enrolperiod'=>0,'status'=>ENROL_INSTANCE_ENABLED];
        $this->assertArrayHasKey('roleid',$form->validation($data,[]));
        // An actual existing legacy role remains editable under Moodle's native contract.
        enrol_get_plugin('storefront')->add_instance($course,['customint1'=>$productid,'roleid'=>$systemrole]);
        $this->assertArrayNotHasKey('roleid',$form->validation($data,[]));
    }
    public function test_mail_tokens_survive_sanitising_and_queue_content_remains_immutable(): void {
        global $DB;
        $this->resetAfterTest();$this->setAdminUser();
        $service=\local_storefront\local\communication_service::class;
        $body=$service::clean_template('<script>alert(1)</script><p>{order_reference}</p><a href="{manage_url}">Access</a>');
        $this->assertStringNotContainsString('<script>',$body);
        $this->assertStringContainsString('href="{manage_url}"',$body);
        $user=$this->getDataGenerator()->create_user(['email'=>'buyer@example.invalid']);
        $orderid=$DB->insert_record('local_storefront_order',(object)['orderkey'=>'order-mail-reference','status'=>'completed',
            'userid'=>$user->id,'email'=>$user->email,'customerlang'=>'en','total'=>12.50,'timecreated'=>time(),'timemodified'=>time()]);
        $ruleid=$DB->insert_record('local_storefront_comrule',(object)['eventtype'=>'order_ready','language'=>'en',
            'subject'=>'Access ready','body'=>clean_text($body,FORMAT_HTML),'adminemails'=>'ops@example.invalid',
            'delayminutes'=>10,'timecreated'=>time(),'timemodified'=>time()]);
        $this->assertSame(2,$service::enqueue_completed($orderid));
        $rows=$DB->get_records('local_storefront_comqueue',['orderid'=>$orderid]);
        foreach ($rows as $row) {
            $payload=json_decode($row->payload,true,512,JSON_THROW_ON_ERROR);
            $this->assertStringContainsString('order-mail-reference',$payload['bodyhtml']);
            $this->assertStringContainsString((new \moodle_url('/local/storefront/my.php'))->out(false),$payload['bodyhtml']);
            $this->assertStringNotContainsString('manage_url',$payload['bodyhtml']);
            $this->assertGreaterThanOrEqual(time()+590,(int)$row->availableat);
        }
        $DB->set_field('local_storefront_comrule','body','Changed template',['id'=>$ruleid]);
        $this->assertSame(0,$service::enqueue_completed($orderid));
        $this->assertEquals($rows,$DB->get_records('local_storefront_comqueue',['orderid'=>$orderid]));
    }
    public function test_native_editor_preserves_existing_product_identity(): void {
        global $PAGE;
        $this->resetAfterTest();$this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $url=new \moodle_url('/local/storefront/product_edit.php',['id'=>73]);
        $PAGE->set_url($url);
        $form=new \local_storefront\form\editor($url,['mode'=>'product','existing'=>73]);
        $form->set_data((object)['id'=>73,'identifier'=>'course-identity','name'=>'Course identity',
            'shortdescription_editor'=>['text'=>'Summary','format'=>FORMAT_HTML,'itemid'=>0],
            'description_editor'=>['text'=>'Description','format'=>FORMAT_HTML,'itemid'=>0]]);
        $html=$form->render();
        $dom=new \DOMDocument();@$dom->loadHTML($html);
        $xpath=new \DOMXPath($dom);
        $identities=$xpath->query('//input[@name="id"]');
        $this->assertGreaterThan(0,$identities->length);
        foreach ($identities as $field) { $this->assertSame('73',$field->getAttribute('value')); }
        // Disabled browser fields are absent from POST; an existing identifier must not be required again.
        \local_storefront\form\editor::mock_submit(['id'=>73,'name'=>'Updated course',
            'shortdescription_editor'=>['text'=>'Summary','format'=>FORMAT_HTML,'itemid'=>0],
            'description_editor'=>['text'=>'Description','format'=>FORMAT_HTML,'itemid'=>0]]);
        $submittedform=new \local_storefront\form\editor($url,['mode'=>'product','existing'=>73]);
        $submittedform->set_data((object)['id'=>73,'identifier'=>'course-identity','name'=>'Original course']);
        $submitted=$submittedform->get_data();
        $this->assertNotNull($submitted);
        $this->assertEquals(73,$submitted->id);
        $this->assertSame('Updated course',$submitted->name);
    }
    public function test_privacy_clears_buyer_and_messages_and_preserves_order_legal_evidence(): void {
        global $DB;
        $this->resetAfterTest();$this->setAdminUser();
        $user=$this->getDataGenerator()->create_user();
        $orderid=$DB->insert_record('local_storefront_order',(object)['orderkey'=>'privacy-course-order',
            'userid'=>$user->id,'email'=>$user->email,'total'=>12.50,'timecreated'=>time(),'timemodified'=>time()]);
        $data=\local_storefront\local\legal_service::edit_data(0,'en');
        $data->identifier='privacy-terms';$data->title='Terms';$data->documentlanguage='en';$data->originlang='en';
        $data->contentformat=FORMAT_HTML;$data->content_editor=['text'=>'<p>Terms</p>','format'=>FORMAT_HTML];$data->published=1;
        $docid=\local_storefront\local\legal_service::save($data,(int)$user->id);
        $doc=\local_storefront\local\legal_service::get($docid);
        $legalid=$DB->insert_record('local_storefront_orderlegal',(object)['orderid'=>$orderid,'source'=>'storefront','sourceid'=>$docid,
            'revisionid'=>$doc->currentrevisionid,'identifier'=>'privacy-terms','title'=>'Terms','lang'=>'en',
            'contenthash'=>'unchanged-proof','required'=>1,'timeaccepted'=>time()]);
        foreach (['customer','internal'] as $type) {
            $DB->insert_record('local_storefront_comqueue',(object)['eventtype'=>'order_ready','orderid'=>$orderid,
                'dedupekey'=>hash('sha256',$type),'recipienttype'=>$type,'recipientuserid'=>$type==='customer'?$user->id:null,
                'recipientemail'=>$type==='customer'?$user->email:'ops@example.invalid','recipienthash'=>hash('sha256',$type),
                'language'=>'en','payload'=>'{"body":"Buyer details"}','availableat'=>time(),'timecreated'=>time(),'timemodified'=>time()]);
        }
        $before=$DB->get_record('local_storefront_orderlegal',['id'=>$legalid],'*',MUST_EXIST);
        \local_storefront\privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $user,'local_storefront',[\context_system::instance()->id]));
        $order=$DB->get_record('local_storefront_order',['id'=>$orderid],'*',MUST_EXIST);
        $this->assertNull($order->userid);$this->assertNull($order->email);$this->assertEquals(12.50,(float)$order->total);
        $this->assertEquals($before,$DB->get_record('local_storefront_orderlegal',['id'=>$legalid],'*',MUST_EXIST));
        foreach ($DB->get_records('local_storefront_comqueue',['orderid'=>$orderid]) as $message) {
            $this->assertNull($message->payload);$this->assertNull($message->recipientemail);$this->assertEquals('cancelled',$message->status);
        }
        $this->assertEquals(0,$DB->get_field('local_storefront_legalrev','createdby',['id'=>$doc->currentrevisionid]));
    }
    public function test_public_catalogue_checks_guest_role_without_authenticating(): void {
        global $USER,$CFG,$DB;
        $this->resetAfterTest();$this->setUser(null);
        set_config('storevisibility','public','local_storefront');
        set_config('forcelogin',0);
        store_access::require_access();store_access::require_catalogue_capability();
        $this->assertFalse(isloggedin());$this->assertEmpty($USER->id);
        assign_capability('local/storefront:viewcatalogue',CAP_PROHIBIT,$CFG->guestroleid,\context_system::instance()->id,true);
        $this->assertEquals(CAP_PROHIBIT,$DB->get_field('role_capabilities','permission',[
            'roleid'=>$CFG->guestroleid,'contextid'=>\context_system::instance()->id,'capability'=>'local/storefront:viewcatalogue']));
        accesslib_clear_all_caches_for_unit_testing();
        $this->expectException(\required_capability_exception::class);
        store_access::require_catalogue_capability();
    }
    public function test_global_login_policy_blocks_anonymous_catalogue_and_files(): void {
        $this->resetAfterTest();$this->setUser(null);
        set_config('storevisibility','public','local_storefront');set_config('forcelogin',1);
        $this->assertFalse(store_access::can_view());
        $this->expectException(\required_capability_exception::class);store_access::require_catalogue_capability();
    }
    public function test_native_image_and_embedded_description_files_keep_product_ids_and_visibility(): void {
        global $DB,$USER,$CFG;
        $this->resetAfterTest();$this->setAdminUser();
        set_config('storevisibility','public','local_storefront');
        $data=(object)['identifier'=>'image-course','name'=>'Image course','visible'=>1,'sortorder'=>0,
            'shortdescription'=>'Summary','description'=>'Description','descriptionformat'=>FORMAT_HTML,'shortdescriptionformat'=>FORMAT_HTML];
        $data=product_content::prepare_editors($data,0);
        $imageid=file_get_unused_draft_itemid();
        $usercontext=\context_user::instance($USER->id);
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6l8AAAAASUVORK5CYII=');
        $fs=get_file_storage();
        $record=['contextid'=>$usercontext->id,'component'=>'user','filearea'=>'draft','filepath'=>'/',
            'filename'=>'course.png','mimetype'=>'image/png','userid'=>$USER->id];
        $fs->create_file_from_string($record+['itemid'=>$imageid],$png);
        $descriptionid=$data->description_editor['itemid'];
        $fs->create_file_from_string($record+['itemid'=>$descriptionid],$png);
        $drafturl=\moodle_url::make_draftfile_url($descriptionid,'/','course.png');
        $data->description_editor['text']='<p>Course</p><img src="'.$drafturl->out(false).'" alt="Course">';
        $data->productimage=$imageid;
        $id=product_service::save($data);
        $context=\context_system::instance();
        $this->assertNotNull(product_image::url($id));
        $this->assertNotFalse($fs->get_file($context->id,'local_storefront','productimage',$id,'/','course.png'));
        $this->assertNotFalse($fs->get_file($context->id,'local_storefront','description',$id,'/','course.png'));
        $product=$DB->get_record('local_storefront_product',['id'=>$id],'*',MUST_EXIST);
        $this->assertStringContainsString('@@PLUGINFILE@@',$product->description);
        $this->assertStringContainsString('pluginfile.php',product_content::format($product,'description'));
        require_once($CFG->dirroot.'/local/storefront/lib.php');
        $this->setUser($this->getDataGenerator()->create_user());
        $DB->set_field('local_storefront_product','visible',0,['id'=>$id]);
        $this->assertFalse(\local_storefront_pluginfile(null,null,$context,'productimage',[$id,'course.png'],false));
        $DB->set_field('local_storefront_product','visible',1,['id'=>$id]);
        set_config('storevisibility','editors','local_storefront');
        $this->assertFalse(\local_storefront_pluginfile(null,null,$context,'description',[$id,'course.png'],false));
    }
    public function test_local_upgrade_guard_rejects_unknown_or_larger_state_without_rewriting_marker(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot.'/local/storefront/db/upgrade.php');
        set_config('distribution','course','local_storefront');
        $this->assertTrue(\xmldb_local_storefront_upgrade(2026010100));
        foreach ([['full',2026010100],['course',2026100100]] as [$marker,$version]) {
            set_config('distribution',$marker,'local_storefront');
            try { \xmldb_local_storefront_upgrade($version); $this->fail('Unallowed state accepted'); }
            catch (\moodle_exception $e) { $this->assertEquals('distributionmismatch',$e->errorcode); }
            $this->assertEquals($marker,get_config('local_storefront','distribution'));
        }
        unset_config('distribution','local_storefront');
        $this->expectException(\moodle_exception::class);\xmldb_local_storefront_upgrade(2026010100);
    }
}
