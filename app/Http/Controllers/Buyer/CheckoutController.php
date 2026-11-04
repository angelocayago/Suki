<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\DirectCheckout;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function index(Request $request, CheckoutService $checkout): View|RedirectResponse
    {
        $direct = app(DirectCheckout::class);
        $buyNowToken = $direct->token($request);
        if ($buyNowToken && ($completed = $direct->completed($request,$buyNowToken))) return redirect()->route('buyer.order-success',['order'=>$completed->order_number]);
        try {
            $state = $buyNowToken ? $direct->selection($request,$buyNowToken) : null;
            $summary = $buyNowToken ? $checkout->directSummary($request->user(),$state,$state['voucher_codes']) : $checkout->cartSummary($request->user(),$direct->codes($request,null));
        } catch (ValidationException $error) {
            if($buyNowToken)$request->session()->forget('buy_now_selections.'.$buyNowToken);
            return redirect()->route('buyer.cart')->withErrors($error->errors());
        }

        if ($summary['cart'] === []) {
            return redirect()
                ->route('buyer.cart')
                ->with('error', 'Your cart is empty.');
        }

        $databaseAddresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'name' => $address->recipient,
                'phone' => $address->phone,
                'province' => $address->province,
                'municipality' => $address->city,
                'barangay' => $address->barangay,
                'street' => $address->line1,
                'house_number' => null,
                'postal_code' => $address->postal_code,
                'label' => $address->label,
                'is_default' => $address->is_default,
            ]);

        $addresses = $databaseAddresses->isNotEmpty()
            ? $databaseAddresses->all()
            : $request->session()->get('buyer_addresses', []);
        $defaultAddress = collect($addresses)->firstWhere('is_default', true)
            ?? ($addresses[0] ?? null);

        return view('buyer.checkout', array_merge($summary, [
            'buyNowToken' => $buyNowToken,
            'buyNowProduct' => $state['product_slug'] ?? null,
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
            'claimedVouchers' => app(\App\Services\ShopVoucherService::class)->claimedForCheckout($request->user(), collect($summary['cart'])->filter(fn($line)=>$line['available'] && $line['seller_id'])->groupBy('seller_id')->map(fn($lines)=>$lines->sum(fn($line)=>$line['price_minor']*$line['quantity']))),
        ]));
    }

    public function store(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $direct = app(DirectCheckout::class);
        $buyNowToken = $direct->token($request);
        if ($buyNowToken && ($completed = $direct->completed($request,$buyNowToken))) return redirect()->route('buyer.order-success',['order'=>$completed->order_number]);
        $state = $buyNowToken ? $direct->selection($request,$buyNowToken) : null;
        $validated = $request->validate([
            'address_id' => ['required'],
            'payment_method' => ['required', 'in:cod'],
        ]);

        $buyer = $request->user();
        $address = $this->resolveAddress($request, $validated['address_id']);

        try {
            $order = $checkout->checkout($buyer, $address, $validated['payment_method'], $direct->codes($request,$buyNowToken), $state, $buyNowToken ? $direct->reference($request,$buyNowToken) : null);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('buyer.checkout',$buyNowToken ? ['buy_now'=>$buyNowToken] : [])
                ->with('error', 'Unable to place your order. Please try again.');
        }

        if($buyNowToken)$request->session()->forget('buy_now_selections.'.$buyNowToken);
        else $request->session()->forget('checkout_voucher_codes');
        return redirect()
            ->route('buyer.order-success', ['order' => $order->order_number])
            ->with('success', 'Your order has been placed successfully!');
    }

    public function buyNow(Request $request, string $slug, CheckoutService $checkout): RedirectResponse
    {
        abort_unless($request->user()->role === 'buyer',403);
        $data = $request->validate(['variant_id'=>['required','integer','min:1'],'quantity'=>['required','integer','min:1','max:2147483647']]);
        $product = app(\App\Services\ProductCatalogService::class)->marketplaceProduct($slug);
        abort_unless($product,404);
        if(!collect($product['variants'])->contains(fn($variant)=>(int)$variant['id']===(int)$data['variant_id']))throw ValidationException::withMessages(['variant_id'=>'Choose an available option belonging to this product.']);
        $state = ['variant_id'=>(int)$data['variant_id'],'quantity'=>(int)$data['quantity'],'product_slug'=>$slug];
        if($checkout->directSummary($request->user(),$state)['has_unavailable_items'])throw ValidationException::withMessages(['quantity'=>'Choose a quantity within the current available stock.']);
        $token = app(DirectCheckout::class)->start($request,$state);
        return redirect()->route('buyer.checkout',['buy_now'=>$token]);
    }

    public function cancelBuyNow(Request $request): RedirectResponse
    {
        $direct=app(DirectCheckout::class);$token=$direct->token($request);abort_unless($token,400);
        $state=$direct->selection($request,$token);
        $request->session()->forget('buy_now_selections.'.$token);
        return redirect()->route('buyer.product',$state['product_slug'])->with('success','Buy Now cancelled. Your cart is unchanged.');
    }

    private function resolveAddress(Request $request, mixed $addressId): array
    {
        $databaseAddress = Address::query()
            ->where('user_id', $request->user()->id)
            ->find($addressId);

        if ($databaseAddress) {
            return [
                'recipient_name' => $databaseAddress->recipient,
                'recipient_phone' => $databaseAddress->phone,
                'province' => $databaseAddress->province,
                'region_code' => $databaseAddress->region_code,
                'province_code' => $databaseAddress->province_code,
                'municipality' => $databaseAddress->city,
                'city_municipality_code' => $databaseAddress->city_municipality_code,
                'barangay' => $databaseAddress->barangay,
                'barangay_code' => $databaseAddress->barangay_code,
                'street_address' => $databaseAddress->line1,
                'house_number' => null,
                'postal_code' => $databaseAddress->postal_code,
                'address_label' => $databaseAddress->label,
                'delivery_notes' => null,
                'source_address_id' => $databaseAddress->id,
            ];
        }

        $sessionAddress = collect($request->session()->get('buyer_addresses', []))
            ->first(fn (array $address) => (string) ($address['id'] ?? '') === (string) $addressId);

        if (! $sessionAddress) {
            throw ValidationException::withMessages([
                'address_id' => 'Please select a valid delivery address.',
            ]);
        }

        return [
            'recipient_name' => $sessionAddress['name'] ?? '',
            'recipient_phone' => $sessionAddress['phone'] ?? '',
            'province' => $sessionAddress['province'] ?? '',
            'municipality' => $sessionAddress['municipality'] ?? '',
            'barangay' => $sessionAddress['barangay'] ?? '',
            'street_address' => $sessionAddress['street'] ?? '',
            'house_number' => $sessionAddress['house_number'] ?? null,
            'postal_code' => $sessionAddress['postal_code'] ?? null,
            'address_label' => $sessionAddress['label'] ?? null,
            'delivery_notes' => null,
            'source_address_id' => null,
        ];
    }
}
