@extends('layouts.app')

@push('styles')
@vite('resources/css/auth/login.css')
@endpush

@section('content')
<div class="auth-page">
    <div class="auth-container">

        <!-- Left Visual Panel -->
        <div class="auth-visual">
            <img
                src="{{ asset('images/hero-marketplace.jpg') }}"
                alt="BiliHub Marketplace"
                class="auth-visual-image"
            >

            <div class="auth-visual-overlay">
                <h2 class="text-white fw-bold mb-1" style="font-size: 1.5rem;">
                    Forgot your password?
                </h2>

                <p class="text-white-50 small mb-0">
                    No worries. We'll help you recover your account.
                </p>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="auth-form-panel">

            <div class="text-center mb-3 d-none d-lg-block">
                <img
                    src="{{ asset('images/bilihublogo.png') }}"
                    alt="BiliHub"
                    class="auth-logo"
                >
            </div>

            <div class="text-center mb-3 d-lg-none">
                <img
                    src="{{ asset('images/bilihublogo.png') }}"
                    alt="BiliHub"
                    class="auth-logo-mobile"
                >
            </div>

            <h2
                class="fw-bold text-center mb-2"
                style="color: var(--color-text-dark);"
            >
                Reset Password
            </h2>

            <p class="text-center text-muted small mb-4">
                Enter your email address and we'll send you a password reset link.
            </p>

            @if (session('status'))
                <div
                    class="alert alert-success alert-dismissible fade show rounded-2 border-0"
                    role="alert"
                >
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        {{ session('status') }}
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('password.email') }}"
            >
                @csrf

                <div class="mb-4">
                    <label
                        for="email"
                        class="form-label fw-medium"
                        style="color: #334159;"
                    >
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control auth-input @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                        autofocus
                    >

                    @error('email')
                        <div class="invalid-feedback d-block mt-1 small">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100 rounded-xl py-3 fw-semibold mb-3"
                >
                    Send Password Reset Link
                </button>

                <div class="text-center">
                    <a
                        href="{{ route('login') }}"
                        class="fw-semibold small"
                        style="
                            color: var(--color-deep-purple);
                            text-decoration: none;
                        "
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Login
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection