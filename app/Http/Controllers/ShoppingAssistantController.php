<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Services\MessagingService;
use App\Services\ShoppingAssistantService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShoppingAssistantController extends Controller
{
    public function message(Request $request, ShoppingAssistantService $assistant)
    {
        $data=$request->validate(['message'=>['required','string','min:1','max:600']]);
        return response()->json($assistant->reply($request,$data['message']));
    }
    public function clear(Request $request, ShoppingAssistantService $assistant)
    {
        $assistant->clear($request); return response()->json(['cleared'=>true]);
    }
    public function handoff(Request $request, Product $product, MessagingService $messages)
    {
        abort_unless($request->user()?->isBuyer(),403);
        $data=$request->validate(['confirm'=>['required','accepted']]);
        $visible=app(\App\Services\ProductCatalogService::class)->marketplaceProduct($product->slug);
        abort_unless($visible,404);
        $conversation=$messages->createProductInquiry($request->user(),$product,'Buyer requested human support from SUKI Assistant.');
        return response()->json(['conversation_url'=>route('buyer.messages.show',$conversation)]);
    }
}
