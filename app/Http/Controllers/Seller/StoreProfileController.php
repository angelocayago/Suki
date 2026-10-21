<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Seller;
use App\Services\PhilippineAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('seller.store-profile', ['seller'=>$this->seller($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $seller=$this->seller($request); $data=$request->validate(['store_name'=>['nullable','string','max:150'],'phone'=>['required','string','max:30'],'description'=>['nullable','string','max:500'],'profile_photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096']]);
        DB::transaction(function () use ($seller, $request, $data): void {
            $changes=['name'=>filled($data['store_name'] ?? null) ? trim($data['store_name']) : $seller->name,'description'=>filled($data['description'] ?? null) ? trim($data['description']) : null];
            foreach (['profile_photo'=>'logo_path','banner'=>'banner_path'] as $input=>$column) if ($request->hasFile($input)) { $old=$seller->{$column}; $changes[$column]=$request->file($input)->store('seller-store-assets', 'public'); if ($old && str_starts_with($old, 'seller-store-assets/')) Storage::disk('public')->delete($old); }
            $seller->update($changes); $seller->owner->update(['phone'=>trim($data['phone'])]);
        }, 3);
        return redirect(route('seller.store.profile').'#shop-profile')->with('success', 'Shop profile updated successfully.');
    }

    public function address(Request $request, PhilippineAddressService $addresses): RedirectResponse
    {
        $seller=$this->seller($request); $data=$request->validate(['region'=>['required','string','max:20'],'province'=>['nullable','string','max:20'],'municipality'=>['required','string','max:20'],'barangay'=>['required','string','max:20'],'address'=>['required','string','max:255']]); $resolved=$addresses->resolve($data);
        DB::transaction(function () use ($seller, $data, $resolved): void { $address=$seller->pickupAddress ?: new Address(['user_id'=>$seller->user_id,'label'=>'Store pickup']); $address->fill(['recipient'=>$seller->name,'phone'=>$seller->owner->phone ?? '','line1'=>$data['address'],'region'=>$resolved['region'],'region_code'=>$resolved['region_code'],'barangay'=>$resolved['barangay'],'barangay_code'=>$resolved['barangay_code'],'city'=>$resolved['municipality'],'city_municipality_code'=>$resolved['city_municipality_code'],'province'=>$resolved['province'],'province_code'=>$resolved['province_code'],'postal_code'=>'']); $address->save(); $seller->update(['pickup_address_id'=>$address->id]); }, 3);
        return redirect(route('seller.store.profile').'#pickup-address')->with('success', 'Pickup address updated successfully.');
    }

    private function seller(Request $request): Seller
    {
        $user = $request->user();
        abort_unless($user?->isSeller() && $user->status === 'active' && ! $user->is_suspended, 403);

        return $user->sellers()->where('status', 'approved')->with(['owner', 'category', 'pickupAddress'])->firstOrFail();
    }
}
