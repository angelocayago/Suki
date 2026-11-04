<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Bounded, Buyer-owned session selections. No permanent cart records. */
class DirectCheckout
{
    private function buyer(Request $request): void
    {
        abort_unless($request->user()?->role === 'buyer' && $request->user()->status === 'active' && ! $request->user()->is_suspended,403);
    }

    public function token(Request $request): ?string
    {
        if (! $request->has('buy_now')) return null;
        $this->buyer($request);
        return $request->validate(['buy_now'=>['required','string','regex:/^[a-f0-9]{64}$/']])['buy_now'];
    }

    public function selection(Request $request, string $token): array
    {
        $state = $request->session()->get('buy_now_selections.'.$token);
        if (! $state || (int)$state['buyer_id'] !== (int)$request->user()->id || $state['expires_at'] <= now()->getTimestamp()) {
            $request->session()->forget('buy_now_selections.'.$token);
            throw ValidationException::withMessages(['buy_now'=>'This Buy Now selection has expired. Return to the product and choose Buy Now again.']);
        }
        return $state;
    }

    public function start(Request $request, array $selection): string
    {
        $this->buyer($request);
        $states = array_filter($request->session()->get('buy_now_selections', []), fn($state)=>$state['expires_at'] > now()->getTimestamp() && (int)$state['buyer_id'] === (int)$request->user()->id);
        $token = bin2hex(random_bytes(32));
        $states[$token] = $selection + ['buyer_id'=>$request->user()->id,'expires_at'=>now()->getTimestamp()+1800,'voucher_codes'=>[]];
        $request->session()->put('buy_now_selections', array_slice($states,-5,null,true));
        return $token;
    }

    public function reference(Request $request, string $token): string
    {
        return 'BN-'.strtoupper(substr(hash_hmac('sha256',$request->user()->id.'|'.$token,(string)config('app.key')),0,32));
    }

    public function completed(Request $request, string $token): ?Order
    {
        $order = Order::where('buyer_id',$request->user()->id)->where('order_number',$this->reference($request,$token))->first();
        if($order)$request->session()->forget('buy_now_selections.'.$token);
        return $order;
    }

    public function codes(Request $request, ?string $token): array
    {
        return $token ? $this->selection($request,$token)['voucher_codes'] : $request->session()->get('checkout_voucher_codes',[]);
    }

    public function putCodes(Request $request, ?string $token, array $codes): void
    {
        if ($token) $this->selection($request,$token);
        $request->session()->put($token ? 'buy_now_selections.'.$token.'.voucher_codes' : 'checkout_voucher_codes',$codes);
    }
}
