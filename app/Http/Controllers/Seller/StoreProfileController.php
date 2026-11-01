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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StoreProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('seller.store-profile', ['seller'=>$this->seller($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $seller=$this->seller($request); $data=$request->validate(['store_name'=>['nullable','string','max:150'],'phone'=>['required','string','max:30'],'description'=>['nullable','string','max:500'],'profile_photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096']]);
        $staged = []; $old = [];
        try {
            foreach (['profile_photo'=>'logo_path', 'banner'=>'banner_path'] as $input=>$column) {
                if (! $request->hasFile($input)) continue;
                try {
                    $path = $request->file($input)->store('seller-store-assets', 'public');
                    if (is_string($path) && $path !== '') $staged[$column] = $path;
                    if (! isset($staged[$column]) || ! Storage::disk('public')->exists($path)) {
                        throw new \RuntimeException('Store image write could not be verified.');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages([$input=>'The image could not be saved. Your previous image is unchanged. Please try again.']);
                }
            }
            DB::transaction(function () use ($seller, $data, $staged, &$old): void {
                $locked = Seller::query()->whereKey($seller->id)->where('user_id', $seller->user_id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
                $changes = ['name'=>filled($data['store_name'] ?? null) ? trim($data['store_name']) : $locked->name,
                    'description'=>filled($data['description'] ?? null) ? trim($data['description']) : null];
                foreach ($staged as $column=>$path) { $old[$column] = $locked->{$column}; $changes[$column] = $path; }
                $locked->update($changes);
                $locked->owner->update(['phone'=>trim($data['phone'])]);
            }, 3);
        } catch (Throwable $exception) {
            foreach ($staged as $path) $this->deleteUnusedImage($path);
            throw $exception;
        }
        foreach ($old as $path) $this->deleteUnusedImage($path);

        return $this->profileSaved('Shop profile updated successfully.');
    }

    public function address(Request $request, PhilippineAddressService $addresses): RedirectResponse
    {
        $seller=$this->seller($request); $data=$request->validate(['region'=>['required','string','max:20'],'province'=>['nullable','string','max:20'],'municipality'=>['required','string','max:20'],'barangay'=>['required','string','max:20'],'address'=>['required','string','max:255']]); $resolved=$addresses->resolve($data);
        DB::transaction(function () use ($seller, $data, $resolved): void { $address=$seller->pickupAddress ?: new Address(['user_id'=>$seller->user_id,'label'=>'Store pickup']); $address->fill(['recipient'=>$seller->name,'phone'=>$seller->owner->phone ?? '','line1'=>$data['address'],'region'=>$resolved['region'],'region_code'=>$resolved['region_code'],'barangay'=>$resolved['barangay'],'barangay_code'=>$resolved['barangay_code'],'city'=>$resolved['municipality'],'city_municipality_code'=>$resolved['city_municipality_code'],'province'=>$resolved['province'],'province_code'=>$resolved['province_code'],'postal_code'=>$resolved['postal_code'] ?? null]); $address->save(); $seller->update(['pickup_address_id'=>$address->id]); }, 3);
        return redirect(route('seller.store.profile').'#pickup-address')->with('success', 'Pickup address updated successfully.');
    }

    public function removePhoto(Request $request): RedirectResponse
    {
        return $this->removeImage($request, 'logo_path', 'Shop photo removed successfully.');
    }

    public function removeBanner(Request $request): RedirectResponse
    {
        return $this->removeImage($request, 'banner_path', 'Store banner removed successfully.');
    }

    private function removeImage(Request $request, string $column, string $message): RedirectResponse
    {
        $seller = $this->seller($request);
        $path = DB::transaction(function () use ($seller, $column) {
            $locked = Seller::query()->whereKey($seller->id)->where('user_id', $seller->user_id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
            $path = $locked->{$column};
            $locked->update([$column=>null]);
            return $path;
        }, 3);
        $this->deleteUnusedImage($path);

        return $this->profileSaved($message);
    }

    private function profileSaved(string $message): RedirectResponse
    {
        return redirect(route('seller.store.profile').'#store-profile-feedback')
            ->with('success', $message)->with('store_profile_feedback', true);
    }

    private function deleteUnusedImage(?string $path): void
    {
        // Never delete arbitrary paths or files still referenced by either image field.
        if (! $path || ! preg_match('~\A(?:seller-store-assets|seller-profiles)/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)\z~i', $path)) return;
        try {
            if (! Seller::query()->where('logo_path', $path)->orWhere('banner_path', $path)->exists()) {
                if (! Storage::disk('public')->delete($path)) report(new \RuntimeException('An unused store image could not be removed.'));
            }
        } catch (Throwable $exception) {
            report($exception); // A successful committed profile must not be reported as failed by old-file cleanup.
        }
    }

    private function seller(Request $request): Seller
    {
        $user = $request->user();
        abort_unless($user?->isSeller() && $user->status === 'active' && ! $user->is_suspended, 403);

        return $user->sellers()->where('status', 'approved')->with(['owner', 'category', 'pickupAddress'])->firstOrFail();
    }
}
