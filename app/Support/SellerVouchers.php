<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\SellerVoucher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerVouchers
{
    public const STATUSES = ['active' => 'Active schedule', 'scheduled' => 'Scheduled', 'paused' => 'Paused', 'expired' => 'Expired', 'exhausted' => 'Usage limit reached', 'unavailable' => 'Unavailable products/shop', 'archived' => 'Archived'];

    public const SORTS = ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'ending' => 'Ending Soonest', 'name' => 'Name A–Z'];

    public const TERM_INPUTS = ['code', 'discount_type', 'discount_value', 'minimum_spend', 'maximum_discount', 'product_scope', 'product_ids', 'starts_at', 'ends_at', 'total_limit', 'per_buyer_limit'];

    public static function products(Seller $seller)
    {
        return $seller->products()->where('is_active', true)->whereHas('variants', fn ($q) => $q->where('is_active', true));
    }

    public static function owned(Seller $seller, string $id): SellerVoucher
    {
        return SellerVoucher::where('seller_id', $seller->id)->findOrFail($id);
    }

    public static function minor(string|int|float $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function availableProductsSql(): string
    {
        return "EXISTS (SELECT 1 FROM products p WHERE p.seller_id = seller_vouchers.seller_id AND p.is_active = 1 AND p.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1 AND pv.deleted_at IS NULL)
            AND (seller_vouchers.product_scope = 'all' OR EXISTS (SELECT 1 FROM seller_voucher_products vp WHERE vp.seller_voucher_id = seller_vouchers.id AND vp.product_id = p.id)))";
    }

    public static function availabilitySql(): string
    {
        return "CASE WHEN archived_at IS NOT NULL THEN 'archived' WHEN status = 'paused' THEN 'paused'
            WHEN ends_at <= ? THEN 'expired' WHEN total_limit IS NOT NULL AND used_count >= total_limit THEN 'exhausted'
            WHEN NOT ".self::availableProductsSql()." OR NOT EXISTS (SELECT 1 FROM sellers s JOIN users u ON u.id=s.user_id WHERE s.id=seller_vouchers.seller_id AND s.status='approved' AND u.status='active' AND u.is_suspended=0) THEN 'unavailable'
            WHEN starts_at > ? THEN 'scheduled' ELSE 'active' END";
    }

    public static function availability(SellerVoucher $voucher): string
    {
        $at = now()->format('Y-m-d H:i:s');

        return SellerVoucher::whereKey($voucher->id)->selectRaw(self::availabilitySql().' as availability', [$at, $at])->value('availability');
    }

    public static function page(Seller $seller, Request $request): array
    {
        $products = self::products($seller)->orderBy('name')->get();
        $criteria = $request->validate(['search' => 'nullable|string|max:200', 'availability' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'discount_type' => 'nullable|in:percentage,fixed', 'product_scope' => 'nullable|in:all,selected', 'product_id' => ['nullable', 'integer', Rule::in($products->pluck('id')->all())],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))], 'page' => 'nullable|integer|min:1']);
        $at = now()->format('Y-m-d H:i:s');
        $query = SellerVoucher::where('seller_id', $seller->id)->select('seller_vouchers.*')->selectRaw(self::availabilitySql().' as availability', [$at, $at]);
        if (($criteria['search'] ?? '') !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$criteria['search'].'%')->orWhere('code', 'like', '%'.$criteria['search'].'%'));
        }
        foreach (['discount_type', 'product_scope'] as $field) {
            if (! empty($criteria[$field])) {
                $query->where($field, $criteria[$field]);
            }
        }
        if (! empty($criteria['availability'])) {
            $query->whereRaw('('.self::availabilitySql().') = ?', [$at, $at, $criteria['availability']]);
        }
        if (! empty($criteria['product_id'])) {
            $query->where(fn ($q) => $q->where('product_scope', 'all')->orWhereHas('products', fn ($q) => $q->where('products.id', $criteria['product_id'])));
        }
        SellerDateFilter::apply($query, $request, 'seller_vouchers.created_at');
        $criteria += $request->only(['date_range', 'from', 'to']);
        [$column, $direction] = match ($criteria['sort'] ?? 'newest') {
            'oldest' => ['created_at', 'asc'], 'ending' => ['ends_at', 'asc'], 'name' => ['name', 'asc'], default => ['created_at', 'desc']
        };
        $paginator = $query->orderBy($column, $direction)->orderBy('id')->paginate(15)->appends(array_diff_key($criteria, ['page' => true]));
        $filterChips = SellerFilterChips::make($criteria, ['availability' => self::STATUSES, 'discount_type' => ['percentage' => 'Percentage', 'fixed' => 'Fixed Amount'],
            'product_scope' => ['all' => 'All Seller Products', 'selected' => 'Selected Products'], 'product_id' => $products->pluck('name', 'id')->all(), 'sort' => self::SORTS],
            route('seller.vouchers.index'), ['search', 'availability', 'discount_type', 'product_scope', 'product_id', 'sort', 'date_range', 'from', 'to'], 'newest');

        return compact('products', 'criteria', 'paginator', 'filterChips');
    }

    public static function save(Seller $seller, Request $request, ?SellerVoucher $voucher = null): SellerVoucher
    {
        return DB::transaction(function () use ($seller, $request, $voucher) {
            if ($voucher) {
                $voucher = SellerVoucher::where('seller_id', $seller->id)->lockForUpdate()->findOrFail($voucher->id);
                abort_if($voucher->archived_at !== null, 409, 'Archived vouchers are read-only.');
            }
            if ($voucher?->termsLocked()) {
                $rules = ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:2000'];
                foreach (self::TERM_INPUTS as $field) {
                    $rules[$field] = 'prohibited';
                }
                $rules['status'] = 'prohibited';
                $validated = $request->validate($rules);
                $voucher->update(array_intersect_key($validated, array_flip(['name', 'description'])));

                return $voucher;
            }
            $request->merge(['code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code')]);
            $allowedProducts = self::products($seller)->pluck('id');
            if ($voucher) {
                $allowedProducts = $allowedProducts->merge($voucher->products()->where('products.seller_id', $seller->id)->pluck('products.id'))->unique();
            }
            $money = ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/D', 'numeric', 'min:0', 'max:42949672.95'];
            $data = $request->validate([
                'name' => 'required|string|max:100', 'description' => 'nullable|string|max:2000',
                'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]{2,31}$/D', Rule::unique('seller_vouchers', 'code')->ignore($voucher?->id)],
                'discount_type' => 'required|in:percentage,fixed', 'discount_value' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/D', 'numeric', 'min:0.01', 'max:'.($request->input('discount_type') === 'percentage' ? '100' : '42949672.95')],
                'minimum_spend' => $money, 'maximum_discount' => [...$money, 'gt:0', Rule::prohibitedIf($request->input('discount_type') === 'fixed')],
                'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at',
                'total_limit' => 'nullable|integer|min:1|max:1000000000', 'per_buyer_limit' => ['required', 'integer', 'min:1', 'max:1000000000', Rule::when($request->filled('total_limit'), 'lte:total_limit')],
                'product_scope' => 'required|in:all,selected', 'product_ids' => ['array', 'max:500', Rule::requiredIf($request->input('product_scope') === 'selected')],
                'product_ids.*' => ['integer', 'distinct', Rule::in($allowedProducts->all())], 'status' => 'required|in:active,paused',
            ]);
            if ($voucher && $voucher->code !== $data['code']) {
                throw ValidationException::withMessages(['code' => 'Saved codes cannot change. Create a new voucher.']);
            }
            if ($data['product_scope'] === 'selected' && empty($data['product_ids'])) {
                throw ValidationException::withMessages(['product_ids' => 'Choose at least one eligible product.']);
            }
            $voucher ??= new SellerVoucher(['seller_id' => $seller->id]);
            $voucher->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'code' => $data['code'], 'discount_type' => $data['discount_type'],
                'discount_bps' => $data['discount_type'] === 'percentage' ? self::minor($data['discount_value']) : null,
                'discount_minor' => $data['discount_type'] === 'fixed' ? self::minor($data['discount_value']) : null,
                'minimum_minor' => self::minor($data['minimum_spend'] ?? '0'), 'cap_minor' => isset($data['maximum_discount']) ? self::minor($data['maximum_discount']) : null,
                'starts_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], 'Asia/Manila')->setTimezone(config('app.timezone')),
                'ends_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], 'Asia/Manila')->setTimezone(config('app.timezone')),
                'total_limit' => $data['total_limit'] ?? null, 'per_buyer_limit' => $data['per_buyer_limit'], 'product_scope' => $data['product_scope'], 'status' => $data['status']]);
            $voucher->save();
            $voucher->products()->sync($data['product_scope'] === 'selected' ? $data['product_ids'] : []);
            if ($voucher->status === 'active') {
                self::assertActivatable($seller, $voucher);
            }

            return $voucher;
        }, 3);
    }

    private static function assertActivatable(Seller $seller, SellerVoucher $voucher): void
    {
        $valid = $voucher->ends_at->isFuture() && $voucher->ends_at->greaterThan($voucher->starts_at)
            && ($voucher->total_limit === null || $voucher->used_count < $voucher->total_limit)
            && $voucher->per_buyer_limit > 0 && ($voucher->total_limit === null || $voucher->per_buyer_limit <= $voucher->total_limit)
            && ($voucher->discount_type === 'percentage' ? $voucher->discount_bps > 0 && $voucher->discount_bps <= 10000 && $voucher->discount_minor === null : $voucher->discount_minor > 0 && $voucher->discount_bps === null && $voucher->cap_minor === null)
            && ($voucher->cap_minor === null || $voucher->cap_minor > 0);
        $query = self::products($seller);
        if ($voucher->product_scope === 'selected') {
            $query->whereIn('id', $voucher->products()->pluck('products.id'));
        }
        if (! $valid || ! $query->exists()) {
            throw ValidationException::withMessages(['status' => 'This voucher has expired, reached its limit, or has invalid terms or no eligible products.']);
        }
    }

    public static function lifecycle(Seller $seller, SellerVoucher $voucher, string $action): void
    {
        DB::transaction(function () use ($seller, $voucher, $action) {
            $voucher = SellerVoucher::where('seller_id', $seller->id)->lockForUpdate()->findOrFail($voucher->id);
            if ($action === 'archive') {
                if ($voucher->archived_at === null) {
                    $voucher->forceFill(['status' => 'paused', 'archived_at' => now()])->save();
                }

                return;
            }
            abort_if($voucher->archived_at !== null, 409, 'Archived vouchers are read-only.');
            if ($action === 'activate') {
                self::assertActivatable($seller, $voucher);
            }
            $voucher->update(['status' => $action === 'activate' ? 'active' : 'paused']);
        }, 3);
    }
}
