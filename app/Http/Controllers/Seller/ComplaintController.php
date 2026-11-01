<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Seller;
use App\Services\Admin\ComplaintService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = collect(ComplaintStatus::cases())->mapWithKeys(fn ($status) => [$status->value => str($status->value)->lower()->replace('_', ' ')->headline()->toString()])->all();
        $dates = ['any' => 'Any date', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'];
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...array_keys($statuses)])],
            'date' => ['nullable', Rule::in(array_keys($dates))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $criteria = ['search' => trim($validated['search'] ?? ''), 'status' => $validated['status'] ?? 'all', 'date' => $validated['date'] ?? 'any'];
        $owned = $this->visible($request);
        $counts = (clone $owned)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $summary = [
            'Total complaints' => (int) $counts->sum(),
            'Open' => (int) ($counts[ComplaintStatus::Open->value] ?? 0),
            'In progress' => (int) $counts->only([ComplaintStatus::UnderReview->value, ComplaintStatus::WaitingResponse->value])->sum(),
            'Closed' => (int) $counts->only([ComplaintStatus::Resolved->value, ComplaintStatus::Dismissed->value])->sum(),
        ];
        if ($criteria['search'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($criteria['search'])).'%';
            $owned->where(fn (Builder $query) => $query
                ->whereRaw("LOWER(reference) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('order', fn (Builder $order) => $order->whereRaw("LOWER(COALESCE(order_number, '')) LIKE ? ESCAPE '!'", [$pattern])));
        }
        if ($criteria['status'] !== 'all') $owned->where('status', $criteria['status']);
        if ($criteria['date'] !== 'any') {
            $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $days = $criteria['date'] === 'today' ? 1 : (int) $criteria['date'];
            $owned->where('created_at', '>=', $today->subDays($days - 1)->utc())->where('created_at', '<', $today->addDay()->utc());
        }
        $complaints = $owned->with(['order:id,order_number', 'complainant:id,name,first_name,last_name'])
            ->latest()->orderByDesc('id')->paginate(15)->appends($criteria);

        return view('seller.complaints.index', compact('complaints', 'statuses', 'dates', 'criteria', 'summary'));
    }

    public function show(Request $request, int $complaint, ComplaintService $service): View
    {
        $complaint = $this->visible($request)->whereKey($complaint)->with([
            'order:id,order_number', 'sellerOrder:id,seller_id,order_id', 'product:id,name',
            'complainant:id,name,first_name,last_name',
            'events:id,complaint_id,action,previous_status,new_status,waiting_on,created_at',
            'evidence' => fn ($query) => $query->where('uploaded_by', $request->user()->id),
        ])->firstOrFail();
        $service->ensureCanAccess($complaint, $request->user());

        return view('seller.complaints.show', compact('complaint'));
    }

    private function visible(Request $request): Builder
    {
        $seller = Seller::query()->where('user_id', $request->user()->id)->firstOrFail();

        // ComplaintService authorizes non-Admins as complainants, not as respondents.
        // A store/order association alone must never grant complaint or evidence access.
        return Complaint::query()->where('complainant_id', $request->user()->id)
            ->where(fn (Builder $query) => $query->where('seller_id', $seller->id)
                ->orWhere(fn (Builder $query) => $query->whereNull('seller_id')
                    ->whereHas('sellerOrder', fn (Builder $order) => $order->where('seller_id', $seller->id))))
            ->where(fn (Builder $query) => $query->whereNull('seller_order_id')
                ->orWhereHas('sellerOrder', fn (Builder $order) => $order->where('seller_id', $seller->id)));
    }
}
