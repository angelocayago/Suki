
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../styles/register_style.dart';

class BuyerRegistration extends StatefulWidget {
  const BuyerRegistration({super.key});

  @override
  State<BuyerRegistration> createState() =>
      _BuyerRegistrationState();
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

  final List<String> sexOptions = [
    'Male',
    'Female',
    'Prefer not to say',
  ];

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

  void _showMessage(
    String message, {
    bool success = false,
  }) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: success
              ? RegisterStyle.primary
              : Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // =========================================================
  // BIRTHDAY VALIDATION
  // MINIMUM AGE: 18 YEARS OLD
  // YEAR 2008 AND EARLIER (BASED ON CURRENT DATE)
  // =========================================================

  DateTime get _latestAllowedBirthday {
    final now = DateTime.now();

    return DateTime(
      now.year - 18,
      now.month,
      now.day,
    );
  }

  Future<void> selectBirthday() async {
    final latestDate = _latestAllowedBirthday;

    final initialDate = DateTime(2000);

    final DateTime? selectedDate = await showDatePicker(
      context: context,
      initialDate: initialDate.isAfter(latestDate)
          ? latestDate
          : initialDate,
      firstDate: DateTime(1950),
      lastDate: latestDate,
      initialDatePickerMode: DatePickerMode.year,
      helpText: 'SELECT YOUR BIRTHDAY',
    );

    if (selectedDate != null && mounted) {
      setState(() {
        birthdayController.text =
            '${selectedDate.month.toString().padLeft(2, '0')}/'
            '${selectedDate.day.toString().padLeft(2, '0')}/'
            '${selectedDate.year}';
      });
    }
  }

  DateTime? _parseBirthday(String value) {
    try {
      final parts = value.split('/');

      if (parts.length != 3) return null;

      final month = int.parse(parts[0]);
      final day = int.parse(parts[1]);
      final year = int.parse(parts[2]);

      final date = DateTime(year, month, day);

      if (date.year != year ||
          date.month != month ||
          date.day != day) {
        return null;
      }

      return date;
    } catch (_) {
      return null;
    }
  }

  String? _validateBirthday(String? value) {
    if (value == null || value.isEmpty) {
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
  // NAME VALIDATION
  // =========================================================

  String? _validateName(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    if (value.trim().length < 2) {
      return 'Enter at least 2 characters';
    }

    if (!RegExp(r"^[a-zA-Z\s'-]+$").hasMatch(value.trim())) {
      return 'Letters only are allowed';
    }

    return null;
  }

  // =========================================================
  // MIDDLE INITIAL
  // ONE UPPERCASE LETTER ONLY
  // =========================================================

  String? _validateMiddleInitial(String? value) {
    if (value == null || value.trim().isEmpty) {
      return null;
    }

    if (!RegExp(r'^[A-Z]$').hasMatch(value.trim())) {
      return 'Enter one uppercase letter only';
    }

    return null;
  }

  List<TextInputFormatter> get _middleInitialFormatters {
    return [
      FilteringTextInputFormatter.allow(
        RegExp(r'[a-zA-Z]'),
      ),
      LengthLimitingTextInputFormatter(1),
      TextInputFormatter.withFunction(
        (oldValue, newValue) {
          final uppercaseText =
              newValue.text.toUpperCase();

          return TextEditingValue(
            text: uppercaseText,
            selection: TextSelection.collapsed(
              offset: uppercaseText.length,
            ),
          );
        },
      ),
    ];
  }

  // =========================================================
  // EMAIL VALIDATION
  // =========================================================

  String? _validateEmail(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Email address is required';
    }

    final email = value.trim();

    if (email.contains(' ')) {
      return 'Spaces are not allowed in email';
    }

    final emailRegex = RegExp(
      r'^[A-Za-z0-9]+(?:[._%+-][A-Za-z0-9]+)*@'
      r'(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+'
      r'[A-Za-z]{2,}$',
    );

    if (!emailRegex.hasMatch(email)) {
      return 'Enter a valid email address (example@gmail.com)';
    }

    if (email.length > 254) {
      return 'Email address is too long';
    }

    return null;
  }

  // =========================================================
  // MOBILE NUMBER VALIDATION
  //
  // 09XXXXXXXXX = 11 DIGITS
  // 639XXXXXXXXX = 12 DIGITS
  // NUMBERS ONLY
  // =========================================================

  String? _validatePhone(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Mobile number is required';
    }

    final phone = value.trim();

    if (!RegExp(r'^\d+$').hasMatch(phone)) {
      return 'Numbers only are allowed';
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
  // STRONG PASSWORD VALIDATION
  // =========================================================

  String? _validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Password is required';
    }

    if (value.length < 8) {
      return 'Password must be at least 8 characters';
    }

    if (!RegExp(r'[A-Z]').hasMatch(value)) {
      return 'Add at least one uppercase letter';
    }

    if (!RegExp(r'[a-z]').hasMatch(value)) {
      return 'Add at least one lowercase letter';
    }

    if (!RegExp(r'[0-9]').hasMatch(value)) {
      return 'Add at least one number';
    }

    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
      return 'Add at least one special character';
    }

    return null;
  }

  // =========================================================
  // LOCAL BUYER REGISTRATION
  //
  // TEMPORARY ONLY
  // NO LARAVEL API REQUEST
  // NO DATABASE SAVING
  // =========================================================

  Future<void> registerBuyer() async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    // Validate all form fields.
    if (!_formKey.currentState!.validate()) {
      return;
    }

    // Validate Terms and Conditions.
    if (!agreeTerms) {
      _showMessage(
        'Please agree to the Terms of Service and Privacy Policy.',
      );
      return;
    }

    // Validate Confirm Password.
    if (passwordController.text !=
        confirmPasswordController.text) {
      _showMessage('Passwords do not match.');
      return;
    }

    // Validate birthday.
    final birthday = _parseBirthday(
      birthdayController.text,
    );

    if (birthday == null) {
      _showMessage('Please enter a valid birthday.');
      return;
    }

    setState(() {
      isSubmitting = true;
    });

    try {
      // Temporary local validation.
      // No AuthService or Laravel API call.

      await Future.delayed(
        Duration(milliseconds: 500),
      );

      if (!mounted) return;

      _showMessage(
        'Registration form validated successfully! '
        'Account saving will be enabled when API is connected.',
        success: true,
      );
    } catch (error) {
      if (!mounted) return;

      _showMessage('Something went wrong: $error');
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
    Theme.of(context);
    return Scaffold(
      backgroundColor: RegisterStyle.backgroundColor,
      resizeToAvoidBottomInset: true,

      body: SafeArea(
        child: SingleChildScrollView(
          physics: BouncingScrollPhysics(),
          keyboardDismissBehavior:
              ScrollViewKeyboardDismissBehavior.onDrag,

          padding: const EdgeInsets.symmetric(
            horizontal: 20,
            vertical: 18,
          ),

          child: Center(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: 460,
              ),

              child: Form(
                key: _formKey,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    // HEADER

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

                    SizedBox(height: 15),

                    Center(
                      child: Text(
                        'MOBILE SUKI',
                        style: RegisterStyle.brandText,
                      ),
                    ),

                    SizedBox(height: 22),

                    Text(
                      'BUYER REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Create Buyer Account',
                      style: RegisterStyle.pageTitle,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Fill in your personal information to start '
                      'shopping with Mobile Suki.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 25),

                    // PERSONAL INFORMATION

                    _sectionTitle('Personal Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'First Name',
                      controller: firstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      capitalization: TextCapitalization.words,
                      validator: _validateName,
                    ),

                    SizedBox(height: 13),

                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: _buildTextField(
                            label: 'Middle Initial',
                            controller: middleInitialController,
                            hint: 'M.I. (Optional)',
                            capitalization:
                                TextCapitalization.characters,
                            validator: _validateMiddleInitial,
                            inputFormatters:
                                _middleInitialFormatters,
                          ),
                        ),

                        SizedBox(width: 12),

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

                    SizedBox(height: 13),

                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: _buildBirthdayField(),
                        ),

                        SizedBox(width: 12),

                        Expanded(
                          child: _buildSexField(),
                        ),
                      ],
                    ),

                    SizedBox(height: 25),

                    // CONTACT INFORMATION

                    _sectionTitle('Contact Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Email Address',
                      controller: emailController,
                      hint: 'juan@gmail.com',
                      icon: Icons.mail_outline,
                      keyboardType: TextInputType.emailAddress,
                      capitalization: TextCapitalization.none,
                      validator: _validateEmail,
                      inputFormatters: [
                        FilteringTextInputFormatter.deny(
                          RegExp(r'\s'),
                        ),
                      ],
                    ),

                    SizedBox(height: 6),

                    Text(
                      'Enter a valid email address, '
                      'example: juan@gmail.com',
                      style: TextStyle(
                        fontSize: 10,
                        color: RegisterStyle.textColor,
                      ),
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Mobile Number',
                      controller: phoneController,
                      hint: '09171234567 or 639171234567',
                      icon: Icons.phone_outlined,
                      keyboardType: TextInputType.number,
                      capitalization: TextCapitalization.none,
                      validator: _validatePhone,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(12),
                      ],
                    ),

                    SizedBox(height: 6),

                    Text(
                      'Enter 11 digits starting with 09 or '
                      '12 digits starting with 63.',
                      style: TextStyle(
                        fontSize: 10,
                        color: RegisterStyle.textColor,
                      ),
                    ),

                    SizedBox(height: 25),

                    // ADDRESS INFORMATION

                    _sectionTitle('Address Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Region',
                      controller: regionController,
                      hint: 'Enter region',
                      icon: Icons.map_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Province',
                      controller: provinceController,
                      hint: 'Enter province',
                      icon: Icons.location_city_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'City / Municipality',
                      controller: cityController,
                      hint: 'Enter city or municipality',
                      icon: Icons.location_city_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Barangay',
                      controller: barangayController,
                      hint: 'Enter barangay',
                      icon: Icons.place_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Street / House Number',
                      controller: streetController,
                      hint: 'House number, street, building',
                      icon: Icons.home_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 25),

                    // ACCOUNT SECURITY

                    _sectionTitle('Account Security'),

                    SizedBox(height: 13),

                    _buildPasswordField(),

                    SizedBox(height: 13),

                    _buildConfirmPasswordField(),

                    SizedBox(height: 12),

                    _buildPasswordRequirements(),

                    SizedBox(height: 20),

                    // TERMS AND CONDITIONS

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
                            visualDensity: VisualDensity.compact,
                            onChanged: isSubmitting
                                ? null
                                : (value) {
                                    setState(() {
                                      agreeTerms = value ?? false;
                                    });
                                  },
                          ),
                        ),

                        SizedBox(width: 9),

                        Expanded(
                          child: Text.rich(
                            TextSpan(
                              style: RegisterStyle.termsText,
                              children: [
                                TextSpan(
                                  text: 'I agree to Mobile Suki\'s ',
                                ),
                                TextSpan(
                                  text: 'Terms of Service',
                                  style: RegisterStyle.linkText,
                                ),
                                TextSpan(text: ' and '),
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

                    SizedBox(height: 22),

                    // CREATE ACCOUNT BUTTON

                    SizedBox(
                      width: double.infinity,
                      height: 49,

                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed:
                            isSubmitting ? null : registerBuyer,

                        child: isSubmitting
                            ? SizedBox(
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

                    SizedBox(height: 22),

                    // LOGIN LINK

                    Center(
                      child: Wrap(
                        alignment: WrapAlignment.center,
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

                    SizedBox(height: 18),

                    // BACK BUTTON

                    Center(
                      child: TextButton.icon(
                        onPressed: isSubmitting
                            ? null
                            : () {
                                Navigator.pop(context);
                              },

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

                    SizedBox(height: 20),
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
    return Text(
      title,
      style: RegisterStyle.sectionTitle,
    );
  }

  // =========================================================
  // NORMAL TEXT FIELD
  // =========================================================

  Widget _buildTextField({
    required String label,
    required TextEditingController controller,
    required String hint,
    IconData? icon,
    TextInputType? keyboardType,
    TextCapitalization capitalization =
        TextCapitalization.sentences,
    String? Function(String?)? validator,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        TextFormField(
          controller: controller,
          keyboardType: keyboardType ?? TextInputType.text,
          textCapitalization: capitalization,
          autocorrect: keyboardType != TextInputType.emailAddress,
          enabled: !isSubmitting,
          inputFormatters: inputFormatters,
          style: RegisterStyle.inputText,

          decoration: RegisterStyle.inputDecoration(
            hint: hint,
            icon: icon,
          ),

          validator: validator ??
              (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'This field is required';
                }

                return null;
              },
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
        Text(
          'Birthday',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        TextFormField(
          controller: birthdayController,
          readOnly: true,
          enabled: !isSubmitting,
          onTap: isSubmitting ? null : selectBirthday,
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
  // SEX FIELD
  // =========================================================

  Widget _buildSexField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Sex',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        DropdownButtonFormField<String>(
          value: selectedSex,
          isExpanded: true,
          decoration: RegisterStyle.dropdownDecoration(),

          hint: Text(
            'Select sex',
            style: RegisterStyle.hint,
          ),

          items: sexOptions.map((sex) {
            return DropdownMenuItem(
              value: sex,
              child: Text(
                sex,
                style: RegisterStyle.inputText,
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
            if (value == null) {
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
        Text(
          'Password',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

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
  // PASSWORD CHECKLIST ITEM
  // =========================================================

  Widget _passwordRule(
    String label,
    bool passed,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 5),

      child: Row(
        children: [
          Icon(
            passed
                ? Icons.check_circle
                : Icons.circle_outlined,
            size: 14,
            color: passed
                ? RegisterStyle.primary
                : RegisterStyle.mutedText,
          ),

          SizedBox(width: 7),

          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 10,
                color: passed
                    ? RegisterStyle.primary
                    : RegisterStyle.textColor,
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

    final hasMinimumLength = password.length >= 8;
    final hasUppercase = RegExp(r'[A-Z]').hasMatch(password);
    final hasLowercase = RegExp(r'[a-z]').hasMatch(password);
    final hasNumber = RegExp(r'[0-9]').hasMatch(password);
    final hasSpecial = RegExp(r'[^A-Za-z0-9]').hasMatch(password);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),

      decoration: BoxDecoration(
        color: RegisterStyle.selectedBackground,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
          color: RegisterStyle.borderColor,
        ),
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

          SizedBox(height: 9),

          _passwordRule(
            'At least 8 characters',
            hasMinimumLength,
          ),

          _passwordRule(
            'One uppercase letter (A-Z)',
            hasUppercase,
          ),

          _passwordRule(
            'One lowercase letter (a-z)',
            hasLowercase,
          ),

          _passwordRule(
            'One number (0-9)',
            hasNumber,
          ),

          _passwordRule(
            'One special character (!@#\$%)',
            hasSpecial,
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
        Text(
          'Confirm Password',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

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
                        obscureConfirmPassword =
                            !obscureConfirmPassword;
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

          validator: (value) {
            if (value == null || value.isEmpty) {
              return 'Please confirm your password';
            }

            if (value != passwordController.text) {
              return 'Passwords do not match';
            }

            return null;
          },
        ),
      ],
    );
  }
}
