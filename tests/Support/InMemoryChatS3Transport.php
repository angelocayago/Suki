<?php

namespace Tests\Support;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** AWS SDK HTTP boundary substitute: RAM only; never resolves DNS or opens sockets. */
class InMemoryChatS3Transport
{
    public array $objects=[];
    public array $requests=[];
    public ?int $failPut=null;
    public bool $failReads=false;
    private int $puts=0;

    public static function config(): array
    {
        return ['driver'=>'chat_private_s3','endpoint'=>'https://isolated-chat.storage.supabase.co/storage/v1/s3',
            'region'=>'ap-southeast-1','bucket'=>'chat-test','key'=>'FAKE-TEST-ACCESS','secret'=>'FAKE-TEST-SECRET',
            'visibility'=>'private','serve'=>false,'throw'=>true,'use_path_style_endpoint'=>true,'private_bucket_verified'=>true];
    }

    public function __invoke(RequestInterface $request,array $options): PromiseInterface
    {
        if($request->getUri()->getHost()!=='isolated-chat.storage.supabase.co') throw new \RuntimeException('Unexpected test host.');
        $prefix='/storage/v1/s3/chat-test/';$path=rawurldecode($request->getUri()->getPath());
        if(!str_starts_with($path,$prefix))throw new \RuntimeException('Unexpected test bucket.');
        $key=substr($path,strlen($prefix));$method=$request->getMethod();$this->requests[]=$request;
        if($method==='PUT'){
            if(++$this->puts >= ($this->failPut ?? PHP_INT_MAX))return $this->failure($request);
            $this->objects[$key]=(string)$request->getBody();return Create::promiseFor(new Response(200,['ETag'=>'"test-etag"']));
        }
        if($method==='DELETE'){unset($this->objects[$key]);return Create::promiseFor(new Response(204));}
        if(!in_array($method,['HEAD','GET'],true))throw new \RuntimeException('Unexpected test operation.');
        if(!array_key_exists($key,$this->objects))return Create::promiseFor(new Response(404,[], '<Error><Code>NoSuchKey</Code></Error>'));
        if($this->failReads && $method==='GET')return $this->failure($request);
        return Create::promiseFor(new Response(200,['Content-Length'=>(string)strlen($this->objects[$key]),'Content-Type'=>'image/png','Last-Modified'=>'Wed, 04 Nov 2026 12:00:00 GMT'],$method==='GET'?$this->objects[$key]:''));
    }

    private function failure(RequestInterface $request): PromiseInterface
    {
        return Create::rejectionFor(new \GuzzleHttp\Exception\ServerException('Isolated storage failure',$request,new Response(503,[], '<Error><Code>ServiceUnavailable</Code></Error>')));
    }
}
