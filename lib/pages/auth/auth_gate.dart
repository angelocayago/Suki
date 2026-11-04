import 'package:flutter/material.dart';

import '../../services/auth_service.dart';
import '../buyer/buyer_home.dart';
import '../logistics/logistics_home.dart';
import '../rider/rider_home.dart';
import 'loginpage.dart';

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  String? _destination;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    var destination = '/login';

    try {
      final rememberMe = await AuthService.isRememberMeEnabled();
      final token = await AuthService.getToken();

      if (!rememberMe) {
        await AuthService.clearToken();
      } else if (token != null && token.isNotEmpty) {
        // Restore from secure storage immediately. Requiring a network call
        // here can send a valid saved session back to login when the API is
        // temporarily unavailable during app startup.
        destination =
            await AuthService.getRememberedHomeRoute() ?? '/buyer-home';
      }
    } catch (_) {
      destination = '/login';
    }

    if (mounted) {
      setState(() => _destination = destination);
    }
  }

  Widget _pageForDestination(String destination) {
    switch (destination) {
      case '/rider-home':
        return const RiderHomePage();
      case '/logistics-home':
        return const LogisticsHomePage();
      case '/buyer-home':
        return const BuyerHomePage();
      default:
        return const LoginPage();
    }
  }

  @override
  Widget build(BuildContext context) {
    final destination = _destination;
    if (destination != null) {
      return _pageForDestination(destination);
    }

    return Scaffold(
      body: Center(
        child: CircularProgressIndicator(
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
    );
  }
}
