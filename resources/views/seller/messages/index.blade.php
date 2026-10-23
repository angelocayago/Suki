@extends('layouts.seller')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('content')
<nav aria-label="Breadcrumb" class="mb-4 text-xs text-[#728078]"><a href="{{ route('seller.dashboard') }}">Dashboard</a> / <a href="{{ route('seller.messages.index') }}">Messages</a></nav>
@include('shared.chat-workspace')
@endsection
