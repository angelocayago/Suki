<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Enums\SellerViolationStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerViolation;
use App\Services\Admin\SellerComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SellerComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'severity' => ['nullable', Rule::in(['ALL', ...array_column(SellerViolationSeverity::cases(), 'value')])],
            'status' => ['nullable', Rule::in(['ALL', ...array_column(SellerViolationStatus::cases(), 'value')])],
            'category' => ['nullable', Rule::in(['ALL', ...array_column(SellerViolationCategory::cases(), 'value')])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = SellerViolation::query()->with(['seller.owner', 'product', 'creator']);

        if (filled($validated['search'] ?? null)) {
            $search = trim($validated['search']);
            $query->where(function ($inner) use ($search) {
                $inner->where('reference', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('seller', fn ($seller) => $seller->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"));
            });
        }

        $query
            ->when(filled($validated['seller_id'] ?? null), fn ($q) => $q->where('seller_id', $validated['seller_id']))
            ->when(filled($validated['severity'] ?? null) && $validated['severity'] !== 'ALL', fn ($q) => $q->where('severity', $validated['severity']))
            ->when(filled($validated['status'] ?? null) && $validated['status'] !== 'ALL', fn ($q) => $q->where('status', $validated['status']))
            ->when(filled($validated['category'] ?? null) && $validated['category'] !== 'ALL', fn ($q) => $q->where('category', $validated['category']))
            ->when(filled($validated['date_from'] ?? null), fn ($q) => $q->whereDate('created_at', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn ($q) => $q->whereDate('created_at', '<=', $validated['date_to']));

        $violations = $query->latest()->paginate(15)->withQueryString();
        $sellers = Seller::query()->orderBy('name')->get(['id', 'name']);
        $stats = collect(SellerViolationStatus::cases())->mapWithKeys(
            fn ($status) => [$status->value => SellerViolation::where('status', $status->value)->count()]
        );

        return view('admin.compliance.index', compact('violations', 'sellers', 'stats'));
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
        ]);

        $sellers = Seller::query()->with('owner')->orderBy('name')->get();
        $selectedSeller = filled($validated['seller_id'] ?? null)
            ? Seller::with(['owner', 'products' => fn ($query) => $query->orderBy('name')])->findOrFail($validated['seller_id'])
            : null;

        return view('admin.compliance.create', compact('sellers', 'selectedSeller'));
    }

    public function store(Request $request, SellerComplianceService $compliance): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category' => ['required', Rule::enum(SellerViolationCategory::class)],
            'severity' => ['required', Rule::enum(SellerViolationSeverity::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'details' => ['nullable', 'string', 'max:10000'],
        ]);

        $violation = $compliance->createViolation(
            Seller::findOrFail($validated['seller_id']),
            $request->user(),
            SellerViolationCategory::from($validated['category']),
            SellerViolationSeverity::from($validated['severity']),
            $validated['reason'],
            $validated['details'] ?? null,
            filled($validated['product_id'] ?? null) ? Product::findOrFail($validated['product_id']) : null
        );

        return redirect()->route('admin.compliance.show', $violation)
            ->with('success', 'Compliance case created.');
    }

    public function show(
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): View
    {
        $violation->load([
            'seller.owner',
            'product.category',
            'creator',
            'resolver',
            'warnings.issuer',
            'evidence.uploader',
            'events.actor',
        ]);

        $suspensionImpact = $compliance->suspensionImpact($violation);

        return view('admin.compliance.show', compact('violation', 'suspensionImpact'));
    }

    public function updateSeverity(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $validated = $request->validate([
            'severity' => ['required', Rule::enum(SellerViolationSeverity::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $compliance->changeSeverity(
            $violation,
            $request->user(),
            SellerViolationSeverity::from($validated['severity']),
            $validated['reason'] ?? null
        );

        return back()->with('success', 'Violation severity updated.');
    }

    public function storeWarning(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $compliance->addWarning($violation, $request->user(), $validated['message']);

        return back()->with('success', 'Seller warning issued.');
    }

    public function resolve(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        $compliance->resolve($violation, $request->user(), $validated['resolution_notes']);

        return back()->with('success', 'Compliance case resolved.');
    }

    public function dismiss(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        $compliance->dismiss($violation, $request->user(), $validated['resolution_notes']);

        return back()->with('success', 'Compliance case dismissed.');
    }

    public function suspendSeller(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'confirmation' => ['required', 'accepted'],
        ]);

        $compliance->suspendSeller(
            $violation,
            $request->user(),
            $validated['reason'],
            $request->boolean('confirmation')
        );

        return back()->with('success', 'Seller account and related profiles suspended.');
    }
}
