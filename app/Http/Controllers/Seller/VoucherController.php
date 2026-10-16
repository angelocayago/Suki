<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    private function seller(): Seller
    {
        $seller = Auth::user()?->seller;
        abort_unless($seller && $seller->status === 'approved', 403, 'Approved seller account required.');
        return $seller;
    }

    private function ownedVoucher(Seller $seller, Voucher $voucher): Voucher
    {
        abort_unless($voucher->owner_type === 'seller' && (int) $voucher->seller_id === (int) $seller->id, 404);
        return $voucher;
    }

    public function index(Request $request)
    {
        $seller = $this->seller();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $query = Voucher::query()
            ->where('owner_type', 'seller')
            ->where('seller_id', $seller->id);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('code', 'ilike', '%' . $search . '%');
            });
        }

        switch ($status) {
            case 'active':
                $query->where('is_enabled', true)->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now())
                    ->where(fn ($q) => $q->whereNull('usage_limit')
                        ->orWhereColumn('usage_count', '<', 'usage_limit'));
                break;
            case 'scheduled':
                $query->where('is_enabled', true)->where('starts_at', '>', now());
                break;
            case 'paused':
                $query->where('is_enabled', false);
                break;
            case 'expired':
                $query->where('is_enabled', true)->where('ends_at', '<=', now());
                break;
            case 'exhausted':
                $query->where('is_enabled', true)->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now())->whereNotNull('usage_limit')
                    ->whereColumn('usage_count', '>=', 'usage_limit');
                break;
        }

        $vouchers = $query->latest()->paginate(15)->withQueryString();
        return view('seller.vouchers.index', compact('seller', 'vouchers'));
    }

    public function create()
    {
        $seller = $this->seller();
        $products = $seller->products()->where('status', 'published')
            ->where('compliance_status', 'approved')->orderBy('name')->get(['id', 'name']);
        return view('seller.vouchers.create', compact('seller', 'products'));
    }

    private function validated(Request $request, Seller $seller, ?Voucher $voucher = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code', '')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_amount' => ['required_if:discount_type,fixed', 'nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'discount_percent' => ['required_if:discount_type,percentage', 'nullable', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
            'minimum_spend' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'maximum_discount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'product_scope' => ['required', Rule::in(['all', 'selected'])],
            'product_ids' => ['required_if:product_scope,selected', 'array'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->where(fn ($q) => $q->where('seller_id', $seller->id)->where('status', 'published')->where('compliance_status', 'approved'))],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
        if ($data['product_scope'] === 'selected' && empty($data['product_ids'])) {
            throw ValidationException::withMessages(['product_ids' => 'Select at least one eligible product.']);
        }
        if ($data['discount_type'] === 'fixed' && !empty($data['maximum_discount'])) {
            throw ValidationException::withMessages(['maximum_discount' => 'Maximum discount applies only to percentage vouchers.']);
        }
        if ($data['discount_type'] === 'percentage' && !empty($data['discount_amount'])) {
            throw ValidationException::withMessages(['discount_amount' => 'Fixed amount applies only to fixed vouchers.']);
        }
        return $data;
    }

    private function minor(string|int|float $value): int
    {
        // Input is validated to at most two decimal places.
        $parts = explode('.', (string) $value, 2);
        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function attributes(array $data, Seller $seller): array
    {
        return [
            'owner_type' => 'seller',
            'seller_id' => $seller->id,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value_minor' => $data['discount_type'] === 'fixed' ? $this->minor($data['discount_amount']) : null,
            'discount_percent_bps' => $data['discount_type'] === 'percentage' ? $this->minor($data['discount_percent']) : null,
            'minimum_spend_minor' => $this->minor($data['minimum_spend']),
            'maximum_discount_minor' => $data['discount_type'] === 'percentage' && isset($data['maximum_discount']) ? $this->minor($data['maximum_discount']) : null,
            'product_scope' => $data['product_scope'],
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_user_limit' => $data['per_user_limit'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
        ];
    }

    public function store(Request $request)
    {
        $seller = $this->seller();
        $data = $this->validated($request, $seller);
        DB::transaction(function () use ($data, $seller) {
            $voucher = Voucher::create($this->attributes($data, $seller));
            if ($data['product_scope'] === 'selected') {
                $voucher->products()->sync($data['product_ids']);
            }
        });
        return redirect()->route('seller.vouchers.index')->with('success', 'Voucher created. Checkout redemption is not enabled yet.');
    }

    public function edit(Voucher $voucher)
    {
        $seller = $this->seller();
        $voucher = $this->ownedVoucher($seller, $voucher);
        $products = $seller->products()->where('status', 'published')
            ->where('compliance_status', 'approved')->orderBy('name')->get(['id', 'name']);
        $voucher->load('products:id');
        return view('seller.vouchers.edit', compact('seller', 'voucher', 'products'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $seller = $this->seller();
        $voucher = $this->ownedVoucher($seller, $voucher);
        $data = $this->validated($request, $seller, $voucher);
        DB::transaction(function () use ($voucher, $data, $seller) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($locked->usage_count > 0 || $locked->redemptions()->exists()) {
                throw ValidationException::withMessages(['voucher' => 'A used voucher cannot be edited. Pause it and create a new one.']);
            }
            $locked->update($this->attributes($data, $seller));
            $locked->products()->sync($data['product_scope'] === 'selected' ? $data['product_ids'] : []);
        });
        return redirect()->route('seller.vouchers.index')->with('success', 'Voucher updated.');
    }

    public function toggle(Voucher $voucher)
    {
        $seller = $this->seller();
        $voucher = $this->ownedVoucher($seller, $voucher);
        $voucher->update(['is_enabled' => !$voucher->is_enabled]);
        return back()->with('success', 'Voucher availability updated.');
    }
}
