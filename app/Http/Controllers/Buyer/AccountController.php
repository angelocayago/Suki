<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function account(Request $request): View
    {
        $user = $this->buyer($request);
        return view('buyer.account', ['profile'=>['first_name'=>$user->first_name ?? strtok($user->name, ' '),'last_name'=>$user->last_name ?? trim(substr($user->name, strlen(strtok($user->name, ' ')))),'email'=>$user->email,'phone'=>$user->phone]]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->buyer($request);
        $data = $request->validate(['first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'email'=>['required','email','max:255',Rule::unique('users')->ignore($user->id)],'phone'=>['required','string','max:30',Rule::unique('users')->ignore($user->id)]]);
        $user->update(['first_name'=>trim($data['first_name']),'last_name'=>trim($data['last_name']),'name'=>trim($data['first_name'].' '.$data['last_name']),'email'=>strtolower(trim($data['email'])),'phone'=>trim($data['phone'])]);
        return redirect()->route('buyer.account')->with('success', 'Your profile has been updated successfully.');
    }

    public function addresses(Request $request): View
    {
        $addresses = $this->buyer($request)->addresses()->orderByDesc('is_default')->latest()->get()->map(fn (Address $a) => ['id'=>$a->id,'name'=>$a->recipient,'phone'=>$a->phone,'province'=>$a->province,'municipality'=>$a->city,'barangay'=>$a->barangay,'street'=>$a->line1,'house_number'=>'','postal_code'=>$a->postal_code,'label'=>$a->label,'is_default'=>$a->is_default])->all();
        return view('buyer.addresses', compact('addresses'));
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $data = $this->addressData($request); $user = $this->buyer($request);
        DB::transaction(function () use ($data, $user, $request): void { $default=$request->boolean('is_default') || ! $user->addresses()->exists(); if ($default) $user->addresses()->update(['is_default'=>false]); $user->addresses()->create($data+['is_default'=>$default]); }, 3);
        return redirect()->route('buyer.addresses')->with('success', 'New address added successfully.');
    }

    public function setDefault(Request $request, int $address): RedirectResponse
    {
        $user=$this->buyer($request); DB::transaction(function () use ($user, $address): void { $owned=$user->addresses()->lockForUpdate()->findOrFail($address); $user->addresses()->update(['is_default'=>false]); $owned->update(['is_default'=>true]); }, 3);
        return redirect()->route('buyer.addresses')->with('success', 'Default address updated.');
    }

    public function deleteAddress(Request $request, int $address): RedirectResponse
    {
        $user=$this->buyer($request); DB::transaction(function () use ($user, $address): void { $owned=$user->addresses()->lockForUpdate()->findOrFail($address); $wasDefault=$owned->is_default; $owned->delete(); if ($wasDefault && ($next=$user->addresses()->lockForUpdate()->oldest('id')->first())) $next->update(['is_default'=>true]); }, 3);
        return redirect()->route('buyer.addresses')->with('success', 'Address removed successfully.');
    }

    private function addressData(Request $request): array
    {
        $data=$request->validate(['name'=>['required','string','max:100'],'phone'=>['required','string','max:30'],'province'=>['required','string','max:100'],'municipality'=>['required','string','max:100'],'barangay'=>['required','string','max:100'],'street'=>['required','string','max:255'],'postal_code'=>['required','string','max:20'],'label'=>['required','string','max:50']]);
        return ['label'=>$data['label'],'recipient'=>$data['name'],'phone'=>$data['phone'],'line1'=>$data['street'],'barangay'=>$data['barangay'],'city'=>$data['municipality'],'province'=>$data['province'],'postal_code'=>$data['postal_code']];
    }

    private function buyer(Request $request): \App\Models\User
    {
        $user = $request->user();
        abort_unless($user?->isBuyer() && $user->status === 'active' && ! $user->is_suspended, 403);

        return $user;
    }
}
