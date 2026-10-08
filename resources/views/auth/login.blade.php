<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log In - SUKI SHOP</title>

    @vite([
        'resources/css/auth.css',
        'resources/js/app.js'
    ])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script defer src="https://unpkg.com/lucide@latest"></script>
</head>


<body>

<div class="auth-page">


    <!-- =====================================================
         LEFT - SUKI BRAND / LIFESTYLE
    ====================================================== -->

    <section
        class="auth-brand"
        style="--auth-bg: url('{{ asset('images/auth/auth-lifestyle.png') }}');"
    >

        <div class="auth-brand-content">


            <div class="auth-brand-logo">

                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                >

                <span>
                    Your Everyday Marketplace
                </span>

            </div>



            <div class="auth-message">

                <h1>
                    Good Products.
                    <br>
                    <span>Brighter Days.</span>
                </h1>


                <p>
                    SUKI SHOP brings you everyday essentials from trusted
                    sellers — for a simpler, brighter everyday.
                </p>



                <div class="auth-benefits">


                    <div class="auth-benefit">

                        <div class="auth-benefit-icon">
                            <i data-lucide="leaf"></i>
                        </div>

                        <p>
                            <strong>Quality Products</strong>
                            Everyday essentials from trusted sellers.
                        </p>

                    </div>



                    <div class="auth-benefit">

                        <div class="auth-benefit-icon">
                            <i data-lucide="truck"></i>
                        </div>

                        <p>
                            <strong>Fast & Reliable Delivery</strong>
                            Get what you need, when you need it.
                        </p>

                    </div>



                    <div class="auth-benefit">

                        <div class="auth-benefit-icon">
                            <i data-lucide="shield-check"></i>
                        </div>

                        <p>
                            <strong>A Trusted Marketplace</strong>
                            A safer, simpler way to shop everyday essentials.
                        </p>

                    </div>


                </div>

            </div>


        </div>

    </section>



    <!-- =====================================================
         RIGHT - LOGIN
    ====================================================== -->

    <section class="auth-form-side">


        <a
            href="{{ url('/') }}"
            class="back-home"
        >
            ← Back to SUKI SHOP
        </a>



        <div class="auth-form-container">


            <p class="auth-eyebrow">
                WELCOME BACK
            </p>


            <h2>
                Log in to <span>SUKI SHOP</span>
            </h2>


            <p class="auth-description">
                Access your account and continue your SUKI journey.
            </p>



            @if ($errors->any())

                <div class="auth-error">

                    @foreach ($errors->all() as $error)

                        <p>{{ $error }}</p>

                    @endforeach

                </div>

            @endif



            <form
                action="{{ route('login.submit') }}"
                method="POST"
                class="auth-form"
            >

                @csrf


                <div class="field-group">

                    <label for="login">
                        Phone Number or Gmail
                    </label>

                    <input
                        type="text"
                        id="login"
                        name="login"
                        value="{{ old('login') }}"
                        placeholder="e.g. name@gmail.com or 09XXXXXXXXX"
                        autocomplete="username"
                        required
                    >

                </div>



                <div class="field-group">


                    <div class="field-label-row">

                        <label for="password">
                            Password
                        </label>

                        <a href="#">
                            Forgot Password?
                        </a>

                    </div>


                    <div class="password-input-wrap">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i data-lucide="eye"></i>
                        </button>

                    </div>

                </div>



                <div class="remember-row">

                    <label for="remember">

                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            value="1"
                            {{ old('remember') ? 'checked' : '' }}
                        >

                        <span>Remember me</span>

                    </label>

                </div>



                <button
                    type="submit"
                    class="auth-submit"
                >
                    Log In →
                </button>

            </form>



            <div class="auth-divider">
                <span>OR</span>
            </div>



            <p class="auth-bottom">

                Don't have a SUKI SHOP account?

                <a href="{{ route('register') }}">
                    Create Account
                </a>

            </p>


        </div>

    </section>


</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.lucide) {
            lucide.createIcons();
        }

        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');

        if (passwordInput && passwordToggle) {
            passwordToggle.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';

                passwordToggle.setAttribute(
                    'aria-label',
                    isHidden ? 'Hide password' : 'Show password'
                );

                passwordToggle.setAttribute(
                    'aria-pressed',
                    isHidden ? 'true' : 'false'
                );

                passwordToggle.innerHTML = isHidden
                    ? '<i data-lucide="eye-off"></i>'
                    : '<i data-lucide="eye"></i>';

                if (window.lucide) {
                    lucide.createIcons();
                }
            });
        }
    });
</script>

</body>
</html>
