import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

// =========================================================
// SUKISHOP MOBILE - THEME
// =========================================================

import 'theme/app_theme.dart';
import 'theme/theme_controller.dart';

// =========================================================
// AUTHENTICATION PAGES
// =========================================================

import 'pages/auth/loginpage.dart';
import 'pages/auth/auth_gate.dart';
import 'pages/auth/registerpage.dart';
import 'pages/auth/buyer_registration.dart';
import 'pages/auth/rider_registration.dart';
import 'pages/auth/logistics_registration.dart';

// =========================================================
// BUYER PAGES
// =========================================================

import 'pages/buyer/buyer_home.dart';
import 'pages/buyer/buyer_profile.dart';
import 'pages/buyer/cart.dart';
import 'pages/buyer/checkout.dart';
import 'pages/buyer/product_list.dart';
import 'pages/buyer/product_details.dart';
import 'pages/buyer/account_pages.dart';
import 'pages/buyer/order_history.dart';
import 'pages/buyer/order_tracking.dart';

// =========================================================
// SHARED PAGES
// =========================================================

import 'pages/shared/laravel_route_page.dart';

// =========================================================
// RIDER PAGES
// =========================================================

import 'pages/rider/rider_home.dart';
import 'pages/rider/rider_deliveries.dart';
import 'pages/rider/rider_scanner.dart';
import 'pages/rider/parcel_information.dart' as rider;
import 'pages/rider/delivery_details.dart';
import 'pages/rider/rider_earnings.dart';
import 'pages/rider/rider_profile.dart';

// =========================================================
// LOGISTICS PAGES
// =========================================================

import 'pages/logistics/logistics_home.dart';
import 'pages/logistics/parcel_scanner.dart';
import 'pages/logistics/parcel_information.dart' as logistics;
import 'pages/logistics/shipment_management.dart';
import 'pages/logistics/logistics_profile.dart';

// =========================================================
// APPLICATION ENTRY POINT
// =========================================================

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Restore saved light/dark theme preference.
  await ThemeController.load();

  runApp(const MobileSukiApp());
}

// =========================================================
// SUKISHOP MOBILE APPLICATION
// =========================================================

class MobileSukiApp extends StatelessWidget {
  const MobileSukiApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeController.mode,
      builder: (context, themeMode, child) {
        return MaterialApp(
          // =================================================
          // APPLICATION CONFIGURATION
          // =================================================
          debugShowCheckedModeBanner: false,

          title: 'Mobile Suki',

          // =================================================
          // THEME CONFIGURATION
          // =================================================
          theme: AppTheme.lightTheme,

          darkTheme: AppTheme.darkTheme,

          themeMode: themeMode,

          builder: (context, child) {
            return AnnotatedRegion<SystemUiOverlayStyle>(
              value: AppTheme.systemOverlayStyle,
              child: child ?? const SizedBox.shrink(),
            );
          },

          // =================================================
          // INITIAL SCREEN
          // =================================================

          // AuthGate determines whether to show:
          //
          // Login Page
          // Buyer Dashboard
          // Rider Dashboard
          // Logistics Dashboard
          //
          // It also verifies remembered sessions
          // through the existing AuthService.
          home: const AuthGate(),

          // =================================================
          // REGISTERED APPLICATION ROUTES
          // =================================================
          routes: _applicationRoutes,

          // =================================================
          // DYNAMIC ROUTES
          // =================================================
          onGenerateRoute: _generateRoute,

          // =================================================
          // UNKNOWN ROUTES
          // =================================================
          onUnknownRoute: _unknownRoute,
        );
      },
    );
  }
}

// =========================================================
// SUKISHOP REGISTERED ROUTES
// =========================================================
//
// Route names are retained for compatibility with
// existing Navigator.pushNamed calls.
//
// =========================================================

final Map<String, WidgetBuilder> _applicationRoutes = {
  // =======================================================
  // AUTHENTICATION
  // =======================================================
  '/login': (context) => const LoginPage(),

  '/register': (context) => const RegisterPage(),

  // =======================================================
  // BUYER REGISTRATION
  // =======================================================
  '/buyer-registration': (context) => const BuyerRegistration(),

  '/register/buyer': (context) => const BuyerRegistration(),

  // =======================================================
  // RIDER REGISTRATION
  // =======================================================
  '/rider-registration': (context) => const RiderRegistration(),

  '/register/rider': (context) => const RiderRegistration(),

  '/rider/apply': (context) => const RiderRegistration(),

  // =======================================================
  // LOGISTICS REGISTRATION
  // =======================================================
  '/logistics-registration': (context) => const LogisticsRegistration(),

  '/register/logistics': (context) => const LogisticsRegistration(),

  // =======================================================
  // BUYER DASHBOARD
  // =======================================================
  '/buyer-home': (context) => const BuyerHomePage(),

  '/buyer/home': (context) => const BuyerHomePage(),

  '/home': (context) => const BuyerHomePage(),

  // =======================================================
  // BUYER PRODUCTS
  // =======================================================
  '/products': (context) => const BuyerProductListPage(),

  '/shop': (context) => const BuyerProductListPage(),

  // =======================================================
  // BUYER CART
  // =======================================================
  '/buyer-cart': (context) => const BuyerCartPage(),

  '/cart': (context) => const BuyerCartPage(),

  // =======================================================
  // BUYER CHECKOUT
  // =======================================================
  '/buyer-checkout': (context) => const BuyerCheckoutPage(),

  '/checkout': (context) => const BuyerCheckoutPage(),

  '/checkout-legacy': (context) => const BuyerCheckoutPage(),

  // =======================================================
  // BUYER PROFILE
  // =======================================================
  '/buyer-profile': (context) => const BuyerProfilePage(),

  '/my-account': (context) => const BuyerProfilePage(),

  '/my-account/addresses': (context) => const BuyerAddressesPage(),

  // =======================================================
  // BUYER ORDERS
  // =======================================================
  '/my-orders': (context) => const BuyerOrderHistoryPage(),

  // =======================================================
  // BUYER WISHLIST
  // =======================================================
  '/wishlist': (context) => const BuyerWishlistPage(),

  // =======================================================
  // FOLLOWED STORES
  // =======================================================
  '/stores/following': (context) => const LaravelRoutePage(
    title: 'Followed Stores',
    description: 'Stores you follow on SUKI.',
    emptyTitle: 'No followed stores yet',
    emptyMessage: 'Visit a store page to follow a seller.',
    icon: Icons.storefront_outlined,
    actionLabel: 'Browse products',
    actionRoute: '/products',
  ),

  // =======================================================
  // BUYER HELP CENTER
  // =======================================================
  '/buyer/help': (context) => const LaravelRoutePage(
    title: 'Help Center',
    description: 'Help with shopping, payments, and deliveries.',
    emptyTitle: 'How can we help?',
    emptyMessage:
        'For order help, open My Orders. '
        'For delivery updates, open the tracking '
        'page from an order.',
    icon: Icons.help_outline,
    actionLabel: 'My Orders',
    actionRoute: '/my-orders',
  ),

  // =======================================================
  // BUYER REVIEWS
  // =======================================================
  '/buyer/reviews': (context) => const LaravelRoutePage(
    title: 'My Reviews',
    description: 'Reviews you have submitted for SUKI orders.',
    emptyTitle: 'No reviews yet',
    emptyMessage:
        'You can review eligible orders '
        'after delivery.',
    icon: Icons.rate_review_outlined,
    actionLabel: 'My Orders',
    actionRoute: '/my-orders',
  ),

  // =======================================================
  // BUYER RETURNS AND REFUNDS
  // =======================================================
  '/return-refunds': (context) => const LaravelRoutePage(
    title: 'Returns & Refunds',
    description:
        'Review return and refund requests '
        'for your orders.',
    emptyTitle: 'No return requests',
    emptyMessage:
        'Eligible return requests will appear '
        'here after you submit one.',
    icon: Icons.assignment_return_outlined,
    actionLabel: 'My Orders',
    actionRoute: '/my-orders',
  ),

  // =======================================================
  // BUYER ORDER SUCCESS
  // =======================================================
  '/buyer/order-success': (context) {
    final arguments = ModalRoute.of(context)?.settings.arguments;

    final String? orderNumber = arguments is Map
        ? arguments['order_number']?.toString()
        : null;

    return LaravelRoutePage(
      title: 'Order Placed',
      description: 'Your order was submitted to SUKI.',
      emptyTitle: 'Thank you for your order',
      emptyMessage: 'You can review its status from My Orders.',
      icon: Icons.check_circle_outline,
      identifier: orderNumber,
      actionLabel: 'My Orders',
      actionRoute: '/my-orders',
    );
  },

  // =======================================================
  // RIDER DASHBOARD
  // =======================================================
  '/rider-home': (context) => const RiderHomePage(),

  '/rider/dashboard': (context) => const RiderHomePage(),

  // =======================================================
  // RIDER DELIVERIES
  // =======================================================
  '/rider-deliveries': (context) => const RiderDeliveriesPage(),

  '/rider/deliveries': (context) => const RiderDeliveriesPage(),

  // =======================================================
  // RIDER SCANNER
  // =======================================================
  '/rider-scanner': (context) => const RiderScannerPage(),

  '/rider/shipment-scanner': (context) => const RiderScannerPage(),

  // =======================================================
  // RIDER PARCEL INFORMATION
  // =======================================================
  '/rider-parcel-information': (context) => const rider.ParcelInformationPage(),

  // =======================================================
  // RIDER DELIVERY DETAILS
  // =======================================================
  '/delivery-details': (context) => const DeliveryDetailsPage(),

  // =======================================================
  // RIDER EARNINGS
  // =======================================================
  '/rider-earnings': (context) => const RiderEarningsPage(),

  '/rider/earnings': (context) => const RiderEarningsPage(),

  '/legacy/rider/earnings': (context) => const RiderEarningsPage(),

  // =======================================================
  // RIDER PROFILE
  // =======================================================
  '/rider-profile': (context) => const RiderProfilePage(),

  '/rider/profile': (context) => const RiderProfilePage(),

  // =======================================================
  // RIDER HISTORY
  // =======================================================
  '/rider/history': (context) => const LaravelRoutePage(
    title: 'Delivery History',
    description: 'Completed and past rider deliveries.',
    emptyTitle: 'No completed deliveries yet',
    emptyMessage: 'Completed deliveries will appear here.',
    icon: Icons.history,
    actionLabel: 'Available deliveries',
    actionRoute: '/rider/deliveries',
  ),

  // =======================================================
  // RIDER REPORTS
  // =======================================================
  '/rider/reports': (context) => const LaravelRoutePage(
    title: 'Rider Reports',
    description: 'Delivery and performance reports.',
    emptyTitle: 'No report data yet',
    emptyMessage: 'Your rider activity will appear in reports.',
    icon: Icons.bar_chart_outlined,
    actionLabel: 'Dashboard',
    actionRoute: '/rider/dashboard',
  ),

  // =======================================================
  // RIDER MONEY REPORT
  // =======================================================
  '/rider/money': (context) => const LaravelRoutePage(
    title: 'Money Report',
    description: 'Your rider earnings and payment summary.',
    emptyTitle: 'No money report yet',
    emptyMessage: 'Earnings data will appear after deliveries.',
    icon: Icons.account_balance_wallet_outlined,
    actionLabel: 'Earnings',
    actionRoute: '/rider/earnings',
  ),

  // =======================================================
  // RIDER COD REMITTANCES
  // =======================================================
  '/rider/cod-remittances': (context) => const LaravelRoutePage(
    title: 'COD Remittances',
    description: 'Cash-on-delivery amounts to remit.',
    emptyTitle: 'No remittances due',
    emptyMessage: 'COD remittance tasks will appear here.',
    icon: Icons.payments_outlined,
    actionLabel: 'Dashboard',
    actionRoute: '/rider/dashboard',
  ),

  // =======================================================
  // RIDER LOGIN
  // =======================================================
  '/rider/login': (context) => const LoginPage(),

  // =======================================================
  // RIDER APPLICATION PENDING
  // =======================================================
  '/rider/application-pending': (context) => const LaravelRoutePage(
    title: 'Application Status',
    description: 'Your SUKI rider application status.',
    emptyTitle: 'Application under review',
    emptyMessage:
        'You can sign in as a rider once '
        'your application is approved.',
    icon: Icons.hourglass_top,
    actionLabel: 'Return to login',
    actionRoute: '/rider/login',
  ),

  // =======================================================
  // LOGISTICS DASHBOARD
  // =======================================================
  '/logistics-home': (context) => const LogisticsHomePage(),

  '/logistics/dashboard': (context) => const LogisticsHomePage(),

  // =======================================================
  // LOGISTICS PARCEL SCANNER
  // =======================================================
  '/parcel-scanner': (context) => const ParcelScannerPage(),

  '/logistics/parcel-scanner': (context) => const ParcelScannerPage(),

  // =======================================================
  // LOGISTICS PARCEL INFORMATION
  // =======================================================
  '/logistics-parcel-information': (context) =>
      const logistics.ParcelInformationPage(),

  // =======================================================
  // LOGISTICS SHIPMENT MANAGEMENT
  // =======================================================
  '/shipment-management': (context) => const ShipmentManagementPage(),

  '/logistics/shipments': (context) => const ShipmentManagementPage(),

  // =======================================================
  // LOGISTICS PROFILE
  // =======================================================
  '/logistics-profile': (context) => const LogisticsProfilePage(),

  '/logistics/profile': (context) => const LogisticsProfilePage(),

  '/logistics/login': (context) => const LoginPage(),
};

// =========================================================
// DYNAMIC ROUTE HANDLER
// =========================================================
//
// Handles parameterized routes from the
// SukiShop Laravel-style navigation.
//
// =========================================================

Route<dynamic>? _generateRoute(RouteSettings settings) {
  final String routeName = settings.name ?? '';

  final Uri? uri = Uri.tryParse(routeName);

  if (uri == null) {
    return null;
  }

  final List<String> segments = uri.pathSegments
      .where((segment) => segment.isNotEmpty)
      .toList();

  // ========================================================
  // PRODUCT DETAILS
  // /product/{slug}
  // ========================================================

  if (segments.length == 2 && segments[0] == 'product') {
    final String slug = segments[1];

    return _pageRoute(settings, BuyerProductRoutePage(slug: slug));
  }

  // ========================================================
  // STORE PRODUCTS
  // /stores/{sellerSlug}
  // ========================================================

  if (segments.length == 2 &&
      segments[0] == 'stores' &&
      segments[1] != 'following') {
    final String sellerSlug = segments[1];

    return _pageRoute(
      settings,
      BuyerProductListPage(sellerSlug: sellerSlug, categoryName: 'Store'),
    );
  }

  // ========================================================
  // BUYER ORDER DETAILS
  // /my-orders/{orderId}
  // ========================================================

  if (segments.length == 2 && segments[0] == 'my-orders') {
    final String orderId = segments[1];

    return _pageRoute(settings, BuyerOrderDetailsPage(orderId: orderId));
  }

  // ========================================================
  // BUYER ORDER TRACKING
  //
  // /my-orders/{orderId}/seller-orders/
  // {sellerOrderId}/track
  // ========================================================

  if (segments.length == 5 &&
      segments[0] == 'my-orders' &&
      segments[2] == 'seller-orders' &&
      segments[4] == 'track') {
    final String orderId = segments[1];
    final String sellerOrderId = segments[3];

    return _pageRoute(
      settings,
      BuyerOrderTrackingPage(orderId: orderId, sellerOrderId: sellerOrderId),
    );
  }

  // ========================================================
  // RETURN / REFUND DETAILS
  // /return-refunds/{requestId}
  // ========================================================

  if (segments.length == 2 && segments[0] == 'return-refunds') {
    final String requestId = segments[1];

    return _pageRoute(
      settings,
      LaravelRoutePage(
        title: 'Return Request',
        description: 'Details for your return or refund request.',
        emptyTitle: 'Request $requestId',
        emptyMessage: 'Request status and updates will appear here.',
        icon: Icons.assignment_return_outlined,
        identifier: requestId,
      ),
    );
  }

  // ========================================================
  // BUYER ORDER COMPLAINT
  // /my-orders/{orderId}/complaints/create
  // ========================================================

  if (segments.length == 4 &&
      segments[0] == 'my-orders' &&
      segments[2] == 'complaints' &&
      segments[3] == 'create') {
    return _pageRoute(
      settings,
      LaravelRoutePage(
        title: 'Order Help',
        description: 'Get help with an issue on your order.',
        emptyTitle: 'Order support',
        emptyMessage: 'Contact SUKI support with your order details.',
        icon: Icons.support_agent_outlined,
        identifier: segments[1],
      ),
    );
  }

  // ========================================================
  // RIDER ACTIVATION
  // /rider/activate/{token}
  // ========================================================

  if (segments.length == 3 &&
      segments[0] == 'rider' &&
      segments[1] == 'activate') {
    // Do not activate an account based on a route alone.
    // The activation token must be validated by Laravel.
    //
    // The token is intentionally not displayed.

    return _pageRoute(
      settings,
      const LaravelRoutePage(
        title: 'Rider Activation',
        description: 'Activate your SUKI rider account.',
        emptyTitle: 'Check your activation link',
        emptyMessage:
            'Open the activation link provided by '
            'SUKI to continue.',
        icon: Icons.verified_user_outlined,
      ),
    );
  }

  // ========================================================
  // RIDER DELIVERY COMPLAINT
  //
  // /rider/erp-orders/{id}/complaints/create
  // ========================================================

  if (segments.length == 5 &&
      segments[0] == 'rider' &&
      segments[1] == 'erp-orders' &&
      segments[3] == 'complaints' &&
      segments[4] == 'create') {
    return _pageRoute(
      settings,
      LaravelRoutePage(
        title: 'Delivery Issue',
        description: 'Report an issue with an assigned delivery.',
        emptyTitle: 'Delivery support',
        emptyMessage: 'Include this reference when contacting support.',
        icon: Icons.report_problem_outlined,
        identifier: segments[2],
      ),
    );
  }

  // ========================================================
  // PRODUCT REVIEWS
  // /reviews/products/{id}
  // ========================================================

  if (segments.length == 3 &&
      segments[0] == 'reviews' &&
      segments[1] == 'products') {
    return _pageRoute(
      settings,
      LaravelRoutePage(
        title: 'Product Reviews',
        description: 'Buyer feedback for this product.',
        emptyTitle: 'Reviews',
        emptyMessage: 'Product reviews will appear here.',
        icon: Icons.rate_review_outlined,
        identifier: segments[2],
      ),
    );
  }

  return null;
}

// =========================================================
// REUSABLE MATERIAL PAGE ROUTE
// =========================================================

MaterialPageRoute<dynamic> _pageRoute(RouteSettings settings, Widget page) {
  return MaterialPageRoute<dynamic>(
    settings: settings,
    builder: (context) => page,
  );
}

// =========================================================
// UNKNOWN ROUTE HANDLER
// =========================================================
//
// Previously, unknown routes redirected to LoginPage.
//
// This can be confusing because users may think their
// session has expired when the actual problem is an
// incorrect or unregistered route.
//
// Show an error page instead.
//
// =========================================================

Route<dynamic> _unknownRoute(RouteSettings settings) {
  return MaterialPageRoute<dynamic>(
    settings: settings,
    builder: (context) {
      return Scaffold(
        appBar: AppBar(title: const Text('Page Not Found')),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.error_outline_rounded,
                      size: 75,
                      color: Theme.of(context).colorScheme.primary,
                    ),

                    const SizedBox(height: 20),

                    Text(
                      'Page Not Found',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),

                    const SizedBox(height: 12),

                    Text(
                      'The requested page is unavailable '
                      'or has not been implemented yet.',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),

                    const SizedBox(height: 25),

                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: () {
                          final navigator = Navigator.of(context);

                          if (navigator.canPop()) {
                            navigator.pop();
                          } else {
                            navigator.pushNamedAndRemoveUntil(
                              '/login',
                              (route) => false,
                            );
                          }
                        },
                        icon: const Icon(Icons.arrow_back_rounded),
                        label: const Text('GO BACK'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
    },
  );
}
