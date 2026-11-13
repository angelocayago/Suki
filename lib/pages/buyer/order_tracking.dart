
import 'package:flutter/material.dart';

import '../../services/order_service.dart';

class BuyerOrderTrackingPage extends StatefulWidget {
  final String orderId;
  final String sellerOrderId;

  const BuyerOrderTrackingPage({
    super.key,
    required this.orderId,
    required this.sellerOrderId,
  });

  @override
  State<BuyerOrderTrackingPage> createState() => _BuyerOrderTrackingPageState();
}

class _BuyerOrderTrackingPageState extends State<BuyerOrderTrackingPage> {
  late Future<Map<String, dynamic>> orderFuture;

  @override
  void initState() {
    super.initState();
    orderFuture = OrderService.getTracking(widget.orderId, widget.sellerOrderId);
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Track Order')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: orderFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  snapshot.error.toString().replaceFirst('Exception: ', ''),
                  textAlign: TextAlign.center,
                ),
              ),
            );
          }

          final tracking = snapshot.data ?? const <String, dynamic>{};
          final status = (tracking['status']?.toString() ?? 'pending').toLowerCase();
          final currentStep = status == 'delivered' || status == 'completed'
              ? 3
              : status.contains('delivery') ||
                      status.contains('rider') ||
                      status.contains('pickup') ||
                      status.contains('ship') ||
                      status.contains('sorting')
                  ? 2
                  : status.contains('confirm') || status.contains('prepar')
                      ? 1
                      : 0;
          const steps = ['Order placed', 'Preparing', 'On the way', 'Delivered'];
          final latitude = tracking['latitude']?.toString();
          final longitude = tracking['longitude']?.toString();
          final updatedAt = tracking['updated_at']?.toString();

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.local_shipping_outlined),
                   title: Text('Tracking ${tracking['tracking_code'] ?? widget.sellerOrderId}'),
                  subtitle: Text(
                     'Status: ${tracking['status_label'] ?? tracking['status'] ?? 'Pending'}'
                     '\nOrder: ${widget.orderId}',
                  ),
                ),
              ),
              if (tracking['trackable'] == true) ...[
                const SizedBox(height: 10),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.my_location),
                    title: const Text('Rider location'),
                    subtitle: Text(
                      latitude == null || longitude == null
                          ? 'Location has not been shared yet.'
                          : '$latitude, $longitude${updatedAt == null ? '' : '\nUpdated: $updatedAt'}',
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 20),
              ...List.generate(steps.length, (index) {
                final complete = index <= currentStep;
                return ListTile(
                  leading: Icon(
                    complete ? Icons.check_circle : Icons.radio_button_unchecked,
                    color: complete
                        ? Theme.of(context).colorScheme.primary
                        : Theme.of(context).disabledColor,
                  ),
                  title: Text(steps[index]),
                  subtitle: index == currentStep
                      ? const Text('Current order status')
                      : null,
                );
              }),
            ],
          );
        },
      ),
    );
  }
}
