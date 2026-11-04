
import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';
import '../../styles/register_style.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  // =========================================================
  // CAROUSEL CONTROLLER
  // =========================================================

  final PageController _pageController = PageController(
    viewportFraction: 0.88,
  );

  int currentIndex = 0;

  bool get isFirstPage => currentIndex == 0;

  bool get isLastPage =>
      currentIndex == accountTypes.length - 1;

  // =========================================================
  // ACCOUNT TYPES
  // =========================================================

  final List<Map<String, dynamic>> accountTypes = [
    {
      'title': 'Buyer',
      'badge': 'SHOP',
      'icon': Icons.shopping_cart_outlined,
      'description':
          'Create an account to browse products, place orders, '
          'track deliveries, and interact with sellers.',
      'features': [
        'Browse and purchase products',
        'Place and track orders',
        'Rate and provide feedback',
      ],
      'button': 'Register as Buyer',
      'route': '/buyer-registration',
    },
    {
      'title': 'Rider',
      'badge': 'RIDE',
      'icon': Icons.delivery_dining_outlined,
      'description':
          'Register as a delivery rider to accept assignments '
          'and deliver customer orders.',
      'features': [
        'Accept delivery assignments',
        'Pick up and deliver orders',
        'Track delivery history',
      ],
      'button': 'Register as Rider',
      'route': '/rider-registration',
    },
    {
      'title': 'Logistics',
      'badge': 'LOGISTICS',
      'icon': Icons.business_outlined,
      'description':
          'Register your logistics company to manage hubs, '
          'shipment operations, and delivery services.',
      'features': [
        'Manage logistics company operations',
        'Manage hubs and service coverage',
        'Monitor shipments and deliveries',
      ],
      'button': 'Register as Logistics',
      'route': '/logistics-registration',
    },
  ];

  // =========================================================
  // DISPOSE
  // =========================================================

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  // =========================================================
  // NEXT PAGE
  // =========================================================

  void _nextPage() {
    if (isLastPage) return;

    _pageController.nextPage(
      duration: Duration(milliseconds: 350),
      curve: Curves.easeInOut,
    );
  }

  // =========================================================
  // PREVIOUS PAGE
  // =========================================================

  void _previousPage() {
    if (isFirstPage) return;

    _pageController.previousPage(
      duration: Duration(milliseconds: 350),
      curve: Curves.easeInOut,
    );
  }

  // =========================================================
  // PAGE CHANGE
  // =========================================================

  void _onPageChanged(int index) {
    setState(() {
      currentIndex = index;
    });
  }

  // =========================================================
  // NAVIGATE TO REGISTRATION
  // =========================================================

  void _goToRegistration() {
    final String route =
        accountTypes[currentIndex]['route'] as String;

    Navigator.pushNamed(context, route);
  }

  // =========================================================
  // LOGIN
  // =========================================================

  void _goToLogin() {
    Navigator.pushReplacementNamed(
      context,
      '/login',
    );
  }

  // =========================================================
  // BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: RegisterStyle.backgroundColor,

      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: BouncingScrollPhysics(),

              padding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 22,
              ),

              child: ConstrainedBox(
                constraints: BoxConstraints(
                  minHeight: constraints.maxHeight - 44,
                ),

                child: Column(
                  children: [
                    SizedBox(height: 15),

                    // =========================================
                    // LOGO
                    // =========================================

                    Container(
                      width: 65,
                      height: 65,
                      decoration: RegisterStyle.logoContainer,

                      child: Icon(
                        Icons.shopping_bag_rounded,
                        size: 38,
                        color: RegisterStyle.primary,
                      ),
                    ),

                    SizedBox(height: 13),

                    // BRAND NAME

                    Text(
                      'MOBILE SUKI',
                      style: RegisterStyle.brandText,
                    ),

                    SizedBox(height: 30),

                    // =========================================
                    // HEADER
                    // =========================================

                    Text(
                      'CREATE YOUR ACCOUNT',
                      style: RegisterStyle.smallHeading,
                    ),

                    SizedBox(height: 8),

                    Text(
                      'Choose Your Account Type',
                      textAlign: TextAlign.center,
                      style: RegisterStyle.pageTitle,
                    ),

                    SizedBox(height: 9),

                    Padding(
                      padding: EdgeInsets.symmetric(
                        horizontal: 15,
                      ),

                      child: Text(
                        'Select the account you want to register. '
                        'Swipe to explore the available platforms.',
                        textAlign: TextAlign.center,
                        style: RegisterStyle.subtitle,
                      ),
                    ),

                    SizedBox(height: 30),

                    // =========================================
                    // CAROUSEL
                    // =========================================

                    SizedBox(
                      height: 365,

                      child: PageView.builder(
                        controller: _pageController,
                        itemCount: accountTypes.length,

                        physics: BouncingScrollPhysics(),

                        onPageChanged: _onPageChanged,

                        itemBuilder: (context, index) {
                          final account = accountTypes[index];

                          return _buildAccountCard(account);
                        },
                      ),
                    ),

                    SizedBox(height: 18),

                    // =========================================
                    // PAGE INDICATORS
                    // =========================================

                    Row(
                      mainAxisAlignment:
                          MainAxisAlignment.center,

                      children: List.generate(
                        accountTypes.length,
                        (index) {
                          final bool isSelected =
                              currentIndex == index;

                          return AnimatedContainer(
                            duration:
                                Duration(milliseconds: 250),

                            margin:
                                const EdgeInsets.symmetric(
                              horizontal: 4,
                            ),

                            width: isSelected ? 25 : 8,
                            height: 8,

                            decoration: BoxDecoration(
                              color: isSelected
                                  ? RegisterStyle.primary
                                  : RegisterStyle.borderColor,

                              borderRadius:
                                  BorderRadius.circular(20),
                            ),
                          );
                        },
                      ),
                    ),

                    SizedBox(height: 15),

                    // =========================================
                    // PREVIOUS / NEXT BUTTONS
                    // =========================================

                    Row(
                      mainAxisAlignment:
                          MainAxisAlignment.center,

                      children: [
                        IconButton(
                          onPressed: isFirstPage
                              ? null
                              : _previousPage,

                          icon: Icon(
                            Icons.arrow_back_ios_rounded,
                            size: 18,
                          ),

                          color: RegisterStyle.primary,
                        ),

                        Text(
                          '${currentIndex + 1} / ${accountTypes.length}',

                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: RegisterStyle.textColor,
                          ),
                        ),

                        IconButton(
                          onPressed: isLastPage
                              ? null
                              : _nextPage,

                          icon: Icon(
                            Icons.arrow_forward_ios_rounded,
                            size: 18,
                          ),

                          color: RegisterStyle.primary,
                        ),
                      ],
                    ),

                    SizedBox(height: 15),

                    // =========================================
                    // REGISTER BUTTON
                    // =========================================

                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,
                      ),

                      child: SizedBox(
                        width: double.infinity,
                        height: 50,

                        child: ElevatedButton(
                          style: RegisterStyle.primaryButton,

                          onPressed: _goToRegistration,

                          child: Text(
                            accountTypes[currentIndex]['button']
                                as String,

                            style: RegisterStyle.buttonText,
                          ),
                        ),
                      ),
                    ),

                    SizedBox(height: 25),

                    // =========================================
                    // LOGIN
                    // =========================================

                    Wrap(
                      alignment: WrapAlignment.center,

                      children: [
                        Text(
                          'Already have an account? ',
                          style: RegisterStyle.bottomText,
                        ),

                        GestureDetector(
                          onTap: _goToLogin,

                          child: Text(
                            'Log In',
                            style: RegisterStyle.bottomLink,
                          ),
                        ),
                      ],
                    ),

                    SizedBox(height: 20),

                    // =========================================
                    // BACK BUTTON
                    // =========================================

                    TextButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                      },

                      icon: Icon(
                        Icons.arrow_back,
                        size: 15,
                        color: RegisterStyle.mutedText,
                      ),

                      label: Text(
                        'Back to Mobile Suki',
                        style: RegisterStyle.backText,
                      ),
                    ),

                    SizedBox(height: 18),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // =========================================================
  // ACCOUNT CARD
  // =========================================================

  Widget _buildAccountCard(
    Map<String, dynamic> account,
  ) {
    final Color accent = AppTheme.isDark
        ? AppTheme.accentGreen
        : AppTheme.forestGreen;

    final Color background = AppTheme.softGreen;

    final List<String> features =
        account['features'] as List<String>;

    return Container(
      margin: const EdgeInsets.symmetric(
        horizontal: 7,
      ),

      padding: const EdgeInsets.all(22),

      decoration: BoxDecoration(
        color: AppTheme.surface,

        borderRadius: BorderRadius.circular(20),

        border: Border.all(
          color: RegisterStyle.borderColor,
          width: 1,
        ),

        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: Offset(0, 5),
          ),
        ],
      ),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          // =========================================
          // ICON AND BADGE
          // =========================================

          Row(
            children: [
              Container(
                width: 62,
                height: 62,

                decoration: BoxDecoration(
                  color: background,
                  borderRadius: BorderRadius.circular(16),
                ),

                child: Icon(
                  account['icon'] as IconData,
                  size: 31,
                  color: accent,
                ),
              ),

              Spacer(),

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 13,
                  vertical: 6,
                ),

                decoration: BoxDecoration(
                  color: background,
                  borderRadius: BorderRadius.circular(20),
                ),

                child: Text(
                  account['badge'] as String,

                  style: TextStyle(
                    color: accent,
                    fontWeight: FontWeight.bold,
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),

          SizedBox(height: 20),

          // =========================================
          // ACCOUNT TITLE
          // =========================================

          Text(
            account['title'] as String,

            style: TextStyle(
              fontSize: 23,
              fontWeight: FontWeight.w800,
              color: RegisterStyle.titleColor,
            ),
          ),

          SizedBox(height: 10),

          // =========================================
          // DESCRIPTION
          // =========================================

          Text(
            account['description'] as String,

            style: TextStyle(
              fontSize: 12,
              height: 1.5,
              color: RegisterStyle.textColor,
            ),
          ),

          SizedBox(height: 20),

          Divider(
            color: RegisterStyle.dividerColor,
          ),

          SizedBox(height: 12),

          // =========================================
          // FEATURES
          // =========================================

          ...features.map(
            (feature) {
              return Padding(
                padding: const EdgeInsets.only(
                  bottom: 12,
                ),

                child: Row(
                  crossAxisAlignment:
                      CrossAxisAlignment.start,

                  children: [
                    Icon(
                      Icons.check_circle_outline,
                      size: 17,
                      color: accent,
                    ),

                    SizedBox(width: 9),

                    Expanded(
                      child: Text(
                        feature,

                        style: TextStyle(
                          fontSize: 12,
                          color: RegisterStyle.titleColor,
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
