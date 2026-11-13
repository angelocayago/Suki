import 'package:flutter/material.dart';

import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';

class RiderEarningsPage extends StatefulWidget {
  const RiderEarningsPage({super.key});

  @override
  State<RiderEarningsPage> createState() => _RiderEarningsPageState();
}

class _RiderEarningsPageState extends State<RiderEarningsPage> {
  late Future<Map<String, dynamic>> _performance;

  @override
  void initState() {
    super.initState();
    _performance = RiderService.getPerformance();
  }

  Future<void> _reload() async {
    final request = RiderService.getPerformance();
    setState(() => _performance = request);
    try {
      await request;
    } catch (_) {}
  }

  int _integer(dynamic value) => int.tryParse(value?.toString() ?? '') ?? 0;

  String _money(dynamic minor) =>
      '₱${(_integer(minor) / 100).toStringAsFixed(2)}';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,
      appBar: AppBar(
        title: Text('Rider Earnings', style: RiderStyle.appTitle),
        actions: [IconButton(onPressed: _reload, icon: const Icon(Icons.refresh))],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _performance,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _message(snapshot.error.toString().replaceFirst('Exception: ', ''), _reload);
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          final raw = data['performance'];
          final performance = raw is Map
              ? Map<String, dynamic>.from(raw)
              : data;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(18),
              children: [
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: RiderStyle.statusCard(),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Delivery fee earnings', style: RiderStyle.normalText),
                      const SizedBox(height: 8),
                      Text(_money(performance['shippingFeeEarningsMinor']), style: RiderStyle.amount),
                      const SizedBox(height: 6),
                      Text('${_integer(performance['completedDeliveries'])} completed deliveries'),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                _metric('Assigned shipments', '${_integer(performance['assigned'])}', Icons.local_shipping_outlined),
                _metric('Pending deliveries', '${_integer(performance['pending'])}', Icons.pending_actions_outlined),
                _metric('Failed attempts', '${_integer(performance['failedAttempts'])}', Icons.report_outlined),
                _metric('COD collected', _money(performance['codCollectedMinor']), Icons.payments_outlined),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () => Navigator.pushNamed(context, '/rider/history'),
                  icon: const Icon(Icons.history),
                  label: const Text('View delivery history'),
                ),
                OutlinedButton.icon(
                  onPressed: () => Navigator.pushNamed(context, '/rider/money'),
                  icon: const Icon(Icons.account_balance_wallet_outlined),
                  label: const Text('Open money report'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _metric(String label, String value, IconData icon) => Card(
        child: ListTile(
          leading: Icon(icon, color: RiderStyle.primary),
          title: Text(label),
          trailing: Text(value, style: RiderStyle.cardTitle),
        ),
      );

  Widget _message(String message, Future<void> Function() retry) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: retry, child: const Text('Try again')),
            ],
          ),
        ),
      );
}
