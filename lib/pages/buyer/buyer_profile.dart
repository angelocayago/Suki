
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'buyer_profile_style.dart';
import '../../services/auth_service.dart';
import '../../widgets/theme_toggle_button.dart';
import '../../widgets/buyer_bottom_nav.dart';

class BuyerProfilePage extends StatefulWidget {
  const BuyerProfilePage({super.key});

  @override
  State<BuyerProfilePage> createState() =>
      _BuyerProfilePageState();
}

class _BuyerProfilePageState extends State<BuyerProfilePage> {
  // =========================================================
  // LOCAL PROFILE DATA
  // TEMPORARY - API READY
  // =========================================================

  String firstName = '';
  String lastName = '';
  String phone = '';
  String email = '';

  // =========================================================
  // CONTROLLERS
  // =========================================================

  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final phoneController = TextEditingController();
  final emailController = TextEditingController();

  final currentPasswordController = TextEditingController();
  final newPasswordController = TextEditingController();
  final confirmPasswordController = TextEditingController();

  final _editFormKey = GlobalKey<FormState>();
  final _passwordFormKey = GlobalKey<FormState>();

  // =========================================================
  // STATES
  // =========================================================

  bool obscureCurrent = true;
  bool obscureNew = true;
  bool obscureConfirm = true;
  bool isLoggingOut = false;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    _loadLocalProfile();
  }

  void _loadLocalProfile() {
    firstNameController.text = firstName;
    lastNameController.text = lastName;
    phoneController.text = phone;
    emailController.text = email;
  }

  @override
  void dispose() {
    firstNameController.dispose();
    lastNameController.dispose();
    phoneController.dispose();
    emailController.dispose();

    currentPasswordController.dispose();
    newPasswordController.dispose();
    confirmPasswordController.dispose();

    super.dispose();
  }

  // =========================================================
  // PROFILE HELPERS
  // =========================================================

  String get fullName {
    final name = '$firstName $lastName'.trim();

    return name.isEmpty ? 'SUKI SHOP Buyer' : name;
  }

  String _display(String value) {
    return value.trim().isEmpty ? 'Not provided' : value;
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
              ? BuyerProfileStyle.primary
              : Colors.redAccent,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // =========================================================
  // VALIDATIONS
  // =========================================================

  String? _validateName(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'This field is required';
    }

    if (!RegExp(r"^[a-zA-Z\s'-]+$").hasMatch(value.trim())) {
      return 'Letters only are allowed';
    }

    return null;
  }

  String? _validatePhone(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Phone number is required';
    }

    final phoneValue = value.trim();

    if (RegExp(r'^09\d{9}$').hasMatch(phoneValue) ||
        RegExp(r'^639\d{9}$').hasMatch(phoneValue)) {
      return null;
    }

    return 'Use 09171234567 or 639171234567';
  }

  String? _validateEmail(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Email address is required';
    }

    final emailValue = value.trim();

    final regex = RegExp(
      r'^[A-Za-z0-9]+(?:[._%+-][A-Za-z0-9]+)*@'
      r'(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+'
      r'[A-Za-z]{2,}$',
    );

    if (!regex.hasMatch(emailValue)) {
      return 'Enter a valid email address';
    }

    return null;
  }

  String? _validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Password is required';
    }

    if (value.length < 8) {
      return 'At least 8 characters required';
    }

    if (!RegExp(r'[A-Z]').hasMatch(value)) {
      return 'Add an uppercase letter';
    }

    if (!RegExp(r'[a-z]').hasMatch(value)) {
      return 'Add a lowercase letter';
    }

    if (!RegExp(r'[0-9]').hasMatch(value)) {
      return 'Add a number';
    }

    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
      return 'Add a special character';
    }

    return null;
  }

  // =========================================================
  // EDIT PROFILE
  // =========================================================

  void _openEditProfile() {
    _loadLocalProfile();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,

      builder: (modalContext) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(modalContext).viewInsets.bottom,
          ),

          child: Container(
            constraints: BoxConstraints(
              maxHeight:
                  MediaQuery.of(modalContext).size.height * 0.85,
            ),

            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(
                top: Radius.circular(22),
              ),
            ),

            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),

              child: Form(
                key: _editFormKey,

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    _modalHeader(
                      'Edit Profile',
                      'Update your personal information.',
                      () => Navigator.pop(modalContext),
                    ),

                    SizedBox(height: 22),

                    _inputField(
                      label: 'First Name',
                      controller: firstNameController,
                      hint: 'Enter first name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 15),

                    _inputField(
                      label: 'Last Name',
                      controller: lastNameController,
                      hint: 'Enter last name',
                      icon: Icons.person_outline,
                      validator: _validateName,
                      capitalization: TextCapitalization.words,
                    ),

                    SizedBox(height: 15),

                    _inputField(
                      label: 'Phone Number',
                      controller: phoneController,
                      hint: '09171234567',
                      icon: Icons.phone_outlined,
                      keyboardType: TextInputType.number,
                      validator: _validatePhone,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                        LengthLimitingTextInputFormatter(12),
                      ],
                    ),

                    SizedBox(height: 15),

                    _inputField(
                      label: 'Email Address',
                      controller: emailController,
                      hint: 'example@gmail.com',
                      icon: Icons.mail_outline,
                      keyboardType: TextInputType.emailAddress,
                      validator: _validateEmail,
                    ),

                    SizedBox(height: 24),

                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            style: BuyerProfileStyle.outlinedButton,
                            onPressed: () {
                              Navigator.pop(modalContext);
                            },
                            child: Text('Cancel'),
                          ),
                        ),

                        SizedBox(width: 10),

                        Expanded(
                          child: ElevatedButton(
                            style: BuyerProfileStyle.primaryButton,

                            onPressed: () {
                              if (!_editFormKey.currentState!.validate()) {
                                return;
                              }

                              setState(() {
                                firstName =
                                    firstNameController.text.trim();

                                lastName =
                                    lastNameController.text.trim();

                                phone = phoneController.text.trim();

                                email = emailController.text.trim();
                              });

                              Navigator.pop(modalContext);

                              _showMessage(
                                'Profile updated locally. '
                                'Changes are not saved permanently.',
                                success: true,
                              );
                            },

                            child: Text('Save Changes'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  // =========================================================
  // CHANGE PASSWORD
  // =========================================================

  void _openPasswordModal() {
    currentPasswordController.clear();
    newPasswordController.clear();
    confirmPasswordController.clear();

    setState(() {
      obscureCurrent = true;
      obscureNew = true;
      obscureConfirm = true;
    });

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,

      builder: (modalContext) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(context).viewInsets.bottom,
              ),

              child: Container(
                constraints: BoxConstraints(
                  maxHeight:
                      MediaQuery.of(context).size.height * 0.85,
                ),

                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(
                    top: Radius.circular(22),
                  ),
                ),

                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),

                  child: Form(
                    key: _passwordFormKey,

                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        _modalHeader(
                          'Change Password',
                          'Keep your SUKI SHOP account secure.',
                          () => Navigator.pop(modalContext),
                        ),

                        SizedBox(height: 22),

                        _passwordField(
                          label: 'Current Password',
                          controller: currentPasswordController,
                          obscure: obscureCurrent,

                          onToggle: () {
                            setModalState(() {
                              obscureCurrent = !obscureCurrent;
                            });
                          },
                        ),

                        SizedBox(height: 15),

                        _passwordField(
                          label: 'New Password',
                          controller: newPasswordController,
                          obscure: obscureNew,
                          validator: _validatePassword,

                          onToggle: () {
                            setModalState(() {
                              obscureNew = !obscureNew;
                            });
                          },
                        ),

                        SizedBox(height: 15),

                        _passwordField(
                          label: 'Confirm New Password',
                          controller: confirmPasswordController,
                          obscure: obscureConfirm,

                          onToggle: () {
                            setModalState(() {
                              obscureConfirm = !obscureConfirm;
                            });
                          },

                          validator: (value) {
                            if (value == null || value.isEmpty) {
                              return 'Please confirm your password';
                            }

                            if (value != newPasswordController.text) {
                              return 'Passwords do not match';
                            }

                            return null;
                          },
                        ),

                        SizedBox(height: 24),

                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                style: BuyerProfileStyle.outlinedButton,
                                onPressed: () {
                                  Navigator.pop(modalContext);
                                },
                                child: Text('Cancel'),
                              ),
                            ),

                            SizedBox(width: 10),

                            Expanded(
                              child: ElevatedButton(
                                style: BuyerProfileStyle.primaryButton,

                                onPressed: () {
                                  if (!_passwordFormKey.currentState!
                                      .validate()) {
                                    return;
                                  }

                                  Navigator.pop(modalContext);

                                  _showMessage(
                                    'Password form validated. '
                                    'Password has not been changed because '
                                    'API authentication is not connected.',
                                    success: true,
                                  );
                                },

                                child: Text(
                                  'Update Password',
                                  textAlign: TextAlign.center,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  // =========================================================
  // REUSABLE INPUT FIELD
  // =========================================================

  Widget _inputField({
    required String label,
    required TextEditingController controller,
    required String hint,
    IconData? icon,
    TextInputType? keyboardType,
    TextCapitalization capitalization =
        TextCapitalization.none,
    String? Function(String?)? validator,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          label,
          style: BuyerProfileStyle.label,
        ),

        SizedBox(height: 7),

        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          textCapitalization: capitalization,
          inputFormatters: inputFormatters,
          style: BuyerProfileStyle.value,

          decoration: BuyerProfileStyle.inputDecoration(
            hint: hint,
            icon: icon,
          ),

          validator: validator,
        ),
      ],
    );
  }

  // =========================================================
  // PASSWORD FIELD
  // =========================================================

  Widget _passwordField({
    required String label,
    required TextEditingController controller,
    required bool obscure,
    required VoidCallback onToggle,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          label,
          style: BuyerProfileStyle.label,
        ),

        SizedBox(height: 7),

        TextFormField(
          controller: controller,
          obscureText: obscure,
          autocorrect: false,
          enableSuggestions: false,
          style: BuyerProfileStyle.value,

          decoration: BuyerProfileStyle.inputDecoration(
            hint: 'Enter $label',
            icon: Icons.lock_outline,

            suffixIcon: IconButton(
              onPressed: onToggle,

              icon: Icon(
                obscure
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                size: 18,
                color: BuyerProfileStyle.textSecondary,
              ),
            ),
          ),

          validator: validator ??
              (value) {
                if (value == null || value.isEmpty) {
                  return 'This field is required';
                }

                return null;
              },
        ),
      ],
    );
  }

  // =========================================================
  // MODAL HEADER
  // =========================================================

  Widget _modalHeader(
    String title,
    String subtitle,
    VoidCallback onClose,
  ) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(
                title,
                style: BuyerProfileStyle.sectionTitle.copyWith(
                  fontSize: 19,
                ),
              ),

              SizedBox(height: 4),

              Text(
                subtitle,
                style: BuyerProfileStyle.subtitle,
              ),
            ],
          ),
        ),

        IconButton(
          onPressed: onClose,
          icon: Icon(Icons.close),
        ),
      ],
    );
  }

  // =========================================================
  // PROFILE INFORMATION ITEM
  // =========================================================

  Widget _profileItem(
    String label,
    String value,
    IconData icon,
  ) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Icon(
          icon,
          size: 17,
          color: BuyerProfileStyle.textSecondary,
        ),

        SizedBox(width: 9),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(
                label,
                style: BuyerProfileStyle.label,
              ),

              SizedBox(height: 5),

              Text(
                _display(value),
                style: BuyerProfileStyle.value,
                softWrap: true,
              ),
            ],
          ),
        ),
      ],
    );
  }

  // =========================================================
  // ACCOUNT MENU ITEM
  // =========================================================

  Widget _accountMenuItem(
    String title,
    IconData icon,
    VoidCallback onTap,
  ) {
    return Material(
      color: Colors.transparent,

      child: InkWell(
        borderRadius: BorderRadius.circular(11),
        onTap: onTap,

        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: 8,
            vertical: 11,
          ),

          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BuyerProfileStyle.iconDecoration,

                child: Icon(
                  icon,
                  color: BuyerProfileStyle.primary,
                  size: 19,
                ),
              ),

              SizedBox(width: 12),

              Expanded(
                child: Text(
                  title,
                  style: BuyerProfileStyle.value,
                ),
              ),

              Icon(
                Icons.chevron_right,
                size: 20,
                color: BuyerProfileStyle.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // NAVIGATION
  // =========================================================

  void _openRegistration() {
    Navigator.pushNamed(
      context,
      '/register',
    );
  }

  Future<void> _logout() async {
    if (isLoggingOut) return;

    setState(() => isLoggingOut = true);
    try {
      await AuthService.logout();
    } catch (_) {
      // AuthService clears the local session even if the server is offline.
    }

    if (!mounted) return;
    Navigator.of(context).pushNamedAndRemoveUntil(
      '/login',
      (route) => false,
    );
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: BuyerProfileStyle.background,
      bottomNavigationBar: BuyerBottomNav(
        selectedIndex: 3,
        onSelected: (index) {
          switch (index) {
            case 0:
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/buyer-home',
                (route) => false,
              );
              break;
            case 1:
              Navigator.pushNamed(context, '/buyer-shop');
              break;
            case 2:
              Navigator.pushNamed(context, '/buyer-cart');
              break;
          }
        },
      ),

      appBar: AppBar(
        backgroundColor: BuyerProfileStyle.white,
        elevation: 0,
        scrolledUnderElevation: 0,

        title: Text(
          'My Account',
          style: BuyerProfileStyle.sectionTitle,
        ),

        centerTitle: false,
        actions: const [ThemeToggleButton()],
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          physics: BouncingScrollPhysics(),

          padding: const EdgeInsets.symmetric(
            horizontal: BuyerProfileStyle.horizontalPadding,
            vertical: 16,
          ),

          child: Center(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxWidth: 650,
              ),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Text(
                    'Manage your profile, addresses, '
                    'and account settings.',
                    style: BuyerProfileStyle.subtitle,
                  ),

                  SizedBox(height: 20),

                  _buildProfileCard(),

                  SizedBox(height: 18),

                  _buildAccountMenu(),

                  SizedBox(height: 18),

                  _buildPlatformPromotion(),

                  SizedBox(height: 25),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // =========================================================
  // PROFILE CARD
  // =========================================================

  Widget _buildProfileCard() {
    return Container(
      width: double.infinity,
      decoration: BuyerProfileStyle.cardDecoration,

      child: Column(
        children: [
          // PROFILE HEADER

          Padding(
            padding: const EdgeInsets.all(18),

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Row(
                  children: [
                    Container(
                      width: 62,
                      height: 62,

                      decoration: BoxDecoration(
                        color: BuyerProfileStyle.lightGreen,
                        shape: BoxShape.circle,
                      ),

                      child: Icon(
                        Icons.person_outline,
                        color: BuyerProfileStyle.primary,
                        size: 34,
                      ),
                    ),

                    SizedBox(width: 13),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,

                        children: [
                          Text(
                            fullName,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: BuyerProfileStyle.sectionTitle,
                          ),

                          SizedBox(height: 5),

                          Text(
                            _display(email),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: BuyerProfileStyle.subtitle,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                SizedBox(height: 15),

                SizedBox(
                  width: double.infinity,

                  child: OutlinedButton.icon(
                    style: BuyerProfileStyle.outlinedButton,
                    onPressed: _openEditProfile,

                    icon: Icon(
                      Icons.edit_outlined,
                      size: 17,
                    ),

                    label: Text('Edit Profile'),
                  ),
                ),
              ],
            ),
          ),

          Divider(
            height: 1,
            color: BuyerProfileStyle.border,
          ),

          // PERSONAL INFORMATION

          Padding(
            padding: const EdgeInsets.all(18),

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(
                  'Personal Information',
                  style: BuyerProfileStyle.sectionTitle,
                ),

                SizedBox(height: 5),

                Text(
                  'Your basic account information.',
                  style: BuyerProfileStyle.subtitle,
                ),

                SizedBox(height: 20),

                LayoutBuilder(
                  builder: (context, constraints) {
                    final wide = constraints.maxWidth >= 420;

                    final items = [
                      _profileItem(
                        'First Name',
                        firstName,
                        Icons.person_outline,
                      ),
                      _profileItem(
                        'Last Name',
                        lastName,
                        Icons.person_outline,
                      ),
                      _profileItem(
                        'Phone Number',
                        phone,
                        Icons.phone_outlined,
                      ),
                      _profileItem(
                        'Email Address',
                        email,
                        Icons.mail_outline,
                      ),
                    ];

                    if (wide) {
                      return Wrap(
                        spacing: 15,
                        runSpacing: 22,

                        children: items.map((item) {
                          return SizedBox(
                            width: (constraints.maxWidth - 15) / 2,
                            child: item,
                          );
                        }).toList(),
                      );
                    }

                    return Column(
                      children: [
                        for (int i = 0; i < items.length; i++) ...[
                          items[i],

                          if (i != items.length - 1)
                            SizedBox(height: 18),
                        ],
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // ACCOUNT MENU
  // =========================================================

  Widget _buildAccountMenu() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(17),

      decoration: BuyerProfileStyle.cardDecoration,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text(
            'Account',
            style: BuyerProfileStyle.sectionTitle,
          ),

          SizedBox(height: 10),

          _accountMenuItem(
            'My Orders',
            Icons.inventory_2_outlined,
            () => Navigator.pushNamed(context, '/my-orders'),
          ),

          _accountMenuItem(
            'My Addresses',
            Icons.location_on_outlined,
            () => Navigator.pushNamed(context, '/my-account/addresses'),
          ),

          _accountMenuItem(
            'Wishlist',
            Icons.favorite_border,
            () => Navigator.pushNamed(context, '/wishlist'),
          ),

          _accountMenuItem(
            'Followed Stores',
            Icons.storefront_outlined,
            () => Navigator.pushNamed(context, '/stores/following'),
          ),

          _accountMenuItem(
            'Returns & Refunds',
            Icons.assignment_return_outlined,
            () => Navigator.pushNamed(context, '/return-refunds'),
          ),

          _accountMenuItem(
            'My Reviews',
            Icons.rate_review_outlined,
            () => Navigator.pushNamed(context, '/buyer/reviews'),
          ),

          _accountMenuItem(
            'Help Center',
            Icons.help_outline,
            () => Navigator.pushNamed(context, '/buyer/help'),
          ),

          _accountMenuItem(
            'Change Password',
            Icons.lock_outline,
            _openPasswordModal,
          ),

          SizedBox(height: 12),

          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: isLoggingOut ? null : _logout,
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.redAccent,
                side: BorderSide(color: Colors.redAccent),
                padding: const EdgeInsets.symmetric(vertical: 13),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              icon: isLoggingOut
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.redAccent,
                      ),
                    )
                  : const Icon(Icons.logout),
              label: Text(isLoggingOut ? 'Signing out...' : 'Log Out'),
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // RIDER AND LOGISTICS PROMOTION
  // NO SELLER OPTION IN MOBILE
  // =========================================================

  Widget _buildPlatformPromotion() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),

      decoration: BoxDecoration(
        color: BuyerProfileStyle.lightGreen,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: BuyerProfileStyle.border,
        ),
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,

                decoration: BoxDecoration(
                  color: BuyerProfileStyle.white,
                  shape: BoxShape.circle,
                ),

                child: Icon(
                  Icons.delivery_dining_outlined,
                  color: BuyerProfileStyle.primary,
                  size: 23,
                ),
              ),

              SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      'Join Mobile SUKI',
                      style: BuyerProfileStyle.sectionTitle,
                    ),

                    SizedBox(height: 4),

                    Text(
                      'Explore Rider and Logistics opportunities.',
                      style: BuyerProfileStyle.subtitle,
                    ),
                  ],
                ),
              ),
            ],
          ),

          SizedBox(height: 17),

          SizedBox(
            width: double.infinity,

            child: ElevatedButton.icon(
              style: BuyerProfileStyle.primaryButton,
              onPressed: _openRegistration,

              icon: Icon(
                Icons.delivery_dining,
                size: 18,
              ),

              label: Text('Apply as Rider'),
            ),
          ),

          SizedBox(height: 9),

          SizedBox(
            width: double.infinity,

            child: OutlinedButton.icon(
              style: BuyerProfileStyle.outlinedButton,
              onPressed: _openRegistration,

              icon: Icon(
                Icons.local_shipping_outlined,
                size: 18,
              ),

              label: Text('Register as Logistics'),
            ),
          ),
        ],
      ),
    );
  }
}
