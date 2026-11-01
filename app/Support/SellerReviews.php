<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\TransactionReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Read-only Seller review presentation; submission, replies and moderation stay canonical. */
class SellerReviews
{
    public function listing(Request $request): array
    {
        // Preserve the existing Seller page's owner scope, including its labeled
        // hidden reviews. Public Buyer review visibility is a different policy.
        $sellerIds = Seller::where('user_id',$request->user()->id)->pluck('id');
        $criteria = $request->validate([
            'search'=>['nullable','string','max:100'],
            'rating'=>['nullable','integer','between:1,5'],
            'product'=>['nullable','integer',Rule::exists('products','id')->where(fn($q)=>$q->whereIn('seller_id',$sellerIds))],
            'page'=>['nullable','integer','min:1'],
        ]);
        $criteria = array_filter(Arr::except($criteria,'page'),fn($v)=>$v !== null && $v !== '');
        if (isset($criteria['search'])) {
            $criteria['search'] = trim($criteria['search']);
            if ($criteria['search'] === '') unset($criteria['search']);
        }
        $query = TransactionReview::whereIn('seller_id',$sellerIds)->whereIn('kind',['product','seller']);
        if (isset($criteria['rating'])) $query->where('rating',$criteria['rating']);
        if (isset($criteria['product'])) $query->where('product_id',$criteria['product']);
        if (isset($criteria['search'])) {
            $term = self::like($criteria['search']);
            $query->where(function($search)use($term,$sellerIds){
                $search->whereRaw("LOWER(body) LIKE ? ESCAPE '!'",[$term])
                    ->orWhereExists(fn($q)=>$q->selectRaw('1')->from('users')->whereColumn('users.id','transaction_reviews.buyer_id')
                        ->whereRaw("LOWER(COALESCE(NULLIF(TRIM(name), ''), TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')))) LIKE ? ESCAPE '!'",[$term]))
                    ->orWhereExists(fn($q)=>$q->selectRaw('1')->from('order_items')->whereColumn('order_items.id','transaction_reviews.order_item_id')
                        ->whereColumn('order_items.seller_order_id','transaction_reviews.seller_order_id')->whereColumn('order_items.product_id','transaction_reviews.product_id')
                        ->whereIn('order_items.seller_order_id',SellerOrder::whereIn('seller_id',$sellerIds)->select('id'))
                        ->whereRaw("LOWER(product_name) LIKE ? ESCAPE '!'",[$term]))
                    ->orWhereExists(fn($q)=>$q->selectRaw('1')->from('products')->whereColumn('products.id','transaction_reviews.product_id')
                        ->whereColumn('products.seller_id','transaction_reviews.seller_id')->whereIn('products.seller_id',$sellerIds)
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'",[$term]));
            });
        }
        $reviews = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->appends($criteria);
        $buyers = User::whereIn('id',$reviews->pluck('buyer_id'))->get(['id','name','first_name','last_name'])->keyBy('id');
        $items = OrderItem::whereIn('id',$reviews->pluck('order_item_id')->filter())
            ->whereIn('seller_order_id',SellerOrder::whereIn('seller_id',$sellerIds)->select('id'))
            ->get(['id','seller_order_id','product_id','product_name','variant_name','variation','image'])->keyBy('id');
        $products = Product::withTrashed()->whereIn('seller_id',$sellerIds)->whereIn('id',$reviews->pluck('product_id')->filter())
            ->select(['id','seller_id','name'])->addSelect(['review_thumbnail'=>ProductImage::select('path')->whereColumn('product_id','products.id')->orderBy('position')->orderBy('id')->limit(1)])
            ->get()->keyBy('id');
        $reviews->through(function($review)use($buyers,$items,$products){
            $buyer = $buyers->get($review->buyer_id);
            $name = trim((string)$buyer?->name) ?: trim(($buyer?->first_name ?? '').' '.($buyer?->last_name ?? ''));
            $item = $items->get($review->order_item_id);
            if ($item && ((int)$item->seller_order_id !== (int)$review->seller_order_id || (int)$item->product_id !== (int)$review->product_id)) $item = null;
            $product = $products->get($review->product_id);
            if ($product && (int)$product->seller_id !== (int)$review->seller_id) $product = null;
            return [
                'id'=>$review->id, 'kind'=>$review->kind, 'buyer'=>$name ?: 'Buyer',
                'date'=>$review->created_at?->setTimezone('Asia/Manila'), 'rating'=>(int)$review->rating,
                'body'=>$review->body, 'hidden'=>$review->hidden_at !== null, 'response'=>$review->seller_response,
                'product'=>$review->kind === 'product' ? ($item?->product_name ?: $product?->name ?: 'Product no longer available') : null,
                'variant'=>$item?->variant_name, 'options'=>is_array($item?->variation) ? Arr::dot($item->variation) : [],
                'image'=>$review->kind === 'product' ? self::image($item?->image ?: $product?->review_thumbnail) : null,
            ];
        });
        $selectedProduct = isset($criteria['product']) ? Product::withTrashed()->whereIn('seller_id',$sellerIds)->find($criteria['product'],['id','name']) : null;
        $chips = [];
        foreach($criteria as $key=>$value) $chips[$key] = [
            'label'=>match($key){'search'=>'Search: '.$value,'rating'=>$value.' Stars','product'=>'Product: '.$selectedProduct?->name},
            'url'=>route('reviews.seller-page',Arr::except($criteria,$key)),
        ];
        return compact('reviews','criteria','selectedProduct','chips');
    }

    public function products(Request $request): array
    {
        $input = $request->validate(['q'=>['nullable','string','max:100']]);
        $sellerIds = Seller::where('user_id',$request->user()->id)->pluck('id');
        return Product::withTrashed()->whereIn('seller_id',$sellerIds)
            ->whereIn('id',TransactionReview::whereIn('seller_id',$sellerIds)->where('kind','product')->select('product_id'))
            ->when(filled($input['q'] ?? null),fn($q)=>$q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'",[self::like(trim($input['q']))]))
            ->orderBy('name')->orderBy('id')->limit(15)->get(['id','name'])->toArray();
    }

    private static function like(string $value): string
    {
        return '%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($value)).'%';
    }

    private static function image(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path,'https://') || str_starts_with($path,'http://')) return $path;
        if (str_starts_with($path,'/storage/')) return url($path);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
