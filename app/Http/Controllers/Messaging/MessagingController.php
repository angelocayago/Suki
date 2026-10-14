<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

abstract class MessagingController extends Controller
{
    protected function portal(Request $request): array
    {
        return match ($request->route('messaging_portal')) {
            'buyer' => [
                'name' => 'buyer',
                'layout' => 'layouts.app',
                'route_prefix' => 'buyer.messages',
                'role_contexts' => ['BUYER'],
            ],
            'seller' => [
                'name' => 'seller',
                'layout' => 'layouts.seller',
                'route_prefix' => 'seller.messages',
                'role_contexts' => ['SELLER'],
            ],
            'rider' => [
                'name' => 'rider',
                'layout' => 'layouts.rider',
                'route_prefix' => 'rider.messages',
                'role_contexts' => ['PICKUP_RIDER', 'DELIVERY_RIDER'],
            ],
            'logistics' => [
                'name' => 'logistics',
                'layout' => 'layouts.logistics',
                'route_prefix' => 'logistics.messages',
                'role_contexts' => ['LOGISTICS'],
            ],
            'superadmin' => [
                'name' => 'superadmin',
                'layout' => 'superadmin.layout',
                'route_prefix' => 'superadmin.messages',
                'role_contexts' => ['ADMIN'],
            ],
            default => abort(404),
        };
    }

    protected function authorizeConversation(
        Request $request,
        Conversation $conversation,
        MessagingService $messaging,
        string $ability = 'view',
    ): array {
        $portal = $this->portal($request);
        Gate::forUser($request->user())->authorize($ability, $conversation);
        $messaging->ensureCanAccessAs($conversation, $request->user(), $portal['role_contexts']);

        return $portal;
    }
}
