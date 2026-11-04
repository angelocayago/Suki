import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../services/auth_service.dart';
import '../../styles/register_style.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER REGISTRATION
// =========================================================
//
// Features:
// - Personal information
// - Contact information
// - Complete address
// - Birthday selection
// - Input validation
// - Strong password requirements
// - Laravel API registration
// - Loading and error handling
// - Success confirmation
//
// Uses AuthService.registerBuyer()
// API endpoint: POST /api/register
//
// =========================================================

class BuyerRegistration extends StatefulWidget {
  const BuyerRegistration({super.key});

  @override
  State<BuyerRegistration> createState() => _BuyerRegistrationState();
}

class _BuyerRegistrationState extends State<BuyerRegistration> {
  final _formKey = GlobalKey<FormState>();

  // =========================================================
  // PERSONAL INFORMATION
  // =========================================================

  final firstNameController = TextEditingController();
  final middleInitialController = TextEditingController();
  final lastNameController = TextEditingController();
  final birthdayController = TextEditingController();

  // =========================================================
  // CONTACT INFORMATION
  // =========================================================

  final emailController = TextEditingController();
  final phoneController = TextEditingController();

  // =========================================================
  // ADDRESS INFORMATION
  // =========================================================

  final regionController = TextEditingController();
  final provinceController = TextEditingController();
  final cityController = TextEditingController();
  final barangayController = TextEditingController();
  final streetController = TextEditingController();

  // =========================================================
  // ACCOUNT SECURITY
  // =========================================================

  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  String? selectedSex;

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;
  bool agreeTerms = false;
  bool isSubmitting = false;

  final List<String> sexOptions = ['Male', 'Female', 'Prefer not to say'];

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    passwordController.addListener(_onPasswordChanged);
  }

  void _onPasswordChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  // =========================================================
  // DISPOSE
  // =========================================================

  @override
  void dispose() {
    passwordController.removeListener(_onPasswordChanged);

    firstNameController.dispose();
    middleInitialController.dispose();
    lastNameController.dispose();
    birthdayController.dispose();

    emailController.dispose();
    phoneController.dispose();

    regionController.dispose();
    provinceController.dispose();
    cityController.dispose();
    barangayController.dispose();
    streetController.dispose();

    passwordController.dispose();
    confirmPasswordController.dispose();

    super.dispose();
  }

  // =========================================================
  // SNACKBAR
  // =========================================================

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: success ? RegisterStyle.primary : Colors.redAccent,
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 5),
        ),
      );
  }

  // =========================================================
  // DATE HELPERS
  // =========================================================

  DateTime get _latestAllowedBirthday {
    final now = DateTime.now();

    return DateTime(now.year - 18, now.month, now.day);
  }

  String _formatDisplayDate(DateTime date) {
    return '${date.month.toString().padLeft(2, '0')}/'
        '${date.day.toString().padLeft(2, '0')}/'
        '${date.year}';
  }

  String _formatApiDate(DateTime date) {
    return '${date.year}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }

  DateTime? _parseBirthday(String value) {
    try {
      final parts = value.trim().split('/');

      if (parts.length != 3) {
        return null;
      }

      final month = int.parse(parts[0]);
      final day = int.parse(parts[1]);
      final year = int.parse(parts[2]);

      final birthday = DateTime(year, month, day);

      if (birthday.year != year ||
          birthday.month != month ||
          birthday.day != day) {
        return null;
      }

      return birthday;
    } catch (_) {
      return null;
    }
  }

  // =========================================================
  // BIRTHDAY PICKER
  // =========================================================

  Future<void> selectBirthday() async {
    if (isSubmitting) return;

    final latestDate = _latestAllowedBirthday;

    final existingBirthday = _parseBirthday(birthdayController.text);

    final defaultDate = DateTime(2000);

    DateTime initialDate = defaultDate;

    if (existingBirthday != null &&
        !existingBirthday.isAfter(latestDate) &&
        !existingBirthday.isBefore(DateTime(1950))) {
      initialDate = existingBirthday;
    } else if (defaultDate.isAfter(latestDate)) {
      initialDate = latestDate;
    }

    final DateTime? selectedDate = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1950),
      lastDate: latestDate,
      initialDatePickerMode: DatePickerMode.year,
      helpText: 'SELECT YOUR BIRTHDAY',
    );

    if (selectedDate != null && mounted) {
      setState(() {
        birthdayController.text = _formatDisplayDate(selectedDate);
      });
    }
  }

  // =========================================================
  // BIRTHDAY VALIDATION
  // =========================================================

  String? _validateBirthday(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Birthday is required';
    }

    final birthday = _parseBirthday(value);

    if (birthday == null) {
      return 'Invalid birthday';
    }

    if (birthday.isAfter(_latestAllowedBirthday)) {
      return 'You must be at least 18 years old';
    }

    if (birthday.isBefore(DateTime(1950))) {
      return 'Invalid birth year';
    }

    return null;
  }

  // =========================================================
  // REQUIRED FIELD VALIDATION
  // =========================================================

  String? _validateRequired(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    return null;
  }

  // =========================================================
  // NAME VALIDATION
  // =========================================================

  String? _validateName(String? value) {
    final name = value?.trim() ?? '';

    if (name.isEmpty) {
      return 'This field is required';
    }

    if (name.length < 2) {
      return 'Enter at least 2 characters';
    }

    // Allow Filipino names including Ñ/ñ.
    // Also supports spaces, hyphens and apostrophes.
    if (!RegExp(r"^[A-Za-zÀ-ÿÑñ\s.'-]+$").hasMatch(name)) {
      return 'Letters and valid name punctuation only';
    }

    return null;
  }

  // =========================================================
  // MIDDLE INITIAL VALIDATION
  // =========================================================

  String? _validateMiddleInitial(String? value) {
    final initial = value?.trim() ?? '';

    if (initial.isEmpty) {
      return null;
    }

    if (!RegExp(r'^[A-ZÑ]$').hasMatch(initial.toUpperCase())) {
      return 'Enter one letter only';
    }

    return null;
  }

  List<TextInputFormatter> get _middleInitialFormatters {
    return [
      LengthLimitingTextInputFormatter(1),
      TextInputFormatter.withFunction((oldValue, newValue) {
        final uppercase = newValue.text.toUpperCase();

        if (uppercase.isNotEmpty && !RegExp(r'^[A-ZÑ]$').hasMatch(uppercase)) {
          return oldValue;
        }

        return newValue.copyWith(
          text: uppercase,
          selection: TextSelection.collapsed(offset: uppercase.length),
          composing: TextRange.empty,
        );
      }),
    ];
  }

  // =========================================================
  // EMAIL VALIDATION
  // =========================================================

  String? _validateEmail(String? value) {
    final email = value?.trim() ?? '';

    if (email.isEmpty) {
      return 'Email address is required';
    }

    if (email.length > 254) {
      return 'Email address is too long';
    }

    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
      return 'Enter a valid email address';
    }

    return null;
  }

  // =========================================================
  // MOBILE NUMBER VALIDATION
  // =========================================================

  String? _validatePhone(String? value) {
    final phone = value?.trim() ?? '';

    if (phone.isEmpty) {
      return 'Mobile number is required';
    }

    if (RegExp(r'^09\d{9}$').hasMatch(phone)) {
      return null;
    }

    if (RegExp(r'^639\d{9}$').hasMatch(phone)) {
      return null;
    }

    return 'Use 09171234567 or 639171234567';
  }

  // =========================================================
  // NORMALIZE MOBILE NUMBER
  // =========================================================

  String _normalizePhone(String value) {
    final phone = value.trim();

    if (phone.startsWith('639')) {
      return '0${phone.substring(2)}';
    }

    return phone;
  }

  // =========================================================
  // PASSWORD VALIDATION
  // =========================================================

  String? _validatePassword(String? value) {
    final password = value ?? '';

    if (password.isEmpty) {
      return 'Password is required';
    }

    if (password.length < 8) {
      return 'Password must be at least 8 characters';
    }

    if (!RegExp(r'[A-Z]').hasMatch(password)) {
      return 'Add at least one uppercase letter';
    }

    if (!RegExp(r'[a-z]').hasMatch(password)) {
      return 'Add at least one lowercase letter';
    }

    if (!RegExp(r'[0-9]').hasMatch(password)) {
      return 'Add at least one number';
    }

    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(password)) {
      return 'Add at least one special character';
    }

    return null;
  }

  String? _validateConfirmPassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please confirm your password';
    }

    if (value != passwordController.text) {
      return 'Passwords do not match';
    }

    return null;
  }

  // =========================================================
  // BUILD BUYER REGISTRATION PAYLOAD
  // =========================================================

  Map<String, dynamic> _buildBuyerData(DateTime birthday) {
    String field(TextEditingController controller) {
      return controller.text.trim();
    }

    return {
      'role': 'buyer',

      // Personal information
      'first_name': field(firstNameController),

      'middle_initial': field(middleInitialController).isEmpty
          ? null
          : field(middleInitialController).toUpperCase(),

      'last_name': field(lastNameController),

      'birthday': _formatApiDate(birthday),

      'sex': selectedSex,

      // Contact information
      'email': field(emailController).toLowerCase(),

      'phone': _normalizePhone(phoneController.text),

      // Address information
      'region': field(regionController),
      'province': field(provinceController),
      'city': field(cityController),
      'barangay': field(barangayController),
      'street': field(streetController),

      // Account security
      'password': passwordController.text,

      'password_confirmation': confirmPasswordController.text,
    };
  }

  // =========================================================
  // REGISTER BUYER USING LARAVEL API
  // =========================================================

  Future<void> registerBuyer() async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    // Validate all form inputs.
    if (!(_formKey.currentState?.validate() ?? false)) {
      _showMessage('Please complete all required fields correctly.');
      return;
    }

    // Validate Terms and Privacy Policy.
    if (!agreeTerms) {
      _showMessage(
        'Please agree to the Terms of Service '
        'and Privacy Policy.',
      );
      return;
    }

    // Additional password check.
    if (passwordController.text != confirmPasswordController.text) {
      _showMessage('Passwords do not match.');
      return;
    }

    // Validate birthday.
    final birthday = _parseBirthday(birthdayController.text);

    if (birthday == null) {
      _showMessage('Please enter a valid birthday.');
      return;
    }

    setState(() {
      isSubmitting = true;
    });

    try {
      // Prepare registration data.
      final buyerData = _buildBuyerData(birthday);

      // Submit to Laravel via AuthService.
      final Map<String, dynamic> response = await AuthService.registerBuyer(
        buyerData: buyerData,
      ).timeout(const Duration(seconds: 30));

      if (!mounted) return;

      // The AuthService validates success: true.
      final message =
          response['message']?.toString() ??
          'Your buyer account has been registered.';

      // Show confirmation before navigating.
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) {
          return AlertDialog(
            title: const Text('Registration Submitted'),
            content: Text(
              '$message\n\n'
              'Please sign in after completing '
              'any verification required by SUKI.',
            ),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.of(dialogContext).pop();
                },
                child: const Text('Continue'),
              ),
            ],
          );
        },
      );

      if (!mounted) return;

      // Do not automatically grant buyer access.
      // Authentication and approval are controlled
      // by the Laravel backend.
      Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
    } on TimeoutException {
      if (!mounted) return;

      _showMessage(
        'Registration request timed out. '
        'Please check whether your account '
        'was created before trying again.',
      );
    } catch (error) {
      if (!mounted) return;

      final message = error.toString().replaceFirst('Exception: ', '').trim();

      _showMessage(
        message.isEmpty ? 'Unable to register. Please try again.' : message,
      );
    } finally {
      if (mounted) {
        setState(() {
          isSubmitting = false;
        });
      }
    }
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RegisterStyle.backgroundColor,
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // =====================================
                    // HEADER
                    // =====================================
                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: RegisterStyle.logoContainer,
                        child: Icon(
                          Icons.shopping_bag_rounded,
                          size: 38,
                          color: RegisterStyle.primary,
                        ),
                      ),
                    ),

                    const SizedBox(height: 15),

                    Center(
                      child: Text(
                        'MOBILE SUKI',
                        style: RegisterStyle.brandText,
                      ),
                    ),

                    const SizedBox(height: 22),

                    Text(
                      'BUYER REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Create Buyer Account',
                      style: RegisterStyle.pageTitle,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Fill in your personal information '
                      'to start shopping with Mobile Suki.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // PERSONAL INFORMATION
                    // =====================================
                    _sectionTitle('Personal Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'First Name',
                      controller: firstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      capitalization: TextCapitalization.words,
                      validator: _validateName,
                    ),

                    const SizedBox(height: 13),

                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: _buildTextField(
                            label: 'Middle Initial',
                            controller: middleInitialController,
                            hint: 'M.I. (Optional)',
                            capitalization: TextCapitalization.characters,
                            validator: _validateMiddleInitial,
                            inputFormatters: _middleInitialFormatters,
                          ),
                        ),

                        const SizedBox(width: 12),

                        Expanded(
                          child: _buildTextField(
                            label: 'Last Name',
                            controller: lastNameController,
                            hint: 'Last name',
                            capitalization: TextCapitalization.words,
                            validator: _validateName,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 13),

                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(child: _buildBirthdayField()),

                        const SizedBox(width: 12),

                        Expanded(child: _buildSexField()),
                      ],
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // CONTACT INFORMATION
                    // =====================================
                    _sectionTitle('Contact Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Email Address',
                      controller: emailController,
                      hint: 'juan@gmail.com',
                      icon: Icons.mail_outline,
                      keyboardType: TextInputType.emailAddress,
                      capitalization: TextCapitalization.none,
                      validator: _validateEmail,
                      inputFormatters: [
                        FilteringTextInputFormatter.deny(RegExp(r'\s')),
                      ],
                    ),

                    const SizedBox(height: 6),

                    Text(
                      'Enter a valid email address, '
                      'example: juan@gmail.com',
                      style: TextStyle(
                        fontSize: 10,
                        color: RegisterStyle.textColor,
                      ),
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Mobile Number',
                      controller: phoneController,
                      hint: '09171234567 or 639171234567',
                      icon: Icons.phone_outlined,
                      keyboardType: TextInputType.phone,
                      capitalization: TextCapitalization.none,
                      validator: _validatePhone,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(12),
                      ],
                    ),

                    const SizedBox(height: 6),

                    Text(
                      'Enter 11 digits starting with 09 or '
                      '12 digits starting with 63.',
                      style: TextStyle(
                        fontSize: 10,
                        color: RegisterStyle.textColor,
                      ),
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // ADDRESS INFORMATION
                    // =====================================
                    _sectionTitle('Address Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Region',
                      controller: regionController,
                      hint: 'Enter region',
                      icon: Icons.map_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Province',
                      controller: provinceController,
                      hint: 'Enter province',
                      icon: Icons.location_city_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'City / Municipality',
                      controller: cityController,
                      hint: 'Enter city or municipality',
                      icon: Icons.location_city_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Barangay',
                      controller: barangayController,
                      hint: 'Enter barangay',
                      icon: Icons.place_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Street / House Number',
                      controller: streetController,
                      hint: 'House number, street, building',
                      icon: Icons.home_outlined,
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // ACCOUNT SECURITY
                    // =====================================
                    _sectionTitle('Account Security'),

                    const SizedBox(height: 13),

                    _buildPasswordField(),

                    const SizedBox(height: 13),

                    _buildConfirmPasswordField(),

                    const SizedBox(height: 12),

                    _buildPasswordRequirements(),

                    const SizedBox(height: 20),

                    // =====================================
                    // TERMS AND CONDITIONS
                    // =====================================
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: 22,
                          height: 22,
                          child: Checkbox(
                            value: agreeTerms,
                            activeColor: RegisterStyle.primary,
                            materialTapTargetSize:
                                MaterialTapTargetSize.shrinkWrap,
                            onChanged: isSubmitting
                                ? null
                                : (value) {
                                    setState(() {
                                      agreeTerms = value ?? false;
                                    });
                                  },
                          ),
                        ),

                        const SizedBox(width: 9),

                        Expanded(
                          child: Text.rich(
                            TextSpan(
                              style: RegisterStyle.termsText,
                              children: [
                                const TextSpan(
                                  text: "I agree to Mobile Suki's ",
                                ),
                                TextSpan(
                                  text: 'Terms of Service',
                                  style: RegisterStyle.linkText,
                                ),
                                const TextSpan(text: ' and '),
                                TextSpan(
                                  text: 'Privacy Policy.',
                                  style: RegisterStyle.linkText,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // =====================================
                    // CREATE ACCOUNT BUTTON
                    // =====================================
                    SizedBox(
                      width: double.infinity,
                      height: 49,
                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed: isSubmitting ? null : registerBuyer,
                        child: isSubmitting
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : Text(
                                'CREATE BUYER ACCOUNT',
                                style: RegisterStyle.buttonText,
                              ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // =====================================
                    // LOGIN LINK
                    // =====================================
                    Center(
                      child: Wrap(
                        alignment: WrapAlignment.center,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(
                            'Already have an account? ',
                            style: RegisterStyle.bottomText,
                          ),
                          GestureDetector(
                            onTap: isSubmitting
                                ? null
                                : () {
                                    Navigator.pushReplacementNamed(
                                      context,
                                      '/login',
                                    );
                                  },
                            child: Text(
                              'Log In',
                              style: RegisterStyle.bottomLink,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 18),

                    // =====================================
                    // BACK BUTTON
                    // =====================================
                    Center(
                      child: TextButton.icon(
                        onPressed: isSubmitting
                            ? null
                            : () => Navigator.pop(context),
                        icon: Icon(
                          Icons.arrow_back,
                          size: 15,
                          color: RegisterStyle.mutedText,
                        ),
                        label: Text(
                          'Back to Account Selection',
                          style: RegisterStyle.backText,
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // =========================================================
  // SECTION TITLE
  // =========================================================

  Widget _sectionTitle(String title) {
    return Text(title, style: RegisterStyle.sectionTitle);
  }

  // =========================================================
  // REUSABLE TEXT FIELD
  // =========================================================

  Widget _buildTextField({
    required String label,
    required TextEditingController controller,
    required String hint,
    IconData? icon,
    TextInputType? keyboardType,
    TextCapitalization capitalization = TextCapitalization.words,
    String? Function(String?)? validator,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: RegisterStyle.label),

        const SizedBox(height: 6),

        TextFormField(
          controller: controller,
          keyboardType: keyboardType ?? TextInputType.text,
          textCapitalization: capitalization,
          autocorrect: keyboardType != TextInputType.emailAddress,
          enabled: !isSubmitting,
          inputFormatters: inputFormatters,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(hint: hint, icon: icon),
          validator: validator ?? _validateRequired,
        ),
      ],
    );
  }

  // =========================================================
  // BIRTHDAY FIELD
  // =========================================================

  Widget _buildBirthdayField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Birthday', style: RegisterStyle.label),

        const SizedBox(height: 6),

        TextFormField(
          controller: birthdayController,
          readOnly: true,
          enabled: !isSubmitting,
          onTap: selectBirthday,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(
            hint: 'mm/dd/yyyy',
            suffixIcon: Icon(
              Icons.calendar_today_outlined,
              size: 17,
              color: RegisterStyle.iconColor,
            ),
          ),
          validator: _validateBirthday,
        ),
      ],
    );
  }

  // =========================================================
  // SEX DROPDOWN
  // =========================================================

  Widget _buildSexField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Sex', style: RegisterStyle.label),

        const SizedBox(height: 6),

        DropdownButtonFormField<String>(
          value: selectedSex,
          isExpanded: true,
          decoration: RegisterStyle.dropdownDecoration(),
          hint: Text('Select sex', style: RegisterStyle.hint),
          items: sexOptions.map((sex) {
            return DropdownMenuItem<String>(
              value: sex,
              child: Text(
                sex,
                style: RegisterStyle.inputText,
                overflow: TextOverflow.ellipsis,
              ),
            );
          }).toList(),
          onChanged: isSubmitting
              ? null
              : (value) {
                  setState(() {
                    selectedSex = value;
                  });
                },
          validator: (value) {
            if (value == null || value.isEmpty) {
              return 'Required';
            }
            return null;
          },
        ),
      ],
    );
  }

  // =========================================================
  // PASSWORD FIELD
  // =========================================================

  Widget _buildPasswordField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Password', style: RegisterStyle.label),

        const SizedBox(height: 6),

        TextFormField(
          controller: passwordController,
          obscureText: obscurePassword,
          enabled: !isSubmitting,
          autocorrect: false,
          enableSuggestions: false,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(
            hint: 'Enter password',
            icon: Icons.lock_outline,
            suffixIcon: IconButton(
              onPressed: isSubmitting
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
                size: 18,
                color: RegisterStyle.iconColor,
              ),
            ),
          ),
          validator: _validatePassword,
        ),
      ],
    );
  }

  // =========================================================
  // PASSWORD REQUIREMENT ITEM
  // =========================================================

  Widget _passwordRule(String label, bool passed) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),
      child: Row(
        children: [
          Icon(
            passed ? Icons.check_circle : Icons.circle_outlined,
            size: 14,
            color: passed ? RegisterStyle.primary : RegisterStyle.mutedText,
          ),

          const SizedBox(width: 7),

          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 10,
                color: passed ? RegisterStyle.primary : RegisterStyle.textColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // LIVE PASSWORD REQUIREMENTS
  // =========================================================

  Widget _buildPasswordRequirements() {
    final password = passwordController.text;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: RegisterStyle.selectedBackground,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: RegisterStyle.borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Password Requirements',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: RegisterStyle.titleColor,
            ),
          ),

          const SizedBox(height: 9),

          _passwordRule('At least 8 characters', password.length >= 8),

          _passwordRule(
            'One uppercase letter (A-Z)',
            RegExp(r'[A-Z]').hasMatch(password),
          ),

          _passwordRule(
            'One lowercase letter (a-z)',
            RegExp(r'[a-z]').hasMatch(password),
          ),

          _passwordRule(
            'One number (0-9)',
            RegExp(r'[0-9]').hasMatch(password),
          ),

          _passwordRule(
            'One special character',
            RegExp(r'[^A-Za-z0-9]').hasMatch(password),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // CONFIRM PASSWORD
  // =========================================================

  Widget _buildConfirmPasswordField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Confirm Password', style: RegisterStyle.label),

        const SizedBox(height: 6),

        TextFormField(
          controller: confirmPasswordController,
          obscureText: obscureConfirmPassword,
          enabled: !isSubmitting,
          autocorrect: false,
          enableSuggestions: false,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(
            hint: 'Re-enter password',
            icon: Icons.lock_outline,
            suffixIcon: IconButton(
              onPressed: isSubmitting
                  ? null
                  : () {
                      setState(() {
                        obscureConfirmPassword = !obscureConfirmPassword;
                      });
                    },
              icon: Icon(
                obscureConfirmPassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                size: 18,
                color: RegisterStyle.iconColor,
              ),
            ),
          ),
          validator: _validateConfirmPassword,
        ),
      ],
    );
  }
}
