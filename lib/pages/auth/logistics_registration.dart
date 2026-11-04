import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../services/api_services.dart';
import '../../styles/register_style.dart';

class LogisticsRegistration extends StatefulWidget {
  const LogisticsRegistration({super.key});

  @override
  State<LogisticsRegistration> createState() => _LogisticsRegistrationState();
}

class _LogisticsRegistrationState extends State<LogisticsRegistration> {
  // =========================================================
  // CONFIGURATION
  // =========================================================

  // Configure this only after checking Laravel routes/api.php.
  //
  // Example launch argument:
  // --dart-define=LOGISTICS_REGISTER_ENDPOINT=/logistics/register
  //
  // The example is NOT a confirmed backend route.
  static const String _registrationEndpoint = String.fromEnvironment(
    'LOGISTICS_REGISTER_ENDPOINT',
    defaultValue: '',
  );

  final _formKey = GlobalKey<FormState>();

  // =========================================================
  // COMPANY INFORMATION
  // =========================================================

  final companyNameController = TextEditingController();
  final companyEmailController = TextEditingController();
  final companyPhoneController = TextEditingController();

  // =========================================================
  // AUTHORIZED REPRESENTATIVE
  // =========================================================

  final representativeFirstNameController = TextEditingController();
  final representativeMiddleInitialController = TextEditingController();
  final representativeLastNameController = TextEditingController();
  final representativePositionController = TextEditingController();
  final representativePhoneController = TextEditingController();
  final representativeEmailController = TextEditingController();

  // =========================================================
  // COMPANY ADDRESS
  // =========================================================

  final regionController = TextEditingController();
  final provinceController = TextEditingController();
  final cityController = TextEditingController();
  final barangayController = TextEditingController();
  final streetController = TextEditingController();

  // =========================================================
  // PRIMARY HUB
  // =========================================================

  final hubNameController = TextEditingController();
  final hubRegionController = TextEditingController();
  final hubProvinceController = TextEditingController();
  final hubCityController = TextEditingController();
  final hubBarangayController = TextEditingController();
  final hubStreetController = TextEditingController();

  // =========================================================
  // ACCOUNT SECURITY
  // =========================================================

  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;
  bool agreeTerms = false;
  bool isSubmitting = false;

  // Existing sample coverage choices.
  // Replace or extend based on confirmed backend rules.
  final List<String> serviceAreaOptions = ['NCR', 'CALABARZON', 'Davao'];

  final List<String> selectedServiceAreas = [];

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

    companyNameController.dispose();
    companyEmailController.dispose();
    companyPhoneController.dispose();

    representativeFirstNameController.dispose();
    representativeMiddleInitialController.dispose();
    representativeLastNameController.dispose();
    representativePositionController.dispose();
    representativePhoneController.dispose();
    representativeEmailController.dispose();

    regionController.dispose();
    provinceController.dispose();
    cityController.dispose();
    barangayController.dispose();
    streetController.dispose();

    hubNameController.dispose();
    hubRegionController.dispose();
    hubProvinceController.dispose();
    hubCityController.dispose();
    hubBarangayController.dispose();
    hubStreetController.dispose();

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
  // VALIDATION
  // =========================================================

  String? _validateRequired(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }
    return null;
  }

  String? _validateCompanyName(String? value) {
    final name = value?.trim() ?? '';

    if (name.isEmpty) {
      return 'Company name is required';
    }

    if (name.length < 3) {
      return 'Enter at least 3 characters';
    }

    return null;
  }

  String? _validateName(String? value) {
    final name = value?.trim() ?? '';

    if (name.isEmpty) {
      return 'This field is required';
    }

    if (name.length < 2) {
      return 'Enter at least 2 characters';
    }

    // Support ordinary Filipino names, including Ñ.
    if (!RegExp(r"^[A-Za-zÀ-ÿÑñ\s.'-]+$").hasMatch(name)) {
      return 'Enter a valid name';
    }

    return null;
  }

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

  String? _validatePhone(String? value) {
    final phone = value?.trim() ?? '';

    if (phone.isEmpty) {
      return 'Contact number is required';
    }

    if (RegExp(r'^09\d{9}$').hasMatch(phone)) {
      return null;
    }

    if (RegExp(r'^639\d{9}$').hasMatch(phone)) {
      return null;
    }

    return 'Use 09XXXXXXXXX or 639XXXXXXXXX';
  }

  String _normalizePhone(String value) {
    final phone = value.trim();

    if (phone.startsWith('639')) {
      return '0${phone.substring(2)}';
    }

    return phone;
  }

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
  // BUILD REGISTRATION PAYLOAD
  // =========================================================

  Map<String, dynamic> _buildRegistrationPayload() {
    String value(TextEditingController controller) => controller.text.trim();

    return {
      'role': 'logistics',

      'company': {
        'company_name': value(companyNameController),
        'email': value(companyEmailController),
        'phone': _normalizePhone(companyPhoneController.text),
      },

      'authorized_representative': {
        'first_name': value(representativeFirstNameController),
        'middle_initial': value(representativeMiddleInitialController).isEmpty
            ? null
            : value(representativeMiddleInitialController).toUpperCase(),
        'last_name': value(representativeLastNameController),
        'position': value(representativePositionController),
        'phone': _normalizePhone(representativePhoneController.text),
        'email': value(representativeEmailController),
      },

      'company_address': {
        'region': value(regionController),
        'province': value(provinceController),
        'city': value(cityController),
        'barangay': value(barangayController),
        'street': value(streetController),
      },

      'service_areas': List<String>.from(selectedServiceAreas),

      'primary_hub': {
        'hub_name': value(hubNameController),
        'region': value(hubRegionController),
        'province': value(hubProvinceController),
        'city': value(hubCityController),
        'barangay': value(hubBarangayController),
        'street': value(hubStreetController),
      },

      'password': passwordController.text,
      'password_confirmation': confirmPasswordController.text,
    };
  }

  // =========================================================
  // REGISTER LOGISTICS
  // =========================================================

  Future<void> registerLogistics() async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    if (!(_formKey.currentState?.validate() ?? false)) {
      _showMessage('Please complete all required fields.');
      return;
    }

    if (selectedServiceAreas.isEmpty) {
      _showMessage('Please select at least one service area.');
      return;
    }

    if (!agreeTerms) {
      _showMessage(
        'Please agree to the Terms of Service '
        'and Privacy Policy.',
      );
      return;
    }

    // Do not assume an unverified backend endpoint.
    final endpoint = _registrationEndpoint.trim();

    if (endpoint.isEmpty) {
      _showMessage(
        'Logistics registration API is not configured. '
        'Confirm the Laravel endpoint first.',
      );
      return;
    }

    // Restrict the configurable endpoint to a relative
    // path under the existing Laravel API base URL.
    final parsedEndpoint = Uri.tryParse(endpoint);

    if (parsedEndpoint == null ||
        parsedEndpoint.hasScheme ||
        parsedEndpoint.hasAuthority ||
        parsedEndpoint.hasQuery ||
        parsedEndpoint.hasFragment ||
        endpoint.contains('\\') ||
        parsedEndpoint.pathSegments.contains('..') ||
        parsedEndpoint.pathSegments.isEmpty ||
        parsedEndpoint.pathSegments.last.isEmpty) {
      _showMessage('Invalid logistics registration API endpoint.');
      return;
    }

    setState(() {
      isSubmitting = true;
    });

    try {
      final payload = _buildRegistrationPayload();

      final dynamic response = await ApiServices.post(
        endpoint,
        payload,
      ).timeout(const Duration(seconds: 30));

      if (!mounted) return;

      if (response is! Map) {
        throw Exception('Invalid response from the Laravel server.');
      }

      // Use the same success contract as the
      // current AuthService and Laravel registration.
      if (response['success'] != true) {
        throw Exception(
          response['message']?.toString() ?? 'Registration was not confirmed.',
        );
      }

      final message =
          response['message']?.toString() ??
          'Company registration submitted successfully.';

      // Show a dialog so confirmation is visible
      // before navigating away from the form.
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) {
          return AlertDialog(
            title: const Text('Registration Submitted'),
            content: Text(
              '$message\n\n'
              'Your account may require approval '
              'before you can access logistics operations.',
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

      Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
    } on TimeoutException {
      _showMessage(
        'The request timed out. Check whether your '
        'registration was received before trying again.',
      );
    } catch (error) {
      if (!mounted) return;

      final message = error.toString().replaceFirst('Exception: ', '');

      _showMessage(
        message.isEmpty ? 'Unable to submit company registration.' : message,
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
  // SERVICE COVERAGE
  // =========================================================

  Widget _buildServiceAreaSelection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Service Coverage', style: RegisterStyle.label),
        const SizedBox(height: 6),
        Text(
          'Select the regions where your logistics company '
          'provides delivery and shipment services.',
          style: RegisterStyle.subtitle,
        ),
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: RegisterStyle.backgroundColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: RegisterStyle.borderColor),
          ),
          child: Column(
            children: serviceAreaOptions.map((area) {
              final selected = selectedServiceAreas.contains(area);

              return CheckboxListTile(
                value: selected,
                activeColor: RegisterStyle.primary,
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(area, style: RegisterStyle.inputText),
                controlAffinity: ListTileControlAffinity.leading,
                onChanged: isSubmitting
                    ? null
                    : (checked) {
                        setState(() {
                          if (checked == true) {
                            if (!selectedServiceAreas.contains(area)) {
                              selectedServiceAreas.add(area);
                            }
                          } else {
                            selectedServiceAreas.remove(area);
                          }
                        });
                      },
              );
            }).toList(),
          ),
        ),
      ],
    );
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
                    // HEADER
                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: RegisterStyle.logoContainer,
                        child: Icon(
                          Icons.business_rounded,
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
                      'LOGISTICS REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Register Your Company',
                      style: RegisterStyle.pageTitle,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Create a logistics company account '
                      'to manage hubs, shipments and '
                      'delivery operations.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // COMPANY INFORMATION
                    // =====================================
                    _sectionTitle('Company Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Company Name',
                      controller: companyNameController,
                      hint: 'Enter registered company name',
                      icon: Icons.business_outlined,
                      validator: _validateCompanyName,
                    ),

                    const SizedBox(height: 13),

                    _buildEmailField(
                      label: 'Company Email Address',
                      controller: companyEmailController,
                      hint: 'company@example.com',
                    ),

                    const SizedBox(height: 13),

                    _buildPhoneField(
                      label: 'Company Contact Number',
                      controller: companyPhoneController,
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // AUTHORIZED REPRESENTATIVE
                    // =====================================
                    _sectionTitle('Authorized Representative'),

                    const SizedBox(height: 7),

                    Text(
                      'Person responsible for managing '
                      'the company account.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'First Name',
                      controller: representativeFirstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Middle Initial',
                      controller: representativeMiddleInitialController,
                      hint: 'M.I. (Optional)',
                      capitalization: TextCapitalization.characters,
                      validator: _validateMiddleInitial,
                      inputFormatters: _middleInitialFormatters,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Last Name',
                      controller: representativeLastNameController,
                      hint: 'Enter last name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Position / Designation',
                      controller: representativePositionController,
                      hint: 'Example: Operations Manager',
                      icon: Icons.work_outline,
                    ),

                    const SizedBox(height: 13),

                    _buildPhoneField(
                      label: 'Representative Contact Number',
                      controller: representativePhoneController,
                    ),

                    const SizedBox(height: 13),

                    _buildEmailField(
                      label: 'Representative Email',
                      controller: representativeEmailController,
                      hint: 'representative@example.com',
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // COMPANY ADDRESS
                    // =====================================
                    _sectionTitle('Company Address'),

                    const SizedBox(height: 13),

                    _buildAddressFields(
                      region: regionController,
                      province: provinceController,
                      city: cityController,
                      barangay: barangayController,
                      street: streetController,
                    ),

                    const SizedBox(height: 25),

                    // =====================================
                    // SERVICE COVERAGE
                    // =====================================
                    _sectionTitle('Service Coverage'),

                    const SizedBox(height: 13),

                    _buildServiceAreaSelection(),

                    const SizedBox(height: 25),

                    // =====================================
                    // PRIMARY HUB
                    // =====================================
                    _sectionTitle('Primary Hub Information'),

                    const SizedBox(height: 7),

                    Text(
                      "Enter the details of your company's "
                      'main operating hub.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Name',
                      controller: hubNameController,
                      hint: 'Example: Company Main Hub',
                      icon: Icons.warehouse_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildAddressFields(
                      region: hubRegionController,
                      province: hubProvinceController,
                      city: hubCityController,
                      barangay: hubBarangayController,
                      street: hubStreetController,
                      prefix: 'Hub ',
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
                    // TERMS
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
                    // SUBMIT BUTTON
                    // =====================================
                    SizedBox(
                      width: double.infinity,
                      height: 49,
                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed: isSubmitting ? null : registerLogistics,
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
                                'SUBMIT COMPANY REGISTRATION',
                                style: RegisterStyle.buttonText,
                              ),
                      ),
                    ),

                    const SizedBox(height: 22),

                    // LOGIN LINK
                    Center(
                      child: Wrap(
                        alignment: WrapAlignment.center,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: [
                          Text(
                            'Already have an account? ',
                            style: RegisterStyle.bottomText,
                          ),
                          TextButton(
                            onPressed: isSubmitting
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

                    const SizedBox(height: 15),

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
  // REUSABLE UI HELPERS
  // =========================================================

  Widget _sectionTitle(String title) {
    return Text(title, style: RegisterStyle.sectionTitle);
  }

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
          enabled: !isSubmitting,
          keyboardType: keyboardType ?? TextInputType.text,
          textCapitalization: capitalization,
          inputFormatters: inputFormatters,
          autocorrect: keyboardType != TextInputType.emailAddress,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(hint: hint, icon: icon),
          validator: validator ?? _validateRequired,
        ),
      ],
    );
  }

  Widget _buildEmailField({
    required String label,
    required TextEditingController controller,
    required String hint,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildTextField(
          label: label,
          controller: controller,
          hint: hint,
          icon: Icons.mail_outline,
          keyboardType: TextInputType.emailAddress,
          capitalization: TextCapitalization.none,
          validator: _validateEmail,
          inputFormatters: [FilteringTextInputFormatter.deny(RegExp(r'\s'))],
        ),
        const SizedBox(height: 6),
        Text(
          'Enter a valid email address, example: juan@gmail.com',
          style: TextStyle(
            fontSize: 10,
            height: 1.5,
            color: RegisterStyle.textColor,
          ),
        ),
      ],
    );
  }

  Widget _buildPhoneField({
    required String label,
    required TextEditingController controller,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildTextField(
          label: label,
          controller: controller,
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
          '12 digits starting with 63. Numbers only.',
          style: TextStyle(
            fontSize: 10,
            height: 1.5,
            color: RegisterStyle.textColor,
          ),
        ),
      ],
    );
  }

  Widget _buildAddressFields({
    required TextEditingController region,
    required TextEditingController province,
    required TextEditingController city,
    required TextEditingController barangay,
    required TextEditingController street,
    String prefix = '',
  }) {
    return Column(
      children: [
        _buildTextField(
          label: '${prefix}Region',
          controller: region,
          hint: 'Enter region',
          icon: Icons.map_outlined,
        ),
        const SizedBox(height: 13),
        _buildTextField(
          label: '${prefix}Province',
          controller: province,
          hint: 'Enter province',
          icon: Icons.location_city_outlined,
        ),
        const SizedBox(height: 13),
        _buildTextField(
          label: '${prefix}City / Municipality',
          controller: city,
          hint: 'Enter city or municipality',
          icon: Icons.location_city_outlined,
        ),
        const SizedBox(height: 13),
        _buildTextField(
          label: '${prefix}Barangay',
          controller: barangay,
          hint: 'Enter barangay',
          icon: Icons.place_outlined,
        ),
        const SizedBox(height: 13),
        _buildTextField(
          label: '${prefix}Street / Building / House Number',
          controller: street,
          hint: 'Enter complete address',
          icon: Icons.home_outlined,
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
          enabled: !isSubmitting,
          obscureText: obscurePassword,
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

  Widget _passwordRule(String label, bool passed) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Icon(
            passed ? Icons.check_circle : Icons.circle_outlined,
            color: passed ? RegisterStyle.primary : RegisterStyle.mutedText,
            size: 15,
          ),
          const SizedBox(width: 8),
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
          const SizedBox(height: 10),
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
          enabled: !isSubmitting,
          obscureText: obscureConfirmPassword,
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
