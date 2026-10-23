<?php

namespace App\Support;

use App\Models\SellerMessage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Composes bounded presentation queries; never sends messages or selects orders. */
class ChatWorkspace
{
    public static function seller(Request $request, ?string $id = null): array
    {
        $shop = SellerAccount::current();
        $listRequest = self::listRequest($request, $id, 'seller');
        $details = $id === null ? [] : SellerMessaging::details($shop, $id);
        $inbox = SellerMessaging::page($shop, $listRequest);
        $inbox['unread'] = SellerMessaging::unread($shop);

        return self::compose('seller', $request, $id, $inbox, $details);
    }

    public static function buyer(Request $request, ?string $id = null): array
    {
        $listRequest = self::listRequest($request, $id, 'buyer');
        $details = $id === null ? [] : SellerMessaging::buyerDetails($id);
        $inbox = SellerMessaging::buyerPage($listRequest);

        return self::compose('buyer', $request, $id, $inbox, $details);
    }

    private static function listRequest(Request $request, ?string $id, string $side): Request
    {
        if ($id === null) {
            return $request;
        }
        $request->validate(['page' => 'nullable|integer|min:1', 'list' => 'sometimes|array']);

        $list = Request::create($request->url(), 'GET', array_intersect_key($request->query('list', []), array_flip($side === 'seller' ? ['search', 'read_status', 'sort', 'page'] : ['search', 'page'])));
        $rules = ['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1'];
        if ($side === 'seller') {
            $rules += ['read_status' => 'nullable|in:read,unread', 'sort' => 'nullable|in:recent,oldest'];
        }
        $list->validate($rules);

        return $list;
    }

    public static function conversationUrl(string $side, int $id, array $criteria): string
    {
        return route($side.'.messages.show', ['conversation' => $id] + ($criteria ? ['list' => $criteria] : []));
    }

    public static function returnList(Request $request, string $side): array
    {
        $navigation = Request::create($request->url(), 'GET', ['list' => $request->query('list', [])]);
        $criteria = self::listRequest($navigation, '1', $side)->query();

        return $criteria ? ['list' => $criteria] : [];
    }

    private static function compose(string $side, Request $request, ?string $id, array $inbox, array $details): array
    {
        $selectedConversation = $details['conversation'] ?? null;
        $listCriteria = $inbox['criteria'];
        $listUrl = route($side.'.messages.index', $listCriteria);
        $filterAction = $id === null ? route($side.'.messages.index') : route($side.'.messages.show', $id);
        $filterReset = $filterAction;
        $filterPrefix = $id === null ? '' : 'list';
        $inboxPaginator = $inbox['paginator'];
        $historyPaginator = $details['paginator'] ?? null;
        $historyPage = $historyPaginator?->currentPage() ?? 1;
        $filterChips = $inbox['filterChips'] ?? [];
        if ($id !== null) {
            $inboxPaginator = new LengthAwarePaginator($inboxPaginator->getCollection(), $inboxPaginator->total(), $inboxPaginator->perPage(), $inboxPaginator->currentPage(), [
                'path' => $filterAction, 'pageName' => 'list[page]',
                'query' => ['list' => array_diff_key($listCriteria, ['page' => true]), 'page' => $historyPage],
            ]);
            $historyPaginator->appends($listCriteria ? ['list' => $listCriteria] : []);
            foreach ($filterChips as &$chip) {
                parse_str(parse_url($chip['url'], PHP_URL_QUERY) ?? '', $remaining);
                $chip['url'] = self::conversationUrl($side, (int) $id, $remaining);
            }
            unset($chip);
        }
        $selectedPreview = null;
        if ($selectedConversation && ! $inboxPaginator->getCollection()->contains('id', $selectedConversation->id)) {
            // A selected thread outside the filtered/current inbox page is pinned separately.
            $latest = SellerMessage::where('conversation_id', $selectedConversation->id)->orderByDesc('created_at')->orderByDesc('id')->first(['body']);
            $selectedPreview = (object) ['id' => $selectedConversation->id, 'buyer_name' => $details['buyerName'] ?? null,
                'shop_name' => $details['shop']->name ?? null, 'last_body' => $latest?->body, 'last_message_at' => $selectedConversation->last_message_at,
                'unread_count' => SellerMessage::where('conversation_id', $selectedConversation->id)->where('sender_role', $side === 'seller' ? 'buyer' : 'seller')->whereNull('read_at')->count()];
        }
        $participantName = $details['buyerName'] ?? ($details['shop']->name ?? null);
        $unreadCount = $inbox['unread'];

        return ($id === null ? $inbox : $details) + compact('side', 'inbox', 'selectedConversation', 'selectedPreview', 'listCriteria', 'listUrl', 'filterAction', 'filterReset', 'filterPrefix', 'inboxPaginator', 'historyPaginator', 'historyPage', 'participantName', 'unreadCount')
            + ['workspaceFilterChips' => $filterChips];
    }
}
