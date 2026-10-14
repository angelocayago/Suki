@extends('admin.layout')

@section('title', 'Complaints & Disputes')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[#173F35]">Complaints &amp; Disputes</h1>
        <p class="mt-1 text-sm text-gray-500">Review marketplace complaints, assignments, response status, and resolution history.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach(\App\Enums\ComplaintStatus::cases() as $case)
            <a href="{{ route('admin.complaints.index', ['status' => $case->value]) }}" class="rounded-2xl border border-[#DCE5E0] bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str(strtolower($case->value))->replace('_', ' ')->headline() }}</div>
                <div class="mt-2 text-2xl font-bold text-[#173F35]">{{ $stats[$case->value] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.complaints.index') }}" class="grid gap-3 rounded-2xl border border-[#DCE5E0] bg-white p-5 shadow-sm md:grid-cols-2 xl:grid-cols-4">
        <select name="status" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All statuses</option>
            @foreach(\App\Enums\ComplaintStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('status', 'ALL') === $case->value)>{{ str(strtolower($case->value))->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>

        <select name="category" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All categories</option>
            @foreach(\App\Enums\ComplaintCategory::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('category', 'ALL') === $case->value)>{{ str(strtolower($case->value))->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>

        <select name="assigned_to" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All assignments</option>
            <option value="UNASSIGNED" @selected(request('assigned_to') === 'UNASSIGNED')>Unassigned</option>
            @foreach($admins as $admin)
                <option value="{{ $admin->id }}" @selected((string) request('assigned_to') === (string) $admin->id)>{{ $admin->name }}</option>
            @endforeach
        </select>

        <input name="order_reference" value="{{ request('order_reference') }}" maxlength="100" placeholder="Order reference"
               class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">

        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Created from"
               class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Created to"
               class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">

        <div class="flex gap-2 md:col-span-2 xl:col-span-4">
            <button class="rounded-xl bg-[#173F35] px-5 py-2 text-sm font-semibold text-white">Apply filters</button>
            <a href="{{ route('admin.complaints.index') }}" class="rounded-xl border border-[#DCE5E0] px-5 py-2 text-sm font-semibold text-gray-700">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#DCE5E0] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E7EDE9] text-sm">
                <thead class="bg-[#F6F9F7] text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-4">Complaint</th>
                        <th class="px-5 py-4">Order</th>
                        <th class="px-5 py-4">Category</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Assigned admin</th>
                        <th class="px-5 py-4">Created</th>
                        <th class="px-5 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EDF1EF]">
                    @forelse($complaints as $complaint)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-[#173F35]">{{ $complaint->reference }}</div>
                                <div class="mt-1 max-w-xs truncate text-xs text-gray-500">{{ $complaint->subject }}</div>
                                <div class="mt-1 text-xs text-gray-400">{{ str(strtolower($complaint->complainant_role))->headline() }}: {{ $complaint->complainant->name ?? 'Deleted user' }}</div>
                            </td>
                            <td class="px-5 py-4">{{ $complaint->order->order_number ?? 'Not linked' }}</td>
                            <td class="px-5 py-4">{{ str(strtolower($complaint->category->value))->replace('_', ' ')->headline() }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-xs font-semibold text-[#173F35]">{{ str(strtolower($complaint->status->value))->replace('_', ' ')->headline() }}</span>
                                @if($complaint->waiting_on)
                                    <div class="mt-2 text-xs text-amber-700">Waiting: {{ str(strtolower($complaint->waiting_on->value))->replace('_', ' ')->headline() }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">{{ $complaint->assignee->name ?? 'Unassigned' }}</td>
                            <td class="px-5 py-4 text-gray-500">{{ $complaint->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('admin.complaints.show', $complaint) }}" class="font-semibold text-[#1F6F5B] hover:underline">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No complaints match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#E7EDE9] px-5 py-4">{{ $complaints->links() }}</div>
    </div>
</div>
@endsection
