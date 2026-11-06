<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\MessageAttachmentService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Guarded SQLite memory database and unique disposable private files only. */
class MessageAttachmentsTest extends TestCase
{
    use \Tests\Support\CreatesMessageAttachmentSchema;
    private User $seller;
    private User $buyer;
    private Conversation $conversation;
    private string $imageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing',app()->environment());
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName(),'Refuse financial/shared database fixture writes.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        Schema::enableForeignKeyConstraints();
        foreach ([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT',
            'conversations'=>'id INTEGER PRIMARY KEY, reference TEXT, type TEXT, status TEXT, seller_id INTEGER, seller_order_id INTEGER, order_id INTEGER, complaint_id INTEGER, shipment_id INTEGER, last_message_at TEXT, created_at TEXT, updated_at TEXT',
            'conversation_participants'=>'id INTEGER PRIMARY KEY, conversation_id INTEGER, user_id INTEGER, role_context TEXT, seller_id INTEGER, rider_id INTEGER, logistics_provider_id INTEGER, left_at TEXT, last_read_message_id INTEGER, last_read_at TEXT, updated_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        DB::statement('CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL REFERENCES conversations(id) ON DELETE CASCADE, sender_id INTEGER REFERENCES users(id) ON DELETE SET NULL, type TEXT NOT NULL DEFAULT \'TEXT\', body TEXT NOT NULL, reply_to_id INTEGER REFERENCES messages(id) ON DELETE SET NULL, sent_at TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
        $this->createAttachmentTable();
        foreach ([1=>'seller',2=>'buyer',3=>'buyer',4=>'seller',5=>'admin'] as $id=>$role) DB::table('users')->insert(['id'=>$id,'name'=>'User '.$id,'role'=>$role,'status'=>'active']);
        DB::table('sellers')->insert([['id'=>11,'user_id'=>1,'status'=>'approved'],['id'=>22,'user_id'=>4,'status'=>'approved']]);
        $this->seller=User::findOrFail(1);$this->buyer=User::findOrFail(2);
        $this->conversation=$this->makeConversation(1);
        $this->imageRoot=sys_get_temp_dir().'/suki-message-images-'.bin2hex(random_bytes(12));
        config(['messaging.attachments_enabled'=>true,'filesystems.disks.message_attachments.root'=>$this->imageRoot]);
        Storage::forgetDisk('message_attachments');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('message_attachments');
        if (isset($this->imageRoot) && is_dir($this->imageRoot)) {
            $resolved=realpath($this->imageRoot);$temp=realpath(sys_get_temp_dir());
            $this->assertNotFalse($resolved);$this->assertNotFalse($temp);
            $this->assertSame(strtolower($temp),strtolower(dirname($resolved)));
            $this->assertStringStartsWith('suki-message-images-',basename($resolved));
            File::deleteDirectory($resolved);
        }
        parent::tearDown();
    }

    private function makeConversation(int $id): Conversation
    {
        DB::table('conversations')->insert(['id'=>$id,'reference'=>(string)Str::uuid(),'type'=>'STORE_INQUIRY','status'=>'ACTIVE','seller_id'=>11]);
        DB::table('conversation_participants')->insert([
            ['conversation_id'=>$id,'user_id'=>1,'role_context'=>'SELLER','seller_id'=>11],
            ['conversation_id'=>$id,'user_id'=>2,'role_context'=>'BUYER','seller_id'=>null],
        ]);
        return Conversation::findOrFail($id);
    }

    private function bytes(string $format='png', int $width=32, int $height=16): string
    {
        $image=imagecreatetruecolor($width,$height);imagefill($image,0,0,imagecolorallocate($image,12,100,70));
        ob_start();match ($format) {'png'=>imagepng($image),'jpg'=>imagejpeg($image),'webp'=>imagewebp($image)};
        $bytes=ob_get_clean();unset($image);return $bytes;
    }

    private function image(string $name='photo.png', ?string $bytes=null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name,$bytes ?? $this->bytes());
    }

    private function send(array $data, string $portal='seller', ?User $actor=null)
    {
        return $this->actingAs($actor ?? ($portal==='buyer' ? $this->buyer : $this->seller))
            ->post(route($portal.'.messages.store',$this->conversation->reference),$data,['Accept'=>'application/json']);
    }

    private function assertNoUpload(): void
    {
        $this->assertSame(0,Message::count());$this->assertSame(0,MessageAttachment::count());
        $this->assertSame([],Storage::disk('message_attachments')->allFiles());
        $this->assertNull($this->conversation->fresh()->last_message_at);
        $this->assertNull(DB::table('conversation_participants')->where('user_id',1)->value('last_read_message_id'));
    }

    public function test_photo_unavailability_reports_the_first_gate_without_connecting_to_postgresql(): void
    {
        $files=app(MessageAttachmentService::class);
        $this->assertNull($files->uploadUnavailableReason());
        $default=config('database.default');
        config(['database.default'=>'pgsql','messaging.attachments_enabled'=>false,'messaging.deployment_verified'=>false]);
        $this->assertStringContainsString('not been enabled',$files->uploadUnavailableReason());
        config(['messaging.attachments_enabled'=>true]);
        $this->assertStringContainsString('deployment verification',$files->uploadUnavailableReason());
        // Neither branch queries the configured PostgreSQL connection.
        config(['database.default'=>$default,'filesystems.disks.message_attachments.visibility'=>'public']);
        $this->assertStringContainsString('Private photo storage',$files->uploadUnavailableReason());
        config(['filesystems.disks.message_attachments.visibility'=>'private']);Schema::drop('message_attachments');
        $this->assertStringContainsString('database setup',$files->uploadUnavailableReason());
    }

    public function test_buyer_and_seller_s3_photo_routes_use_private_proxy_and_deny_guests_and_foreign_participants(): void
    {
        $transport=new \Tests\Support\InMemoryChatS3Transport();$configuration=$transport::config();
        config(['filesystems.disks.message_attachments'=>$configuration]);
        Storage::set('message_attachments',\App\Filesystem\PrivateChatS3Filesystem::make(app('filesystem'),$configuration,$transport));
        $buyer=$this->send(['images'=>[$this->image()]],'buyer')->assertCreated()->json('message.attachments.0');
        $seller=$this->send(['body'=>'Photo reply','images'=>[$this->image()]],'seller')->assertCreated()->json('message.attachments.0');
        $this->assertCount(4,$transport->objects);$this->assertSame(2,MessageAttachment::count());
        foreach([['buyer',2],['seller',1]] as [$portal,$id]){
            $this->actingAs(User::findOrFail($id));
            foreach(['show','thumbnail','download'] as $action){$response=$this->get(route($portal.'.messages.attachments.'.$action,['conversation'=>$this->conversation->reference,'attachment'=>$buyer['id']]))->assertOk();$this->assertNotEmpty($response->streamedContent());}
            $this->getJson(route($portal.'.messages.poll',$this->conversation->reference))->assertOk()->assertJsonCount(2,'messages')->assertJsonMissingPath('messages.0.attachments.0.path')->assertDontSee('FAKE-TEST-SECRET')->assertDontSee('storage.supabase.co');
        }
        foreach([['buyer',3],['seller',4]] as [$portal,$id])$this->actingAs(User::findOrFail($id))->get(route($portal.'.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>$seller['id']]))->assertForbidden();
        auth()->logout();$this->getJson(route('buyer.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>$buyer['id']]))->assertUnauthorized();
        \Illuminate\Support\Facades\Log::spy();$transport->failReads=true;$this->actingAs($this->buyer)->get(route('buyer.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>$buyer['id']]))->assertStatus(503)->assertDontSee('FAKE-TEST')->assertDontSee('storage.supabase.co');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with('Private chat image read failed.');
        $this->assertFalse(config('returns.backend_enabled'));$this->assertFalse(config('returns.earning_release_enabled'));
    }

    public function test_s3_late_thumbnail_failure_removes_only_new_objects_and_rolls_back_metadata(): void
    {
        $transport=new \Tests\Support\InMemoryChatS3Transport();$transport->objects['existing.png']='Preserve';$transport->failPut=2;
        $configuration=$transport::config();config(['filesystems.disks.message_attachments'=>$configuration]);
        Storage::set('message_attachments',\App\Filesystem\PrivateChatS3Filesystem::make(app('filesystem'),$configuration,$transport));
        $this->send(['images'=>[$this->image()]])->assertUnprocessable()->assertDontSee('FAKE-TEST')->assertDontSee('storage.supabase.co');
        $this->assertSame(['existing.png'=>'Preserve'],$transport->objects);$this->assertSame(0,Message::count());$this->assertSame(0,MessageAttachment::count());
    }

    public function test_s3_database_failure_deletes_new_keys_and_logs_no_sdk_or_path_details(): void
    {
        $transport=new \Tests\Support\InMemoryChatS3Transport();$transport->objects['existing.png']='Preserve';
        $configuration=$transport::config();config(['filesystems.disks.message_attachments'=>$configuration]);
        Storage::set('message_attachments',\App\Filesystem\PrivateChatS3Filesystem::make(app('filesystem'),$configuration,$transport));
        DB::statement("CREATE TRIGGER attachment_s3_test_fail BEFORE INSERT ON message_attachments BEGIN SELECT RAISE(ABORT, 'PRIVATE-SERVER-DETAIL'); END");
        \Illuminate\Support\Facades\Log::spy();
        $this->send(['images'=>[$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('images')->assertDontSee('PRIVATE-SERVER-DETAIL')->assertDontSee('FAKE-TEST')->assertDontSee('storage.supabase.co');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with('Chat attachment persistence failed.');
        $this->assertSame(['existing.png'=>'Preserve'],$transport->objects);$this->assertSame(0,Message::count());$this->assertSame(0,MessageAttachment::count());
    }

    public function test_text_image_only_and_mixed_messages_preserve_canonical_identity_and_poll_contract(): void
    {
        $this->send(['body'=>'Text only'])->assertCreated()->assertJsonPath('message.body','Text only')->assertJsonCount(0,'message.attachments');
        $image=$this->send(['images'=>[$this->image()],'sender_id'=>3,'message_id'=>999,'disk'=>'public','path'=>'../forged.png'])
            ->assertCreated()->assertJsonPath('message.sender.id',1)->assertJsonPath('message.body','')->assertJsonPath('message.type','TEXT')->json('message');
        $this->send(['body'=>'Mixed buyer reply','images'=>[$this->image('buyer.jpg',$this->bytes('jpg'))]],'buyer')->assertCreated()->assertJsonPath('message.sender.id',2)->assertJsonCount(1,'message.attachments');
        $this->assertSame([0],Message::find($image['id'])->attachments->pluck('position')->all());
        $response=$this->actingAs($this->buyer)->getJson(route('buyer.messages.poll',['conversation'=>$this->conversation->reference,'after_id'=>0]))->assertOk()->assertJsonCount(3,'messages')->assertJsonCount(1,'messages.1.attachments')->assertJsonMissingPath('messages.1.attachments.0.path')->assertJsonMissingPath('messages.1.attachments.0.disk');
        $this->assertStringNotContainsString('/storage/',$response->getContent());
        $this->assertStringNotContainsString($this->imageRoot,$response->getContent());
        $this->assertSame(3,(int)DB::table('conversation_participants')->where('user_id',2)->value('last_read_message_id'));
        $this->assertArrayNotHasKey('path',MessageAttachment::first()->toArray());
    }

    public function test_five_images_are_ordered_and_sixth_is_rejected_before_persistence(): void
    {
        $files=[];for($i=0;$i<6;$i++)$files[]=$this->image('image-'.$i.'.png');
        $this->send(['images'=>$files])->assertUnprocessable()->assertJsonValidationErrors('images');$this->assertNoUpload();
        $this->send(['images'=>array_slice($files,0,5)])->assertCreated()->assertJsonCount(5,'message.attachments');
        $this->assertSame(range(0,4),Message::sole()->attachments->pluck('position')->all());
        $this->assertCount(10,Storage::disk('message_attachments')->allFiles());
    }

    public function test_unsupported_spoofed_corrupt_oversized_and_excessive_dimension_images_are_rejected(): void
    {
        foreach ([
            $this->image('script.php','<?php echo 1;'),$this->image('vector.svg','<svg xmlns="http://www.w3.org/2000/svg"/>'),
            $this->image('document.pdf',"%PDF-1.4\nNot an image"),$this->image('animation.gif',base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==')),
            $this->image('photo.jpg'),$this->image('fake.png','not an image'),
            $this->image('huge.png',$this->bytes().str_repeat('x',5242880)),
            $this->image('wide.png',$this->bytes('png',4097,1)),
            $this->image('pixels.png',$this->bytes('png',2001,2000)),
            $this->image('corrupt.png',substr($this->bytes(),0,33)),
        ] as $file) {
            $this->send(['body'=>'Must not persist','images'=>[$file]])->assertUnprocessable()->assertJsonValidationErrors('images.0');$this->assertNoUpload();
        }
        $this->send(['images'=>['not-a-file']])->assertUnprocessable();$this->assertNoUpload();
        $this->send(['images'=>[]])->assertUnprocessable();$this->assertNoUpload();
        $this->send(['body'=>str_repeat('x',10001),'images'=>[$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('body');$this->assertNoUpload();
    }

    public function test_generated_paths_metadata_removal_and_private_thumbnails_for_each_supported_format(): void
    {
        foreach (['jpg','png','webp'] as $format) {
            $bytes=$this->bytes($format,640,320);
            if ($format==='jpg') {
                $metadata="Exif\0\0GPS-TEST-PRIVATE";
                $bytes=substr($bytes,0,2)."\xff\xe1".pack('n',strlen($metadata)+2).$metadata.substr($bytes,2);
            }
            $data=$this->send(['images'=>[$this->image('../../evil.'.$format,$bytes)]])->assertCreated()->json('message.attachments.0');
            $record=MessageAttachment::findOrFail($data['id']);$stored=Storage::disk('message_attachments')->get($record->path);
            $this->assertSame($record->mime_type,getimagesizefromstring($stored)['mime']);
            $this->assertStringNotContainsString('GPS-TEST-PRIVATE',$stored);
            $this->assertStringNotContainsString('evil',$record->path);$this->assertStringNotContainsString('..',$record->path);
            $this->assertSame('evil.'.$format,$record->original_name);$this->assertSame('message_attachments',$record->disk);
            $this->assertSame('private',config('filesystems.disks.message_attachments.visibility'));
            // Windows mode bits do not establish ACL visibility. Web privacy
            // is independently enforced by root checks and authorized routes.
            if (PHP_OS_FAMILY!=='Windows') $this->assertSame('private',Storage::disk('message_attachments')->getVisibility($record->path));
            $inline=$this->get($data['url'])->assertOk()->assertHeader('Content-Type',$record->mime_type)->assertHeader('X-Content-Type-Options','nosniff');
            $this->assertStringContainsString('inline;',$inline->headers->get('Content-Disposition'));$this->assertStringContainsString('private',$inline->headers->get('Cache-Control'));
            $this->assertSame($stored,$inline->streamedContent());
            $thumbnail=$this->get($data['thumbnail_url'])->assertOk()->streamedContent();$dimensions=getimagesizefromstring($thumbnail);
            $this->assertSame([320,160],array_slice($dimensions,0,2));
            $download=$this->get($data['download_url'])->assertOk();
            $this->assertStringContainsString('attachment;',$download->headers->get('Content-Disposition'));$this->assertStringNotContainsString('evil',$download->headers->get('Content-Disposition'));
            $this->assertSame($stored,$download->streamedContent());
        }
    }

    public function test_upload_and_delivery_reauthorize_portal_membership_shop_and_conversation(): void
    {
        $data=$this->send(['images'=>[$this->image()]])->assertCreated()->json('message.attachments.0');
        foreach ([['seller',4],['buyer',3]] as [$portal,$user]) {
            $this->send(['images'=>[$this->image()]],$portal,User::findOrFail($user))->assertForbidden();
            foreach (['show','download','thumbnail'] as $action) $this->get(route($portal.'.messages.attachments.'.$action,['conversation'=>$this->conversation->reference,'attachment'=>$data['id']]))->assertForbidden();
        }
        $other=$this->makeConversation(2);$this->actingAs($this->seller);
        $this->get(route('seller.messages.attachments.show',['conversation'=>$other->reference,'attachment'=>$data['id']]))->assertNotFound();
        $this->get(route('buyer.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>$data['id']]))->assertForbidden();
        DB::table('conversation_participants')->where('conversation_id',1)->where('user_id',1)->update(['left_at'=>now()]);
        $this->get($data['url'])->assertForbidden();$this->send(['images'=>[$this->image()]])->assertForbidden();
        $this->actingAs($this->buyer);$buyer=route('buyer.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>$data['id']]);
        $response=$this->get($buyer)->assertOk();$this->assertNotEmpty($response->streamedContent());
        $this->assertSame(1,MessageAttachment::count());
    }

    public function test_closed_archived_and_suspended_accounts_cannot_upload(): void
    {
        foreach (['CLOSED','ARCHIVED'] as $status) {
            $this->conversation->update(['status'=>$status]);$this->send(['images'=>[$this->image()]])->assertForbidden();$this->assertNoUpload();
        }
        $this->conversation->update(['status'=>'ACTIVE']);
        $this->seller->forceFill(['is_suspended'=>true])->save();$this->send(['images'=>[$this->image()]])->assertForbidden();$this->assertNoUpload();
    }

    public function test_database_failure_rolls_back_message_cursor_metadata_and_both_files_without_exposing_paths(): void
    {
        DB::statement("CREATE TRIGGER attachment_test_fail BEFORE INSERT ON message_attachments BEGIN SELECT RAISE(ABORT, 'PRIVATE-SERVER-DETAIL'); END");
        $response=$this->send(['images'=>[$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertStringNotContainsString('PRIVATE-SERVER-DETAIL',$response->getContent());$this->assertStringNotContainsString($this->imageRoot,$response->getContent());
        $this->assertNoUpload();
    }

    public function test_filesystem_failure_is_sanitized_and_rolls_back_the_message(): void
    {
        $disk=\Mockery::mock(FilesystemAdapter::class);$disk->shouldReceive('put')->once()->andReturn(false);$disk->shouldReceive('delete')->once()->andReturn(true);
        $manager=Storage::getFacadeRoot();
        try {
        Storage::shouldReceive('disk')->with('message_attachments')->andReturn($disk);
        $this->send(['images'=>[$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame(0,Message::count());$this->assertSame(0,MessageAttachment::count());
        } finally { Storage::swap($manager); }
    }

    public function test_missing_and_tampered_files_return_generic_errors(): void
    {
        $data=$this->send(['images'=>[$this->image()]])->assertCreated()->json('message.attachments.0');
        $this->get(route('seller.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>str_repeat('9',50)]))->assertNotFound();
        $record=MessageAttachment::findOrFail($data['id']);Storage::disk('message_attachments')->delete($record->path);
        $this->get($data['url'])->assertNotFound();
        $record->update(['path'=>'../private-document.png']);$this->get($data['download_url'])->assertNotFound();
        $record->update(['disk'=>'seller_documents']);$this->get($data['url'])->assertNotFound();
    }

    public function test_disabled_or_production_feature_needs_no_attachment_table_and_preserves_text(): void
    {
        config(['messaging.attachments_enabled'=>false]);Schema::dropIfExists('message_attachments');
        DB::enableQueryLog();
        $this->send(['body'=>'Legacy text without migrated table'])->assertCreated()->assertJsonMissingPath('message.attachments');
        $this->getJson(route('seller.messages.poll',$this->conversation->reference))->assertOk()->assertJsonMissingPath('messages.0.attachments');
        $this->send(['body'=>'Not silently accepted','images'=>[$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->get(route('seller.messages.attachments.show',['conversation'=>$this->conversation->reference,'attachment'=>1]))->assertNotFound();
        $queries=DB::getQueryLog();DB::disableQueryLog();
        foreach ($queries as $query) $this->assertStringNotContainsString('message_attachments',$query['query']);
        config(['messaging.attachments_enabled'=>true]);$this->app['env']='production';
        $this->assertFalse(app(MessageAttachmentService::class)->available());
        $this->actingAs($this->seller)->withSession(['_token'=>'gate-test-token'])
            ->post(route('seller.messages.store',$this->conversation->reference),['_token'=>'gate-test-token','images'=>[$this->image()]],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertSame(1,Message::count());
    }

    public function test_public_or_missing_schema_configuration_fails_closed_and_admin_text_still_works(): void
    {
        $default=config('database.default');config(['database.default'=>'pgsql']);
        $this->assertFalse(app(MessageAttachmentService::class)->available(),'A local environment must not enable shared PostgreSQL uploads.');
        config(['database.default'=>$default]);
        config(['filesystems.disks.message_attachments.root'=>storage_path('app/public')]);
        $this->send(['images'=>[$this->image()]])->assertUnprocessable();
        config(['filesystems.disks.message_attachments.root'=>$this->imageRoot]);
        DB::table('conversation_participants')->insert(['conversation_id'=>1,'user_id'=>5,'role_context'=>'ADMIN']);
        $admin=User::findOrFail(5);
        $this->send(['body'=>'Admin text'],'admin',$admin)->assertCreated()->assertJsonPath('message.body','Admin text');
        $this->send(['body'=>'No Admin uploads','images'=>[$this->image()]],'admin',$admin)->assertForbidden();
        Schema::dropIfExists('message_attachments');$this->send(['images'=>[$this->image()]])->assertUnprocessable();
        $this->assertSame(1,Message::count());
    }

    public function test_schema_enforces_fk_positions_private_path_uniqueness_and_positive_dimensions(): void
    {
        $this->send(['images'=>[$this->image()]])->assertCreated();$record=MessageAttachment::sole();
        foreach ([['width'=>0],['height'=>-1],['position'=>5],['size'=>0],['message_id'=>999999]] as $changes) {
            try { DB::table('message_attachments')->where('id',$record->id)->update($changes);$this->fail('Invalid attachment bounds/FK accepted.'); }
            catch (\Illuminate\Database\QueryException) { $this->assertSame(32,MessageAttachment::sole()->width); }
        }
        foreach ([['position'=>0],['position'=>1]] as $changes) {
            try { DB::table('message_attachments')->insert($changes+collect($record->getAttributes())->except('id')->all());$this->fail('Duplicate attachment identity/path accepted.'); }
            catch (\Illuminate\Database\QueryException) { $this->assertSame(1,MessageAttachment::count()); }
        }
        Message::sole()->delete();$this->assertSame(0,MessageAttachment::count());
        Schema::dropIfExists('message_attachments');$this->assertFalse(Schema::hasTable('message_attachments'));
    }

    public function test_later_decode_failure_removes_earlier_images_and_rolls_back_all_metadata(): void
    {
        $png=$this->bytes();$offset=strpos($png,'IDAT');$length=unpack('N',substr($png,$offset-4,4))[1];
        $badData=str_repeat("\0",$length);
        $corrupt=substr($png,0,$offset+4).$badData.pack('N',crc32('IDAT'.$badData)).substr($png,$offset+8+$length);
        $this->send(['images'=>[$this->image('first.png'),$this->image('second.png',$corrupt)]])->assertUnprocessable()->assertJsonValidationErrors('images.1');
        $this->assertNoUpload();
    }

    public function test_normal_form_redirect_and_verification_restrictions_remain_intact(): void
    {
        $this->actingAs($this->buyer)->post(route('buyer.messages.store',$this->conversation->reference),['body'=>'Ordinary form','images'=>[$this->image()]])
            ->assertRedirect(route('buyer.messages.show',$this->conversation->reference));
        $this->assertSame('Ordinary form',Message::sole()->body);$this->assertSame(1,MessageAttachment::count());
        $this->buyer->forceFill(['verification_required_at'=>now(),'email_verified_at'=>null])->save();
        $this->send(['images'=>[$this->image()]],'buyer')->assertForbidden();
        $this->assertSame(1,Message::count());
    }

    public function test_exact_five_mib_boundary_is_accepted_and_normalized_before_storage(): void
    {
        $png=$this->bytes();$bytes=$png.str_repeat('x',5242880-strlen($png));
        $result=$this->send(['images'=>[$this->image('boundary.png',$bytes)]])->assertCreated()->json('message.attachments.0');
        $this->assertLessThanOrEqual(5242880,$result['size']);$this->assertSame(32,$result['width']);
    }
}
