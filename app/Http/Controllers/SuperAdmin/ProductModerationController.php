<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ProductModerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ProductModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = strtoupper((string) $request->input('status', ProductModerationStatus::Pending->value));
        $allowed = array_map(fn ($case) => $case->value, ProductModerationStatus::cases());
        abort_unless($status === 'ALL' || in_array($status, $allowed, true), 422);

        $products = Product::query()
            ->with(['seller.owner', 'category', 'variants', 'images'])
            ->when($status !== 'ALL', fn ($query) => $query->where('moderation_status', $status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhereHas('seller', fn ($seller) => $seller->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = collect(ProductModerationStatus::cases())->mapWithKeys(
            fn ($case) => [$case->value => Product::where('moderation_status', $case->value)->count()]
        );

        return view('superadmin.products.index', compact('products', 'stats', 'status'));
    }

    public function show(Product $product): View
    {
        $product->load([
            'seller.owner',
            'seller.allowedCategories',
            'category',
            'variants',
            'images',
            'moderator',
            'moderationEvents.actor',
        ]);

        return view('superadmin.products.show', compact('product'));
    }

    public function approve(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ): RedirectResponse {
        $moderation->approve($product, $request->user());

        return back()->with('success', 'Product approved for the marketplace.');
    }

    public function reject(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $moderation->reject($product, $request->user(), $validated['reason']);

        return back()->with('success', 'Product rejected.');
    }

    public function hide(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $moderation->hide($product, $request->user(), $validated['reason']);

        return back()->with('success', 'Product hidden from the marketplace.');
    }

    public function restore(
        Request $request,
        Product $product,
        ProductModerationService $moderation
    ): RedirectResponse {
        $moderation->restore($product, $request->user());

        return back()->with('success', 'Product restored to the marketplace.');
    }
}
