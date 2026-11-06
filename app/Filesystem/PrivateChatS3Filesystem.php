<?php

namespace App\Filesystem;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3;

/** Dedicated Supabase transport; no changes to the application's general S3 disk. */
class PrivateChatS3Filesystem extends \Illuminate\Filesystem\AwsS3V3Adapter
{
    public static function configured(array $config): bool
    {
        if (!class_exists(S3Client::class) || !class_exists(FlysystemS3::class)) return false;
        if (($config['driver'] ?? null)!=='chat_private_s3' || ($config['visibility'] ?? null)!=='private'
            || ($config['serve'] ?? true)!==false || ($config['throw'] ?? false)!==true
            || ($config['use_path_style_endpoint'] ?? false)!==true
            || !filter_var($config['private_bucket_verified'] ?? false,FILTER_VALIDATE_BOOLEAN)
            || !empty($config['root']) || !empty($config['url']) || !empty($config['temporary_url'])) return false;
        foreach (['endpoint','region','bucket','key','secret'] as $field) if (!is_string($config[$field] ?? null) || trim($config[$field])==='') return false;
        $url=parse_url($config['endpoint']);
        return is_array($url) && ($url['scheme'] ?? '')==='https'
            && preg_match('/^[a-z0-9-]+(?:\.storage)?\.supabase\.co$/D',$url['host'] ?? '')===1
            && rtrim($url['path'] ?? '','/')==='/storage/v1/s3'
            && !isset($url['user']) && !isset($url['pass']) && !isset($url['port'])
            && !isset($url['query']) && !isset($url['fragment'])
            && preg_match('/^[a-z0-9-]{2,40}$/D',$config['region'])===1
            && preg_match('/^[a-z0-9][a-z0-9._-]{1,62}$/D',$config['bucket'])===1;
    }

    /** Optional transport is for isolated tests, never loaded from env/config. */
    public static function make(FilesystemManager $manager,array $config,?callable $testTransport=null): self
    {
        if (!self::configured($config)) throw new \RuntimeException('Private chat object storage is not configured and verified.');
        $safe=array_intersect_key($config,array_flip(['endpoint','region','bucket','key','secret']));
        $safe += ['driver'=>'s3','version'=>'latest','visibility'=>'private','throw'=>true,'use_path_style_endpoint'=>true,
            'stream_reads'=>true,'http'=>['connect_timeout'=>5,'timeout'=>30,'verify'=>true],
            'retries'=>0,'request_checksum_calculation'=>'when_required','response_checksum_validation'=>'when_required'];
        if ($testTransport) $safe['http_handler']=$testTransport;
        try {
            $base=$manager->createS3Driver($safe);
            $base->getClient()->getHandlerList()->appendInit(Middleware::mapCommand(function(CommandInterface $command){
                $allowed=['PutObject','GetObject','HeadObject','DeleteObject','DeleteObjects','ListObjectsV2',
                    'CreateMultipartUpload','UploadPart','CompleteMultipartUpload','AbortMultipartUpload'];
                if (!in_array($command->getName(),$allowed,true)) throw new \LogicException('Unsupported private chat storage operation.');
                if (isset($command['ACL']) && $command['ACL']!=='private') throw new \LogicException('Public chat storage ACLs are forbidden.');
                // Supabase does not implement S3 ACL headers. Bucket privacy and
                // deployment verification are required instead of object ACLs.
                unset($command['ACL']);
                foreach (['GrantFullControl','GrantRead','GrantReadACP','GrantWrite','GrantWriteACP'] as $grant) {
                    if (isset($command[$grant])) throw new \LogicException('Chat storage grants are forbidden.');
                }
                return $command;
            }),'private_chat_no_acl');
            return new self($base->getDriver(),$base->getAdapter(),$safe,$base->getClient());
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Private chat object storage could not be initialized.');
        }
    }

    public function url($path) { throw new \LogicException('Use the authorized chat attachment route.'); }
    public function providesTemporaryUrls() { return false; }
    public function temporaryUrl($path,$expiration,array $options=[]) { throw new \LogicException('Use the authorized chat attachment route.'); }
    public function temporaryUploadUrl($path,$expiration,array $options=[]) { throw new \LogicException('Use the authorized chat upload route.'); }
}
