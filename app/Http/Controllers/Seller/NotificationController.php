<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationInboxController;
use App\Support\SellerNotifications;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request, SellerNotifications $presentation)
    {
        $criteria = $this->criteria($request);
        $query = $request->user()->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id');
        if (($criteria['filter'] ?? '') === 'unread') $query->whereNull('read_at');
        $notifications = $query->paginate(25)->appends($criteria);
        $rows = $presentation->present($request->user(),$notifications->getCollection());
        return view('seller.notifications',compact('notifications','rows','criteria'));
    }

    public function preview(Request $request, SellerNotifications $presentation)
    {
        $user = $request->user();
        $rows = $presentation->present($user,$presentation->recent($user));
        return response()->json([
            'unread_count'=>$user->unreadNotifications()->count(),
            'notifications'=>array_map(fn($row)=>[
                'title'=>$row['title'],'message'=>$row['message'],'unread'=>$row['unread'],
                'date'=>$row['date']?->format('M d, Y h:i A'),'iso_date'=>$row['date']?->toIso8601String(),
                'open_url'=>$row['open_url'],
            ],$rows),
        ])->header('Cache-Control','private, no-store');
    }

    public function read(Request $request, string $notification, NotificationInboxController $inbox)
    {
        $criteria = $this->criteria($request);
        $result = $inbox->markRead($request,$notification);
        return $request->expectsJson() ? $result : redirect()->route('seller.notifications.index',$criteria)->with('success','Notification marked as read.');
    }

    public function readAll(Request $request, NotificationInboxController $inbox)
    {
        $criteria = $this->criteria($request);
        $result = $inbox->markAllRead($request);
        return $request->expectsJson() ? $result : redirect()->route('seller.notifications.index',$criteria)->with('success','All notifications marked as read.');
    }

    public function open(Request $request, string $notification, NotificationInboxController $inbox, SellerNotifications $presentation)
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $row = $presentation->present($request->user(),collect([$record]))[0];
        $inbox->markRead($request,$notification);
        return redirect()->to($row['destination'] ?? route('seller.notifications.index'));
    }

    private function criteria(Request $request): array
    {
        $values = $request->validate(['filter'=>['nullable',Rule::in(['all','unread'])],'page'=>['nullable','integer','min:1']]);
        return ($values['filter'] ?? '') === 'unread' ? ['filter'=>'unread'] : [];
    }
}
