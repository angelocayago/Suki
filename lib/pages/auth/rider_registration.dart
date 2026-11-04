
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';

import '../../styles/register_style.dart';

class RiderRegistration extends StatefulWidget {
  const RiderRegistration({super.key});

  @override
  State<RiderRegistration> createState() =>
      _RiderRegistrationState();
}

class _RiderRegistrationState extends State<RiderRegistration> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _imagePicker = ImagePicker();

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

  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  // =========================================================
  // ADDRESS INFORMATION
  // =========================================================

  final regionController = TextEditingController();
  final provinceController = TextEditingController();
  final cityController = TextEditingController();
  final barangayController = TextEditingController();
  final streetController = TextEditingController();

  // =========================================================
  // DRIVER'S LICENSE
  // =========================================================

  final licenseNumberController = TextEditingController();
  final licenseExpiryController = TextEditingController();

  // =========================================================
  // VEHICLE INFORMATION
  // =========================================================

  final vehicleModelController = TextEditingController();
  final plateNumberController = TextEditingController();

  // =========================================================
  // PASSWORD
  // =========================================================

  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  // =========================================================
  // SELECTIONS
  // =========================================================

  String? selectedSex;
  String? selectedVehicle;
  String? selectedLicenseType;
  String? selectedCompany;

  // =========================================================
  // DOCUMENTS
  // =========================================================

  Uint8List? licenseImageBytes;
  Uint8List? orImageBytes;
  Uint8List? crImageBytes;

  String? licenseFileName;
  String? orFileName;
  String? crFileName;

  // =========================================================
  // FORM STATES
  // =========================================================

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;
  bool agreeTerms = false;
  bool isSubmitting = false;

  static const int maxFileSize = 5 * 1024 * 1024;

  final List<String> sexOptions = [
    'Male',
    'Female',
    'Prefer not to say',
  ];

  final List<String> vehicleOptions = [
    'Motorcycle',
    'Van',
    'Car',
    'Tricycle',
    'Truck',
  ];

  final List<String> licenseTypes = [
    'Non-Professional',
    'Professional',
    'Student Permit',
  ];

  // Temporary test companies.
  // Replace with registered logistics companies from Laravel.
  final List<String> companyOptions = [
    'Bilis Hatid Express Inc.',
    'Agila Cargo Movers Corp.',
  ];

  // =========================================================
  // INIT STATE
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

    phoneController.dispose();
    emailController.dispose();

    regionController.dispose();
    provinceController.dispose();
    cityController.dispose();
    barangayController.dispose();
    streetController.dispose();

    licenseNumberController.dispose();
    licenseExpiryController.dispose();

    vehicleModelController.dispose();
    plateNumberController.dispose();

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
          backgroundColor:
              success ? RegisterStyle.primary : Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // =========================================================
  // DATE HELPERS
  // =========================================================

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

  DateTime? _parseDate(String value) {
    try {
      final parts = value.split('/');

      if (parts.length != 3) {
        return null;
      }

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

  // =========================================================
  // BIRTHDAY PICKER
  // MINIMUM AGE: 18
  // CALENDAR OPENS AT LATEST ELIGIBLE BIRTH YEAR
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
    final firstDate = DateTime(1950);

    final DateTime? selectedDate = await showDatePicker(
      context: context,
      initialDate: latestDate,
      firstDate: firstDate,
      lastDate: latestDate,
      initialDatePickerMode: DatePickerMode.year,
      helpText: 'SELECT YOUR BIRTHDAY',
    );

    if (selectedDate != null && mounted) {
      setState(() {
        birthdayController.text =
            _formatDisplayDate(selectedDate);
      });
    }
  }

  String? _validateBirthday(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Birthday is required';
    }

    final birthday = _parseDate(value);

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
  // LICENSE EXPIRATION DATE
  // =========================================================

  Future<void> selectLicenseExpiry() async {
    final now = DateTime.now();

    final firstDate = DateTime(
      now.year,
      now.month,
      now.day,
    );

    final lastDate = DateTime(now.year + 30, 12, 31);

    final DateTime? selectedDate = await showDatePicker(
      context: context,
      initialDate: firstDate,
      firstDate: firstDate,
      lastDate: lastDate,
      helpText: 'SELECT LICENSE EXPIRATION DATE',
    );

    if (selectedDate != null && mounted) {
      setState(() {
        licenseExpiryController.text =
            _formatDisplayDate(selectedDate);
      });
    }
  }

  String? _validateLicenseExpiry(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Expiration date is required';
    }

    final expiry = _parseDate(value);

    if (expiry == null) {
      return 'Invalid expiration date';
    }

    final now = DateTime.now();

    final today = DateTime(
      now.year,
      now.month,
      now.day,
    );

    if (expiry.isBefore(today)) {
      return 'Driver license has expired';
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
  // MIDDLE INITIAL
  // ONE LETTER ONLY, UPPERCASE
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

 // =========================================================
// STRICT EMAIL ADDRESS VALIDATION
// =========================================================

String? _validateEmail(String? value) {
  if (value == null || value.trim().isEmpty) {
    return 'Email is required';
  }

  final email = value.trim();

  if (email.contains(' ')) {
    return 'Spaces are not allowed in email';
  }

  final regex = RegExp(
    r'^[A-Za-z0-9]+(?:[._%+-][A-Za-z0-9]+)*@'
    r'(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+'
    r'[A-Za-z]{2,}$',
  );

  if (!regex.hasMatch(email)) {
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
  // VALID:
  // 09171234567   (11 DIGITS)
  // 639171234567  (12 DIGITS)
  //
  // INVALID:
  // LETTERS, SPECIAL CHARACTERS, WRONG LENGTH
  // =========================================================

  String? _validatePhone(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Mobile number is required';
    }

    final phone = value.trim();

    if (!RegExp(r'^\d+$').hasMatch(phone)) {
      return 'Numbers only are allowed';
    }

    if (phone.startsWith('09')) {
      if (phone.length != 11) {
        return '09 mobile number must have 11 digits';
      }

      if (!RegExp(r'^09\d{9}$').hasMatch(phone)) {
        return 'Invalid mobile number';
      }

      return null;
    }

    if (phone.startsWith('63')) {
      if (phone.length != 12) {
        return '63 mobile number must have 12 digits';
      }

      if (!RegExp(r'^639\d{9}$').hasMatch(phone)) {
        return 'Use 639XXXXXXXXX format';
      }

      return null;
    }

    return 'Mobile number must start with 09 or 63';
  }

  // =========================================================
  // NORMALIZE MOBILE NUMBER FOR API
  //
  // 639171234567 -> 09171234567
  // 09171234567  -> 09171234567
  // =========================================================

  String _normalizePhone(String value) {
    final phone = value.trim();

    if (phone.startsWith('63')) {
      return '0${phone.substring(2)}';
    }

    return phone;
  }

  // =========================================================
  // PLATE NUMBER VALIDATION
  // =========================================================

  String? _validatePlateNumber(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Plate number is required';
    }

    if (!RegExp(r'^[A-Za-z0-9 -]+$')
        .hasMatch(value.trim())) {
      return 'Invalid plate number';
    }

    return null;
  }

  // =========================================================
  // STRONG PASSWORD VALIDATION
  // SAME AS BUYER REGISTRATION
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
  // IMAGE VALIDATION
  // JPG / PNG ONLY
  // =========================================================

  bool _isValidImage(Uint8List bytes) {
    if (bytes.length < 8) {
      return false;
    }

    final isJpeg =
        bytes[0] == 0xFF &&
        bytes[1] == 0xD8 &&
        bytes[2] == 0xFF;

    final isPng =
        bytes[0] == 0x89 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x4E &&
        bytes[3] == 0x47 &&
        bytes[4] == 0x0D &&
        bytes[5] == 0x0A &&
        bytes[6] == 0x1A &&
        bytes[7] == 0x0A;

    return isJpeg || isPng;
  }

  // =========================================================
  // PDF VALIDATION
  // =========================================================

  bool _isValidPdf(Uint8List bytes) {
    if (bytes.length < 5) {
      return false;
    }

    return bytes[0] == 0x25 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x44 &&
        bytes[3] == 0x46 &&
        bytes[4] == 0x2D;
  }

  // =========================================================
  // DRIVER'S LICENSE IMAGE PICKER
  // =========================================================

  Future<void> pickLicenseImage(ImageSource source) async {
    try {
      final XFile? file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
      );

      if (file == null) return;

      final bytes = await file.readAsBytes();

      if (bytes.length > maxFileSize) {
        _showMessage(
          'File exceeds 5 MB. Please select a smaller image.',
        );
        return;
      }

      if (!_isValidImage(bytes)) {
        _showMessage(
          'Invalid image. Only JPG and PNG files are accepted.',
        );
        return;
      }

      if (!mounted) return;

      setState(() {
        licenseImageBytes = bytes;
        licenseFileName = file.name;
      });

      _showMessage(
        'Driver license image attached.',
        success: true,
      );
    } catch (_) {
      _showMessage(
        'Unable to access camera or gallery. Check app permissions.',
      );
    }
  }

  // =========================================================
  // LICENSE IMAGE SOURCE
  // =========================================================

  Future<void> showLicensePicker() async {
    if (isSubmitting) return;

    await showModalBottomSheet(
      context: context,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(20),
        ),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Upload Driver\'s License',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                  ),
                ),

                SizedBox(height: 15),

                ListTile(
                  leading: Icon(
                    Icons.camera_alt_outlined,
                    color: RegisterStyle.primary,
                  ),
                  title: Text('Take Photo'),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    pickLicenseImage(ImageSource.camera);
                  },
                ),

                ListTile(
                  leading: Icon(
                    Icons.photo_library_outlined,
                    color: RegisterStyle.primary,
                  ),
                  title: Text('Choose from Gallery'),
                  onTap: () {
                    Navigator.pop(sheetContext);
                    pickLicenseImage(ImageSource.gallery);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // =========================================================
  // OR / CR PDF PICKER
  // =========================================================

  Future<void> pickVehicleDocument(
    String documentType,
  ) async {
    if (isSubmitting) return;

    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf'],
        withData: true,
      );

      if (result == null || result.files.isEmpty) {
        return;
      }

      final file = result.files.single;
      final bytes = file.bytes;

      if (bytes == null) {
        _showMessage('Unable to read selected file.');
        return;
      }

      if (bytes.length > maxFileSize) {
        _showMessage('PDF must not exceed 5 MB.');
        return;
      }

      if (!_isValidPdf(bytes)) {
        _showMessage('Invalid PDF document.');
        return;
      }

      if (!mounted) return;

      setState(() {
        if (documentType == 'OR') {
          orImageBytes = bytes;
          orFileName = file.name;
        } else {
          crImageBytes = bytes;
          crFileName = file.name;
        }
      });

      _showMessage(
        '$documentType document attached.',
        success: true,
      );
    } catch (_) {
      _showMessage(
        'Unable to access Files. Please check permissions.',
      );
    }
  }

  // =========================================================
  // REQUIRED DOCUMENTS
  // =========================================================

  bool _validateDocuments() {
    if (licenseImageBytes == null) {
      _showMessage(
        'Please upload your Driver\'s License.',
      );
      return false;
    }

    if (orImageBytes == null) {
      _showMessage(
        'Please upload your vehicle OR.',
      );
      return false;
    }

    if (crImageBytes == null) {
      _showMessage(
        'Please upload your vehicle CR.',
      );
      return false;
    }

    return true;
  }

  // =========================================================
  // RIDER REGISTRATION SUBMISSION
  // =========================================================

  void registerRider() {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (!_validateDocuments()) {
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

    final birthday = _parseDate(
      birthdayController.text,
    );

    final licenseExpiry = _parseDate(
      licenseExpiryController.text,
    );

    if (birthday == null || licenseExpiry == null) {
      _showMessage('Please check your dates.');
      return;
    }

    // Prepare actual data entered by the rider.
    // This is not yet sent to Laravel.

    final riderData = <String, dynamic>{
      'role': 'rider',

      'first_name': firstNameController.text.trim(),

      'middle_initial':
          middleInitialController.text.trim().isEmpty
              ? null
              : middleInitialController.text.trim().toUpperCase(),

      'last_name': lastNameController.text.trim(),

      'birthday': _formatApiDate(birthday),
      'sex': selectedSex,

      'email': emailController.text.trim(),

      // Convert 63 prefix into 09 format before API submission.
      'phone': _normalizePhone(phoneController.text),

      'region': regionController.text.trim(),
      'province': provinceController.text.trim(),
      'city': cityController.text.trim(),
      'barangay': barangayController.text.trim(),
      'street': streetController.text.trim(),

      'license_number':
          licenseNumberController.text.trim().toUpperCase(),

      'license_type': selectedLicenseType,
      'license_expiry': _formatApiDate(licenseExpiry),

      'vehicle_type': selectedVehicle,
      'vehicle_model': vehicleModelController.text.trim(),

      'plate_number':
          plateNumberController.text.trim().toUpperCase(),

      'logistics_company': selectedCompany,

      'password': passwordController.text,
      'password_confirmation':
          confirmPasswordController.text,
    };

    // TODO:
    // Connect riderData to Laravel using multipart/form-data.
    // Upload licenseImageBytes, orImageBytes and crImageBytes.
    // Replace sample company names with registered company IDs.
    // Never print passwords or identity document data.

    _showMessage(
      'Rider form validated. Laravel API registration is not yet connected.',
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
                    // =========================================
                    // HEADER
                    // =========================================

                    Center(
                      child: Container(
                        width: 64,
                        height: 64,
                        decoration: RegisterStyle.logoContainer,

                        child: Icon(
                          Icons.delivery_dining,
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
                      'RIDER REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Create Rider Account',
                      style: RegisterStyle.pageTitle,
                    ),

                    SizedBox(height: 5),

                    Text(
                      'Register as a delivery rider and join '
                      'the Mobile Suki delivery network.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // PERSONAL INFORMATION
                    // =========================================

                    _sectionTitle('Personal Information'),

                    SizedBox(height: 13),

                    _buildTextField(
  label: 'Email Address',
  controller: emailController,
  hint: 'you@example.com',
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

                    SizedBox(height: 13),

                    // MIDDLE INITIAL:
                    // ONE LETTER, AUTO UPPERCASE

                    _buildTextField(
                      label: 'Middle Initial',
                      controller: middleInitialController,
                      hint: 'M.I. (Optional)',
                      capitalization:
                          TextCapitalization.characters,
                      validator: _validateMiddleInitial,

                      inputFormatters: [
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
                            );
                          },
                        ),
                      ],
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Last Name',
                      controller: lastNameController,
                      hint: 'Enter last name',
                      icon: Icons.person_outline,
                      capitalization: TextCapitalization.words,
                      validator: _validateName,
                    ),

                    SizedBox(height: 13),

                    _buildBirthdayField(),

                    SizedBox(height: 13),

                    _buildDropdown(
                      label: 'Sex',
                      value: selectedSex,
                      options: sexOptions,
                      hint: 'Select sex',
                      onChanged: (value) {
                        setState(() {
                          selectedSex = value;
                        });
                      },
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // CONTACT INFORMATION
                    // =========================================

                    _sectionTitle('Contact Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Email Address',
                      controller: emailController,
                      hint: 'you@example.com',
                      icon: Icons.mail_outline,
                      keyboardType: TextInputType.emailAddress,
                      capitalization: TextCapitalization.none,
                      validator: _validateEmail,
                    ),

                    SizedBox(height: 13),

                    // MOBILE NUMBER
                    // 09 = 11 DIGITS
                    // 63 = 12 DIGITS
                    // NO LETTERS OR SPECIAL CHARACTERS

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

                    // =========================================
                    // ADDRESS INFORMATION
                    // =========================================

                    _sectionTitle('Address Information'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Region',
                      controller: regionController,
                      hint: 'Enter region',
                      icon: Icons.map_outlined,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Province',
                      controller: provinceController,
                      hint: 'Enter province',
                      icon: Icons.location_city_outlined,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'City / Municipality',
                      controller: cityController,
                      hint: 'Enter city or municipality',
                      icon: Icons.location_city_outlined,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Barangay',
                      controller: barangayController,
                      hint: 'Enter barangay',
                      icon: Icons.place_outlined,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Street / House Number',
                      controller: streetController,
                      hint: 'Enter complete address',
                      icon: Icons.home_outlined,
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // DRIVER'S LICENSE
                    // =========================================

                    _sectionTitle('Driver\'s License'),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'License Number',
                      controller: licenseNumberController,
                      hint: 'Example: N01-23-456789',
                      icon: Icons.badge_outlined,
                      capitalization:
                          TextCapitalization.characters,
                    ),

                    SizedBox(height: 13),

                    _buildDropdown(
                      label: 'License Type',
                      value: selectedLicenseType,
                      options: licenseTypes,
                      hint: 'Select license type',
                      onChanged: (value) {
                        setState(() {
                          selectedLicenseType = value;
                        });
                      },
                    ),

                    SizedBox(height: 13),

                    _buildLicenseExpiryField(),

                    SizedBox(height: 13),

                    _buildUploadCard(
                      title: 'Driver\'s License Image',
                      subtitle: 'JPG or PNG - Maximum 5 MB',
                      fileName: licenseFileName,
                      icon: Icons.credit_card_outlined,
                      onTap: showLicensePicker,
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // VEHICLE INFORMATION
                    // =========================================

                    _sectionTitle('Vehicle Information'),

                    SizedBox(height: 13),

                    _buildDropdown(
                      label: 'Vehicle Type',
                      value: selectedVehicle,
                      options: vehicleOptions,
                      hint: 'Select vehicle type',
                      onChanged: (value) {
                        setState(() {
                          selectedVehicle = value;
                        });
                      },
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Vehicle Model',
                      controller: vehicleModelController,
                      hint: 'Example: Honda Click 125i',
                      icon: Icons.motorcycle_outlined,
                    ),

                    SizedBox(height: 13),

                    _buildTextField(
                      label: 'Plate Number',
                      controller: plateNumberController,
                      hint: 'Example: 123ABC',
                      icon: Icons.confirmation_number_outlined,
                      capitalization:
                          TextCapitalization.characters,
                      validator: _validatePlateNumber,
                    ),

                    SizedBox(height: 15),

                    Text(
                      'Vehicle Documents',
                      style: RegisterStyle.label,
                    ),

                    SizedBox(height: 10),

                    _buildUploadCard(
                      title: 'Official Receipt (OR)',
                      subtitle: 'PDF only - Maximum 5 MB',
                      fileName: orFileName,
                      icon: Icons.description_outlined,
                      onTap: () => pickVehicleDocument('OR'),
                    ),

                    SizedBox(height: 10),

                    _buildUploadCard(
                      title: 'Certificate of Registration (CR)',
                      subtitle: 'PDF only - Maximum 5 MB',
                      fileName: crFileName,
                      icon: Icons.file_present_outlined,
                      onTap: () => pickVehicleDocument('CR'),
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // LOGISTICS COMPANY
                    // =========================================

                    _sectionTitle('Logistics Company'),

                    SizedBox(height: 7),

                    Text(
                      'Choose the logistics company you want '
                      'to be affiliated with. Your request '
                      'will require verification.',
                      style: RegisterStyle.subtitle,
                    ),

                    SizedBox(height: 13),

                    _buildDropdown(
                      label: 'Logistics Company',
                      value: selectedCompany,
                      options: companyOptions,
                      hint: 'Select logistics company',
                      onChanged: (value) {
                        setState(() {
                          selectedCompany = value;
                        });
                      },
                    ),

                    SizedBox(height: 10),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),

                      decoration: BoxDecoration(
                        color: RegisterStyle.selectedBackground,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(
                          color: RegisterStyle.borderColor,
                        ),
                      ),

                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.info_outline,
                            size: 18,
                            color: RegisterStyle.primary,
                          ),

                          SizedBox(width: 9),

                          Expanded(
                            child: Text(
                              'Your company affiliation and rider '
                              'documents will be reviewed before '
                              'your account is approved.',
                              style: TextStyle(
                                fontSize: 10,
                                height: 1.5,
                                color: RegisterStyle.textColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // ACCOUNT SECURITY
                    // =========================================

                    _sectionTitle('Account Security'),

                    SizedBox(height: 13),

                    _buildPasswordField(),

                    SizedBox(height: 13),

                    _buildConfirmPasswordField(),

                    SizedBox(height: 12),

                    _buildPasswordRequirements(),

                    SizedBox(height: 20),

                    // =========================================
                    // TERMS AND CONDITIONS
                    // =========================================

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

                    // =========================================
                    // SUBMIT BUTTON
                    // =========================================

                    SizedBox(
                      width: double.infinity,
                      height: 49,

                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed:
                            isSubmitting ? null : registerRider,

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
                                'SUBMIT RIDER REGISTRATION',
                                style: RegisterStyle.buttonText,
                              ),
                      ),
                    ),

                    SizedBox(height: 20),

                    // =========================================
                    // LOGIN LINK
                    // =========================================

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

                    // =========================================
                    // BACK BUTTON
                    // =========================================

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
  // REUSABLE TEXT FIELD
  // =========================================================

  Widget _buildTextField({
    required String label,
    required TextEditingController controller,
    required String hint,

    IconData? icon,
    TextInputType? keyboardType,

    TextCapitalization capitalization =
        TextCapitalization.words,

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
  // LICENSE EXPIRATION FIELD
  // =========================================================

  Widget _buildLicenseExpiryField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          'License Expiration Date',
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        TextFormField(
          controller: licenseExpiryController,
          readOnly: true,
          enabled: !isSubmitting,

          onTap: isSubmitting ? null : selectLicenseExpiry,

          style: RegisterStyle.inputText,

          decoration: RegisterStyle.inputDecoration(
            hint: 'mm/dd/yyyy',

            suffixIcon: Icon(
              Icons.calendar_month_outlined,
              size: 18,
              color: RegisterStyle.iconColor,
            ),
          ),

          validator: _validateLicenseExpiry,
        ),
      ],
    );
  }

  // =========================================================
  // REUSABLE DROPDOWN FIELD
  // =========================================================

  Widget _buildDropdown({
    required String label,
    required String? value,
    required List<String> options,
    required String hint,
    required ValueChanged<String?> onChanged,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          label,
          style: RegisterStyle.label,
        ),

        SizedBox(height: 6),

        DropdownButtonFormField<String>(
          value: value,
          isExpanded: true,

          decoration: RegisterStyle.dropdownDecoration(),

          hint: Text(
            hint,
            style: RegisterStyle.hint,
          ),

          items: options.map((option) {
            return DropdownMenuItem<String>(
              value: option,

              child: Text(
                option,
                style: RegisterStyle.inputText,
                overflow: TextOverflow.ellipsis,
              ),
            );
          }).toList(),

          onChanged: isSubmitting ? null : onChanged,

          validator: (selectedValue) {
            if (selectedValue == null ||
                selectedValue.isEmpty) {
              return 'This field is required';
            }

            return null;
          },
        ),
      ],
    );
  }

  // =========================================================
  // DOCUMENT UPLOAD CARD
  // =========================================================

  Widget _buildUploadCard({
    required String title,
    required String subtitle,
    required String? fileName,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    final uploaded = fileName != null;

    return InkWell(
      onTap: isSubmitting ? null : onTap,
      borderRadius: BorderRadius.circular(10),

      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),

        decoration: BoxDecoration(
          color: uploaded
              ? RegisterStyle.selectedBackground
              : RegisterStyle.backgroundColor,

          borderRadius: BorderRadius.circular(10),

          border: Border.all(
            color: uploaded
                ? RegisterStyle.primary
                : RegisterStyle.borderColor,
          ),
        ),

        child: Row(
          children: [
            Icon(
              icon,
              size: 27,
              color: RegisterStyle.primary,
            ),

            SizedBox(width: 12),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Text(
                    title,

                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: RegisterStyle.titleColor,
                    ),
                  ),

                  SizedBox(height: 4),

                  Text(
                    fileName ?? subtitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,

                    style: TextStyle(
                      fontSize: 10,
                      color: uploaded
                          ? RegisterStyle.primary
                          : RegisterStyle.textColor,
                    ),
                  ),
                ],
              ),
            ),

            SizedBox(width: 8),

            Icon(
              uploaded
                  ? Icons.check_circle
                  : Icons.upload_file_outlined,

              size: 21,

              color: uploaded
                  ? RegisterStyle.primary
                  : RegisterStyle.mutedText,
            ),
          ],
        ),
      ),
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
    final hasUppercase = RegExp(r'[A-Z]').hasMatch(password);
    final hasLowercase = RegExp(r'[a-z]').hasMatch(password);
    final hasNumber = RegExp(r'[0-9]').hasMatch(password);
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
