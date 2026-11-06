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
                'region_code' => $address->region_code,
                'province_code' => $address->province_code,
                'city_municipality_code' => $address->city_municipality_code,
                'barangay_code' => $address->barangay_code,
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
        $addresses = collect($addresses)->map(fn (array $address) => $address + ['delivery_issue' => $this->addressIssue($address)])->all();
        $eligibleAddresses = collect($addresses)->whereNull('delivery_issue');
        $defaultAddress = $eligibleAddresses->firstWhere('id', old('address_id'))
            ?? $eligibleAddresses->firstWhere('is_default', true) ?? $eligibleAddresses->first();

        return view('buyer.checkout', array_merge($summary, [
            'buyNowToken' => $buyNowToken,
            'buyNowProduct' => $state['product_slug'] ?? null,
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
            'hasEligibleAddress' => $eligibleAddresses->isNotEmpty(),
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
        if ($issue = $this->addressIssue($address)) {
            throw ValidationException::withMessages(['address_id' => $issue]);
        }
        try {
            // Reuse the same hierarchy checks as address editing. Never guess codes
            // or depend on provider/rider assignments when validating an address.
            app(\App\Services\PhilippineAddressService::class)->resolve([
                'region' => $address['region_code'],
                'province' => $address['province_code'] ?? null,
                'municipality' => $address['city_municipality_code'],
                'barangay' => $address['barangay_code'],
            ]);
        } catch (ValidationException $exception) {
            $unavailable = collect($exception->errors())->flatten()->contains('Address data could not be verified. Please try again later.');
            throw ValidationException::withMessages(['address_id' => $unavailable
                ? 'Delivery locations could not be verified right now. Please try again shortly. Your cart has not changed.'
                : 'This address has outdated or mismatched delivery locations. Update it in Manage addresses, then return to checkout.']);
        }

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
            'province' => $sessionAddress['province'] ?? null,
            'region_code' => $sessionAddress['region_code'] ?? null,
            'province_code' => $sessionAddress['province_code'] ?? null,
            'city_municipality_code' => $sessionAddress['city_municipality_code'] ?? null,
            'barangay_code' => $sessionAddress['barangay_code'] ?? null,
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

    private function addressIssue(array $address): ?string
    {
        $fields = ['region_code', 'city_municipality_code', 'barangay_code'];
        if (filled($address['province'] ?? null)) $fields[] = 'province_code';
        foreach ($fields as $field) {
            if (! is_string($address[$field] ?? null) || ! preg_match('/^[0-9]{1,20}$/D', $address[$field])) {
                return 'Update this address in Manage addresses and select its region, city/municipality and barangay before checkout.';
            }
        }
        return null;
    }
}
