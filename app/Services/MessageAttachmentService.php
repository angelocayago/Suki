<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Conversation;
use GdImage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MessageAttachmentService
{
    public const DISK = 'message_attachments';
    private const FORMATS = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    private const MAX_BYTES = 5242880;

    /** Disabled requests never touch the attachment schema or filesystem. */
    public function available(): bool
    {
        return filter_var(config('messaging.attachments_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && $this->databaseDeploymentVerified()
            && $this->privateConfiguration()
            && extension_loaded('gd') && extension_loaded('fileinfo')
            && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')
            && function_exists('imagecreatefrompng') && function_exists('imagepng')
            && function_exists('imagecreatefromwebp') && function_exists('imagewebp')
            && Schema::hasTable('message_attachments');
    }

    private function databaseDeploymentVerified(): bool
    {
        $connection=config('database.connections.'.config('database.default'),[]);
        if (app()->environment(['local','testing']) && ($connection['driver'] ?? null)==='sqlite' && empty($connection['url'])) return true;
        // Static portability alone is not a deployment verification. Default OFF;
        // no shared connection is inspected when this attestation is absent.
        return ($connection['driver'] ?? null)==='pgsql'
            && filter_var(config('messaging.deployment_verified',false),FILTER_VALIDATE_BOOLEAN);
    }

    public function canUpload(): bool
    {
        return $this->available() && $this->uploadLimitsSufficient();
    }

    /** Explain the first blocking gate without querying undeployed schema. */
    public function uploadUnavailableReason(): ?string
    {
        if (!filter_var(config('messaging.attachments_enabled', false), FILTER_VALIDATE_BOOLEAN)) return 'Photo sharing has not been enabled yet. You can still send text messages.';
        if (!$this->databaseDeploymentVerified()) return 'Photo sharing is awaiting deployment verification. You can still send text messages.';
        if (!$this->privateConfiguration()) return 'Private photo storage must be configured before sharing photos. You can still send text messages.';
        if (!extension_loaded('gd') || !extension_loaded('fileinfo')
            || !function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')
            || !function_exists('imagecreatefrompng') || !function_exists('imagepng')
            || !function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) return 'Photo processing is unavailable on this server. You can still send text messages.';
        if (!Schema::hasTable('message_attachments')) return 'Photo sharing is awaiting its attachment database setup. You can still send text messages.';
        if (!$this->uploadLimitsSufficient()) return 'Photo uploads exceed the server upload allowance. You can still send text messages.';
        return null;
    }

    public function uploadLimitsSufficient(): bool
    {
        $bytes=function (string $value): int {
            $value=trim($value);
            $factor=match (strtoupper(substr($value,-1))) {'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1};
            return (int)$value*$factor;
        };
        $post=$bytes(ini_get('post_max_size'));
        return $bytes(ini_get('upload_max_filesize'))>=self::MAX_BYTES
            && ($post===0 || $post>=33554432) && (int)ini_get('max_file_uploads')>=5;
    }

    public function assertUploadsAvailable(): void
    {
        if (!$this->canUpload()) throw ValidationException::withMessages(['images'=>'Image uploads are unavailable. Storage, deployment and upload limits must be verified.']);
    }

    public function metadata(MessageAttachment $file, Conversation $conversation, string $prefix): array
    {
        $parameters=['conversation'=>$conversation->reference,'attachment'=>$file->id];
        return ['id'=>$file->id,'position'=>$file->position,'original_name'=>$file->original_name,'mime_type'=>$file->mime_type,
            'size'=>$file->size,'width'=>$file->width,'height'=>$file->height,
            'url'=>route($prefix.'.attachments.show',$parameters),'thumbnail_url'=>route($prefix.'.attachments.thumbnail',$parameters),
            'download_url'=>route($prefix.'.attachments.download',$parameters)];
    }

    public static function preview(?Message $message): string
    {
        if (!$message) return 'No messages yet.';
        if (trim($message->body)!=='') return Str::limit($message->body,100);
        $count=$message->relationLoaded('attachments') ? $message->attachments->count() : (int)($message->attachments_count ?? 0);
        return $count ? ($count===1 ? '📷 Photo' : '📷 '.$count.' photos') : 'Message';
    }

    public function assertAvailable(): void
    {
        if (!$this->available()) throw ValidationException::withMessages(['images'=>'Image attachments are not enabled or ready.']);
    }

    private function privateConfiguration(): bool
    {
        $disk=config('filesystems.disks.'.self::DISK, []);
        if (($disk['driver'] ?? null)==='chat_private_s3') return \App\Filesystem\PrivateChatS3Filesystem::configured($disk);
        if (($disk['driver'] ?? null)!=='local' || ($disk['visibility'] ?? null)!=='private' || ($disk['serve'] ?? true)!==false || empty($disk['root'])) return false;
        $resolve=fn (string $path) => strtolower(rtrim(str_replace('\\','/',realpath($path) ?: ((realpath(dirname($path)) ?: dirname($path)).'/'.basename($path))),'/'));
        $root=$resolve($disk['root']);
        foreach ([public_path(),storage_path('app/public'),...array_values(config('filesystems.links', []))] as $public) {
            $public=$resolve($public);
            if ($root===$public || str_starts_with($root,$public.'/')) return false;
        }
        return true;
    }

    /** Validate every file/header before any image or metadata is persisted. */
    public function validate(array $images): array
    {
        $this->assertUploadsAvailable();
        Validator::make(['images'=>$images],[
            'images'=>['required','array','min:1','max:5'],
            'images.*'=>['bail','required','file','max:5120','extensions:jpg,jpeg,png,webp','mimetypes:image/jpeg,image/png,image/webp','image'],
        ])->validate();
        $validated=[];
        foreach (array_values($images) as $index=>$file) {
            $mime=$file->getMimeType();
            $dimensions=@getimagesize($file->getRealPath());
            if (!$dimensions || !isset(self::FORMATS[$mime]) || ($dimensions['mime'] ?? null)!==$mime) $this->invalid($index);
            $extensions=$mime==='image/jpeg' ? ['jpg','jpeg'] : [self::FORMATS[$mime]];
            if (!in_array(strtolower($file->getClientOriginalExtension()),$extensions,true)) $this->invalid($index);
            [$width,$height]=$dimensions;
            if ($width<1 || $height<1 || $width>4096 || $height>4096 || $width*$height>4000000) {
                $this->invalid($index,'Images must be at most 4096 pixels per side and 4 megapixels.');
            }
            $validated[]=['file'=>$file,'mime'=>$mime,'width'=>$width,'height'=>$height,'index'=>$index];
        }
        return $validated;
    }

    /** Paths are recorded before writing, so partial writes are also cleaned. */
    public function persist(Message $message, string $reference, array $validated, array &$paths): void
    {
        foreach ($validated as $position=>$item) {
            $file=$item['file']; $mime=$item['mime'];
            $this->checkMemory($item['width'],$item['height'],$file->getSize(),$position);
            $image=match ($mime) {
                'image/jpeg'=>@imagecreatefromjpeg($file->getRealPath()),
                'image/png'=>@imagecreatefrompng($file->getRealPath()),
                'image/webp'=>@imagecreatefromwebp($file->getRealPath()),
            };
            if (!$image) $this->invalid($position);
            try {
                if (imagesx($image)!==$item['width'] || imagesy($image)!==$item['height']) $this->invalid($position);
                imagesavealpha($image,true);
                // GD writes pixels, not input EXIF/GPS/text chunks. EXIF-based
                // orientation is deliberately not claimed or preserved here.
                $encoded=$this->encode($image,$mime);
                if (strlen($encoded)>self::MAX_BYTES) $this->invalid($position,'The normalized image exceeds 5 MB.');
                $path=$reference.'/'.Str::uuid().'.'.self::FORMATS[$mime];
                $paths[]=$path;
                if (!Storage::disk(self::DISK)->put($path,$encoded,['visibility'=>'private'])) throw new \RuntimeException('Image storage failed.');
                unset($encoded);
                $scale=min(1,320/max($item['width'],$item['height']));
                $thumb=imagecreatetruecolor(max(1,(int)round($item['width']*$scale)),max(1,(int)round($item['height']*$scale)));
                if (!$thumb) $this->invalid($position);
                try {
                    imagealphablending($thumb,false); imagesavealpha($thumb,true);
                    imagefill($thumb,0,0,imagecolorallocatealpha($thumb,0,0,0,127));
                    if (!imagecopyresampled($thumb,$image,0,0,0,0,imagesx($thumb),imagesy($thumb),imagesx($image),imagesy($image))) $this->invalid($position);
                    $thumbnail=$this->thumbnailPath($path); $paths[]=$thumbnail;
                    if (!Storage::disk(self::DISK)->put($thumbnail,$this->encode($thumb,$mime),['visibility'=>'private'])) throw new \RuntimeException('Thumbnail storage failed.');
                } finally { unset($thumb); }
                $name=basename(str_replace('\\','/',$file->getClientOriginalName()));
                $name=preg_replace('/[\x00-\x1f\x7f]/u','',mb_convert_encoding($name,'UTF-8','UTF-8'));
                $message->attachments()->create([
                    'position'=>$position,'disk'=>self::DISK,'path'=>$path,
                    'original_name'=>mb_substr($name ?: 'image.'.self::FORMATS[$mime],0,255),
                    'mime_type'=>$mime,'size'=>Storage::disk(self::DISK)->size($path),
                    'width'=>$item['width'],'height'=>$item['height'],
                ]);
            } finally { unset($image); }
        }
    }

    private function checkMemory(int $width, int $height, int $size, int $index): void
    {
        $limit=trim(ini_get('memory_limit'));
        if ($limit==='-1') return; // Dimension/pixel/file caps still apply.
        $factor=match (strtoupper(substr($limit,-1))) { 'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1 };
        $remaining=(int)$limit*$factor-memory_get_usage(true)-8388608;
        if ($width*$height*16+$size*2+8388608>$remaining) $this->invalid($index,'This image is too large to process safely.');
    }

    private function encode(GdImage $image, string $mime): string
    {
        ob_start();
        try {
            $ok=match ($mime) { 'image/jpeg'=>imagejpeg($image,null,85),'image/png'=>imagepng($image,null,6),'image/webp'=>imagewebp($image,null,85) };
            $bytes=ob_get_contents();
            if (!$ok || !$bytes) throw new \RuntimeException('Image encoding failed.');
            return $bytes;
        } finally { ob_end_clean(); }
    }

    public function thumbnailPath(string $path): string
    {
        return substr($path,0,strrpos($path,'.')).'-thumb'.substr($path,strrpos($path,'.'));
    }

    public function safePath(MessageAttachment $attachment, string $reference, bool $thumbnail=false): ?string
    {
        $extension=self::FORMATS[$attachment->mime_type] ?? null;
        if ($attachment->disk!==self::DISK || !$extension || dirname($attachment->path)!==$reference) return null;
        $uuid='[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}';
        if (!preg_match('#\A'.$uuid.'/'.$uuid.'\.'.$extension.'\z#',$attachment->path)) return null;
        return $thumbnail ? $this->thumbnailPath($attachment->path) : $attachment->path;
    }

    public function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            try { if(!Storage::disk(self::DISK)->delete($path)) \Illuminate\Support\Facades\Log::warning('Chat attachment cleanup requires review.'); }
            catch (Throwable $exception) { \Illuminate\Support\Facades\Log::warning('Chat attachment cleanup failed.'); }
        }
    }

    private function invalid(int $index, string $message='This file is not a supported, decodable image.'): never
    {
        throw ValidationException::withMessages(['images.'.$index=>$message]);
    }
}
