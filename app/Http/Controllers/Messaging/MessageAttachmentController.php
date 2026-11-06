<?php

namespace App\Http\Controllers\Messaging;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Services\MessageAttachmentService;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class MessageAttachmentController extends MessagingController
{
    public function show(Request $request, Conversation $conversation, string $attachment, MessagingService $messaging, MessageAttachmentService $files): StreamedResponse
    {
        $this->authorizeConversation($request,$conversation,$messaging);
        abort_unless($files->available(),404);
        abort_unless(filter_var($attachment,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false,404);
        // Resolve after the feature gate: disabled deployments need no new table.
        $record=MessageAttachment::query()->whereKey($attachment)
            ->whereHas('message',fn ($q) => $q->where('conversation_id',$conversation->id))->firstOrFail();
        $thumbnail=$request->routeIs('*.attachments.thumbnail');
        $path=$files->safePath($record,$conversation->reference,$thumbnail);
        abort_unless($path,404);
        try { $storage=Storage::disk(MessageAttachmentService::DISK); $exists=$storage->exists($path); }
        catch (\Throwable $exception) { \Illuminate\Support\Facades\Log::warning('Private chat image lookup failed.'); abort(503,'Image temporarily unavailable.'); }
        abort_unless($exists,404);
        $extension=match ($record->mime_type) {'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'};
        $filename='message-image-'.$record->id.($thumbnail ? '-thumbnail' : '').'.'.$extension;
        $headers=['Content-Type'=>$record->mime_type,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store, max-age=0',
            'Content-Security-Policy'=>"default-src 'none'; sandbox"];
        try { $stream=$storage->readStream($path); }
        catch (\Throwable $exception) { \Illuminate\Support\Facades\Log::warning('Private chat image read failed.'); abort(503,'Image temporarily unavailable.'); }
        abort_unless(is_resource($stream),404);
        $headers['Content-Disposition']=HeaderUtils::makeDisposition($request->routeIs('*.attachments.download') ? 'attachment' : 'inline',$filename);
        return response()->stream(function () use ($stream) {
            try { @fpassthru($stream); }
            catch (\Throwable $exception) { \Illuminate\Support\Facades\Log::warning('Private chat image stream failed.'); }
            finally { fclose($stream); }
        },200,$headers);
    }
}
