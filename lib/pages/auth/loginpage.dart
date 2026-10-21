import 'package:flutter/material.dart';
import '../../styles/login_style.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final TextEditingController emailController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  bool rememberMe = false;

  bool obscurePassword = true;

  @override
  void dispose() {
    emailController.dispose();

    passwordController.dispose();

    super.dispose();
  }

  // =========================================================
  // LOGIN FUNCTION
  // =========================================================

  void login() {
    final email = emailController.text.trim();

    final password = passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter your email/phone and password.'),
        ),
      );

      return;
    }

    debugPrint("Login Account: $email");

    // =========================
    // TEMPORARY TEST ACCOUNTS
    // =========================

    if (email == "buyer@test.com" && password == "123456") {
      Navigator.pushReplacementNamed(context, '/buyer-home');
    } else if (email == "rider@test.com" && password == "123456") {
      Navigator.pushReplacementNamed(context, '/rider-home');
    } else if (email == "logistics@test.com" && password == "123456") {
      Navigator.pushReplacementNamed(context, '/logistics-home');
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Invalid email or password.')),
      );
    }
  }

  // =========================================================
  // ROUTES
  // =========================================================

  void goToRegister() {
    Navigator.pushNamed(context, '/register');
  }

  void goToForgotPassword() {
    Navigator.pushNamed(context, '/forgot-password');
  }

  void continueAsGuest() {
    Navigator.pushReplacementNamed(context, '/buyer-home');
  }

  @override
  Widget build(BuildContext context) {
    final Size size = MediaQuery.of(context).size;

    final bool isMobile = size.width < 700;

    return Scaffold(
      backgroundColor: Colors.white,

      resizeToAvoidBottomInset: true,

      body: SafeArea(
        child: isMobile
            ? _buildMobileLayout(context)
            : _buildDesktopLayout(context),
      ),
    );
  }

  // =========================================================
  // MOBILE LAYOUT
  // INFINIX SMART 8 OPTIMIZED
  // =========================================================

  Widget _buildMobileLayout(BuildContext context) {
    final double height = MediaQuery.of(context).size.height;

    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),

      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,

      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),

      child: ConstrainedBox(
        constraints: BoxConstraints(minHeight: height - 32),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // =========================
            // LOGO
            // =========================
            Center(
              child: Column(
                children: [
                  Container(
                    width: 82,

                    height: 82,

                    padding: const EdgeInsets.all(10),

                    decoration: BoxDecoration(
                      color: const Color(0xFFF4FAF7),

                      borderRadius: BorderRadius.circular(20),
                    ),

                    child: Image.asset(
                      'assets/images/suki_logo.png',

                      fit: BoxFit.contain,

                      errorBuilder: (_, __, ___) {
                        return const Icon(
                          Icons.shopping_bag_rounded,

                          size: 50,

                          color: LoginStyle.primaryDark,
                        );
                      },
                    ),
                  ),

                  const SizedBox(height: 7),

                  const Text(
                    "MOBILE SUKI",

                    style: TextStyle(
                      fontSize: 17,

                      fontWeight: FontWeight.w800,

                      color: LoginStyle.primaryDark,

                      letterSpacing: .6,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 22),

            const Text(
              "WELCOME BACK",

              style: TextStyle(
                fontSize: 10.5,

                fontWeight: FontWeight.w700,

                color: LoginStyle.primary,

                letterSpacing: .6,
              ),
            ),

            const SizedBox(height: 5),

            const Text(
              "Log in to Mobile Suki",

              style: TextStyle(
                fontSize: 23,

                fontWeight: FontWeight.w800,

                color: LoginStyle.titleColor,
              ),
            ),

            const SizedBox(height: 5),

            const Text(
              "Sign in to continue shopping with us.",

              style: TextStyle(fontSize: 12.5, color: LoginStyle.textColor),
            ),

            const SizedBox(height: 21),

            // =========================
            // EMAIL
            // =========================
            const Text(
              "Phone Number or Gmail",

              style: TextStyle(
                fontSize: 12.5,

                fontWeight: FontWeight.w600,

                color: LoginStyle.titleColor,
              ),
            ),

            const SizedBox(height: 7),

            SizedBox(
              height: 50,

              child: TextField(
                controller: emailController,

                keyboardType: TextInputType.emailAddress,

                textInputAction: TextInputAction.next,

                decoration: LoginStyle.inputDecoration(
                  hint: "Enter phone number or Gmail",

                  icon: Icons.person_outline,
                ),
              ),
            ),

            const SizedBox(height: 15),

            // =========================
            // PASSWORD
            // =========================
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,

              children: [
                const Text("Password", style: LoginStyle.label),

                GestureDetector(
                  onTap: goToForgotPassword,

                  child: const Text(
                    "Forgot Password?",

                    style: LoginStyle.forgotPassword,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 7),

            SizedBox(
              height: 50,

              child: TextField(
                controller: passwordController,

                obscureText: obscurePassword,

                onSubmitted: (_) => login(),

                decoration: LoginStyle.inputDecoration(
                  hint: "Enter your password",

                  icon: Icons.lock_outline,

                  suffixIcon: IconButton(
                    onPressed: () {
                      setState(() {
                        obscurePassword = !obscurePassword;
                      });
                    },

                    icon: Icon(
                      obscurePassword
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,

                      color: LoginStyle.iconColor,
                    ),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 13),

            // =========================
            // REMEMBER ME
            // =========================
            Row(
              children: [
                SizedBox(
                  width: 20,

                  height: 20,

                  child: Checkbox(
                    value: rememberMe,

                    activeColor: LoginStyle.primary,

                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,

                    visualDensity: VisualDensity.compact,

                    onChanged: (value) {
                      setState(() {
                        rememberMe = value ?? false;
                      });
                    },
                  ),
                ),

                const SizedBox(width: 8),

                const Text(
                  "Remember me",

                  style: TextStyle(fontSize: 11.5, color: LoginStyle.textColor),
                ),

                const Spacer(),

                const Row(
                  children: [
                    Icon(
                      Icons.shield_outlined,

                      size: 14,

                      color: LoginStyle.iconColor,
                    ),

                    SizedBox(width: 5),

                    Text(
                      "Secure login",

                      style: TextStyle(
                        fontSize: 9.5,

                        color: LoginStyle.mutedText,
                      ),
                    ),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 18),

            // =========================
            // LOGIN BUTTON
            // =========================
            SizedBox(
              width: double.infinity,

              height: 48,

              child: ElevatedButton(
                style: LoginStyle.loginButton,

                onPressed: login,

                child: const Text(
                  "LOG IN",

                  style: TextStyle(
                    fontSize: 12.5,

                    fontWeight: FontWeight.w700,

                    color: Colors.white,
                  ),
                ),
              ),
            ),

            const SizedBox(height: 19),

            // =========================
            // DIVIDER
            // =========================
            Row(
              children: [
                const Expanded(child: Divider(color: Color(0xFFE6EAEE))),

                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),

                  child: Text(
                    "OR",

                    style: TextStyle(
                      fontSize: 9.5,

                      color: Colors.grey.shade500,
                    ),
                  ),
                ),

                const Expanded(child: Divider(color: Color(0xFFE6EAEE))),
              ],
            ),

            const SizedBox(height: 17),

            // =========================
            // SIGN UP
            // =========================
            Center(
              child: Wrap(
                alignment: WrapAlignment.center,

                children: [
                  const Text(
                    "Don't have an account? ",

                    style: TextStyle(
                      fontSize: 11.5,

                      color: LoginStyle.textColor,
                    ),
                  ),

                  GestureDetector(
                    onTap: goToRegister,

                    child: const Text(
                      "Sign Up",

                      style: TextStyle(
                        fontSize: 11.5,

                        fontWeight: FontWeight.w700,

                        color: LoginStyle.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 18),

            // =========================
            // CREATE ACCOUNT CARD
            // =========================
            _buildAccountRegistrationCard(),

            const SizedBox(height: 14),

            // =========================
            // CONTINUE AS GUEST
            // =========================
            Center(
              child: TextButton(
                onPressed: continueAsGuest,

                child: const Row(
                  mainAxisSize: MainAxisSize.min,

                  children: [
                    Icon(
                      Icons.arrow_back,

                      size: 14,

                      color: LoginStyle.mutedText,
                    ),

                    SizedBox(width: 5),

                    Text(
                      "Continue as Guest",

                      style: TextStyle(
                        fontSize: 10.5,

                        color: LoginStyle.mutedText,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 15),

            const Center(
              child: Text(
                "Your everyday marketplace.",

                style: TextStyle(fontSize: 9.5, color: LoginStyle.mutedText),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // CREATE ACCOUNT CARD
  // =========================================================

  Widget _buildAccountRegistrationCard() {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(12),

      decoration: LoginStyle.riderBox,

      child: Row(
        children: [
          Container(
            width: 40,

            height: 40,

            decoration: BoxDecoration(
              color: Colors.white,

              borderRadius: BorderRadius.circular(10),

              border: Border.all(color: LoginStyle.lightGreenBorder),
            ),

            child: const Icon(
              Icons.person_add_alt_1_outlined,

              size: 21,

              color: LoginStyle.primary,
            ),
          ),

          const SizedBox(width: 11),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                const Text(
                  "Create your Mobile Suki account",

                  style: TextStyle(
                    fontSize: 11.5,

                    fontWeight: FontWeight.w700,

                    color: LoginStyle.titleColor,
                  ),
                ),

                const SizedBox(height: 3),

                const Text(
                  "Register as Buyer, Rider, or Logistics.",

                  style: TextStyle(fontSize: 9.5, color: LoginStyle.textColor),
                ),

                const SizedBox(height: 5),

                GestureDetector(
                  onTap: goToRegister,

                  child: const Text(
                    "Create Account →",

                    style: TextStyle(
                      fontSize: 10,

                      fontWeight: FontWeight.w700,

                      color: LoginStyle.primary,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // DESKTOP LAYOUT
  // =========================================================

  Widget _buildDesktopLayout(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Container(
            color: LoginStyle.leftBackground,

            child: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,

                children: [
                  Container(
                    width: 220,

                    height: 220,

                    padding: const EdgeInsets.all(30),

                    decoration: LoginStyle.logoBox,

                    child: Image.asset(
                      "assets/images/suki_logo.png",

                      fit: BoxFit.contain,

                      errorBuilder: (_, __, ___) {
                        return const Icon(
                          Icons.shopping_bag,

                          size: 100,

                          color: LoginStyle.primaryDark,
                        );
                      },
                    ),
                  ),

                  const SizedBox(height: 30),

                  const Text(
                    "Your everyday\nmarketplace.",

                    textAlign: TextAlign.center,

                    style: LoginStyle.heroTitle,
                  ),
                ],
              ),
            ),
          ),
        ),

        Expanded(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(40),

              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 430),

                child: _buildDesktopForm(),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // =========================================================
  // DESKTOP FORM
  // =========================================================

  Widget _buildDesktopForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        const Text("WELCOME BACK", style: LoginStyle.welcomeText),

        const SizedBox(height: 8),

        const Text("Log in to Mobile Suki", style: LoginStyle.loginTitle),

        const SizedBox(height: 6),

        const Text(
          "Sign in to continue shopping with us.",

          style: LoginStyle.subtitle,
        ),

        const SizedBox(height: 30),

        const Text("Phone Number or Gmail", style: LoginStyle.label),

        const SizedBox(height: 8),

        TextField(
          controller: emailController,

          decoration: LoginStyle.inputDecoration(
            hint: "Enter phone number or Gmail",

            icon: Icons.person_outline,
          ),
        ),

        const SizedBox(height: 18),

        const Text("Password", style: LoginStyle.label),

        const SizedBox(height: 8),

        TextField(
          controller: passwordController,

          obscureText: obscurePassword,

          decoration: LoginStyle.inputDecoration(
            hint: "Enter your password",

            icon: Icons.lock_outline,

            suffixIcon: IconButton(
              onPressed: () {
                setState(() {
                  obscurePassword = !obscurePassword;
                });
              },

              icon: Icon(
                obscurePassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,

                color: LoginStyle.iconColor,
              ),
            ),
          ),
        ),

        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,

          height: 48,

          child: ElevatedButton(
            style: LoginStyle.loginButton,

            onPressed: login,

            child: const Text("LOG IN", style: LoginStyle.buttonText),
          ),
        ),
      ],
    );
  }
}
