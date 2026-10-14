<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - SUKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#F6F9F7] text-gray-800">
@php
    $portalRoute = match (true) {
        auth()->user()->isSeller() => 'seller.dashboard',
        auth()->user()->isRider() => 'rider.dashboard',
        auth()->user()->isLogistics() => 'logistics.dashboard',
        default => 'buyer.home',
    };
@endphp
<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-bold text-[#173F35]">My Complaints</h1><p class="mt-1 text-sm text-gray-500">Complaints you submitted for marketplace transactions.</p></div>
        <a href="{{ route($portalRoute) }}" class="text-sm font-semibold text-[#1F6F5B]">Return to portal</a>
    </div>

    @if(session('success'))<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif

    <div class="mt-6 overflow-hidden rounded-2xl border border-[#DCE5E0] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E7EDE9] text-sm">
                <thead class="bg-[#EEF5F1] text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-4">Complaint</th><th class="px-5 py-4">Order</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Evidence</th><th class="px-5 py-4">Created</th><th class="px-5 py-4"></th></tr></thead>
                <tbody class="divide-y divide-[#EDF1EF]">
                    @forelse($complaints as $complaint)
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold text-[#173F35]">{{ $complaint->reference }}</div><div class="mt-1 text-xs text-gray-500">{{ $complaint->subject }}</div></td>
                            <td class="px-5 py-4">{{ $complaint->order->order_number ?? 'Not linked' }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-xs font-semibold text-[#173F35]">{{ str(strtolower($complaint->status->value))->replace('_', ' ')->headline() }}</span></td>
                            <td class="px-5 py-4">{{ $complaint->evidence_count }}</td>
                            <td class="px-5 py-4 text-gray-500">{{ $complaint->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('complaints.mine.show', $complaint) }}" class="font-semibold text-[#1F6F5B] hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">You have not submitted any complaints.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#E7EDE9] px-5 py-4">{{ $complaints->links() }}</div>
    </div>
</main>
</body>
</html>
