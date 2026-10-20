@extends('layouts.app')

@section('content')
<section class="mx-auto max-w-4xl px-5 py-12 sm:px-8">
    <div class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm sm:p-10">
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-[#1F6F5B]">SUKI policies</p>
        <h1 class="mt-2 text-3xl font-bold text-[#173F35]">{{ $title }}</h1>
        <div class="mt-7 whitespace-pre-line text-sm leading-7 text-[#52635B]">{{ $content ?: 'This policy is being prepared. Please contact support if you need assistance.' }}</div>
    </div>
</section>
@endsection
