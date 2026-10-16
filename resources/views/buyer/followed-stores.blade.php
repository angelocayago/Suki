@extends('layouts.app')

@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Followed stores</h1>
            <p class="text-sm text-gray-500 mt-1">Stores you follow that are currently active.</p>
        </div>
        <a href="{{ route('buyer.shop') }}" class="text-sm font-semibold text-[#1F6F5B] hover:underline">Browse products</a>
    </div>

    @if($stores->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
            You are not following any active stores yet.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($stores as $seller)
                <article class="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="font-semibold text-gray-900">{{ $seller->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ $seller->category?->name ?? 'Store' }}</p>
                    <a class="mt-4 inline-block text-sm font-semibold text-[#1F6F5B] hover:underline" href="{{ route('buyer.stores.show', $seller) }}">Visit store</a>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
