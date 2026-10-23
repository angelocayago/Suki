<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Support\ChatWorkspace;
use App\Support\SellerMessaging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        return view('buyer.messages.index', ChatWorkspace::buyer($request));
    }

    public function show(Request $request, string $conversation)
    {
        SellerMessaging::buyer();
        $request->validate(['page' => 'nullable|integer|min:1']);

        return view('buyer.messages.show', ChatWorkspace::buyer($request, $conversation));
    }

    public function contact(string $kind, string $id)
    {
        $product = $kind === 'products' ? SellerMessaging::contactProduct($id) : null;
        $shop = SellerMessaging::contactShop($product ? (string) $product->seller_id : $id);

        return view('buyer.messages.contact', ChatWorkspace::buyer(request()) + compact('shop', 'product', 'kind', 'id'));
    }

    private function inputs(Request $request): array
    {
        SellerMessaging::buyer();

        return $request->validate(['body' => 'nullable|string|max:5000', 'images' => 'sometimes|array|max:5', 'images.*' => 'required|file|max:5120', 'submission_key' => 'required|uuid',
            'buyer_id' => 'prohibited', 'seller_id' => 'prohibited', 'sender_id' => 'prohibited', 'sender_user_id' => 'prohibited', 'sender_role' => 'prohibited', 'receiver_id' => 'prohibited', 'conversation_id' => 'prohibited']);
    }

    public function start(Request $request, string $kind, string $id)
    {
        $data = $this->inputs($request);
        $product = $kind === 'products' ? SellerMessaging::contactProduct($id) : null;
        $shop = SellerMessaging::contactShop($product ? (string) $product->seller_id : $id);
        $navigation = ChatWorkspace::returnList($request, 'buyer');
        $conversation = DB::transaction(function () use ($request, $data, $shop) {
            $conversation = SellerMessaging::startForBuyer($shop);
            SellerMessaging::sendForBuyer((string) $conversation->id, $data['body'] ?? '', $data['submission_key'], $request->file('images', []));

            return $conversation;
        });

        return redirect()->route('buyer.messages.show', ['conversation' => $conversation->id] + $navigation);
    }

    public function send(Request $request, string $conversation)
    {
        SellerMessaging::ownedByBuyer($conversation);
        $data = $this->inputs($request);
        $navigation = ChatWorkspace::returnList($request, 'buyer');
        SellerMessaging::sendForBuyer($conversation, $data['body'] ?? '', $data['submission_key'], $request->file('images', []));

        return redirect()->route('buyer.messages.show', ['conversation' => $conversation] + $navigation)->with('success', 'Message sent.');
    }
}
