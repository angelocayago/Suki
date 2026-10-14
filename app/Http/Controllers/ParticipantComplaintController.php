<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintCategory;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Admin\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipantComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $complaints = Complaint::query()
            ->where('complainant_id', $request->user()->id)
            ->with(['order:id,order_number,status', 'seller:id,name', 'assignee:id,name'])
            ->withCount(['evidence' => fn ($query) => $query->where('uploaded_by', $request->user()->id)])
            ->latest()
            ->paginate(15);

        return view('complaints.index', compact('complaints'));
    }

    public function show(Request $request, int $complaint): View
    {
        $complaint = Complaint::query()
            ->whereKey($complaint)
            ->where('complainant_id', $request->user()->id)
            ->with([
                'order:id,order_number,status,payment_status',
                'seller:id,name',
                'product:id,name',
                'shipment:id,tracking_code,workflow_status',
                'events:id,complaint_id,action,previous_status,new_status,waiting_on,created_at',
                'evidence' => fn ($query) => $query
                    ->where('uploaded_by', $request->user()->id)
                    ->with('uploader:id,name'),
            ])
            ->firstOrFail();

        return view('complaints.show', compact('complaint'));
    }

    public function createForOrder(Request $request, Order $order): View
    {
        abort_unless($request->user()->isBuyer() && (int) $order->buyer_id === (int) $request->user()->id, 403);

        $order->load(['sellerOrders.seller', 'sellerOrders.items.product']);

        return view('complaints.create', [
            'contextType' => 'buyer',
            'order' => $order,
            'sellerOrder' => null,
            'sellerOrders' => $order->sellerOrders,
            'products' => collect(),
        ]);
    }

    public function storeForOrder(
        Request $request,
        Order $order,
        ComplaintService $complaints
    ): RedirectResponse {
        abort_unless($request->user()->isBuyer() && (int) $order->buyer_id === (int) $request->user()->id, 403);
        $validated = $this->validateSubmission($request, true);

        $sellerOrder = filled($validated['seller_order_id'] ?? null)
            ? $order->sellerOrders()->with(['seller', 'shipment'])->findOrFail($validated['seller_order_id'])
            : null;
        $product = $this->selectedProduct($sellerOrder, $validated['product_id'] ?? null);

        $complaint = $complaints->createComplaint(
            actor: $request->user(),
            category: ComplaintCategory::from($validated['category']),
            subject: $validated['subject'],
            description: $validated['description'],
            order: $order,
            sellerOrder: $sellerOrder,
            shipment: $sellerOrder?->shipment,
            seller: $sellerOrder?->seller,
            product: $product,
        );

        return redirect()->route('complaints.mine.show', $complaint)
            ->with('success', 'Complaint submitted successfully.');
    }

    public function createForSellerOrder(Request $request, SellerOrder $sellerOrder): View
    {
        $this->authorizeSellerOrderContext($request->user(), $sellerOrder);
        $sellerOrder->load(['order', 'seller', 'shipment', 'items.product']);

        return view('complaints.create', [
            'contextType' => $this->participantRole($request->user()),
            'order' => $sellerOrder->order,
            'sellerOrder' => $sellerOrder,
            'sellerOrders' => collect(),
            'products' => $sellerOrder->items->pluck('product')->filter()->unique('id')->values(),
        ]);
    }

    public function storeForSellerOrder(
        Request $request,
        SellerOrder $sellerOrder,
        ComplaintService $complaints
    ): RedirectResponse {
        $this->authorizeSellerOrderContext($request->user(), $sellerOrder);
        $validated = $this->validateSubmission($request);
        $sellerOrder->load(['order', 'seller', 'shipment']);
        $product = $this->selectedProduct($sellerOrder, $validated['product_id'] ?? null);

        $complaint = $complaints->createComplaint(
            actor: $request->user(),
            category: ComplaintCategory::from($validated['category']),
            subject: $validated['subject'],
            description: $validated['description'],
            order: $sellerOrder->order,
            sellerOrder: $sellerOrder,
            shipment: $sellerOrder->shipment,
            seller: $sellerOrder->seller,
            product: $product,
        );

        return redirect()->route('complaints.mine.show', $complaint)
            ->with('success', 'Complaint submitted successfully.');
    }

    private function validateSubmission(Request $request, bool $buyer = false): array
    {
        $rules = [
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'product_id' => ['nullable', 'integer'],
        ];
        $rules['seller_order_id'] = $buyer
            ? ['nullable', 'integer', 'required_with:product_id']
            : ['prohibited'];

        return $request->validate($rules);
    }

    private function selectedProduct(?SellerOrder $sellerOrder, mixed $productId): ?Product
    {
        if (! filled($productId)) {
            return null;
        }

        if (! $sellerOrder) {
            throw ValidationException::withMessages([
                'seller_order_id' => 'Select the affected seller order before selecting a product.',
            ]);
        }

        $item = $sellerOrder->items()->where('product_id', $productId)->first();
        if (! $item || ! $item->product) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is not part of this seller order.',
            ]);
        }

        return $item->product;
    }

    private function authorizeSellerOrderContext(User $user, SellerOrder $sellerOrder): void
    {
        $sellerOrder->loadMissing(['seller', 'shipment.pickupRider', 'shipment.rider', 'shipment.provider']);

        if ($user->isSeller()) {
            abort_unless((int) $sellerOrder->seller?->user_id === (int) $user->id, 403);

            return;
        }

        if ($user->isRider()) {
            $rider = $user->rider;
            abort_unless($rider && $sellerOrder->shipment && (
                (int) $sellerOrder->shipment->pickup_rider_id === (int) $rider->id
                || (int) $sellerOrder->shipment->rider_id === (int) $rider->id
            ), 403);

            return;
        }

        if ($user->isLogistics()) {
            abort_unless(
                $sellerOrder->shipment?->provider
                && (int) $sellerOrder->shipment->provider->user_id === (int) $user->id,
                403
            );

            return;
        }

        abort(403);
    }

    private function participantRole(User $user): string
    {
        return match (true) {
            $user->isSeller() => 'seller',
            $user->isRider() => 'rider',
            $user->isLogistics() => 'logistics',
            default => abort(403),
        };
    }
}
