<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageAttachmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seller-only read model. All thread actions still use ConversationPolicy/MessagingService. */
class SellerInbox
{
    public function listing(Request $request): array
    {
        $input = $request->validate([
            'search'=>['nullable','string','max:100'],
            'filter'=>['nullable',Rule::in(['all','unread','inquiry','order'])],
            'page'=>['nullable','integer','min:1'],
        ]);
        $criteria = [];
        if (($search=trim($input['search'] ?? '')) !== '') $criteria['search']=$search;
        if (($input['filter'] ?? 'all') !== 'all') $criteria['filter']=$input['filter'];
        $user=$request->user();
        $unread=Message::query()->whereColumn('messages.conversation_id','conversation_participants.conversation_id')
            ->whereRaw('messages.id > COALESCE(conversation_participants.last_read_message_id, 0)')
            ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id','!=',$user->id));

        // Apply the SELLER branch of participantStillOwnsContext before pagination,
        // including stale shop/order/complaint memberships, without per-row queries.
        $query=ConversationParticipant::query()->select('conversation_participants.*')
            ->where('user_id',$user->id)->where('role_context','SELLER')->whereNull('left_at')
            ->whereHas('seller',fn ($q) => $q->where('user_id',$user->id))
            ->whereHas('conversation',function ($q) use ($user) {
                $q->where(fn ($c) => $c->whereNull('seller_id')->orWhereColumn('conversations.seller_id','conversation_participants.seller_id'))
                    ->where(fn ($c) => $c->whereNull('seller_order_id')->orWhereHas('sellerOrder',fn ($o) => $o->whereColumn('seller_orders.seller_id','conversation_participants.seller_id')))
                    ->where(fn ($c) => $c->whereNull('complaint_id')->orWhereHas('complaint',fn ($case) => $case->where('complainant_id',$user->id)));
            });
        if (($criteria['filter'] ?? '') === 'unread') $query->whereExists((clone $unread)->selectRaw('1')->toBase());
        if (in_array($criteria['filter'] ?? '',['inquiry','order'],true)) {
            $query->whereHas('conversation',fn ($q) => $q->where('type',$criteria['filter'] === 'inquiry' ? 'STORE_INQUIRY' : 'ORDER'));
        }
        if (isset($criteria['search'])) {
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($criteria['search'])).'%';
            $query->whereHas('conversation',function ($q) use ($term,$user) {
                $q->where(function ($match) use ($term,$user) {
                    $match->whereRaw("LOWER(COALESCE(subject, '')) LIKE ? ESCAPE '!'",[$term])
                        ->orWhereRaw("LOWER(COALESCE(CAST(context_snapshot AS TEXT), '')) LIKE ? ESCAPE '!'",[$term])
                        ->orWhereHas('participants',fn ($p) => $p->whereNull('left_at')->where('user_id','!=',$user->id)
                            ->whereHas('user',fn ($u) => $u->whereRaw("LOWER(name) LIKE ? ESCAPE '!'",[$term])
                                ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ? ESCAPE '!'",[$term])));
                });
            });
        }
        $images=app(MessageAttachmentService::class)->available();
        $memberships=$query->addSelect(['unread_count'=>(clone $unread)->selectRaw('COUNT(*)')])
            ->with(['conversation.latestMessage'=>fn ($q) => $q->when($images,fn ($q) => $q->withCount('attachments')),'conversation.participants.user:id,name,first_name,last_name'])
            ->orderByDesc(Conversation::query()->selectRaw('COALESCE(last_message_at, created_at)')
                ->whereColumn('conversations.id','conversation_participants.conversation_id')->limit(1))
            ->orderByDesc('conversation_id')->paginate(15)->appends($criteria);
        return compact('memberships','criteria');
    }

    public function identity(Conversation $conversation, User $viewer): array
    {
        $others=$conversation->participants->filter(fn ($p) => !$p->left_at && $p->user && (int)$p->user_id !== (int)$viewer->id)->sortBy('id');
        if (in_array($conversation->type->value,['STORE_INQUIRY','ORDER'],true)) {
            $buyers=$others->where('role_context','BUYER');
            if ($buyers->isNotEmpty()) $others=$buyers;
        }
        $names=$others->map(fn ($p) => $p->user->full_name ?: $p->user->name ?: str($p->role_context)->lower()->replace('_',' ')->headline()->toString());
        $name=$names->take(2)->implode(', ');
        if ($names->count() > 2) $name.=' +'.($names->count()-2);
        $name=$name ?: 'Conversation participant';
        $snapshot=$conversation->context_snapshot ?? [];
        $context=collect([$conversation->subject,$snapshot['order']['order_number'] ?? null,$snapshot['product']['name'] ?? null,
            $snapshot['shipment']['tracking_code'] ?? null,$snapshot['complaint']['reference'] ?? null,$snapshot['seller']['name'] ?? null])
            ->filter()->unique()->implode(' · ');
        return ['name'=>$name,'initial'=>mb_strtoupper(mb_substr($name,0,1)),
            'roles'=>$others->pluck('role_context')->unique()->map(fn ($r) => str($r)->lower()->replace('_',' ')->headline()->toString())->implode(', '),
            'context'=>$context,'type_label'=>str($conversation->type->value)->lower()->replace('_',' ')->headline()->toString()];
    }

    public function messageDisplay(Message $message): array
    {
        $date=CarbonImmutable::instance($message->sent_at)->setTimezone('Asia/Manila');
        $today=CarbonImmutable::now('Asia/Manila')->startOfDay();
        return ['id'=>(string)$message->id,'sender_id'=>$message->sender_id === null ? 'system' : (string)$message->sender_id,
            'day'=>$date->format('Y-m-d'),'day_label'=>$date->isSameDay($today) ? 'Today' : ($date->isSameDay($today->subDay()) ? 'Yesterday' : $date->format('M j, Y')),
            'time'=>$date->format('g:i A'),'relative'=>$date->diffForHumans(short:true),'full_time'=>$date->format('M j, Y g:i A').' Manila'];
    }
}
