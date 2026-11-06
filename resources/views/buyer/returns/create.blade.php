@extends('layouts.app')
@section('content')
<main class="mx-auto max-w-3xl space-y-5 px-4 py-8">
    <a class="underline" href="{{ route('buyer.my-orders') }}">My Orders</a><h1 class="text-2xl font-bold">Return / Refund</h1>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-semibold">{{ $item->product_name }}</h2>
        <p>Purchased variant: {{ $item->variant_name ?? $item->product_variant_id }} · Full quantity: {{ $item->quantity }}</p>
        <p>Original merchandise: ₱{{ number_format(($item->unit_price_minor * $item->quantity)/100,2) }}</p>
        @if($calculation)<p>Eligible refund: ₱{{ number_format($calculation['buyer_refund_due_minor']/100,2) }} (original discounts applied; shipping excluded)</p>@endif
    </section>
    @if(!$available)<p role="status">Return/refund submission is temporarily disabled pending schema and financial activation review. Existing requests remain available in My Returns &amp; Refunds.</p>@endif
    @if($eligibility)<p role="alert">{{ $eligibility }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('buyer.return-refunds.store',$item) }}" class="space-y-4 rounded-xl border bg-white p-5">
        @csrf <input type="hidden" name="type" value="RETURN"><input type="hidden" name="quantity" value="{{ $item->quantity }}">
        <p>This workflow requires a physical return and Admin review. It does not transfer money or restore stock automatically.</p>
        <fieldset @disabled(!$available || $eligibility) class="space-y-4">
            <label class="block">Reason<select name="reason" required class="block w-full rounded border">@foreach(['Damaged item','Incorrect item','Missing parts','Not as described','Other'] as $reason)<option @selected(old('reason')===$reason)>{{ $reason }}</option>@endforeach</select></label>
            <label class="block">Description<textarea name="description" required maxlength="5000" rows="4" class="block w-full rounded border">{{ old('description') }}</textarea></label>
            <label class="block">Optional private photo (JPG/PNG, up to 5 MiB)<input type="file" name="file" accept="image/jpeg,image/png"></label>
            <label class="block"><input type="checkbox" name="confirmed" value="1" required> I confirm the purchased item, full quantity and refund amount above and want to submit this request.</label>
            <button class="rounded bg-[#173F35] px-4 py-2 text-white">Confirm and Submit Return / Refund</button>
        </fieldset>
    </form>
    <a class="underline" href="{{ route('buyer.return-refunds.index') }}">My Returns &amp; Refunds</a>
</main>
@endsection
