
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

// =========================================================
// THEME
// =========================================================

import 'theme/app_theme.dart';
import 'theme/theme_controller.dart';

// =========================================================
// AUTH PAGES
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
// MAIN
// =========================================================

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ThemeController.load();

  runApp(const MobileSukiApp());
}

// =========================================================
// MOBILE SUKI APPLICATION
// =========================================================

class MobileSukiApp extends StatelessWidget {
  const MobileSukiApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeController.mode,
      builder: (context, themeMode, _) => MaterialApp(
      debugShowCheckedModeBanner: false,

      title: 'Mobile Suki',

      // =====================================================
      // APP THEME
      // =====================================================

      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: themeMode,
      builder: (_, child) => AnnotatedRegion<SystemUiOverlayStyle>(
        value: AppTheme.systemOverlayStyle,
        child: child ?? const SizedBox.shrink(),
      ),

      // =====================================================
      // INITIAL PAGE
      // =====================================================

      home: const AuthGate(),

      // =====================================================
      // APPLICATION ROUTES
      // =====================================================

      routes: {
        // ===============================================
        // AUTHENTICATION
        // ===============================================

        '/login': (context) => const LoginPage(),

        '/register': (context) => const RegisterPage(),

        // ===============================================
        // BUYER REGISTRATION
        // ===============================================

        '/buyer-registration': (context) =>
            const BuyerRegistration(),

        '/register/buyer': (context) => const BuyerRegistration(),

        // ===============================================
        // RIDER REGISTRATION
        // ===============================================

        '/rider-registration': (context) =>
            const RiderRegistration(),

        // ===============================================
        // LOGISTICS REGISTRATION
        // ===============================================

        '/logistics-registration': (context) =>
            const LogisticsRegistration(),

        // ===============================================
        // BUYER
        // ===============================================

        '/buyer-home': (context) =>
            const BuyerHomePage(),

        '/home': (context) => const BuyerHomePage(),

        '/buyer/home': (context) => const BuyerHomePage(),

        '/buyer-profile': (context) =>
            const BuyerProfilePage(),

        '/buyer-cart': (context) => const BuyerCartPage(),

        '/buyer-checkout': (context) => const BuyerCheckoutPage(),

        // Laravel buyer web routes mapped to their mobile equivalents.
        '/products': (context) => const BuyerProductListPage(),
        '/shop': (context) => const BuyerProductListPage(),
        '/cart': (context) => const BuyerCartPage(),
        '/checkout': (context) => const BuyerCheckoutPage(),
        '/checkout-legacy': (context) => const BuyerCheckoutPage(),
        '/my-account': (context) => const BuyerProfilePage(),
        '/my-orders': (context) => const BuyerOrderHistoryPage(),
        '/my-account/addresses': (context) => const BuyerAddressesPage(),
        '/wishlist': (context) => const BuyerWishlistPage(),
        '/stores/following': (context) => const LaravelRoutePage(
              title: 'Followed Stores',
              description: 'Stores you follow on SUKI.',
              emptyTitle: 'No followed stores yet',
              emptyMessage: 'Visit a store page to follow a seller.',
              icon: Icons.storefront_outlined,
              actionLabel: 'Browse products',
              actionRoute: '/products',
            ),
        '/buyer/help': (context) => const LaravelRoutePage(
              title: 'Help Center',
              description: 'Help with shopping, payments, and deliveries.',
              emptyTitle: 'How can we help?',
              emptyMessage:
                  'For order help, open My Orders. For delivery updates, open the tracking page from an order.',
              icon: Icons.help_outline,
              actionLabel: 'My Orders',
              actionRoute: '/my-orders',
            ),
        '/buyer/reviews': (context) => const LaravelRoutePage(
              title: 'My Reviews',
              description: 'Reviews you have submitted for SUKI orders.',
              emptyTitle: 'No reviews yet',
              emptyMessage: 'You can review eligible orders after delivery.',
              icon: Icons.rate_review_outlined,
              actionLabel: 'My Orders',
              actionRoute: '/my-orders',
            ),
        '/return-refunds': (context) => const LaravelRoutePage(
              title: 'Returns & Refunds',
              description: 'Review return and refund requests for your orders.',
              emptyTitle: 'No return requests',
              emptyMessage:
                  'Eligible return requests will appear here after you submit one.',
              icon: Icons.assignment_return_outlined,
              actionLabel: 'My Orders',
              actionRoute: '/my-orders',
            ),
        '/buyer/order-success': (context) {
          final arguments = ModalRoute.of(context)?.settings.arguments;
          final orderNumber = arguments is Map
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

        // ===============================================
        // RIDER
        // ===============================================

        '/rider-home': (context) =>
            const RiderHomePage(),

        '/rider/dashboard': (context) => const RiderHomePage(),

        '/rider-deliveries': (context) =>
            const RiderDeliveriesPage(),

        '/rider/deliveries': (context) => const RiderDeliveriesPage(),

        '/rider-scanner': (context) =>
            const RiderScannerPage(),

        '/rider/shipment-scanner': (context) => const RiderScannerPage(),

        '/rider-parcel-information': (context) =>
            const rider.ParcelInformationPage(),

        '/delivery-details': (context) =>
            const DeliveryDetailsPage(),

        '/rider-earnings': (context) =>
            const RiderEarningsPage(),

        '/rider/earnings': (context) => const RiderEarningsPage(),

        '/legacy/rider/earnings': (context) => const RiderEarningsPage(),

        '/rider/history': (context) => const LaravelRoutePage(
              title: 'Delivery History',
              description: 'Completed and past rider deliveries.',
              emptyTitle: 'No completed deliveries yet',
              emptyMessage: 'Completed deliveries will appear here.',
              icon: Icons.history,
              actionLabel: 'Available deliveries',
              actionRoute: '/rider/deliveries',
            ),
        '/rider/reports': (context) => const LaravelRoutePage(
              title: 'Rider Reports',
              description: 'Delivery and performance reports.',
              emptyTitle: 'No report data yet',
              emptyMessage: 'Your rider activity will appear in reports.',
              icon: Icons.bar_chart_outlined,
              actionLabel: 'Dashboard',
              actionRoute: '/rider/dashboard',
            ),
        '/rider/money': (context) => const LaravelRoutePage(
              title: 'Money Report',
              description: 'Your rider earnings and payment summary.',
              emptyTitle: 'No money report yet',
              emptyMessage: 'Earnings data will appear after deliveries.',
              icon: Icons.account_balance_wallet_outlined,
              actionLabel: 'Earnings',
              actionRoute: '/rider/earnings',
            ),
        '/rider/cod-remittances': (context) => const LaravelRoutePage(
              title: 'COD Remittances',
              description: 'Cash-on-delivery amounts to remit.',
              emptyTitle: 'No remittances due',
              emptyMessage: 'COD remittance tasks will appear here.',
              icon: Icons.payments_outlined,
              actionLabel: 'Dashboard',
              actionRoute: '/rider/dashboard',
            ),

        '/rider/login': (context) => const LoginPage(),

        '/rider/apply': (context) => const RiderRegistration(),

        '/rider/application-pending': (context) => const LaravelRoutePage(
              title: 'Application Status',
              description: 'Your SUKI rider application status.',
              emptyTitle: 'Application under review',
              emptyMessage:
                  'You can sign in as a rider once your application is approved.',
              icon: Icons.hourglass_top,
              actionLabel: 'Return to login',
              actionRoute: '/rider/login',
            ),

        '/rider-profile': (context) =>
            const RiderProfilePage(),

        '/rider/profile': (context) => const RiderProfilePage(),

        // ===============================================
        // LOGISTICS
        // ===============================================

        '/logistics-home': (context) =>
            const LogisticsHomePage(),

        '/parcel-scanner': (context) =>
            const ParcelScannerPage(),

        '/logistics-parcel-information': (context) =>
            const logistics.ParcelInformationPage(),

        '/shipment-management': (context) =>
            const ShipmentManagementPage(),

        '/logistics-profile': (context) =>
            const LogisticsProfilePage(),
      },

      // =====================================================
      // UNKNOWN ROUTE FALLBACK
      // =====================================================

      onUnknownRoute: (settings) {
        return MaterialPageRoute(
          builder: (context) => const LoginPage(),
        );
      },

      onGenerateRoute: (settings) {
        final uri = Uri.tryParse(settings.name ?? '');
        final segments = uri?.pathSegments ?? const <String>[];

        if (segments.length == 2 && segments.first == 'product') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => BuyerProductRoutePage(slug: segments[1]),
          );
        }

        if (segments.length == 2 && segments.first == 'stores') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => BuyerProductListPage(
              sellerSlug: segments[1],
              categoryName: 'Store',
            ),
          );
        }

        if (segments.length == 2 && segments.first == 'my-orders') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => BuyerOrderDetailsPage(orderId: segments[1]),
          );
        }

        if (segments.length == 5 &&
            segments[0] == 'my-orders' &&
            segments[2] == 'seller-orders' &&
            segments[4] == 'track') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => BuyerOrderTrackingPage(
              orderId: segments[1],
              sellerOrderId: segments[3],
            ),
          );
        }

        if (segments.length == 2 && segments.first == 'return-refunds') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => LaravelRoutePage(
              title: 'Return Request',
              description: 'Details for your return or refund request.',
              emptyTitle: 'Request ${segments[1]}',
              emptyMessage: 'Request status and updates will appear here.',
              icon: Icons.assignment_return_outlined,
              identifier: segments[1],
            ),
          );
        }

        if (segments.length == 4 &&
            segments[0] == 'my-orders' &&
            segments[2] == 'complaints' &&
            segments[3] == 'create') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => LaravelRoutePage(
              title: 'Order Help',
              description: 'Get help with an issue on your order.',
              emptyTitle: 'Order support',
              emptyMessage: 'Contact SUKI support with your order details.',
              icon: Icons.support_agent_outlined,
              identifier: segments[1],
            ),
          );
        }

        if (segments.length == 3 &&
            segments[0] == 'rider' &&
            segments[1] == 'activate') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => const LaravelRoutePage(
              title: 'Rider Activation',
              description: 'Activate your SUKI rider account.',
              emptyTitle: 'Check your activation link',
              emptyMessage:
                  'Open the activation link provided by SUKI to continue.',
              icon: Icons.verified_user_outlined,
            ),
          );
        }

        if (segments.length == 5 &&
            segments[0] == 'rider' &&
            segments[1] == 'erp-orders' &&
            segments[3] == 'complaints' &&
            segments[4] == 'create') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => LaravelRoutePage(
              title: 'Delivery Issue',
              description: 'Report an issue with an assigned delivery.',
              emptyTitle: 'Delivery support',
              emptyMessage: 'Include this reference when contacting support.',
              icon: Icons.report_problem_outlined,
              identifier: segments[2],
            ),
          );
        }

        if (segments.length == 3 &&
            segments[0] == 'reviews' &&
            segments[1] == 'products') {
          return MaterialPageRoute(
            settings: settings,
            builder: (_) => LaravelRoutePage(
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
      },
      ),
    );
  }
}
