<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\ReturnRefundRequest;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\TransactionReview;
use App\Models\User;
use Illuminate\Support\Collection;

/** Seller-only presentation of the existing recipient-scoped database inbox. */
class SellerNotifications
{
    public function header(User $user, ?Seller $shop): array
    {
        if ($shop && (int)$shop->user_id !== (int)$user->id) $shop = null;
        $name = trim($user->full_name ?: $user->name ?: '') ?: 'Seller';
        return [
            'name'=>$name, 'initial'=>mb_strtoupper(mb_substr($name,0,1)),
            'photo'=>$this->photo($shop?->logo_path),
            'store_url'=>filled($shop?->slug) ? route('buyer.stores.show',$shop->slug) : null,
            'unread_count'=>$user->unreadNotifications()->count(),
            'recent'=>$this->present($user,$this->recent($user),$shop),
        ];
    }

    public function recent(User $user): Collection
    {
        return $user->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
    }

    public function present(User $user, Collection $notifications, ?Seller $shop = null): array
    {
        $shop ??= $user->sellers()->where('status','approved')->first();
        $contexts = $notifications->map(fn($n)=>is_array($n->data['context'] ?? null) ? $n->data['context'] : []);
        $ids = fn($key)=>$contexts->pluck($key)->map(fn($id)=>$this->id($id))->filter()->unique()->values();
        $links = [];
        if ($shop) {
            $orders = $ids('seller_order_id');
            if ($orders->isNotEmpty()) foreach(SellerOrder::where('seller_id',$shop->id)->whereIn('id',$orders)->pluck('id') as $id) $links['seller_order_id'][$id] = route('seller.orders.show',$id);
            $products = $ids('product_id');
            if ($products->isNotEmpty()) foreach(Product::where('seller_id',$shop->id)->whereIn('id',$products)->pluck('id') as $id) $links['product_id'][$id] = route('seller.products.edit',$id);
            $reviews = $ids('review_id');
            if ($reviews->isNotEmpty()) foreach(TransactionReview::where('seller_id',$shop->id)->whereIn('kind',['product','seller'])->whereIn('id',$reviews)->pluck('id') as $id) $links['review_id'][$id] = route('reviews.seller-page');
            $returns = $ids('return_refund_request_id');
            if ($returns->isNotEmpty()) foreach(ReturnRefundRequest::where('seller_id',$shop->id)->whereIn('id',$returns)->pluck('id') as $id) $links['return_refund_request_id'][$id] = route('seller.return-refunds.show',$id);
        }
        $threads = $ids('conversation_id');
        if ($threads->isNotEmpty()) foreach(Conversation::whereIn('id',$threads)->whereHas('participants',fn($q)=>$q->where('user_id',$user->id)->where('role_context','SELLER')->whereNull('left_at'))->get() as $thread) {
            if (app(\App\Services\MessagingService::class)->canAccessAs($thread,$user,['SELLER'])) {
                $links['conversation_id'][$thread->id] = route('seller.messages.show',$thread->reference);
            }
        }

        return $notifications->map(function($notification)use($links){
            $data = is_array($notification->data) ? $notification->data : [];
            $context = is_array($data['context'] ?? null) ? $data['context'] : [];
            $destination = null;
            foreach(['conversation_id','seller_order_id','product_id','review_id','return_refund_request_id'] as $key) {
                $id = $this->id($context[$key] ?? null);
                if ($id && isset($links[$key][$id])) { $destination = $links[$key][$id]; break; }
            }
            return [
                'id'=>$notification->id,
                'title'=>is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
                'message'=>is_string($data['message'] ?? null) ? $data['message'] : '',
                'date'=>$notification->created_at?->setTimezone('Asia/Manila'),
                'unread'=>$notification->read_at === null, 'destination'=>$destination,
                'open_url'=>route('seller.notifications.open',$notification->id),
            ];
        })->all();
    }

    private function id(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value)) return null;
        return filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
    }

    private function photo(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path,'https://') || str_starts_with($path,'http://')) return $path;
        if (str_starts_with($path,'/storage/')) return url($path);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
