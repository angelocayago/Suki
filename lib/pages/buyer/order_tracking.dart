
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
    orderFuture = OrderService.getOrder(widget.orderId);
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

          final order = snapshot.data ?? const <String, dynamic>{};
          final status = (order['status']?.toString() ?? 'pending').toLowerCase();
          final currentStep = status.contains('deliver')
              ? 3
              : status.contains('pickup') || status.contains('ship')
                  ? 2
                  : status.contains('confirm') || status.contains('prepar')
                      ? 1
                      : 0;
          const steps = ['Order placed', 'Preparing', 'On the way', 'Delivered'];

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.local_shipping_outlined),
                  title: Text('Order ${order['order_number'] ?? widget.orderId}'),
                  subtitle: Text(
                    'Status: ${order['status'] ?? 'Pending'}'
                    '\nShipment: ${widget.sellerOrderId}',
                  ),
                ),
              ),
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
