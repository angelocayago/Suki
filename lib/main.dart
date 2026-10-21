import 'package:flutter/material.dart';

// Theme
import 'theme/app_theme.dart';

// Auth Pages
import 'pages/auth/loginpage.dart';
import 'pages/auth/registerpage.dart';
import 'pages/buyer/buyer_home.dart';
import 'pages/rider/rider_home.dart';
import 'pages/rider/rider_deliveries.dart';
import 'pages/rider/rider_scanner.dart';
import 'pages/rider/parcel_information.dart' as rider;
import 'pages/rider/delivery_details.dart';
import 'pages/rider/rider_earnings.dart';
import 'pages/rider/rider_profile.dart';
import 'pages/logistics/logistics_home.dart';
import 'pages/logistics/parcel_scanner.dart';
import 'pages/logistics/parcel_information.dart' as logistics;
import 'pages/logistics/shipment_management.dart';
import 'pages/logistics/logistics_profile.dart';

void main() {
  runApp(const MobileSukiApp());
}

class MobileSukiApp extends StatelessWidget {
  const MobileSukiApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,

      title: 'Mobile Suki',

      // =========================
      // APP THEME
      // =========================
      theme: AppTheme.lightTheme,

      // =========================
      // INITIAL PAGE
      // =========================
      initialRoute: '/login',

      // =========================
      // ROUTES
      // =========================
      routes: {
        '/login': (context) => const LoginPage(),
        '/register': (context) => const RegisterPage(),
        '/buyer-home': (context) => const BuyerHomePage(),
        '/rider-home': (context) => const RiderHomePage(),
        '/rider-deliveries': (context) => const RiderDeliveriesPage(),
        '/rider-scanner': (context) => const RiderScannerPage(),
        '/rider-parcel-information': (context) =>
            const rider.ParcelInformationPage(),
        '/delivery-details': (context) => const DeliveryDetailsPage(),
        '/rider-earnings': (context) => const RiderEarningsPage(),
        '/rider-profile': (context) => const RiderProfilePage(),
        '/logistics-home': (context) => const LogisticsHomePage(),
        '/parcel-scanner': (context) => const ParcelScannerPage(),
        '/logistics-parcel-information': (context) =>
            const logistics.ParcelInformationPage(),
        '/shipment-management': (context) => const ShipmentManagementPage(),
        '/logistics-profile': (context) => const LogisticsProfilePage(),
      },

      // =========================
      // UNKNOWN ROUTE FALLBACK
      // =========================
      onUnknownRoute: (settings) {
        return MaterialPageRoute(builder: (context) => const LoginPage());
      },
    );
  }
}
