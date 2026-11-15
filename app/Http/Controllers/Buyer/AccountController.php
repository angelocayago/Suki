<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\BuyerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function addresses(Request $request, BuyerAddressService $addresses): View
    {
        $items = $addresses->all($this->buyer($request))->map(fn (Address $address) => $addresses->present($address) + ['house_number' => ''])->all();
        return view('buyer.addresses', ['addresses' => $items]);
    }

    public function storeAddress(Request $request, BuyerAddressService $addresses): RedirectResponse
    {
        $addresses->create($this->buyer($request), $addresses->data($request->all()), $request->boolean('is_default'));
        return redirect()->route('buyer.addresses')->with('success', 'New address added successfully.');
    }

    public function updateAddress(Request $request, int $address, BuyerAddressService $addresses): RedirectResponse
    {
        $addresses->update($this->buyer($request), $address, $addresses->data($request->all()));
        return redirect()->route('buyer.addresses')->with('success', 'Address updated successfully.');
    }

    public function setDefault(Request $request, int $address, BuyerAddressService $addresses): RedirectResponse
    {
        $addresses->setDefault($this->buyer($request), $address);
        return redirect()->route('buyer.addresses')->with('success', 'Default address updated.');
    }

    public function deleteAddress(Request $request, int $address, BuyerAddressService $addresses): RedirectResponse
    {
        $addresses->delete($this->buyer($request), $address);
        return redirect()->route('buyer.addresses')->with('success', 'Address removed successfully.');
    }

    private function buyer(Request $request): \App\Models\User
    {
        $user = $request->user();
        abort_unless($user?->isBuyer() && $user->status === 'active' && ! $user->is_suspended, 403);

        return $user;
    }
}
