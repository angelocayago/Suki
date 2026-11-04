
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

  final TextEditingController emailController =
      TextEditingController();

  final TextEditingController passwordController =
      TextEditingController();

  // =========================================================
  // STATES
  // =========================================================

  bool rememberMe = true;
  bool obscurePassword = true;
  bool isLoggingIn = false;
  bool _rememberMeTouched = false;
  late final Future<void> _rememberedLoginLoad;

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
        if (!_rememberMeTouched) rememberMe = shouldRemember;
        if (emailController.text.trim().isEmpty) {
          emailController.text = savedLogin ?? '';
        }
      });
    } catch (_) {
      // Leave the login form usable if secure storage is unavailable.
    }
  }

  Future<void> _setRememberMe(bool value) async {
    setState(() {
      _rememberMeTouched = true;
      rememberMe = value;
    });
    try {
      await AuthService.setRememberMe(value);
    } catch (_) {
      // The current login form still reflects the user's selection.
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
  // LOCAL DEMO LOGIN
  //
  Future<void> login() async {
    if (isLoggingIn) return;

    await _rememberedLoginLoad;
    if (!mounted || isLoggingIn) return;

    FocusScope.of(context).unfocus();

    final String loginValue = emailController.text.trim();
    final String password = passwordController.text;

    if (loginValue.isEmpty || password.isEmpty) {
      _showMessage(
        'Please enter your email/phone and password.',
      );
      return;
    }

    setState(() {
      isLoggingIn = true;
    });

    try {
      final response = await AuthService.login(
        login: loginValue,
        password: password,
        rememberMe: rememberMe,
      );

      if (!mounted) return;

      _showMessage(
        'Signed in to your SUKI account.',
        backgroundColor: LoginStyle.primary,
      );

      Navigator.pushNamedAndRemoveUntil(
        context,
        AuthService.homeRouteForUser(response),
        (route) => false,
      );
    } catch (error) {
      if (!mounted) return;

      _showMessage(
        error.toString().replaceFirst('Exception: ', ''),
        backgroundColor: Colors.redAccent,
      );
    } finally {
      if (mounted) {
        setState(() {
          isLoggingIn = false;
        });
      }
    }
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
        ),
      );
  }

  // =========================================================
  // ROUTES
  // =========================================================

  void goToRegister() {
    if (isLoggingIn) return;

    Navigator.pushNamed(
      context,
      '/register',
    );
  }

  void goToForgotPassword() {
    if (isLoggingIn) return;

    Navigator.pushNamed(
      context,
      '/forgot-password',
    );
  }

  void continueAsGuest() {
    if (isLoggingIn) return;

    Navigator.pushReplacementNamed(
      context,
      '/buyer-home',
    );
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    final double screenWidth =
        MediaQuery.of(context).size.width;

    final bool isMobile = screenWidth < 700;

    return Scaffold(
      backgroundColor: AppTheme.background,
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
  // =========================================================

  Widget _buildMobileLayout(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        return SingleChildScrollView(
          physics: BouncingScrollPhysics(),

          keyboardDismissBehavior:
              ScrollViewKeyboardDismissBehavior.onDrag,

          padding: const EdgeInsets.symmetric(
            horizontal: 22,
            vertical: 16,
          ),

          child: Center(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: 460,
              ),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: const ThemeToggleButton(),
                  ),

                  // =========================================
                  // LOGO
                  // =========================================

                  Center(
                    child: Column(
                      children: [
                        Container(
                          width: 82,
                          height: 82,
                          padding: const EdgeInsets.all(10),

                          decoration: BoxDecoration(
                            color: AppTheme.softGreen,
                            borderRadius:
                                BorderRadius.circular(20),
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

                        SizedBox(height: 7),

                        Text(
                          'MOBILE SUKI',

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

                  SizedBox(height: 22),

                  // =========================================
                  // HEADER
                  // =========================================

                  Text(
                    'WELCOME BACK',

                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: LoginStyle.primary,
                      letterSpacing: .6,
                    ),
                  ),

                  SizedBox(height: 5),

                  Text(
                    'Log in to Mobile Suki',

                    style: TextStyle(
                      fontSize: 23,
                      fontWeight: FontWeight.w800,
                      color: LoginStyle.titleColor,
                    ),
                  ),

                  SizedBox(height: 5),

                  Text(
                    'Sign in to continue shopping with us.',

                    style: TextStyle(
                      fontSize: 12.5,
                      color: LoginStyle.textColor,
                    ),
                  ),

                  SizedBox(height: 21),

                  // =========================================
                  // EMAIL OR PHONE
                  // =========================================

                  Text(
                    'Phone Number or Gmail',

                    style: TextStyle(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w600,
                      color: LoginStyle.titleColor,
                    ),
                  ),

                  SizedBox(height: 7),

                  SizedBox(
                    height: 50,

                    child: TextField(
                      controller: emailController,
                      keyboardType:
                          TextInputType.emailAddress,
                      textInputAction:
                          TextInputAction.next,
                      enabled: !isLoggingIn,

                      decoration:
                          LoginStyle.inputDecoration(
                        hint: 'Enter phone number or Gmail',
                        icon: Icons.person_outline,
                      ),
                    ),
                  ),

                  SizedBox(height: 15),

                  // =========================================
                  // PASSWORD LABEL
                  // =========================================

                  Row(
                    mainAxisAlignment:
                        MainAxisAlignment.spaceBetween,

                    children: [
                      Text(
                        'Password',
                        style: LoginStyle.label,
                      ),

                      GestureDetector(
                        onTap: goToForgotPassword,

                        child: Text(
                          'Forgot Password?',
                          style: LoginStyle.forgotPassword,
                        ),
                      ),
                    ],
                  ),

                  SizedBox(height: 7),

                  // =========================================
                  // PASSWORD INPUT
                  // =========================================

                  SizedBox(
                    height: 50,

                    child: TextField(
                      controller: passwordController,
                      obscureText: obscurePassword,
                      enabled: !isLoggingIn,
                      textInputAction: TextInputAction.done,

                      onSubmitted: (_) {
                        if (!isLoggingIn) login();
                      },

                      decoration:
                          LoginStyle.inputDecoration(
                        hint: 'Enter your password',
                        icon: Icons.lock_outline,

                        suffixIcon: IconButton(
                          onPressed: isLoggingIn
                              ? null
                              : () {
                                  setState(() {
                                    obscurePassword =
                                        !obscurePassword;
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

                  SizedBox(height: 13),

                  // =========================================
                  // REMEMBER ME
                  // =========================================

                  Row(
                    children: [
                      SizedBox(
                        width: 20,
                        height: 20,

                        child: Checkbox(
                          value: rememberMe,
                          activeColor: LoginStyle.primary,

                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,

                          visualDensity:
                              VisualDensity.compact,

                          onChanged: isLoggingIn
                              ? null
                              : (value) =>
                                  _setRememberMe(value ?? false),
                        ),
                      ),

                      SizedBox(width: 8),

                      Text(
                        'Remember me',

                        style: TextStyle(
                          fontSize: 11.5,
                          color: LoginStyle.textColor,
                        ),
                      ),

                      Spacer(),

                      Row(
                        children: [
                          Icon(
                            Icons.shield_outlined,
                            size: 14,
                            color: LoginStyle.iconColor,
                          ),

                          SizedBox(width: 5),

                          Text(
                            'Secure SUKI sign in',

                            style: TextStyle(
                              fontSize: 9.5,
                              color: LoginStyle.mutedText,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),

                  SizedBox(height: 18),

                  // =========================================
                  // LOGIN BUTTON
                  // =========================================

                  SizedBox(
                    width: double.infinity,
                    height: 48,

                    child: ElevatedButton(
                      style: LoginStyle.loginButton,

                      onPressed:
                          isLoggingIn ? null : login,

                      child: isLoggingIn
                          ? SizedBox(
                              width: 20,
                              height: 20,

                              child:
                                  CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              'LOG IN',

                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w700,
                                color: Colors.white,
                              ),
                            ),
                    ),
                  ),

                  SizedBox(height: 19),

                  // =========================================
                  // DIVIDER
                  // =========================================

                  Row(
                    children: [
                      Expanded(
                        child: Divider(
                          color: LoginStyle.borderColor,
                        ),
                      ),

                      Padding(
                        padding:
                            const EdgeInsets.symmetric(
                          horizontal: 12,
                        ),

                        child: Text(
                          'OR',

                          style: TextStyle(
                            fontSize: 9.5,
                            color: LoginStyle.mutedText,
                          ),
                        ),
                      ),

                      Expanded(
                        child: Divider(
                          color: LoginStyle.borderColor,
                        ),
                      ),
                    ],
                  ),

                  SizedBox(height: 17),

                  // =========================================
                  // SIGN UP
                  // =========================================

                  Center(
                    child: Wrap(
                      alignment: WrapAlignment.center,

                      children: [
                        Text(
                          "Don't have an account? ",

                          style: TextStyle(
                            fontSize: 11.5,
                            color: LoginStyle.textColor,
                          ),
                        ),

                        GestureDetector(
                          onTap: goToRegister,

                          child: Text(
                            'Sign Up',

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

                  SizedBox(height: 18),

                  // =========================================
                  // CREATE ACCOUNT CARD
                  // =========================================

                  _buildAccountRegistrationCard(),

                  SizedBox(height: 14),

                  // =========================================
                  // CONTINUE AS GUEST
                  // =========================================

                  Center(
                    child: TextButton(
                      onPressed: continueAsGuest,

                      child: Row(
                        mainAxisSize: MainAxisSize.min,

                        children: [
                          Icon(
                            Icons.arrow_back,
                            size: 14,
                            color: LoginStyle.mutedText,
                          ),

                          SizedBox(width: 5),

                          Text(
                            'Continue as Guest',

                            style: TextStyle(
                              fontSize: 10.5,
                              color: LoginStyle.mutedText,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  SizedBox(height: 15),

                  // FOOTER

                  Center(
                    child: Text(
                      'Your everyday marketplace.',

                      style: TextStyle(
                        fontSize: 9.5,
                        color: LoginStyle.mutedText,
                      ),
                    ),
                  ),

                  SizedBox(height: 15),
                ],
              ),
            ),
          ),
        );
      },
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

              border: Border.all(
                color: LoginStyle.lightGreenBorder,
              ),
            ),

            child: Icon(
              Icons.person_add_alt_1_outlined,
              size: 21,
              color: LoginStyle.primary,
            ),
          ),

          SizedBox(width: 11),

          Expanded(
            child: Column(
              crossAxisAlignment:
                  CrossAxisAlignment.start,

              children: [
                Text(
                  'Create your Mobile Suki account',

                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    color: LoginStyle.titleColor,
                  ),
                ),

                SizedBox(height: 3),

                Text(
                  'Register as Buyer, Rider, or Logistics.',

                  style: TextStyle(
                    fontSize: 9.5,
                    color: LoginStyle.textColor,
                  ),
                ),

                SizedBox(height: 5),

                GestureDetector(
                  onTap: goToRegister,

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
  // DESKTOP LAYOUT
  // =========================================================

  Widget _buildDesktopLayout(BuildContext context) {
    return Row(
      children: [
        // LEFT SIDE

        Expanded(
          child: Container(
            color: LoginStyle.leftBackground,

            child: Center(
              child: Column(
                mainAxisAlignment:
                    MainAxisAlignment.center,

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

                  SizedBox(height: 30),

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

        // RIGHT SIDE

        Expanded(
          child: Center(
            child: SingleChildScrollView(
              keyboardDismissBehavior:
                  ScrollViewKeyboardDismissBehavior.onDrag,

              padding: const EdgeInsets.all(40),

              child: ConstrainedBox(
                constraints: BoxConstraints(
                  maxWidth: 430,
                ),

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
        Text(
          'WELCOME BACK',
          style: LoginStyle.welcomeText,
        ),

        SizedBox(height: 8),

        Text(
          'Log in to Mobile Suki',
          style: LoginStyle.loginTitle,
        ),

        SizedBox(height: 6),

        Text(
          'Sign in to continue shopping with us.',
          style: LoginStyle.subtitle,
        ),

        SizedBox(height: 30),

        // EMAIL

        Text(
          'Phone Number or Gmail',
          style: LoginStyle.label,
        ),

        SizedBox(height: 8),

        TextField(
          controller: emailController,
          enabled: !isLoggingIn,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,

          decoration: LoginStyle.inputDecoration(
            hint: 'Enter phone number or Gmail',
            icon: Icons.person_outline,
          ),
        ),

        SizedBox(height: 18),

        // PASSWORD LABEL

        Row(
          mainAxisAlignment:
              MainAxisAlignment.spaceBetween,

          children: [
            Text(
              'Password',
              style: LoginStyle.label,
            ),

            GestureDetector(
              onTap: goToForgotPassword,

              child: Text(
                'Forgot Password?',
                style: LoginStyle.forgotPassword,
              ),
            ),
          ],
        ),

        SizedBox(height: 8),

        // PASSWORD

        TextField(
          controller: passwordController,
          obscureText: obscurePassword,
          enabled: !isLoggingIn,

          textInputAction: TextInputAction.done,

          onSubmitted: (_) {
            if (!isLoggingIn) login();
          },

          decoration: LoginStyle.inputDecoration(
            hint: 'Enter your password',
            icon: Icons.lock_outline,

            suffixIcon: IconButton(
              onPressed: isLoggingIn
                  ? null
                  : () {
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

        SizedBox(height: 10),

        // REMEMBER ME

        Row(
          children: [
            Checkbox(
              value: rememberMe,
              activeColor: LoginStyle.primary,

              onChanged: isLoggingIn
                  ? null
                  : (value) => _setRememberMe(value ?? false),
            ),

            Text(
              'Remember me',

              style: TextStyle(
                fontSize: 12,
                color: LoginStyle.textColor,
              ),
            ),

            Spacer(),

            Text(
              'Uses your SUKI account',
              style: TextStyle(
                fontSize: 10,
                color: LoginStyle.mutedText,
              ),
            ),
          ],
        ),

        SizedBox(height: 20),

        // LOGIN BUTTON

        SizedBox(
          width: double.infinity,
          height: 48,

          child: ElevatedButton(
            style: LoginStyle.loginButton,

            onPressed: isLoggingIn ? null : login,

            child: isLoggingIn
                ? SizedBox(
                    width: 20,
                    height: 20,

                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Text(
                    'LOG IN',
                    style: LoginStyle.buttonText,
                  ),
          ),
        ),

        SizedBox(height: 20),

        // SIGN UP

        Center(
          child: Wrap(
            alignment: WrapAlignment.center,

            children: [
              Text(
                "Don't have an account? ",
                style: LoginStyle.subtitle,
              ),

              GestureDetector(
                onTap: goToRegister,

                child: Text(
                  'Sign Up',

                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: LoginStyle.primary,
                  ),
                ),
              ),
            ],
          ),
        ),

        SizedBox(height: 20),

        // ACCOUNT REGISTRATION

        _buildAccountRegistrationCard(),

        SizedBox(height: 15),

        // GUEST

        Center(
          child: TextButton(
            onPressed: continueAsGuest,

            child: Text(
              'Continue as Guest',
              style: TextStyle(
                fontSize: 11,
                color: LoginStyle.mutedText,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
