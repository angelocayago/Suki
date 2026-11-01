<?php

namespace App\Support;

use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/** Seller form adapter. VoucherService remains the Buyer redemption authority. */
class SellerVouchers
{
    public static function statuses(): array
    {
        return ['active'=>'Active', 'scheduled'=>'Scheduled', 'inactive'=>'Inactive', 'expired'=>'Expired', 'exhausted'=>'Fully used', 'archived'=>'Archived'];
    }

    public static function status(Voucher $voucher): string
    {
        if ($voucher->archived_at) return 'archived';
        if ($voucher->expires_at?->lt(now())) return 'expired';
        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) return 'exhausted';
        if (!$voucher->is_active) return 'inactive';
        return $voucher->starts_at?->gt(now()) ? 'scheduled' : 'active';
    }

    public function listing($seller, Request $request): array
    {
        $criteria = $request->validate(['search'=>['nullable','string','max:64'], 'status'=>['nullable',Rule::in(array_keys(self::statuses()))], 'page'=>['nullable','integer','min:1']]);
        $criteria = array_filter(\Illuminate\Support\Arr::except($criteria,'page'), fn($v)=>$v !== null && $v !== '');
        if (isset($criteria['search'])) {
            $criteria['search'] = trim($criteria['search']);
            if ($criteria['search'] === '') unset($criteria['search']);
        }
        $query = Voucher::query()->where('scope','SELLER')->where('seller_id',$seller->id);
        if (isset($criteria['search'])) {
            $criteria['search'] = trim($criteria['search']);
            $term = '%'.str_replace(['!','%','_'],['!!','!%','!_'],strtoupper($criteria['search'])).'%';
            $query->whereRaw("UPPER(code) LIKE ? ESCAPE '!'",[$term]);
        }
        if (isset($criteria['status'])) {
            $sqlite = DB::connection()->getDriverName() === 'sqlite';
            $expired = $sqlite ? 'julianday(expires_at) < julianday(?)' : 'expires_at < ?';
            $future = $sqlite ? 'julianday(starts_at) > julianday(?)' : 'starts_at > ?';
            $now = now()->format('Y-m-d H:i:s.u');
            $query->whereRaw("CASE WHEN archived_at IS NOT NULL THEN 'archived' WHEN $expired THEN 'expired' WHEN usage_limit IS NOT NULL AND used_count >= usage_limit THEN 'exhausted' WHEN is_active = FALSE THEN 'inactive' WHEN $future THEN 'scheduled' ELSE 'active' END = ?",[$now,$now,$criteria['status']]);
        }
        $vouchers = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20)->appends($criteria);
        return compact('vouchers','criteria');
    }

    public function attributes(Request $request, ?Voucher $voucher = null): array
    {
        if (is_string($request->input('code'))) $request->merge(['code'=>strtoupper(trim($request->input('code')))]);
        $rules = [
            'code'=>[$voucher ? 'required' : 'nullable','string','max:64'],
            'discount_type'=>['required',Rule::in(['FIXED','PERCENTAGE'])],
            'discount_value'=>['required','numeric','gt:0','regex:/^\d{1,8}(\.\d{1,2})?$/', $request->input('discount_type') === 'PERCENTAGE' ? 'max:100' : 'max:21474836.47'],
            'usage_limit'=>['nullable','integer','min:1','max:2147483647'],
            'starts_at'=>['nullable','date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'expires_at'=>['nullable','date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'is_active'=>['nullable','boolean'],
            'creation_token'=>['nullable','string','alpha_num','size:40'],
        ];
        if ($request->filled('starts_at')) $rules['expires_at'][] = 'after:starts_at';
        // These settings have no canonical storage/redemption implementation.
        // Reject submissions rather than silently accept ineffective restrictions.
        foreach (['name','description','min_purchase','min_purchase_amount','minimum_purchase','min_spend','min_spend_minor','max_discount_cap','max_discount','max_discount_minor','per_buyer_limit','per_buyer_usage_limit','product_id','product_ids','scope','seller_id','used_count','archived_at'] as $field) $rules[$field] = ['prohibited'];
        $data = $request->validate($rules);
        if (filled($data['code'] ?? null) && Voucher::query()->whereRaw('UPPER(code) = ?',[$data['code']])->when($voucher,fn($q)=>$q->whereKeyNot($voucher->id))->exists()) {
            throw ValidationException::withMessages(['code'=>'This voucher code is already in use.']);
        }
        $percentage = $data['discount_type'] === 'PERCENTAGE';
        $value = self::hundredths($data['discount_value']);
        return [
            'code'=>$data['code'] ?? null, 'discount_type'=>$data['discount_type'],
            'discount_minor'=>$percentage ? 0 : $value, 'discount_bps'=>$percentage ? $value : 0,
            'usage_limit'=>$data['usage_limit'] ?? null,
            'starts_at'=>$this->date($data['starts_at'] ?? null), 'expires_at'=>$this->date($data['expires_at'] ?? null),
            'is_active'=>$voucher?->archived_at ? false : $request->boolean('is_active',true),
        ];
    }

    public function generate(): string
    {
        for ($attempt=0; $attempt<10; $attempt++) {
            $code = 'SHOP-'.Str::upper(Str::random(10));
            if (!Voucher::query()->whereRaw('UPPER(code) = ?',[$code])->exists()) return $code;
        }
        throw ValidationException::withMessages(['code'=>'A unique code could not be generated. Please try again.']);
    }

    public function create($seller, array $attributes): Voucher
    {
        $automatic = blank($attributes['code']);
        for ($attempt=0; $attempt<5; $attempt++) {
            if ($automatic) $attributes['code'] = $this->generate();
            try {
                // A transaction/savepoint leaves PostgreSQL usable after a
                // unique-index race. Only automatically generated codes retry.
                return DB::transaction(fn()=>Voucher::create($attributes + ['scope'=>'SELLER','seller_id'=>$seller->id]));
            } catch (UniqueConstraintViolationException) {
                if (!$automatic) throw ValidationException::withMessages(['code'=>'This voucher code was just taken. Choose a different code.']);
            }
        }
        throw ValidationException::withMessages(['code'=>'A unique code could not be saved. Please try again.']);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value,'Asia/Manila')->setTimezone(config('app.timezone')) : null;
    }

    private static function hundredths(string $value): int
    {
        [$whole,$fraction] = array_pad(explode('.',$value,2),2,'');
        return (int)$whole*100 + (int)str_pad($fraction,2,'0');
    }

    public static function decimal(int $value): string
    {
        return intdiv($value,100).'.'.str_pad((string)($value%100),2,'0',STR_PAD_LEFT);
    }
}
