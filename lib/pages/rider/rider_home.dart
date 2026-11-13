import 'package:flutter/material.dart';

import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';
import '../../widgets/theme_toggle_button.dart';

class RiderHomePage extends StatefulWidget {
  const RiderHomePage({super.key});

  @override
  State<RiderHomePage> createState() => _RiderHomePageState();
}

class _RiderHomePageState extends State<RiderHomePage> {
  late Future<Map<String, dynamic>> _dashboard;
  bool _availabilityBusy = false;
  int selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    _dashboard = RiderService.getDashboard();
  }

  Future<void> _reload() async {
    final request = RiderService.getDashboard();
    setState(() => _dashboard = request);
    try {
      await request;
    } catch (_) {
      // The FutureBuilder presents the API error.
    }
  }

  String _text(dynamic value, [String fallback = '']) {
    final result = value?.toString().trim() ?? '';
    return result.isEmpty ? fallback : result;
  }

  Future<void> _setAvailability(bool online) async {
    if (_availabilityBusy) return;
    setState(() => _availabilityBusy = true);
    try {
      await RiderService.updateAvailability(online);
      if (mounted) await _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _availabilityBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _dashboard,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        if (snapshot.hasError) {
          return Scaffold(
            appBar: AppBar(title: const Text('Rider Dashboard')),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snapshot.error.toString().replaceFirst('Exception: ', ''), textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    FilledButton(onPressed: _reload, child: const Text('Try again')),
                  ],
                ),
              ),
            ),
          );
        }

        final data = snapshot.data ?? const <String, dynamic>{};
        final application = data['application'] is Map
            ? Map<String, dynamic>.from(data['application'] as Map)
            : const <String, dynamic>{};
        final orders = data['orders'] is List
            ? (data['orders'] as List)
                .whereType<Map>()
                .map((item) => Map<String, dynamic>.from(item))
                .toList()
            : <Map<String, dynamic>>[];
        final availability = _text(data['availability'] ?? application['availability'], 'OFFLINE').toUpperCase();
        final online = availability == 'ONLINE';
        final current = orders.cast<Map<String, dynamic>?>().firstWhere(
              (order) => order != null &&
                  ['assigned_to_rider', 'out_for_delivery', 'picked_up'].contains(
                    _text(order['status']).toLowerCase(),
                  ),
              orElse: () => null,
            );

        return Scaffold(
          backgroundColor: RiderStyle.background,
          body: SafeArea(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Hello ${_text(application['first_name'], 'Rider')}', style: RiderStyle.greeting),
                            Text('Rider Dashboard', style: RiderStyle.appTitle),
                          ],
                        ),
                      ),
                      const ThemeToggleButton(),
                      IconButton(onPressed: _reload, tooltip: 'Refresh', icon: const Icon(Icons.refresh)),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(15),
                    decoration: RiderStyle.statusCard(),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Delivery status', style: RiderStyle.cardTitle),
                            const SizedBox(height: 5),
                            Text(
                              online ? 'You are online' : 'You are offline',
                              style: TextStyle(
                                color: online ? RiderStyle.success : RiderStyle.danger,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                        _availabilityBusy
                            ? const SizedBox.square(dimension: 22, child: CircularProgressIndicator(strokeWidth: 2))
                            : Switch(
                                value: online,
                                activeColor: RiderStyle.primary,
                                onChanged: _setAvailability,
                              ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text('Current delivery', style: RiderStyle.sectionTitle),
                  const SizedBox(height: 10),
                  if (current == null)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(18),
                        child: Text(orders.isEmpty ? 'No active delivery assigned.' : 'Open My Deliveries to review assigned parcels.'),
                      ),
                    )
                  else
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Order ${_text(current['order_number'], _text(current['id']))}', style: RiderStyle.cardTitle),
                            const SizedBox(height: 10),
                            Text('Pickup: ${_text(current['pickup_address'], '—')}'),
                            const SizedBox(height: 6),
                            Text('Destination: ${_text(current['address'], _text(current['navigation_address'], '—'))}'),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                onPressed: () => Navigator.pushNamed(context, '/delivery-details', arguments: current),
                                child: const Text('View delivery'),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: 22),
                  Text('Quick actions', style: RiderStyle.sectionTitle),
                  const SizedBox(height: 10),
                  Row(children: [
                    _actionCard(Icons.local_shipping_outlined, 'Deliveries', '/rider-deliveries'),
                    const SizedBox(width: 10),
                    _actionCard(Icons.qr_code_scanner, 'Scan parcel', '/rider-scanner'),
                  ]),
                  const SizedBox(height: 12),
                  Row(children: [
                    _actionCard(Icons.wallet_outlined, 'Earnings', '/rider-earnings'),
                    const SizedBox(width: 10),
                    _actionCard(Icons.person_outline, 'Profile', '/rider-profile'),
                  ]),
                  const SizedBox(height: 12),
                  Row(children: [
                    _actionCard(Icons.history, 'History', '/rider/history'),
                    const SizedBox(width: 10),
                    _actionCard(Icons.bar_chart_outlined, 'Reports', '/rider/reports'),
                  ]),
                  const SizedBox(height: 12),
                  Row(children: [
                    _actionCard(Icons.payments_outlined, 'COD remittance', '/rider/cod-remittances'),
                    const SizedBox(width: 10),
                    _actionCard(Icons.account_balance_wallet_outlined, 'Money report', '/rider/money'),
                  ]),
                ],
              ),
            ),
          ),
          bottomNavigationBar: BottomNavigationBar(
            currentIndex: selectedIndex,
            selectedItemColor: RiderStyle.primary,
            onTap: (value) {
              const routes = ['/rider/dashboard', '/rider/deliveries', '/rider/earnings', '/rider-profile'];
              setState(() => selectedIndex = value);
              if (value != 0) Navigator.pushReplacementNamed(context, routes[value]);
            },
            items: const [
              BottomNavigationBarItem(icon: Icon(Icons.home_outlined), label: 'Home'),
              BottomNavigationBarItem(icon: Icon(Icons.local_shipping_outlined), label: 'Deliveries'),
              BottomNavigationBarItem(icon: Icon(Icons.wallet_outlined), label: 'Earnings'),
              BottomNavigationBarItem(icon: Icon(Icons.person_outline), label: 'Profile'),
            ],
          ),
        );
      },
    );
  }

  Widget _actionCard(IconData icon, String title, String route) => Expanded(
        child: InkWell(
          onTap: () => Navigator.pushNamed(context, route),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            height: 88,
            decoration: RiderStyle.cardDecoration(),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: RiderStyle.primary),
                const SizedBox(height: 7),
                Text(title, style: RiderStyle.cardTitle, textAlign: TextAlign.center),
              ],
            ),
          ),
        ),
      );
}
