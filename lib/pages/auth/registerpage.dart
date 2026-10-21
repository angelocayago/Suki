import 'package:flutter/material.dart';
import '../../styles/register_style.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  final _formKey = GlobalKey<FormState>();

  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final middleInitialController = TextEditingController();
  final birthdayController = TextEditingController();
  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  final municipalityController = TextEditingController();
  final barangayController = TextEditingController();
  final streetController = TextEditingController();

  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  String selectedRole = 'Buyer';
  String? selectedSex;
  String? selectedProvince;

  bool obscurePassword = true;
  bool obscureConfirmPassword = true;
  bool agreeTerms = false;

  final List<String> roles = ['Buyer', 'Rider', 'Logistics'];

  final List<String> sexOptions = ['Male', 'Female', 'Prefer not to say'];

  final List<String> provinces = [
    'Batangas',
    'Cavite',
    'Laguna',
    'Quezon',
    'Rizal',
    'Occidental Mindoro',
    'Oriental Mindoro',
  ];

  @override
  void dispose() {
    firstNameController.dispose();
    lastNameController.dispose();
    middleInitialController.dispose();
    birthdayController.dispose();
    phoneController.dispose();
    emailController.dispose();
    municipalityController.dispose();
    barangayController.dispose();
    streetController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> selectBirthday() async {
    final DateTime? selectedDate = await showDatePicker(
      context: context,
      initialDate: DateTime(2000),
      firstDate: DateTime(1950),
      lastDate: DateTime.now(),
    );

    if (selectedDate != null) {
      setState(() {
        birthdayController.text =
            '${selectedDate.month.toString().padLeft(2, '0')}/'
            '${selectedDate.day.toString().padLeft(2, '0')}/'
            '${selectedDate.year}';
      });
    }
  }

  void registerAccount() {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (!agreeTerms) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Please agree to the Terms of Service and Privacy Policy.',
          ),
        ),
      );
      return;
    }

    if (passwordController.text != confirmPasswordController.text) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Passwords do not match.')));
      return;
    }

    debugPrint('Account Type: $selectedRole');
    debugPrint('Email: ${emailController.text}');
    debugPrint('Phone: ${phoneController.text}');

    // TODO:
    // Connect to AuthService / Firebase / API.
    //
    // Save:
    // role: buyer / rider / logistics
    //
    // Example:
    // await AuthService.register(
    //   role: selectedRole,
    //   email: emailController.text,
    //   password: passwordController.text,
    // );

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$selectedRole account created successfully.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RegisterStyle.backgroundColor,
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
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
                        // =========================
                        // HEADER
                        // =========================
                        Center(
                          child: Container(
                            width: 64,
                            height: 64,
                            decoration: RegisterStyle.logoContainer,
                            child: const Icon(
                              Icons.shopping_bag_rounded,
                              size: 38,
                              color: RegisterStyle.primary,
                            ),
                          ),
                        ),

                        const SizedBox(height: 15),

                        const Center(
                          child: Text(
                            'MOBILE SUKI',
                            style: RegisterStyle.brandText,
                          ),
                        ),

                        const SizedBox(height: 22),

                        const Text(
                          'CREATE YOUR ACCOUNT',
                          style: RegisterStyle.smallHeading,
                        ),

                        const SizedBox(height: 5),

                        const Text(
                          'Create your account',
                          style: RegisterStyle.pageTitle,
                        ),

                        const SizedBox(height: 5),

                        const Text(
                          'Choose your account type and fill in your information.',
                          style: RegisterStyle.subtitle,
                        ),

                        const SizedBox(height: 22),

                        // =========================
                        // ACCOUNT TYPE
                        // =========================
                        const Text(
                          'Account Type',
                          style: RegisterStyle.sectionTitle,
                        ),

                        const SizedBox(height: 10),

                        _buildRoleSelector(),

                        const SizedBox(height: 24),

                        // =========================
                        // PERSONAL INFORMATION
                        // =========================
                        const Text(
                          'Personal Information',
                          style: RegisterStyle.sectionTitle,
                        ),

                        const SizedBox(height: 13),

                        _buildTextField(
                          label: 'First Name',
                          controller: firstNameController,
                          hint: 'Enter first name',
                          icon: Icons.person_outline,
                        ),

                        const SizedBox(height: 13),

                        _buildTextField(
                          label: 'Last Name',
                          controller: lastNameController,
                          hint: 'Enter last name',
                          icon: Icons.person_outline,
                        ),

                        const SizedBox(height: 13),

                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: _buildTextField(
                                label: 'Middle Initial',
                                controller: middleInitialController,
                                hint: 'M.I.',
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(child: _buildSexField()),
                          ],
                        ),

                        const SizedBox(height: 13),

                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(child: _buildBirthdayField()),
                            const SizedBox(width: 12),
                            Expanded(
                              child: _buildTextField(
                                label: 'Phone Number',
                                controller: phoneController,
                                hint: '09XXXXXXXXX',
                                keyboardType: TextInputType.phone,
                              ),
                            ),
                          ],
                        ),

                        const SizedBox(height: 13),

                        _buildTextField(
                          label: 'Email Address',
                          controller: emailController,
                          hint: 'you@example.com',
                          icon: Icons.mail_outline,
                          keyboardType: TextInputType.emailAddress,
                        ),

                        const SizedBox(height: 24),

                        // =========================
                        // ADDRESS INFORMATION
                        // =========================
                        const Text(
                          'Address Information',
                          style: RegisterStyle.sectionTitle,
                        ),

                        const SizedBox(height: 13),

                        _buildProvinceField(),

                        const SizedBox(height: 13),

                        _buildTextField(
                          label: 'Municipality / City',
                          controller: municipalityController,
                          hint: 'Enter municipality or city',
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
                          hint: 'Street, house number, etc.',
                          icon: Icons.home_outlined,
                        ),

                        const SizedBox(height: 24),

                        // =========================
                        // SECURITY
                        // =========================
                        const Text(
                          'Account Security',
                          style: RegisterStyle.sectionTitle,
                        ),

                        const SizedBox(height: 13),

                        _buildPasswordField(),

                        const SizedBox(height: 13),

                        _buildConfirmPasswordField(),

                        const SizedBox(height: 18),

                        // =========================
                        // TERMS
                        // =========================
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
                                onChanged: (value) {
                                  setState(() {
                                    agreeTerms = value ?? false;
                                  });
                                },
                              ),
                            ),

                            const SizedBox(width: 9),

                            const Expanded(
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

                        const SizedBox(height: 18),

                        // =========================
                        // CREATE ACCOUNT
                        // =========================
                        SizedBox(
                          width: double.infinity,
                          height: 49,
                          child: ElevatedButton(
                            style: RegisterStyle.primaryButton,
                            onPressed: registerAccount,
                            child: const Text(
                              'CREATE ACCOUNT',
                              style: RegisterStyle.buttonText,
                            ),
                          ),
                        ),

                        const SizedBox(height: 22),

                        // =========================
                        // OR
                        // =========================
                        const Row(
                          children: [
                            Expanded(
                              child: Divider(color: RegisterStyle.dividerColor),
                            ),
                            Padding(
                              padding: EdgeInsets.symmetric(horizontal: 13),
                              child: Text('OR', style: RegisterStyle.orText),
                            ),
                            Expanded(
                              child: Divider(color: RegisterStyle.dividerColor),
                            ),
                          ],
                        ),

                        const SizedBox(height: 20),

                        // =========================
                        // LOGIN
                        // =========================
                        Center(
                          child: Wrap(
                            alignment: WrapAlignment.center,
                            children: [
                              const Text(
                                'Already have an account? ',
                                style: RegisterStyle.bottomText,
                              ),
                              GestureDetector(
                                onTap: () {
                                  Navigator.pushReplacementNamed(
                                    context,
                                    '/login',
                                  );
                                },
                                child: const Text(
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
                            onPressed: () {
                              Navigator.pop(context);
                            },
                            icon: const Icon(
                              Icons.arrow_back,
                              size: 15,
                              color: RegisterStyle.mutedText,
                            ),
                            label: const Text(
                              'Back to Mobile Suki',
                              style: RegisterStyle.backText,
                            ),
                          ),
                        ),

                        const SizedBox(height: 18),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // =========================================================
  // ROLE SELECTOR
  // =========================================================

  Widget _buildRoleSelector() {
    return Row(
      children: roles.map((role) {
        final bool selected = selectedRole == role;

        IconData icon;

        switch (role) {
          case 'Rider':
            icon = Icons.delivery_dining_outlined;
            break;

          case 'Logistics':
            icon = Icons.inventory_2_outlined;
            break;

          default:
            icon = Icons.shopping_bag_outlined;
        }

        return Expanded(
          child: Padding(
            padding: EdgeInsets.only(right: role != roles.last ? 8 : 0),
            child: GestureDetector(
              onTap: () {
                setState(() {
                  selectedRole = role;
                });
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                height: 72,
                decoration: RegisterStyle.roleCard(selected),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      icon,
                      size: 22,
                      color: selected
                          ? RegisterStyle.primary
                          : RegisterStyle.mutedText,
                    ),
                    const SizedBox(height: 5),
                    Text(
                      role,
                      style: selected
                          ? RegisterStyle.selectedRoleText
                          : RegisterStyle.roleText,
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      }).toList(),
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
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: TextFormField(
            controller: controller,
            keyboardType: keyboardType,
            style: RegisterStyle.inputText,
            decoration: RegisterStyle.inputDecoration(hint: hint, icon: icon),
            validator: (value) {
              if (label == 'Middle Initial') {
                return null;
              }

              if (value == null || value.trim().isEmpty) {
                return 'Required';
              }

              return null;
            },
          ),
        ),
      ],
    );
  }

  // =========================================================
  // SEX
  // =========================================================

  Widget _buildSexField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Sex', style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: DropdownButtonFormField<String>(
            value: selectedSex,
            isExpanded: true,
            decoration: RegisterStyle.dropdownDecoration(),
            hint: const Text('Select sex', style: RegisterStyle.hint),
            items: sexOptions
                .map(
                  (sex) => DropdownMenuItem(
                    value: sex,
                    child: Text(sex, style: RegisterStyle.inputText),
                  ),
                )
                .toList(),
            onChanged: (value) {
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
        ),
      ],
    );
  }

  // =========================================================
  // BIRTHDAY
  // =========================================================

  Widget _buildBirthdayField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Birthday', style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: TextFormField(
            controller: birthdayController,
            readOnly: true,
            onTap: selectBirthday,
            style: RegisterStyle.inputText,
            decoration: RegisterStyle.inputDecoration(
              hint: 'mm/dd/yyyy',
              suffixIcon: const Icon(
                Icons.calendar_today_outlined,
                size: 17,
                color: RegisterStyle.iconColor,
              ),
            ),
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Required';
              }
              return null;
            },
          ),
        ),
      ],
    );
  }

  // =========================================================
  // PROVINCE
  // =========================================================

  Widget _buildProvinceField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Province', style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: DropdownButtonFormField<String>(
            value: selectedProvince,
            isExpanded: true,
            decoration: RegisterStyle.dropdownDecoration(),
            hint: const Text('Select Province', style: RegisterStyle.hint),
            items: provinces
                .map(
                  (province) => DropdownMenuItem(
                    value: province,
                    child: Text(province, style: RegisterStyle.inputText),
                  ),
                )
                .toList(),
            onChanged: (value) {
              setState(() {
                selectedProvince = value;
              });
            },
            validator: (value) {
              if (value == null) {
                return 'Required';
              }
              return null;
            },
          ),
        ),
      ],
    );
  }

  // =========================================================
  // PASSWORD
  // =========================================================

  Widget _buildPasswordField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Password', style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: TextFormField(
            controller: passwordController,
            obscureText: obscurePassword,
            style: RegisterStyle.inputText,
            decoration: RegisterStyle.inputDecoration(
              hint: 'Minimum 8 characters',
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
                  size: 18,
                  color: RegisterStyle.iconColor,
                ),
              ),
            ),
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Required';
              }

              if (value.length < 8) {
                return 'Minimum 8 characters';
              }

              return null;
            },
          ),
        ),
      ],
    );
  }

  // =========================================================
  // CONFIRM PASSWORD
  // =========================================================

  Widget _buildConfirmPasswordField() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Confirm Password', style: RegisterStyle.label),

        const SizedBox(height: 6),

        SizedBox(
          height: 49,
          child: TextFormField(
            controller: confirmPasswordController,
            obscureText: obscureConfirmPassword,
            style: RegisterStyle.inputText,
            decoration: RegisterStyle.inputDecoration(
              hint: 'Re-enter password',
              icon: Icons.lock_outline,
              suffixIcon: IconButton(
                onPressed: () {
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
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Required';
              }

              if (value != passwordController.text) {
                return 'Passwords do not match';
              }

              return null;
            },
          ),
        ),
      ],
    );
  }
}
