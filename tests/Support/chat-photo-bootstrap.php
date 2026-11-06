<?php

// Dedicated verification process only. Force database values before Laravel boots.
$projectRoot=dirname(__DIR__,2);
$tempBase=realpath(sys_get_temp_dir());
if($tempBase===false)throw new RuntimeException('Temporary directory is unavailable.');
$root=str_replace('\\','/',$tempBase).'/suki-chat-photos-'.bin2hex(random_bytes(12));
$marker=bin2hex(random_bytes(16));
foreach(['framework/views','framework/cache','framework/sessions','app/private/messages','app/public','logs','cache'] as $directory){
    if(!mkdir($root.'/'.$directory,0700,true) && !is_dir($root.'/'.$directory))throw new RuntimeException('Cannot create isolated test storage.');
}
file_put_contents($root.'/.test-owned',$marker);
$cacheRoot=PHP_OS_FAMILY==='Windows'?substr($root,2):$root;
$values=[
    'APP_BASE_PATH'=>$projectRoot,'APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://localhost',
    'APP_KEY'=>'base64:'.base64_encode(random_bytes(32)),
    'DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','DB_URL'=>'','DATABASE_URL'=>'',
    'DB_HOST'=>'127.0.0.1','DB_PORT'=>'0','DB_USERNAME'=>'isolated','DB_PASSWORD'=>'',
    'CHAT_PHOTOS_STORAGE_DRIVER'=>'local','MESSAGE_ATTACHMENTS_ENABLED'=>'false',
    'MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED'=>'false','CHAT_PHOTOS_S3_PRIVATE_BUCKET_VERIFIED'=>'false',
    'MESSAGE_ATTACHMENTS_ROOT'=>$root.'/app/private/messages','FILESYSTEM_DISK'=>'local',
    // Existing financial/UI regression fixtures require explicit disposable roots.
    'SELLER_RETURNS_CHECK_DIR'=>$root,'SELLER_COMPLAINTS_CHECK_DIR'=>$root,
    'LARAVEL_STORAGE_PATH'=>$root,'VIEW_COMPILED_PATH'=>$root.'/framework/views',
    'APP_CONFIG_CACHE'=>$cacheRoot.'/cache/config.php','APP_ROUTES_CACHE'=>$cacheRoot.'/cache/routes.php',
    'APP_PACKAGES_CACHE'=>$cacheRoot.'/cache/packages.php','APP_SERVICES_CACHE'=>$cacheRoot.'/cache/services.php',
    'APP_EVENTS_CACHE'=>$cacheRoot.'/cache/events.php','CACHE_STORE'=>'array','SESSION_DRIVER'=>'array',
    'MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','BROADCAST_CONNECTION'=>'null','LOG_CHANNEL'=>'null',
];
foreach($values as $name=>$value){putenv($name.'='.$value);$_ENV[$name]=$value;$_SERVER[$name]=$value;}

register_shutdown_function(static function()use($root,$tempBase,$marker): void {
    $resolved=realpath($root);if($resolved===false)return;
    $normalize=static fn($path)=>strtolower(rtrim(str_replace('\\','/',$path),'/'));
    if($normalize($resolved)!==$normalize($root)
        || !str_starts_with($normalize($resolved),$normalize($tempBase).'/suki-chat-photos-')
        || !is_file($root.'/.test-owned') || file_get_contents($root.'/.test-owned')!==$marker){
        fwrite(STDERR,"Isolated test cleanup refused: ownership check failed.\n");return;
    }
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){if($file->isDir() && !$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}
    rmdir($root);
});

require $projectRoot.'/vendor/autoload.php';
if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new RuntimeException('Test database isolation failed.');
fwrite(STDOUT,"Isolated chat verification: SQLite :memory:, local private temporary storage, fake contacts and files only.\n");
