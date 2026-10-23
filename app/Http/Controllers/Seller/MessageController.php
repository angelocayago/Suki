<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\ChatWorkspace;
use App\Support\SellerAccount;
use App\Support\SellerMessaging;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        return view('seller.messages.index', ChatWorkspace::seller($request));
    }

    public function show(string $conversation)
    {
        request()->validate(['page' => 'nullable|integer|min:1']);

        return view('seller.messages.show', ChatWorkspace::seller(request(), $conversation));
    }

    public function reply(Request $request, string $conversation)
    {
        $shop = SellerAccount::current();
        SellerMessaging::owned($shop, $conversation);
        $data = $request->validate(['body' => 'nullable|string|max:5000', 'images' => 'sometimes|array|max:5', 'images.*' => 'required|file|max:5120', 'submission_key' => 'required|uuid',
            'sender_id' => 'prohibited', 'sender_user_id' => 'prohibited', 'sender_role' => 'prohibited', 'seller_id' => 'prohibited', 'buyer_id' => 'prohibited', 'receiver_id' => 'prohibited', 'conversation_id' => 'prohibited']);
        $navigation = ChatWorkspace::returnList($request, 'seller');
        SellerMessaging::sendForSeller($shop, $conversation, $data['body'] ?? '', $data['submission_key'], $request->file('images', []));

        return redirect()->route('seller.messages.show', ['conversation' => $conversation] + $navigation)->with('success', 'Message sent.');
    }
}
