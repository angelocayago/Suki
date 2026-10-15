<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\SellerWalletEntry;
use App\Services\SellerWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function seller(Request $request, SellerWalletService $wallet): View
    {
        $seller = $request->user()->sellers()->where('status', 'approved')->firstOrFail();
        return view('seller.wallet.index', ['wallet'=>$wallet->summary($seller),'payouts'=>Payout::query()->where('seller_id',$seller->id)->latest()->get(),'entries'=>SellerWalletEntry::query()->where('seller_id',$seller->id)->latest()->limit(30)->get()]);
    }

    public function request(Request $request, SellerWalletService $wallet): RedirectResponse
    {
        $seller = $request->user()->sellers()->where('status', 'approved')->firstOrFail();
        $amount = $request->validate(['amount'=>['required','regex:/^\d+(?:\.\d{1,2})?$/']])['amount'];
        $wallet->request($seller, $this->toMinor((string) $amount));
        return back()->with('success', 'Payout requested.');
    }

    public function admin(): View { return view('admin.payouts.index', ['payouts'=>Payout::query()->with('seller')->latest()->paginate(30)]); }

    public function review(Request $request, Payout $payout, SellerWalletService $wallet): RedirectResponse
    {
        $data = $request->validate(['status'=>['required','in:APPROVED,REJECTED'],'reason'=>['nullable','string','max:1000']]);
        $wallet->review($payout, $request->user(), $data['status'], $data['reason'] ?? null);
        return back()->with('success', 'Payout reviewed.');
    }

    public function paid(Request $request, Payout $payout, SellerWalletService $wallet): RedirectResponse
    {
        $data = $request->validate(['method'=>['required','in:manual,bank,e_wallet'],'reference'=>['nullable','string','max:100']]);
        $wallet->markPaid($payout, $request->user(), $data['method'], $data['reference'] ?? null);
        return back()->with('success', 'Payout marked paid.');
    }

    private function toMinor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
