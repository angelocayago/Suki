import 'package:flutter/material.dart';

import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';

class RiderDeliveriesPage extends StatefulWidget {
  const RiderDeliveriesPage({super.key});

  @override
  State<RiderDeliveriesPage> createState() => _RiderDeliveriesPageState();
}

class _RiderDeliveriesPageState extends State<RiderDeliveriesPage> {
  late Future<List<Map<String, dynamic>>> _deliveries;
  final Set<String> _busyOrders = {};

  @override
  void initState() {
    super.initState();
    _deliveries = RiderService.getDeliveries();
  }

  Future<void> _reload() async {
    final request = RiderService.getDeliveries();
    setState(() => _deliveries = request);
    try {
      await request;
    } catch (_) {
      // The FutureBuilder presents the API error and retry button.
    }
  }

  String _text(Map<String, dynamic> order, String key, [String fallback = '']) {
    final value = order[key]?.toString().trim() ?? '';
    return value.isEmpty ? fallback : value;
  }

  String _status(Map<String, dynamic> order) =>
      _text(order, 'status', 'unknown').toLowerCase();

  Future<void> _respond(
    Map<String, dynamic> order, {
    required bool accept,
    required bool pickup,
  }) async {
    final id = _text(order, 'id');
    if (id.isEmpty || _busyOrders.contains(id)) return;
    setState(() => _busyOrders.add(id));
    try {
      if (pickup) {
        if (accept) {
          await RiderService.acceptPickup(id);
        } else {
          await RiderService.declinePickup(id);
        }
      } else if (accept) {
        await RiderService.acceptDelivery(id);
      } else {
        await RiderService.declineDelivery(id);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(accept ? 'Assignment accepted.' : 'Assignment declined.')),
        );
        await _reload();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyOrders.remove(id));
    }
  }

  void _openOrder(Map<String, dynamic> order) {
    Navigator.pushNamed(context, '/delivery-details', arguments: order).then((_) {
      if (mounted) _reload();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,
      appBar: AppBar(
        title: Text('My Deliveries', style: RiderStyle.appTitle),
        centerTitle: true,
        actions: [
          IconButton(onPressed: _reload, icon: const Icon(Icons.refresh), tooltip: 'Refresh'),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _deliveries,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _MessageState(
              message: snapshot.error.toString().replaceFirst('Exception: ', ''),
              onRetry: _reload,
            );
          }
          final orders = snapshot.data ?? const <Map<String, dynamic>>[];
          if (orders.isEmpty) {
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  _MessageState(message: 'No deliveries are assigned to you right now.'),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: orders.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) => _deliveryCard(orders[index]),
            ),
          );
        },
      ),
    );
  }

  Widget _deliveryCard(Map<String, dynamic> order) {
    final status = _status(order);
    final id = _text(order, 'id');
    final shipmentId = _text(order, 'shipment_id');
    final isPickup = status == 'ready_for_pickup';
    final pickupAccepted = _text(order, 'pickup_accepted_at').isNotEmpty;
    final deliveryAccepted = _text(order, 'delivery_accepted_at').isNotEmpty;
    final canAccept = (isPickup && !pickupAccepted) ||
        (status == 'assigned_to_rider' && !deliveryAccepted);
    final busy = _busyOrders.contains(id);
    final address = _text(order, 'address', _text(order, 'navigation_address', 'Address unavailable'));
    final amount = order['total']?.toString() ?? '—';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Order ${_text(order, 'order_number', id)}',
                    style: RiderStyle.cardTitle,
                  ),
                ),
                Chip(label: Text(status.replaceAll('_', ' ').toUpperCase())),
              ],
            ),
            const SizedBox(height: 10),
            _info(Icons.person_outline, 'Buyer', _text(order, 'buyer_name', _text(order, 'customer_name', '—'))),
            _info(Icons.store_outlined, 'Pickup', _text(order, 'pickup_address', '—')),
            _info(Icons.location_on_outlined, 'Destination', address),
            _info(Icons.payments_outlined, 'Collection / amount', '₱$amount'),
            const SizedBox(height: 12),
            if (isPickup && pickupAccepted)
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: busy || shipmentId.isEmpty
                      ? null
                      : () => _confirmPickup(order),
                  child: const Text('Confirm parcel pickup'),
                ),
              )
            else if (canAccept)
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy ? null : () => _respond(order, accept: false, pickup: isPickup),
                      child: const Text('Decline'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: busy ? null : () => _respond(order, accept: true, pickup: isPickup),
                      child: busy
                          ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(isPickup ? 'Accept pickup' : 'Accept delivery'),
                    ),
                  ),
                ],
              )
            else
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => _openOrder(order),
                  icon: const Icon(Icons.local_shipping_outlined),
                  label: const Text('Open delivery'),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmPickup(Map<String, dynamic> order) async {
    final id = _text(order, 'id');
    final shipmentId = _text(order, 'shipment_id');
    if (id.isEmpty || shipmentId.isEmpty || _busyOrders.contains(id)) return;
    setState(() => _busyOrders.add(id));
    try {
      await RiderService.scannerAction(shipmentId, 'confirm_pickup');
      if (mounted) await _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _busyOrders.remove(id));
    }
  }

  Widget _info(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: RiderStyle.primary),
          const SizedBox(width: 8),
          Expanded(child: Text('$label: $value')),
        ],
      ),
    );
  }
}

class _MessageState extends StatelessWidget {
  const _MessageState({required this.message, this.onRetry});

  final String message;
  final Future<void> Function()? onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.local_shipping_outlined, size: 38),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              if (onRetry != null) ...[
                const SizedBox(height: 12),
                FilledButton(onPressed: onRetry, child: const Text('Try again')),
              ],
            ],
          ),
        ),
      );
}
