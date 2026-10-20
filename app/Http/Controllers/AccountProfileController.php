<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function show(Request $request, string $area): View
    {
        $this->authorizeArea($request, $area);

        return view('account.self-profile', ['area' => $area, 'user' => $request->user()]);
    }

    public function update(Request $request, string $area): RedirectResponse
    {
        $this->authorizeArea($request, $area);
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:4'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
        ]);

        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $user->update([
            'first_name' => $first,
            'last_name' => $last,
            'middle_initial' => filled($data['middle_initial'] ?? null) ? trim($data['middle_initial']) : null,
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'name' => trim($first.' '.$last),
        ]);

        return back()->with('success', 'Profile updated.');
    }

    private function authorizeArea(Request $request, string $area): void
    {
        $user = $request->user();
        abort_unless($user && $user->status === 'active' && ! $user->is_suspended, 403);
        abort_unless(match ($area) {
            'admin' => $user->isAdmin(),
            'logistics' => $user->isLogistics(),
            'rider' => $user->isRider(),
            default => false,
        }, 403);
    }
}
