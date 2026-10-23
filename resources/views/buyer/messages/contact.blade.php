@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:py-8">@include('shared.chat-workspace', ['draftShop' => $shop, 'draftProduct' => $product, 'draftAction' => route('buyer.messages.start', compact('kind', 'id'))])</div>
@endsection
