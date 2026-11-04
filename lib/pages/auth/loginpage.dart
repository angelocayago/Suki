import 'package:flutter/material.dart';

import '../../styles/login_style.dart';
import '../../services/auth_service.dart';
import '../../theme/app_theme.dart';
import '../../widgets/theme_toggle_button.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  // =========================================================
  // CONTROLLERS
  // =========================================================

  final TextEditingController emailController = TextEditingController();

  final TextEditingController passwordController = TextEditingController();

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  // =========================================================
  // STATES
  // =========================================================

  bool rememberMe = true;
  bool obscurePassword = true;
  bool isLoggingIn = false;
  bool _rememberMeTouched = false;

  late final Future<void> _rememberedLoginLoad;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    _rememberedLoginLoad = _loadRememberedLogin();
  }

  Future<void> _loadRememberedLogin() async {
    try {
      final shouldRemember = await AuthService.isRememberMeEnabled();

      final savedLogin = shouldRemember
          ? await AuthService.getRememberedLogin()
          : null;

      if (!mounted) return;

      setState(() {
        if (!_rememberMeTouched) {
          rememberMe = shouldRemember;
        }

        if (emailController.text.trim().isEmpty && savedLogin != null) {
          emailController.text = savedLogin;
        }
      });
    } catch (_) {
      // Continue even when secure storage is unavailable.
    }
  }

  Future<void> _setRememberMe(bool value) async {
    if (!mounted) return;

    setState(() {
      _rememberMeTouched = true;
      rememberMe = value;
    });

    try {
      await AuthService.setRememberMe(value);
    } catch (_) {
      _showMessage('Unable to save the Remember Me setting.');
    }
  }

  // =========================================================
  // DISPOSE
  // =========================================================

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  // =========================================================
  // FORM VALIDATION
  // =========================================================

  String? _validateLogin(String? value) {
    final loginValue = value?.trim() ?? '';

    if (loginValue.isEmpty) {
      return 'Please enter your email or phone number.';
    }

    if (loginValue.contains('@')) {
      final emailPattern = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');

      if (!emailPattern.hasMatch(loginValue)) {
        return 'Please enter a valid email address.';
      }
    }

    return null;
  }

  String? _validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please enter your password.';
    }

    return null;
  }

  // =========================================================
  // LARAVEL API LOGIN
  // =========================================================

  Future<void> login() async {
    if (isLoggingIn) return;

    FocusScope.of(context).unfocus();

    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      isLoggingIn = true;
    });

    try {
      await _rememberedLoginLoad;

      if (!mounted) return;

      final String loginValue = emailController.text.trim();

      final String password = passwordController.text;

      // Connect to existing Laravel authentication API.
      final response = await AuthService.login(
        login: loginValue,
        password: password,
        rememberMe: rememberMe,
      );

      if (!mounted) return;

      // Resolve dashboard based on authenticated role.
      final String homeRoute = AuthService.homeRouteForUser(response);

      if (homeRoute.isEmpty) {
        throw Exception('No dashboard is configured for this account.');
      }

      // Remove login screen from navigation history.
      Navigator.pushNamedAndRemoveUntil(context, homeRoute, (route) => false);
    } catch (error) {
      if (!mounted) return;

      _showMessage(_friendlyError(error), backgroundColor: Colors.redAccent);
    } finally {
      if (mounted) {
        setState(() {
          isLoggingIn = false;
        });
      }
    }
  }

  // =========================================================
  // ERROR HANDLING
  // =========================================================

  String _friendlyError(Object error) {
    final raw = error.toString().replaceFirst('Exception: ', '').trim();

    final lower = raw.toLowerCase();

    if (lower.contains('socketexception') ||
        lower.contains('connection refused') ||
        lower.contains('failed host lookup') ||
        lower.contains('network is unreachable') ||
        lower.contains('connection error')) {
      return 'Cannot connect to SUKI. '
          'Check your internet connection and server.';
    }

    if (lower.contains('timeout') || lower.contains('timed out')) {
      return 'The server took too long to respond. '
          'Please try again.';
    }

    if (lower.contains('401') ||
        lower.contains('invalid credentials') ||
        lower.contains('incorrect password') ||
        lower.contains('invalid login')) {
      return 'Incorrect email/phone or password.';
    }

    if (lower.contains('403') ||
        lower.contains('suspended') ||
        lower.contains('banned')) {
      return 'Your account cannot sign in at this time. '
          'Please contact SUKI support.';
    }

    if (lower.contains('500') || lower.contains('internal server error')) {
      return 'SUKI is experiencing a server problem. '
          'Please try again later.';
    }

    if (raw.isEmpty) {
      return 'Unable to log in. Please try again.';
    }

    return raw;
  }

  // =========================================================
  // SNACKBAR
  // =========================================================

  void _showMessage(
    String message, {
    Color backgroundColor = Colors.redAccent,
  }) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: backgroundColor,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
        ),
      );
  }

  // =========================================================
  // NAVIGATION
  // =========================================================

  void goToRegister() {
    if (isLoggingIn) return;

    Navigator.pushNamed(context, '/register');
  }

  void goToForgotPassword() {
    if (isLoggingIn) return;

    // Temporary dialog until the password reset route
    // and Laravel API endpoint are implemented.
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Forgot Password'),
          content: const Text(
            'Password reset is not available yet. '
            'Please contact SUKI support for assistance.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
              },
              child: const Text('OK'),
            ),
          ],
        );
      },
    );
  }

  void continueAsGuest() {
    if (isLoggingIn) return;

    Navigator.pushReplacementNamed(context, '/buyer-home');
  }

  // =========================================================
  // PASSWORD VISIBILITY
  // =========================================================

  void _togglePasswordVisibility() {
    setState(() {
      obscurePassword = !obscurePassword;
    });
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    final double screenWidth = MediaQuery.of(context).size.width;

    final bool isMobile = screenWidth < 700;

    return Scaffold(
      backgroundColor: AppTheme.background,
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: isMobile ? _buildMobileLayout() : _buildDesktopLayout(),
      ),
    );
  }

  // =========================================================
  // MOBILE LAYOUT
  // =========================================================

  Widget _buildMobileLayout() {
    return SingleChildScrollView(
      physics: const BouncingScrollPhysics(),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 460),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Theme toggle
              const Align(
                alignment: Alignment.centerRight,
                child: ThemeToggleButton(),
              ),

              const SizedBox(height: 12),

              // Logo
              _buildMobileLogo(),

              const SizedBox(height: 25),

              // Form
              _buildLoginForm(isMobile: true),

              const SizedBox(height: 16),

              Center(
                child: Text(
                  'Your everyday marketplace.',
                  style: TextStyle(fontSize: 10, color: LoginStyle.mutedText),
                ),
              ),

              const SizedBox(height: 18),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // MOBILE LOGO
  // =========================================================

  Widget _buildMobileLogo() {
    return Center(
      child: Column(
        children: [
          Container(
            width: 82,
            height: 82,
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: AppTheme.softGreen,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Image.asset(
              'assets/images/suki_logo.png',
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) {
                return Icon(
                  Icons.shopping_bag_rounded,
                  size: 50,
                  color: LoginStyle.primaryDark,
                );
              },
            ),
          ),

          const SizedBox(height: 7),

          Text(
            'MOBILE SUKI',
            style: TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: LoginStyle.primaryDark,
              letterSpacing: 0.6,
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // DESKTOP / TABLET LAYOUT
  // =========================================================

  Widget _buildDesktopLayout() {
    return Row(
      children: [
        // LEFT SIDE
        Expanded(
          child: Container(
            color: LoginStyle.leftBackground,
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      width: 220,
                      height: 220,
                      padding: const EdgeInsets.all(30),
                      decoration: LoginStyle.logoBox,
                      child: Image.asset(
                        'assets/images/suki_logo.png',
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) {
                          return Icon(
                            Icons.shopping_bag,
                            size: 100,
                            color: LoginStyle.primaryDark,
                          );
                        },
                      ),
                    ),

                    const SizedBox(height: 30),

                    Text(
                      'Your everyday\nmarketplace.',
                      textAlign: TextAlign.center,
                      style: LoginStyle.heroTitle,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),

        // RIGHT SIDE
        Expanded(
          child: Center(
            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.all(35),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 430),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const ThemeToggleButton(),

                    const SizedBox(height: 18),

                    _buildLoginForm(isMobile: false),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  // =========================================================
  // SHARED LOGIN FORM
  // =========================================================

  Widget _buildLoginForm({required bool isMobile}) {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // WELCOME HEADER
          Text(
            'WELCOME BACK',
            style: isMobile
                ? TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: LoginStyle.primary,
                    letterSpacing: 0.6,
                  )
                : LoginStyle.welcomeText,
          ),

          const SizedBox(height: 6),

          Text(
            'Log in to Mobile Suki',
            style: isMobile
                ? TextStyle(
                    fontSize: 23,
                    fontWeight: FontWeight.w800,
                    color: LoginStyle.titleColor,
                  )
                : LoginStyle.loginTitle,
          ),

          const SizedBox(height: 6),

          Text(
            'Sign in to continue shopping with us.',
            style: isMobile
                ? TextStyle(fontSize: 12.5, color: LoginStyle.textColor)
                : LoginStyle.subtitle,
          ),

          const SizedBox(height: 25),

          // EMAIL OR PHONE INPUT
          _buildLoginInput(),

          const SizedBox(height: 18),

          // PASSWORD INPUT
          _buildPasswordInput(),

          const SizedBox(height: 12),

          // REMEMBER ME
          _buildRememberMe(),

          const SizedBox(height: 20),

          // LOGIN BUTTON
          _buildLoginButton(),

          const SizedBox(height: 20),

          // OR DIVIDER
          _buildDivider(),

          const SizedBox(height: 17),

          // SIGN UP LINK
          _buildSignUpLink(),

          const SizedBox(height: 20),

          // CREATE ACCOUNT CARD
          _buildAccountRegistrationCard(),

          const SizedBox(height: 15),

          // GUEST BUTTON
          _buildGuestButton(),
        ],
      ),
    );
  }

  // =========================================================
  // EMAIL OR PHONE INPUT
  // =========================================================

  Widget _buildLoginInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Phone Number or Gmail', style: LoginStyle.label),

        const SizedBox(height: 8),

        TextFormField(
          controller: emailController,
          enabled: !isLoggingIn,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          autocorrect: false,
          enableSuggestions: false,
          validator: _validateLogin,
          autovalidateMode: AutovalidateMode.onUserInteraction,
          decoration: LoginStyle.inputDecoration(
            hint: 'Enter phone number or Gmail',
            icon: Icons.person_outline,
          ),
        ),
      ],
    );
  }

  // =========================================================
  // PASSWORD INPUT
  // =========================================================

  Widget _buildPasswordInput() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text('Password', style: LoginStyle.label),

            TextButton(
              onPressed: isLoggingIn ? null : goToForgotPassword,
              child: Text('Forgot Password?', style: LoginStyle.forgotPassword),
            ),
          ],
        ),

        const SizedBox(height: 5),

        TextFormField(
          controller: passwordController,
          obscureText: obscurePassword,
          enabled: !isLoggingIn,
          textInputAction: TextInputAction.done,
          validator: _validatePassword,
          onFieldSubmitted: (_) {
            if (!isLoggingIn) {
              login();
            }
          },
          decoration: LoginStyle.inputDecoration(
            hint: 'Enter your password',
            icon: Icons.lock_outline,
            suffixIcon: IconButton(
              onPressed: isLoggingIn ? null : _togglePasswordVisibility,
              icon: Icon(
                obscurePassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                color: LoginStyle.iconColor,
              ),
              tooltip: obscurePassword ? 'Show password' : 'Hide password',
            ),
          ),
        ),
      ],
    );
  }

  // =========================================================
  // REMEMBER ME
  // =========================================================

  Widget _buildRememberMe() {
    return Row(
      children: [
        SizedBox(
          width: 24,
          height: 24,
          child: Checkbox(
            value: rememberMe,
            activeColor: LoginStyle.primary,
            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            onChanged: isLoggingIn
                ? null
                : (value) {
                    _setRememberMe(value ?? false);
                  },
          ),
        ),

        const SizedBox(width: 8),

        Text(
          'Remember me',
          style: TextStyle(fontSize: 12, color: LoginStyle.textColor),
        ),

        const Spacer(),

        Icon(Icons.shield_outlined, size: 14, color: LoginStyle.iconColor),

        const SizedBox(width: 5),

        Flexible(
          child: Text(
            'Secure SUKI sign in',
            style: TextStyle(fontSize: 10, color: LoginStyle.mutedText),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  // =========================================================
  // LOGIN BUTTON
  // =========================================================

  Widget _buildLoginButton() {
    return SizedBox(
      width: double.infinity,
      height: 48,
      child: ElevatedButton(
        style: LoginStyle.loginButton,
        onPressed: isLoggingIn ? null : login,
        child: isLoggingIn
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : const Text(
                'LOG IN',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
      ),
    );
  }

  // =========================================================
  // DIVIDER
  // =========================================================

  Widget _buildDivider() {
    return Row(
      children: [
        Expanded(child: Divider(color: LoginStyle.borderColor)),

        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            'OR',
            style: TextStyle(fontSize: 10, color: LoginStyle.mutedText),
          ),
        ),

        Expanded(child: Divider(color: LoginStyle.borderColor)),
      ],
    );
  }

  // =========================================================
  // SIGN UP LINK
  // =========================================================

  Widget _buildSignUpLink() {
    return Center(
      child: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text(
            "Don't have an account? ",
            style: TextStyle(fontSize: 12, color: LoginStyle.textColor),
          ),

          TextButton(
            onPressed: isLoggingIn ? null : goToRegister,
            child: Text(
              'Sign Up',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: LoginStyle.primary,
              ),
            ),
          ),
        ],
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
              color: LoginStyle.rightBackground,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: LoginStyle.lightGreenBorder),
            ),
            child: Icon(
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
                Text(
                  'Create your Mobile Suki account',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    color: LoginStyle.titleColor,
                  ),
                ),

                const SizedBox(height: 4),

                Text(
                  'Register as Buyer, Rider, or Logistics.',
                  style: TextStyle(fontSize: 10, color: LoginStyle.textColor),
                ),

                const SizedBox(height: 5),

                TextButton(
                  onPressed: isLoggingIn ? null : goToRegister,
                  style: TextButton.styleFrom(
                    padding: EdgeInsets.zero,
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: Text(
                    'Create Account →',
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
  // GUEST BUTTON
  // =========================================================

  Widget _buildGuestButton() {
    return Center(
      child: TextButton.icon(
        onPressed: isLoggingIn ? null : continueAsGuest,
        icon: Icon(Icons.arrow_back, size: 14, color: LoginStyle.mutedText),
        label: Text(
          'Continue as Guest',
          style: TextStyle(fontSize: 11, color: LoginStyle.mutedText),
        ),
      ),
    );
  }
}
