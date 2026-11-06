<?php

namespace Tests\Feature;

use App\Filesystem\PrivateChatS3Filesystem;
use Tests\Support\InMemoryChatS3Transport;
use Tests\TestCase;

class PrivateChatS3StorageTest extends TestCase
{
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated test environment required.');
        parent::setUp();
    }

    public function test_s3_requires_explicit_safe_configuration_and_bucket_verification(): void
    {
        $valid=InMemoryChatS3Transport::config();$this->assertTrue(PrivateChatS3Filesystem::configured($valid));
        foreach(['key','secret','bucket','region','endpoint'] as $field)$this->assertFalse(PrivateChatS3Filesystem::configured(array_replace($valid,[$field=>''])));
        foreach([['private_bucket_verified'=>false],['visibility'=>'public'],['serve'=>true],['throw'=>false],['use_path_style_endpoint'=>false],['root'=>'public'],['url'=>'https://public.invalid'],['endpoint'=>'http://isolated-chat.supabase.co/storage/v1/s3'],['endpoint'=>'https://outside.invalid/storage/v1/s3'],['endpoint'=>'https://user:secret@isolated-chat.supabase.co/storage/v1/s3'],['endpoint'=>$valid['endpoint'].'?token=secret']] as $invalid){
            $config=array_replace($valid,$invalid);$this->assertFalse(PrivateChatS3Filesystem::configured($config));
            try{PrivateChatS3Filesystem::make(app('filesystem'),$config,fn()=>throw new \RuntimeException('Transport must not run'));$this->fail('Unsafe configuration accepted');}
            catch(\RuntimeException $e){$this->assertSame('Private chat object storage is not configured and verified.',$e->getMessage());}
        }
    }

    public function test_official_adapter_removes_acl_headers_and_round_trips_only_through_mocked_transport(): void
    {
        $transport=new InMemoryChatS3Transport();$disk=PrivateChatS3Filesystem::make(app('filesystem'),$transport::config(),$transport);
        $key='aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb.png';
        $this->assertTrue($disk->put($key,'isolated bytes',['visibility'=>'private']));
        $this->assertTrue($disk->exists($key));$stream=$disk->readStream($key);$this->assertSame('isolated bytes',stream_get_contents($stream));fclose($stream);
        $this->assertSame(14,$disk->size($key));$this->assertTrue($disk->delete($key));$this->assertSame([],$transport->objects);
        foreach($transport->requests as $request){$this->assertFalse($request->hasHeader('x-amz-acl'));$this->assertFalse($request->hasHeader('x-amz-grant-read'));}
        $this->assertFalse($disk->providesTemporaryUrls());
        foreach(['url','temporaryUrl','temporaryUploadUrl'] as $method){
            try{$method==='url'?$disk->$method($key):$disk->$method($key,now()->addMinute());$this->fail('Storage URL exposed');}
            catch(\LogicException $e){$this->assertStringContainsString('authorized chat',$e->getMessage());}
        }
    }

    public function test_public_acl_and_bucket_acl_changes_fail_before_the_http_boundary(): void
    {
        $transport=new InMemoryChatS3Transport();$disk=PrivateChatS3Filesystem::make(app('filesystem'),$transport::config(),$transport);
        try{$disk->put('file.png','bytes',['visibility'=>'public']);$this->fail('Public ACL accepted');}catch(\Throwable $e){$this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$e);}
        try{$disk->getClient()->putBucketAcl(['Bucket'=>'chat-test','ACL'=>'public-read']);$this->fail('Bucket ACL accepted');}catch(\Throwable $e){$this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class,$e);}
        $this->assertSame([],$transport->requests);
    }
}
