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
                    Create a New Password
                </h2>

                <p class="text-white-50 small mb-0">
                    Secure your BiliHub account with a new password.
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
                Enter your new password below.
            </p>

            @if ($errors->any())
                <div
                    class="alert alert-danger alert-dismissible fade show rounded-2 border-0"
                    role="alert"
                >
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-circle-fill me-2 mt-1"></i>

                        <div>
                            <strong>Please check the information below.</strong>

                            <ul class="mb-0 mt-1 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
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
                action="{{ route('password.store') }}"
            >
                @csrf

                <!-- Reset Token -->
                <input
                    type="hidden"
                    name="token"
                    value="{{ $request->route('token') }}"
                >

                <!-- Email -->
                <div class="mb-3">
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
                        value="{{ old('email', $request->email) }}"
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

                <!-- New Password -->
                <div class="mb-3">
                    <label
                        for="password"
                        class="form-label fw-medium"
                        style="color: #334159;"
                    >
                        New Password
                    </label>

                    <div class="input-group">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-control auth-input pe-5 @error('password') is-invalid @enderror"
                            placeholder="Enter your new password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary toggle-password"
                            id="toggleNewPassword"
                            aria-label="Toggle password visibility"
                        >
                            <i
                                class="bi bi-eye-slash"
                                id="newPasswordToggleIcon"
                            ></i>
                        </button>
                    </div>

                    @error('password')
                        <div class="invalid-feedback d-block mt-1 small">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label
                        for="password_confirmation"
                        class="form-label fw-medium"
                        style="color: #334159;"
                    >
                        Confirm Password
                    </label>

                    <div class="input-group">
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            class="form-control auth-input pe-5"
                            placeholder="Confirm your new password"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn btn-outline-secondary toggle-password"
                            id="toggleConfirmPassword"
                            aria-label="Toggle password visibility"
                        >
                            <i
                                class="bi bi-eye-slash"
                                id="confirmPasswordToggleIcon"
                            ></i>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100 rounded-xl py-3 fw-semibold mb-3"
                >
                    Reset Password
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('password_confirmation');

    const toggleNewPassword = document.getElementById('toggleNewPassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

    const newPasswordToggleIcon = document.getElementById('newPasswordToggleIcon');
    const confirmPasswordToggleIcon = document.getElementById('confirmPasswordToggleIcon');

    if (toggleNewPassword && passwordInput) {
        toggleNewPassword.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';

            newPasswordToggleIcon.classList.toggle('bi-eye-slash', !isPassword);
            newPasswordToggleIcon.classList.toggle('bi-eye', isPassword);
        });
    }

    if (toggleConfirmPassword && confirmPasswordInput) {
        toggleConfirmPassword.addEventListener('click', function () {
            const isPassword = confirmPasswordInput.type === 'password';

            confirmPasswordInput.type = isPassword ? 'text' : 'password';

            confirmPasswordToggleIcon.classList.toggle('bi-eye-slash', !isPassword);
            confirmPasswordToggleIcon.classList.toggle('bi-eye', isPassword);
        });
    }
});
</script>
@endpush