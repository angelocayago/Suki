
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../styles/register_style.dart';

class LogisticsRegistration extends StatefulWidget {
  const LogisticsRegistration({super.key});

  @override
  State<LogisticsRegistration> createState() =>
      _LogisticsRegistrationState();
}

class _LogisticsRegistrationState
    extends State<LogisticsRegistration> {
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

  final representativeFirstNameController =
      TextEditingController();

  final representativeMiddleInitialController =
      TextEditingController();

  final representativeLastNameController =
      TextEditingController();

  final representativePositionController =
      TextEditingController();

  final representativePhoneController =
      TextEditingController();

  final representativeEmailController =
      TextEditingController();

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

  final List<String> serviceAreaOptions = [
    'NCR',
    'CALABARZON',
    'Davao',
  ];

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
  // GENERAL VALIDATION
  // =========================================================

  String? _validateRequired(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    return null;
  }

  String? _validateCompanyName(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Company name is required';
    }

    if (value.trim().length < 3) {
      return 'Enter at least 3 characters';
    }

    return null;
  }

  String? _validateName(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    if (value.trim().length < 2) {
      return 'Enter at least 2 characters';
    }

    if (RegExp(r'\d').hasMatch(value)) {
      return 'Numbers are not allowed';
    }

    return null;
  }

  // =========================================================
  // MIDDLE INITIAL VALIDATION
  // ONE LETTER ONLY - UPPERCASE
  // =========================================================

  String? _validateMiddleInitial(String? value) {
    if (value == null || value.isEmpty) {
      return null;
    }

    if (!RegExp(r'^[A-Z]$').hasMatch(value)) {
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

          return newValue.copyWith(
            text: uppercaseText,
            selection: newValue.selection,
            composing: TextRange.empty,
          );
        },
      ),
    ];
  }

  // =========================================================
  // STRICT EMAIL VALIDATION
  //
  // VALID:
  // juan@gmail.com
  // juan123@yahoo.com
  // juan.delacruz@gmail.com
  //
  // INVALID:
  // @123gmail.com
  // @.com
  // gmail.com
  // .com
  // juan@.com
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
  // CONTACT NUMBER VALIDATION
  //
  // 09 = 11 DIGITS
  // 63 = 12 DIGITS
  // NUMBERS ONLY
  // =========================================================

  String? _validatePhone(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Contact number is required';
    }

    final phone = value.trim();

    if (!RegExp(r'^\d+$').hasMatch(phone)) {
      return 'Numbers only are allowed';
    }

    if (phone.startsWith('09')) {
      if (phone.length != 11) {
        return '09 number must contain 11 digits';
      }

      if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
        return 'Invalid 09 mobile number';
      }

      return null;
    }

    if (phone.startsWith('63')) {
      if (phone.length != 12) {
        return '63 number must contain 12 digits';
      }

      if (!RegExp(r'^639\d{9}$').hasMatch(phone)) {
        return 'Use 639XXXXXXXXX format';
      }

      return null;
    }

    return 'Contact number must start with 09 or 63';
  }

  // =========================================================
  // NORMALIZE PHONE NUMBER FOR API
  // 639171234567 -> 09171234567
  // =========================================================

  String _normalizePhone(String value) {
    final phone = value.trim();

    if (phone.startsWith('639')) {
      return '0${phone.substring(2)}';
    }

    return phone;
  }

  // =========================================================
  // STRONG PASSWORD VALIDATION
  // SAME AS RIDER REGISTRATION
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
  // SERVICE COVERAGE SELECTION
  // =========================================================

  Widget _buildServiceAreaSelection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Service Coverage',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        Text(
          'Select the regions where your logistics company '
          'provides delivery and shipment services.',
          style: RegisterStyle.subtitle,
        ),

        SizedBox(height: 10),

        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(
            horizontal: 12,
            vertical: 8,
          ),
          decoration: BoxDecoration(
            color: RegisterStyle.backgroundColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: RegisterStyle.borderColor,
            ),
          ),
          child: Column(
            children: serviceAreaOptions.map((area) {
              final selected =
                  selectedServiceAreas.contains(area);

              return CheckboxListTile(
                value: selected,
                activeColor: RegisterStyle.primary,
                contentPadding: EdgeInsets.zero,
                dense: true,
                title: Text(
                  area,
                  style: RegisterStyle.inputText,
                ),
                controlAffinity:
                    ListTileControlAffinity.leading,
                onChanged: isSubmitting
                    ? null
                    : (value) {
                        setState(() {
                          if (value == true) {
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
  // LOGISTICS REGISTRATION
  // =========================================================

  void registerLogistics() {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (selectedServiceAreas.isEmpty) {
      _showMessage(
        'Please select at least one service area.',
      );
      return;
    }

    if (!agreeTerms) {
      _showMessage(
        'Please agree to the Terms of Service and Privacy Policy.',
      );
      return;
    }

    if (passwordController.text !=
        confirmPasswordController.text) {
      _showMessage('Passwords do not match.');
      return;
    }

    final logisticsData = <String, dynamic>{
      'role': 'logistics',

      'company': {
        'company_name': companyNameController.text.trim(),
        'email': companyEmailController.text.trim(),
        'phone': _normalizePhone(
          companyPhoneController.text,
        ),
      },

      'authorized_representative': {
        'first_name':
            representativeFirstNameController.text.trim(),

        'middle_initial':
            representativeMiddleInitialController.text.trim().isEmpty
                ? null
                : representativeMiddleInitialController
                    .text.trim().toUpperCase(),

        'last_name':
            representativeLastNameController.text.trim(),

        'position':
            representativePositionController.text.trim(),

        'phone': _normalizePhone(
          representativePhoneController.text,
        ),

        'email':
            representativeEmailController.text.trim(),
      },

      'company_address': {
        'region': regionController.text.trim(),
        'province': provinceController.text.trim(),
        'city': cityController.text.trim(),
        'barangay': barangayController.text.trim(),
        'street': streetController.text.trim(),
      },

      'service_areas': List<String>.from(
        selectedServiceAreas,
      ),

      'primary_hub': {
        'hub_name': hubNameController.text.trim(),
        'region': hubRegionController.text.trim(),
        'province': hubProvinceController.text.trim(),
        'city': hubCityController.text.trim(),
        'barangay': hubBarangayController.text.trim(),
        'street': hubStreetController.text.trim(),
      },

      'password': passwordController.text,
      'password_confirmation':
          confirmPasswordController.text,
    };

    // TODO:
    // Connect logisticsData to the Laravel registration API.
    // Do not log passwords or sensitive account information.

    _showMessage(
      'Logistics form validated. Laravel API registration '
      'is not yet connected.',
    );
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
                          Icons.business_rounded,
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
                      'LOGISTICS REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Register Your Company',
                      style: RegisterStyle.pageTitle,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Create a logistics company account '
                      'to manage hubs, shipments and '
                      'delivery operations.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 25),

                    // COMPANY INFORMATION

                    _sectionTitle('Company Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Company Name',
                      controller: companyNameController,
                      hint: 'Enter registered company name',
                      icon: Icons.business_outlined,
                      capitalization: TextCapitalization.words,
                      validator: _validateCompanyName,
                    ),

                    SizedBox(height: 13),

                    // COMPANY EMAIL ADDRESS

                    _buildTextField(
                      label: 'Company Email Address',
                      controller: companyEmailController,
                      hint: 'company@example.com',
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

                    _emailInstruction(),

                    SizedBox(height: 13),

                    // COMPANY CONTACT NUMBER

                    _buildTextField(
                      label: 'Company Contact Number',
                      controller: companyPhoneController,
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

                    _buildPhoneInstruction(),

                    SizedBox(height: 25),

                    // AUTHORIZED REPRESENTATIVE

                    _sectionTitle('Authorized Representative'),

                    SizedBox(height: 7),

                    Text(
                      'Person responsible for managing '
                      'the company account.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'First Name',
                      controller: representativeFirstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      capitalization: TextCapitalization.words,
                      validator: _validateName,
                    ),

                    SizedBox(height: 13),

                    // MIDDLE INITIAL

                    _buildTextField(
                      label: 'Middle Initial',
                      controller:
                          representativeMiddleInitialController,
                      hint: 'M.I. (Optional)',
                      capitalization:
                          TextCapitalization.characters,
                      validator: _validateMiddleInitial,
                      inputFormatters: _middleInitialFormatters,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Last Name',
                      controller: representativeLastNameController,
                      hint: 'Enter last name',
                      icon: Icons.person_outline,
                      capitalization: TextCapitalization.words,
                      validator: _validateName,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Position / Designation',
                      controller: representativePositionController,
                      hint: 'Example: Operations Manager',
                      icon: Icons.work_outline,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    // REPRESENTATIVE CONTACT NUMBER

                    _buildTextField(
                      label: 'Representative Contact Number',
                      controller: representativePhoneController,
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

                    _buildPhoneInstruction(),

                    SizedBox(height: 13),

                    // REPRESENTATIVE EMAIL ADDRESS

                    _buildTextField(
                      label: 'Representative Email',
                      controller: representativeEmailController,
                      hint: 'representative@example.com',
                      icon: Icons.email_outlined,
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

                    _emailInstruction(),

                    SizedBox(height: 25),

                    // COMPANY ADDRESS

                    _sectionTitle('Company Address'),

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
                      label: 'Street / Building / House Number',
                      controller: streetController,
                      hint: 'Enter complete address',
                      icon: Icons.home_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 25),

                    // SERVICE COVERAGE

                    _sectionTitle('Service Coverage'),

                    SizedBox(height: 13),

                    _buildServiceAreaSelection(),

                    SizedBox(height: 25),

                    // PRIMARY HUB

                    _sectionTitle('Primary Hub Information'),

                    SizedBox(height: 7),

                    Text(
                      'Enter the details of your company\'s '
                      'main operating hub.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Name',
                      controller: hubNameController,
                      hint: 'Example: Company Main Hub',
                      icon: Icons.warehouse_outlined,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Region',
                      controller: hubRegionController,
                      hint: 'Enter hub region',
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Province',
                      controller: hubProvinceController,
                      hint: 'Enter hub province',
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub City / Municipality',
                      controller: hubCityController,
                      hint: 'Enter hub city',
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Barangay',
                      controller: hubBarangayController,
                      hint: 'Enter hub barangay',
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Hub Street / Building Address',
                      controller: hubStreetController,
                      hint: 'Enter complete hub address',
                      icon: Icons.location_on_outlined,
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

                    // SUBMIT BUTTON

                    SizedBox(
                      width: double.infinity,
                      height: 49,
                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed:
                            isSubmitting ? null : registerLogistics,
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
                                'SUBMIT COMPANY REGISTRATION',
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

                    SizedBox(height: 15),

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
  // EMAIL INSTRUCTION
  // =========================================================

  Widget _emailInstruction() {
    return Text(
      'Enter a valid email address, example: juan@gmail.com',
      style: TextStyle(
        fontSize: 10,
        height: 1.5,
        color: RegisterStyle.textColor,
      ),
    );
  }

  // =========================================================
  // PHONE INSTRUCTION
  // =========================================================

  Widget _buildPhoneInstruction() {
    return Text(
      'Enter 11 digits starting with 09 or '
      '12 digits starting with 63. Numbers only.',
      style: TextStyle(
        fontSize: 10,
        height: 1.5,
        color: RegisterStyle.textColor,
      ),
    );
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
          enabled: !isSubmitting,
          keyboardType: keyboardType ?? TextInputType.text,
          textCapitalization: capitalization,
          inputFormatters: inputFormatters,
          autocorrect:
              keyboardType != TextInputType.emailAddress,
          style: RegisterStyle.inputText,
          decoration: RegisterStyle.inputDecoration(
            hint: hint,
            icon: icon,
          ),
          validator: validator ?? _validateRequired,
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

  // =========================================================
  // LIVE PASSWORD CHECKLIST ITEM
  // =========================================================

  Widget _passwordRule(
    String label,
    bool passed,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Icon(
            passed
                ? Icons.check_circle
                : Icons.circle_outlined,
            color: passed
                ? RegisterStyle.primary
                : RegisterStyle.mutedText,
            size: 15,
          ),

          SizedBox(width: 8),

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
    final hasUppercase =
        RegExp(r'[A-Z]').hasMatch(password);
    final hasLowercase =
        RegExp(r'[a-z]').hasMatch(password);
    final hasNumber =
        RegExp(r'[0-9]').hasMatch(password);
    final hasSpecial =
        RegExp(r'[^A-Za-z0-9]').hasMatch(password);

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

          SizedBox(height: 10),

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
