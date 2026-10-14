<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintWaitingOn;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Admin\ComplaintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['ALL', ...array_column(ComplaintStatus::cases(), 'value')])],
            'category' => ['nullable', Rule::in(['ALL', ...array_column(ComplaintCategory::cases(), 'value')])],
            'assigned_to' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'order_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $assignedTo = $validated['assigned_to'] ?? 'ALL';
        if (! in_array($assignedTo, ['ALL', 'UNASSIGNED'], true)) {
            if (! ctype_digit((string) $assignedTo) || ! $this->activeAdmins()->whereKey((int) $assignedTo)->exists()) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Select an active administrator or unassigned complaints.',
                ]);
            }
        }

        $query = Complaint::query()->with([
            'complainant',
            'order:id,order_number,status',
            'seller:id,name',
            'assignee:id,name,first_name,last_name',
        ]);

        $query
            ->when(
                filled($validated['status'] ?? null) && $validated['status'] !== 'ALL',
                fn (Builder $builder) => $builder->where('status', $validated['status'])
            )
            ->when(
                filled($validated['category'] ?? null) && $validated['category'] !== 'ALL',
                fn (Builder $builder) => $builder->where('category', $validated['category'])
            )
            ->when(
                $assignedTo === 'UNASSIGNED',
                fn (Builder $builder) => $builder->whereNull('assigned_to')
            )
            ->when(
                ctype_digit((string) $assignedTo),
                fn (Builder $builder) => $builder->where('assigned_to', (int) $assignedTo)
            )
            ->when(
                filled($validated['date_from'] ?? null),
                fn (Builder $builder) => $builder->whereDate('created_at', '>=', $validated['date_from'])
            )
            ->when(
                filled($validated['date_to'] ?? null),
                fn (Builder $builder) => $builder->whereDate('created_at', '<=', $validated['date_to'])
            )
            ->when(filled($validated['order_reference'] ?? null), function (Builder $builder) use ($validated) {
                $reference = trim($validated['order_reference']);
                $builder->whereHas('order', fn (Builder $order) => $order->where('order_number', 'like', "%{$reference}%"));
            });

        $complaints = $query->latest()->paginate(15)->withQueryString();
        $admins = $this->activeAdmins()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name']);
        $stats = collect(ComplaintStatus::cases())->mapWithKeys(
            fn (ComplaintStatus $status) => [$status->value => Complaint::where('status', $status->value)->count()]
        );

        return view('admin.complaints.index', compact('complaints', 'admins', 'stats'));
    }

    public function show(Complaint $complaint): View
    {
        $complaint->load([
            'complainant',
            'buyer',
            'seller.owner',
            'product.category',
            'order.buyer',
            'sellerOrder',
            'shipment.pickupRider.user',
            'shipment.rider.user',
            'shipment.provider.owner',
            'rider.user',
            'logisticsProvider.owner',
            'assignee',
            'resolver',
            'events.actor',
            'events.previousAssignee',
            'events.newAssignee',
            'evidence.uploader',
        ]);

        $admins = $this->activeAdmins()->orderBy('name')->get(['id', 'name', 'first_name', 'last_name']);

        return view('admin.complaints.show', compact('complaint', 'admins'));
    }

    public function assign(
        Request $request,
        Complaint $complaint,
        ComplaintService $complaints
    ): RedirectResponse {
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $complaints->assignAdmin(
            $complaint,
            $request->user(),
            User::findOrFail($validated['assigned_to'])
        );

        return back()->with('success', 'Complaint assigned successfully.');
    }

    public function updateStatus(
        Request $request,
        Complaint $complaint,
        ComplaintService $complaints
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'waiting_on' => ['nullable', Rule::enum(ComplaintWaitingOn::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $complaints->changeStatus(
            $complaint,
            $request->user(),
            ComplaintStatus::from($validated['status']),
            filled($validated['waiting_on'] ?? null)
                ? ComplaintWaitingOn::from($validated['waiting_on'])
                : null,
            $validated['notes'] ?? null
        );

        return back()->with('success', 'Complaint status updated.');
    }

    public function resolve(
        Request $request,
        Complaint $complaint,
        ComplaintService $complaints
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        $complaints->resolve($complaint, $request->user(), $validated['resolution_notes']);

        return back()->with('success', 'Complaint resolved.');
    }

    public function dismiss(
        Request $request,
        Complaint $complaint,
        ComplaintService $complaints
    ): RedirectResponse {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        $complaints->dismiss($complaint, $request->user(), $validated['resolution_notes']);

        return back()->with('success', 'Complaint dismissed.');
    }

    private function activeAdmins(): Builder
    {
        return User::query()
            ->where('status', 'active')
            ->where('is_suspended', false)
            ->where(function (Builder $query) {
                $query->whereIn('role', ['admin', 'superadmin'])
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.name', ['admin', 'superadmin']));
            });
    }
}
