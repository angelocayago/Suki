<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Return Waybill {{ $waybill->tracking_number }}</title>
<style>
    *{box-sizing:border-box}body{font:14px Arial,sans-serif;margin:28px;color:#173f35;background:#f8faf8}.actions{max-width:760px;margin:0 auto 18px;display:flex;gap:16px;align-items:center}button{background:#1f6f5b;color:white;border:0;border-radius:6px;padding:10px 18px;cursor:pointer}.box{max-width:760px;margin:auto;border:2px solid #1f6f5b;background:white;padding:24px}h1{margin:8px 0 16px;font-size:26px}.tracking{font:700 17px monospace;overflow-wrap:anywhere}.addresses{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin:24px 0}.address{border:1px solid #c9d8d2;padding:16px;overflow-wrap:anywhere}h2{font-size:16px;margin:0 0 12px}p{line-height:1.6}dl{display:grid;grid-template-columns:150px 1fr;gap:10px}dd{margin:0;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse}th,td{border:1px solid #c9d8d2;padding:12px;text-align:left;overflow-wrap:anywhere}.note{font-size:12px}.payer{font-weight:bold;margin-top:24px}button:focus-visible,a:focus-visible{outline:3px solid #1f6f5b;outline-offset:3px}@media(max-width:520px){body{margin:12px}.box{padding:16px}.addresses{grid-template-columns:1fr}dl{grid-template-columns:1fr;gap:5px}dd{margin-bottom:10px}}@media print{@page{margin:15mm}body{margin:0;background:white;color:#111}.actions{display:none}.box{border:0;max-width:none;margin:0;padding:0}.addresses{grid-template-columns:1fr 1fr}table,.address{break-inside:avoid}a{color:inherit}}
</style></head><body>
<nav class="actions" aria-label="Waybill actions"><button type="button" onclick="window.print()">Print Return Waybill</button><a href="{{ route('buyer.return-refunds.show',$return) }}">Back to request</a></nav>
<main class="box">
    <strong>SUKI SHOP</strong><h1>RETURN WAYBILL</h1>
    <p>Return Tracking Number<br><span class="tracking">{{ $waybill->tracking_number }}</span></p>
    <dl><dt>Original Order</dt><dd>{{ $return->order->order_number ?: $return->order->reference ?: '#'.$return->order_id }}</dd><dt>Return Request</dt><dd>RR-{{ $return->id }}</dd><dt>Seller approval</dt><dd>{{ \App\Support\ManilaTime::format($return->decided_at) }} PHT</dd></dl>
    <div class="addresses">
        <section class="address"><h2>FROM — Buyer pickup</h2><strong>{{ $waybill->buyer_pickup['recipient_name'] }}</strong><p>{{ collect($waybill->buyer_pickup)->only(['house_number','street_address','barangay','municipality','province','postal_code'])->filter(fn($value)=>filled($value))->implode(', ') }}</p></section>
        <section class="address"><h2>TO — Seller return</h2><strong>{{ $waybill->seller_return['store_name'] }}</strong><p>{{ collect($waybill->seller_return)->only(['line1','barangay','city','province','postal_code'])->filter(fn($value)=>filled($value))->implode(', ') }}</p></section>
    </div>
    <table><thead><tr><th>Purchased product</th><th>Purchased variant</th><th>Approved quantity</th></tr></thead><tbody><tr><td>{{ $return->item->product_name }}</td><td>{{ $return->item->variant_name ?: 'Not specified' }}</td><td>{{ $return->quantity }}</td></tr></tbody></table>
    <p class="payer">Shipping Fee Payer: Seller</p>
    <p class="note">Attach this waybill to the return parcel. Printing does not confirm pickup, delivery or refund payment.</p>
</main></body></html>
