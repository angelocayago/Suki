<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Address;
use App\Services\BuyerAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerAddressController
{
    public function index(Request $request, BuyerAddressService $addresses): JsonResponse
    {
        return $this->ok(['addresses' => $addresses->all($request->user())->map(fn (Address $address) => $addresses->present($address))->values()]);
    }

    public function show(Request $request, BuyerAddressService $addresses, int $address): JsonResponse
    {
        return $this->ok(['address' => $addresses->present($this->owned($request, $address))]);
    }

    public function store(Request $request, BuyerAddressService $addresses): JsonResponse
    {
        $input = $this->input($request);
        $address = $addresses->create($request->user(), $addresses->data($input), (bool) ($input['is_default'] ?? false));

        return $this->ok(['address' => $addresses->present($address)], 201);
    }

    public function update(Request $request, BuyerAddressService $addresses, int $address): JsonResponse
    {
        $input = $this->input($request);
        // Scope before contacting the location service, avoiding cross-user data disclosure.
        $this->owned($request, $address);
        $updated = $addresses->update($request->user(), $address, $addresses->data($input), (bool) ($input['is_default'] ?? false));

        return $this->ok(['address' => $addresses->present($updated)]);
    }

    public function destroy(Request $request, BuyerAddressService $addresses, int $address): JsonResponse
    {
        $addresses->delete($request->user(), $address);

        return $this->ok(['message' => 'Address removed successfully.']);
    }

    public function setDefault(Request $request, BuyerAddressService $addresses, int $address): JsonResponse
    {
        return $this->ok(['address' => $addresses->present($addresses->setDefault($request->user(), $address))]);
    }

    private function owned(Request $request, int $address): Address
    {
        return Address::query()->where('user_id', $request->user()->id)->findOrFail($address);
    }

    /** Accept existing Flutter checkout names while never accepting an owner id. */
    private function input(Request $request): array
    {
        $input = $request->all();
        $input['name'] ??= $input['recipient'] ?? null;
        $input['street'] ??= $input['line1'] ?? null;
        $input['municipality'] ??= $input['city'] ?? null;
        $input['is_default'] = $request->boolean('is_default');

        return $input;
    }

    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
