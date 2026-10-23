<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\SellerMessageAttachment;
use App\Support\SellerAccount;
use App\Support\SellerMessaging;
use App\Support\SellerMessagingPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ChatImageController extends Controller
{
    public function show(string $attachment)
    {
        abort_unless(Schema::hasTable('seller_message_attachments'), 503, 'Chat image storage is unavailable.');
        $image = SellerMessageAttachment::findOrFail($attachment);
        $message = SellerMessage::findOrFail($image->message_id);
        $conversation = SellerConversation::findOrFail($message->conversation_id);
        if (! (SellerMessaging::eligibleBuyer(Auth::user()) && $conversation->buyer_id === Auth::id())) {
            $shop = SellerAccount::current();
            abort_unless($conversation->seller_id === $shop->id, 404);
        }
        $imageShop = Seller::findOrFail($conversation->seller_id);
        abort_unless(SellerMessagingPolicy::eligible($imageShop) && $imageShop->user_id !== $conversation->buyer_id, 403);
        abort_unless(preg_match('/\A[0-9a-f-]{36}\.(jpg|png|webp)\z/', $image->path) && in_array($image->mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        abort_unless(Storage::disk('chat_images')->exists($image->path), 404);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$image->mime];

        return response()->file(Storage::disk('chat_images')->path($image->path), ['Content-Type' => $image->mime, 'Content-Disposition' => 'inline; filename="image.'.$extension.'"',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store, max-age=0']);
    }
}
