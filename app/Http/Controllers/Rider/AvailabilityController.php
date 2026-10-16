<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['availability' => ['required', Rule::in(['ONLINE', 'OFFLINE'])]]);
        $rider = $request->user()->rider()->with(['user', 'logisticsProvider'])->firstOrFail();
        abort_unless($rider->isOperationallyEligible(), 403);
        $rider->update(['availability' => $data['availability']]);

        return back()->with('success', 'Availability updated to '.strtolower($data['availability']).'.');
    }
}
