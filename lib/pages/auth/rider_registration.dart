import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../../services/api_services.dart';
import '../../styles/register_style.dart';

// ==========================================================
// SUKISHOP MOBILE - RIDER REGISTRATION
// ==========================================================
//
// Features:
// - Personal and contact information
// - Address information
// - Driver's license
// - Vehicle information
// - OR and CR document upload
// - Logistics company selection
// - Password validation
// - Multipart Laravel registration
// - Loading and error handling
//
// Backend endpoint must be configured explicitly.
//
// Example:
// --dart-define=RIDER_REGISTER_ENDPOINT=/rider/register
//
// This endpoint is an example only. Use the real
// endpoint defined in your Laravel API routes.
//
// ==========================================================

class RiderRegistration extends StatefulWidget {
  const RiderRegistration({super.key});

  @override
  State<RiderRegistration> createState() => _RiderRegistrationState();
}

class _RiderRegistrationState extends State<RiderRegistration> {
  final _formKey = GlobalKey<FormState>();
  final ImagePicker _imagePicker = ImagePicker();

  // ========================================================
  // API CONFIGURATION
  // ========================================================

  static const String riderRegisterEndpoint = String.fromEnvironment(
    'RIDER_REGISTER_ENDPOINT',
    defaultValue: '',
  );

  static const Duration requestTimeout = Duration(seconds: 60);

  static const int maxFileSize = 5 * 1024 * 1024;

  // ========================================================
  // PERSONAL INFORMATION
  // ========================================================

  final firstNameController = TextEditingController();
  final middleInitialController = TextEditingController();
  final lastNameController = TextEditingController();
  final birthdayController = TextEditingController();

  // ========================================================
  // CONTACT INFORMATION
  // ========================================================

  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  // ========================================================
  // ADDRESS INFORMATION
  // ========================================================

  final regionController = TextEditingController();
  final provinceController = TextEditingController();
  final cityController = TextEditingController();
  final barangayController = TextEditingController();
  final streetController = TextEditingController();

  // ========================================================
  // DRIVER'S LICENSE
  // ========================================================

  final licenseNumberController = TextEditingController();
  final licenseExpiryController = TextEditingController();

  // ========================================================
  // VEHICLE INFORMATION
  // ========================================================

  final vehicleModelController = TextEditingController();
  final plateNumberController = TextEditingController();

  // ========================================================
  // ACCOUNT SECURITY
  // ========================================================

  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  // ========================================================
  // SELECTIONS
  // ========================================================

  String? selectedSex;
  String? selectedVehicle;
  String? selectedLicenseType;
  String? selectedCompany;

  // ========================================================
  // DOCUMENTS
  // ========================================================

  Uint8List? licenseImageBytes;
  Uint8List? orImageBytes;
  Uint8List? crImageBytes;

  String? licenseFileName;
  String? orFileName;
  String? crFileName;

  // ========================================================
  // STATES
  // ========================================================

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;
  bool agreeTerms = false;
  bool isSubmitting = false;

  // ========================================================
  // DROPDOWN OPTIONS
  // ========================================================

  final List<String> sexOptions = ['Male', 'Female', 'Prefer not to say'];

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

  // Existing sample company names.
  // These are NOT verified database records.
  // Replace with actual Laravel company IDs later.

  final List<String> companyOptions = [
    'Bilis Hatid Express Inc.',
    'Agila Cargo Movers Corp.',
  ];

  // ========================================================
  // INITIALIZATION
  // ========================================================

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

  // ========================================================
  // DISPOSE
  // ========================================================

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

  // ========================================================
  // NOTIFICATION
  // ========================================================

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

  // ========================================================
  // DATE HELPERS
  // ========================================================

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
      final parts = value.trim().split('/');

      if (parts.length != 3) return null;

      final month = int.parse(parts[0]);
      final day = int.parse(parts[1]);
      final year = int.parse(parts[2]);

      final date = DateTime(year, month, day);

      if (date.year != year || date.month != month || date.day != day) {
        return null;
      }

      return date;
    } catch (_) {
      return null;
    }
  }

  DateTime get _latestAllowedBirthday {
    final now = DateTime.now();

    return DateTime(now.year - 18, now.month, now.day);
  }

  // ========================================================
  // BIRTHDAY PICKER
  // ========================================================

  Future<void> selectBirthday() async {
    if (isSubmitting) return;

    final latestDate = _latestAllowedBirthday;

    final selectedDate = await showDatePicker(
      context: context,
      initialDate: latestDate,
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

  // ========================================================
  // LICENSE EXPIRATION PICKER
  // ========================================================

  Future<void> selectLicenseExpiry() async {
    if (isSubmitting) return;

    final now = DateTime.now();

    final today = DateTime(now.year, now.month, now.day);

    final currentExpiry = _parseDate(licenseExpiryController.text);

    final lastDate = DateTime(now.year + 30, 12, 31);

    final selectedDate = await showDatePicker(
      context: context,
      initialDate:
          currentExpiry != null &&
              !currentExpiry.isBefore(today) &&
              !currentExpiry.isAfter(lastDate)
          ? currentExpiry
          : today,
      firstDate: today,
      lastDate: lastDate,
      helpText: 'SELECT LICENSE EXPIRATION DATE',
    );

    if (selectedDate != null && mounted) {
      setState(() {
        licenseExpiryController.text = _formatDisplayDate(selectedDate);
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

    final today = DateTime(now.year, now.month, now.day);

    if (expiry.isBefore(today)) {
      return 'Driver license has expired';
    }

    return null;
  }

  // ========================================================
  // REQUIRED FIELD VALIDATION
  // ========================================================

  String? _validateRequired(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    return null;
  }

  // ========================================================
  // NAME VALIDATION
  // ========================================================

  String? _validateName(String? value) {
    final name = value?.trim() ?? '';

    if (name.isEmpty) {
      return 'This field is required';
    }

    if (name.length < 2) {
      return 'Enter at least 2 characters';
    }

    if (!RegExp(r"^[a-zA-ZÀ-ÿÑñ\s.'-]+$").hasMatch(name)) {
      return 'Enter a valid name';
    }

    return null;
  }

  String? _validateMiddleInitial(String? value) {
    final middle = value?.trim() ?? '';

    if (middle.isEmpty) return null;

    if (!RegExp(r'^[A-Za-zÑñ]$').hasMatch(middle)) {
      return 'Enter one letter only';
    }

    return null;
  }

  // ========================================================
  // EMAIL VALIDATION
  // ========================================================

  String? _validateEmail(String? value) {
    final email = value?.trim() ?? '';

    if (email.isEmpty) {
      return 'Email is required';
    }

    if (email.length > 254) {
      return 'Email address is too long';
    }

    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
      return 'Enter a valid email address';
    }

    return null;
  }

  // ========================================================
  // PHILIPPINE MOBILE NUMBER VALIDATION
  // ========================================================

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

    return 'Use 09XXXXXXXXX or 639XXXXXXXXX';
  }

  String _normalizePhone(String value) {
    final phone = value.trim();

    if (phone.startsWith('63')) {
      return '0${phone.substring(2)}';
    }

    return phone;
  }

  // ========================================================
  // LICENSE AND PLATE VALIDATION
  // ========================================================

  String? _validateLicenseNumber(String? value) {
    final license = value?.trim() ?? '';

    if (license.isEmpty) {
      return 'License number is required';
    }

    if (!RegExp(r'^[A-Za-z0-9 -]+$').hasMatch(license)) {
      return 'Enter a valid license number';
    }

    return null;
  }

  String? _validatePlateNumber(String? value) {
    final plate = value?.trim() ?? '';

    if (plate.isEmpty) {
      return 'Plate number is required';
    }

    if (!RegExp(r'^[A-Za-z0-9 -]+$').hasMatch(plate)) {
      return 'Enter a valid plate number';
    }

    return null;
  }

  // ========================================================
  // PASSWORD VALIDATION
  // ========================================================

  String? _validatePassword(String? value) {
    final password = value ?? '';

    if (password.isEmpty) {
      return 'Password is required';
    }

    if (password.length < 8) {
      return 'Password must have at least 8 characters';
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

  // ========================================================
  // FILE VALIDATION
  // ========================================================

  bool _isValidImage(Uint8List bytes) {
    if (bytes.length < 8) return false;

    final isJpeg = bytes[0] == 0xFF && bytes[1] == 0xD8 && bytes[2] == 0xFF;

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

  bool _isValidPdf(Uint8List bytes) {
    if (bytes.length < 5) return false;

    return bytes[0] == 0x25 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x44 &&
        bytes[3] == 0x46 &&
        bytes[4] == 0x2D;
  }

  String _fileExtension(String filename, Uint8List bytes) {
    final lower = filename.toLowerCase();

    if (lower.endsWith('.png') && _isValidImage(bytes) && bytes[0] == 0x89) {
      return '.png';
    }

    return '.jpg';
  }

  // ========================================================
  // DRIVER'S LICENSE PICKER
  // ========================================================

  Future<void> pickLicenseImage(ImageSource source) async {
    if (isSubmitting) return;

    try {
      final XFile? file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
      );

      if (file == null) return;

      final bytes = await file.readAsBytes();

      if (bytes.isEmpty || bytes.length > maxFileSize) {
        _showMessage('Image must not exceed 5 MB.');
        return;
      }

      if (!_isValidImage(bytes)) {
        _showMessage('Only valid JPG and PNG images are accepted.');
        return;
      }

      if (!mounted) return;

      setState(() {
        licenseImageBytes = bytes;
        licenseFileName = file.name;
      });

      _showMessage('Driver license image attached.', success: true);
    } catch (_) {
      _showMessage('Unable to access camera or gallery.');
    }
  }

  // ========================================================
  // LICENSE IMAGE SOURCE
  // ========================================================

  Future<void> showLicensePicker() async {
    if (isSubmitting) return;

    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  "Upload Driver's License",
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 15),
                ListTile(
                  leading: Icon(
                    Icons.camera_alt_outlined,
                    color: RegisterStyle.primary,
                  ),
                  title: const Text('Take Photo'),
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
                  title: const Text('Choose from Gallery'),
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

  // ========================================================
  // OR AND CR FILE PICKER
  // ========================================================

  Future<void> pickVehicleDocument(String documentType) async {
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

      if (bytes == null || bytes.isEmpty) {
        _showMessage('Unable to read the selected file.');
        return;
      }

      if (bytes.length > maxFileSize) {
        _showMessage('PDF document must not exceed 5 MB.');
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
        } else if (documentType == 'CR') {
          crImageBytes = bytes;
          crFileName = file.name;
        }
      });

      _showMessage('$documentType document attached.', success: true);
    } catch (_) {
      _showMessage('Unable to select the PDF document.');
    }
  }

  // ========================================================
  // DOCUMENT VALIDATION
  // ========================================================

  bool _validateDocuments() {
    if (licenseImageBytes == null) {
      _showMessage("Please upload your Driver's License.");
      return false;
    }

    if (orImageBytes == null) {
      _showMessage('Please upload the Official Receipt (OR).');
      return false;
    }

    if (crImageBytes == null) {
      _showMessage('Please upload the Certificate of Registration (CR).');
      return false;
    }

    return true;
  }

  // ========================================================
  // API ENDPOINT
  // ========================================================

  Uri? _registrationUri() {
    final endpoint = riderRegisterEndpoint.trim();

    // Never guess the rider registration route.
    if (endpoint.isEmpty) return null;

    final apiBase = Uri.tryParse(ApiServices.baseUrl);

    if (apiBase == null ||
        !apiBase.hasAuthority ||
        !['http', 'https'].contains(apiBase.scheme)) {
      return null;
    }

    final endpointUri = Uri.tryParse(endpoint);

    if (endpointUri == null ||
        endpointUri.hasScheme ||
        endpointUri.hasAuthority) {
      return null;
    }

    final basePath = apiBase.path.replaceFirst(RegExp(r'/+$'), '');

    final path = endpointUri.path.replaceFirst(RegExp(r'^/+'), '');

    if (path.isEmpty || path.split('/').contains('..')) {
      return null;
    }

    return apiBase.replace(
      path: '$basePath/$path',
      query: endpointUri.hasQuery ? endpointUri.query : null,
      fragment: null,
    );
  }

  // ========================================================
  // BUILD REGISTRATION FORM DATA
  // ========================================================

  Map<String, String> _registrationFields(
    DateTime birthday,
    DateTime licenseExpiry,
  ) {
    return {
      'role': 'rider',
      'first_name': firstNameController.text.trim(),
      'middle_initial': middleInitialController.text.trim().toUpperCase(),
      'last_name': lastNameController.text.trim(),
      'birthday': _formatApiDate(birthday),
      'sex': selectedSex ?? '',
      'email': emailController.text.trim(),
      'phone': _normalizePhone(phoneController.text),
      'region': regionController.text.trim(),
      'province': provinceController.text.trim(),
      'city': cityController.text.trim(),
      'barangay': barangayController.text.trim(),
      'street': streetController.text.trim(),
      'license_number': licenseNumberController.text.trim().toUpperCase(),
      'license_type': selectedLicenseType ?? '',
      'license_expiry': _formatApiDate(licenseExpiry),
      'vehicle_type': selectedVehicle ?? '',
      'vehicle_model': vehicleModelController.text.trim(),
      'plate_number': plateNumberController.text.trim().toUpperCase(),
      'logistics_company': selectedCompany ?? '',
      'password': passwordController.text,
      'password_confirmation': confirmPasswordController.text,
    };
  }

  // ========================================================
  // HANDLE BACKEND RESPONSE
  // ========================================================

  String _apiErrorMessage(http.Response response) {
    try {
      final data = jsonDecode(utf8.decode(response.bodyBytes));

      if (data is Map) {
        final errors = data['errors'];

        if (errors is Map) {
          final messages = <String>[];

          for (final value in errors.values) {
            if (value is List) {
              messages.addAll(value.map((item) => item.toString()));
            } else if (value != null) {
              messages.add(value.toString());
            }
          }

          if (messages.isNotEmpty) {
            return messages.join('\n');
          }
        }

        final message = data['message'];

        if (message != null && message.toString().trim().isNotEmpty) {
          return message.toString();
        }
      }
    } catch (_) {
      // Do not show raw HTML or sensitive backend traces.
    }

    switch (response.statusCode) {
      case 401:
        return 'Unauthorized registration request.';
      case 403:
        return 'You are not allowed to register as a rider.';
      case 404:
        return 'Rider registration endpoint was not found.';
      case 413:
        return 'One or more uploaded files are too large.';
      case 422:
        return 'Please check your registration information.';
      case 429:
        return 'Too many attempts. Please try again later.';
      default:
        if (response.statusCode >= 500) {
          return 'Server error. Please try again later.';
        }
        return 'Registration failed. HTTP ${response.statusCode}.';
    }
  }

  // ========================================================
  // SUBMIT MULTIPART REGISTRATION
  // ========================================================

  Future<Map<String, dynamic>> _submitRegistration(
    Uri uri,
    Map<String, String> fields,
  ) async {
    final client = http.Client();

    try {
      final request = http.MultipartRequest('POST', uri);

      request.headers['Accept'] = 'application/json';

      request.fields.addAll(fields);

      final licenseBytes = licenseImageBytes!;
      final orBytes = orImageBytes!;
      final crBytes = crImageBytes!;

      // These multipart field names must match
      // the Laravel backend validation rules.
      request.files.add(
        http.MultipartFile.fromBytes(
          'license_image',
          licenseBytes,
          filename:
              'driver_license${_fileExtension(licenseFileName ?? 'license.jpg', licenseBytes)}',
        ),
      );

      request.files.add(
        http.MultipartFile.fromBytes(
          'or_document',
          orBytes,
          filename: 'vehicle_or.pdf',
        ),
      );

      request.files.add(
        http.MultipartFile.fromBytes(
          'cr_document',
          crBytes,
          filename: 'vehicle_cr.pdf',
        ),
      );

      final streamed = await client.send(request).timeout(requestTimeout);

      final response = await http.Response.fromStream(
        streamed,
      ).timeout(requestTimeout);

      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw Exception(_apiErrorMessage(response));
      }

      dynamic decoded;

      try {
        decoded = jsonDecode(utf8.decode(response.bodyBytes));
      } catch (_) {
        throw Exception(
          'The server returned an invalid registration response.',
        );
      }

      if (decoded is! Map) {
        throw Exception('Unexpected registration response from server.');
      }

      final result = Map<String, dynamic>.from(decoded);

      if (result['success'] != true) {
        throw Exception(
          result['message']?.toString() ??
              'Registration was not confirmed by the server.',
        );
      }

      return result;
    } finally {
      client.close();
    }
  }

  // ========================================================
  // RIDER REGISTRATION
  // ========================================================

  Future<void> registerRider() async {
    if (isSubmitting) return;

    FocusScope.of(context).unfocus();

    if (!(_formKey.currentState?.validate() ?? false)) {
      _showMessage('Please complete the required fields.');
      return;
    }

    if (!_validateDocuments()) return;

    if (!agreeTerms) {
      _showMessage(
        'Please agree to the Terms of Service '
        'and Privacy Policy.',
      );
      return;
    }

    final birthday = _parseDate(birthdayController.text);

    final licenseExpiry = _parseDate(licenseExpiryController.text);

    if (birthday == null || licenseExpiry == null) {
      _showMessage('Please check your dates.');
      return;
    }

    final uri = _registrationUri();

    if (uri == null) {
      _showMessage(
        'Rider registration API is not configured yet. '
        'Set RIDER_REGISTER_ENDPOINT to the confirmed '
        'Laravel route before submitting documents.',
      );
      return;
    }

    setState(() {
      isSubmitting = true;
    });

    try {
      final fields = _registrationFields(birthday, licenseExpiry);

      final response = await _submitRegistration(uri, fields);

      if (!mounted) return;

      final message =
          response['message']?.toString() ??
          'Your rider registration was submitted.';

      _showMessage(message, success: true);

      // Registration does not automatically mean
      // that the rider has been approved.
      // The backend must determine verification status.
      //
      // Return to login after the API confirms success.
      Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
    } on TimeoutException {
      _showMessage(
        'Registration request timed out. '
        'Please check your registration status '
        'before submitting again.',
      );
    } on SocketException {
      _showMessage('Unable to connect to the SUKI server.');
    } on http.ClientException {
      _showMessage(
        'Network error while submitting registration. '
        'Check your connection before retrying.',
      );
    } catch (error) {
      String message = error.toString();

      if (message.startsWith('Exception: ')) {
        message = message.substring(11);
      }

      _showMessage(message);
    } finally {
      if (mounted) {
        setState(() {
          isSubmitting = false;
        });
      }
    }
  }

  // ========================================================
  // MAIN BUILD
  // ========================================================

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
                    // LOGO
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

                    const SizedBox(height: 15),

                    Center(
                      child: Text(
                        'MOBILE SUKI',
                        style: RegisterStyle.brandText,
                      ),
                    ),

                    const SizedBox(height: 22),

                    Text(
                      'RIDER REGISTRATION',
                      style: RegisterStyle.smallHeading,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Create Rider Account',
                      style: RegisterStyle.pageTitle,
                    ),

                    const SizedBox(height: 5),

                    Text(
                      'Register as a delivery rider and join '
                      'the Mobile Suki delivery network.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 25),

                    // ======================================
                    // PERSONAL INFORMATION
                    // ======================================
                    _sectionTitle('Personal Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'First Name',
                      controller: firstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Middle Initial',
                      controller: middleInitialController,
                      hint: 'M.I. (Optional)',
                      capitalization: TextCapitalization.characters,
                      validator: _validateMiddleInitial,
                      inputFormatters: [
                        LengthLimitingTextInputFormatter(1),
                        TextInputFormatter.withFunction((oldValue, newValue) {
                          return newValue.copyWith(
                            text: newValue.text.toUpperCase(),
                            selection: newValue.selection,
                          );
                        }),
                      ],
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Last Name',
                      controller: lastNameController,
                      hint: 'Enter last name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                    ),

                    const SizedBox(height: 13),

                    _buildBirthdayField(),

                    const SizedBox(height: 13),

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

                    const SizedBox(height: 25),

                    // ======================================
                    // CONTACT INFORMATION
                    // ======================================
                    _sectionTitle('Contact Information'),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Email Address',
                      controller: emailController,
                      hint: 'you@example.com',
                      icon: Icons.mail_outline,
                      keyboardType: TextInputType.emailAddress,
                      capitalization: TextCapitalization.none,
                      validator: _validateEmail,
                      inputFormatters: [
                        FilteringTextInputFormatter.deny(RegExp(r'\s')),
                      ],
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

                    // ======================================
                    // ADDRESS INFORMATION
                    // ======================================
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
                      hint: 'Enter complete address',
                      icon: Icons.home_outlined,
                    ),

                    const SizedBox(height: 25),

                    // ======================================
                    // DRIVER'S LICENSE
                    // ======================================
                    _sectionTitle("Driver's License"),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'License Number',
                      controller: licenseNumberController,
                      hint: 'Example: N01-23-456789',
                      icon: Icons.badge_outlined,
                      capitalization: TextCapitalization.characters,
                      validator: _validateLicenseNumber,
                    ),

                    const SizedBox(height: 13),

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

                    const SizedBox(height: 13),

                    _buildLicenseExpiryField(),

                    const SizedBox(height: 13),

                    _buildUploadCard(
                      title: "Driver's License Image",
                      subtitle: 'JPG or PNG - Maximum 5 MB',
                      fileName: licenseFileName,
                      icon: Icons.credit_card_outlined,
                      onTap: showLicensePicker,
                    ),

                    const SizedBox(height: 25),

                    // ======================================
                    // VEHICLE INFORMATION
                    // ======================================
                    _sectionTitle('Vehicle Information'),

                    const SizedBox(height: 13),

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

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Vehicle Model',
                      controller: vehicleModelController,
                      hint: 'Example: Honda Click 125i',
                      icon: Icons.motorcycle_outlined,
                    ),

                    const SizedBox(height: 13),

                    _buildTextField(
                      label: 'Plate Number',
                      controller: plateNumberController,
                      hint: 'Example: 123ABC',
                      icon: Icons.confirmation_number_outlined,
                      capitalization: TextCapitalization.characters,
                      validator: _validatePlateNumber,
                    ),

                    const SizedBox(height: 15),

                    Text('Vehicle Documents', style: RegisterStyle.label),

                    const SizedBox(height: 10),

                    _buildUploadCard(
                      title: 'Official Receipt (OR)',
                      subtitle: 'PDF only - Maximum 5 MB',
                      fileName: orFileName,
                      icon: Icons.description_outlined,
                      onTap: () => pickVehicleDocument('OR'),
                    ),

                    const SizedBox(height: 10),

                    _buildUploadCard(
                      title: 'Certificate of Registration (CR)',
                      subtitle: 'PDF only - Maximum 5 MB',
                      fileName: crFileName,
                      icon: Icons.file_present_outlined,
                      onTap: () => pickVehicleDocument('CR'),
                    ),

                    const SizedBox(height: 25),

                    // ======================================
                    // LOGISTICS COMPANY
                    // ======================================
                    _sectionTitle('Logistics Company'),

                    const SizedBox(height: 7),

                    Text(
                      'Choose the logistics company you want '
                      'to be affiliated with. Your request '
                      'will require verification.',
                      style: RegisterStyle.subtitle,
                    ),

                    const SizedBox(height: 13),

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

                    const SizedBox(height: 10),

                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: RegisterStyle.selectedBackground,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: RegisterStyle.borderColor),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.info_outline,
                            size: 18,
                            color: RegisterStyle.primary,
                          ),
                          const SizedBox(width: 9),
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

                    const SizedBox(height: 25),

                    // ======================================
                    // ACCOUNT SECURITY
                    // ======================================
                    _sectionTitle('Account Security'),

                    const SizedBox(height: 13),

                    _buildPasswordField(),

                    const SizedBox(height: 13),

                    _buildConfirmPasswordField(),

                    const SizedBox(height: 12),

                    _buildPasswordRequirements(),

                    const SizedBox(height: 20),

                    // ======================================
                    // TERMS AND CONDITIONS
                    // ======================================
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
                          child: Text(
                            "I agree to Mobile Suki's "
                            'Terms of Service and Privacy Policy.',
                            style: RegisterStyle.termsText,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // ======================================
                    // SUBMIT BUTTON
                    // ======================================
                    SizedBox(
                      width: double.infinity,
                      height: 49,
                      child: ElevatedButton(
                        style: RegisterStyle.primaryButton,
                        onPressed: isSubmitting ? null : registerRider,
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
                                'SUBMIT RIDER REGISTRATION',
                                style: RegisterStyle.buttonText,
                              ),
                      ),
                    ),

                    const SizedBox(height: 20),

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

  // ========================================================
  // SECTION TITLE
  // ========================================================

  Widget _sectionTitle(String title) {
    return Text(title, style: RegisterStyle.sectionTitle);
  }

  // ========================================================
  // REUSABLE TEXT FIELD
  // ========================================================

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

  // ========================================================
  // BIRTHDAY FIELD
  // ========================================================

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

  // ========================================================
  // LICENSE EXPIRATION FIELD
  // ========================================================

  Widget _buildLicenseExpiryField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('License Expiration Date', style: RegisterStyle.label),
        const SizedBox(height: 6),
        TextFormField(
          controller: licenseExpiryController,
          readOnly: true,
          enabled: !isSubmitting,
          onTap: selectLicenseExpiry,
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

  // ========================================================
  // DROPDOWN FIELD
  // ========================================================

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
        Text(label, style: RegisterStyle.label),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          value: value,
          isExpanded: true,
          decoration: RegisterStyle.dropdownDecoration(),
          hint: Text(hint, style: RegisterStyle.hint),
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
          validator: (selected) {
            if (selected == null || selected.isEmpty) {
              return 'This field is required';
            }
            return null;
          },
        ),
      ],
    );
  }

  // ========================================================
  // DOCUMENT UPLOAD CARD
  // ========================================================

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
            color: uploaded ? RegisterStyle.primary : RegisterStyle.borderColor,
          ),
        ),
        child: Row(
          children: [
            Icon(icon, size: 27, color: RegisterStyle.primary),
            const SizedBox(width: 12),
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
                  const SizedBox(height: 4),
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
            const SizedBox(width: 8),
            Icon(
              uploaded ? Icons.check_circle : Icons.upload_file_outlined,
              size: 21,
              color: uploaded ? RegisterStyle.primary : RegisterStyle.mutedText,
            ),
          ],
        ),
      ),
    );
  }

  // ========================================================
  // PASSWORD FIELD
  // ========================================================

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

  // ========================================================
  // PASSWORD REQUIREMENT ITEM
  // ========================================================

  Widget _passwordRule(String label, bool passed) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Icon(
            passed ? Icons.check_circle : Icons.circle_outlined,
            size: 15,
            color: passed ? RegisterStyle.primary : RegisterStyle.mutedText,
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

  // ========================================================
  // LIVE PASSWORD REQUIREMENTS
  // ========================================================

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
          _passwordRule('At least 8 characters', hasMinimumLength),
          _passwordRule('One uppercase letter (A-Z)', hasUppercase),
          _passwordRule('One lowercase letter (a-z)', hasLowercase),
          _passwordRule('One number (0-9)', hasNumber),
          _passwordRule('One special character', hasSpecial),
        ],
      ),
    );
  }

  // ========================================================
  // CONFIRM PASSWORD
  // ========================================================

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
