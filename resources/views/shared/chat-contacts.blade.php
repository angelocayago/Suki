<div class="chat-contacts">
    <h3>Contact a saved product's shop</h3>
    <p>Messaging contacts from active database products and eligible shops. Up to 20 eligible products/shops are shown.</p>
    <ul>@foreach($inbox['products'] as $product)<li><a href="{{ route('buyer.messages.contact', ['kind' => 'products', 'id' => $product->id]) }}">{{ $product->name }} — Chat Seller</a>@include('buyer.messages.shop-status', ['shop' => $product->seller])</li>@endforeach</ul>
    <h3>Shop contacts</h3>
    <ul>@forelse($inbox['shops'] as $contactShop)<li><a href="{{ route('buyer.messages.contact', ['kind' => 'shops', 'id' => $contactShop->id]) }}">{{ $contactShop->name }} — Contact Seller</a>@include('buyer.messages.shop-status', ['shop' => $contactShop])</li>@empty<li>No eligible shop contacts are available.</li>@endforelse</ul>
</div>
