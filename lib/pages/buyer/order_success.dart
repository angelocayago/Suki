import 'package:flutter/material.dart';

class BuyerOrderSuccessPage extends StatelessWidget {
  final String orderNumber;
  final String orderId;

  const BuyerOrderSuccessPage({
    super.key,
    this.orderNumber = '',
    this.orderId = '',
  });

  @override
  Widget build(BuildContext context) {
    final hasOrderId = orderId.trim().isNotEmpty;
    final hasOrderNumber = orderNumber.trim().isNotEmpty;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Order Confirmation'),
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 450),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.check_circle_rounded,
                    size: 95,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(height: 22),
                  Text(
                    'Order Placed Successfully!',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Thank you for shopping with SUKI SHOP! '
                    'Your order has been received.',
                    textAlign: TextAlign.center,
                  ),
                  if (hasOrderNumber) ...[
                    const SizedBox(height: 20),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Text('Order Number'),
                            const SizedBox(height: 6),
                            SelectableText(
                              orderNumber,
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 28),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: hasOrderId
                          ? () => Navigator.pushNamed(
                              context,
                              '/my-orders/${Uri.encodeComponent(orderId)}',
                            )
                          : () => Navigator.pushNamed(context, '/my-orders'),
                      icon: const Icon(Icons.receipt_long_outlined),
                      label: const Text('View My Order'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pushNamedAndRemoveUntil(
                          context,
                          '/buyer-home',
                          (route) => false,
                        );
                      },
                      icon: const Icon(Icons.shopping_bag_outlined),
                      label: const Text('Continue Shopping'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
