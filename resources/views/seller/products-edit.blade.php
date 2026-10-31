@extends('layouts.seller')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')

@section('content')
@include('seller.partials.product-form', ['editing' => true])
@endsection
