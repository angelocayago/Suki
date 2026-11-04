
import 'package:flutter/material.dart';

import '../../services/order_service.dart';

class BuyerOrderHistoryPage extends StatefulWidget {
  const BuyerOrderHistoryPage({super.key});

  @override
  State<BuyerOrderHistoryPage> createState() => _BuyerOrderHistoryPageState();
}

class _BuyerOrderHistoryPageState extends State<BuyerOrderHistoryPage> {
  late Future<List<Map<String, dynamic>>> ordersFuture;

  @override
  void initState() {
    super.initState();
    ordersFuture = OrderService.getOrders();
  }

  void _reload() {
    setState(() => ordersFuture = OrderService.getOrders());
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Orders'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: ordersFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _OrderMessage(
              message: snapshot.error.toString().replaceFirst('Exception: ', ''),
              onRetry: _reload,
            );
          }

          final orders = snapshot.data ?? const [];
          if (orders.isEmpty) {
            return const _OrderMessage(
              title: 'No orders yet',
              message: 'Your SUKI orders will appear here after checkout.',
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final order = orders[index];
              final id = order['id']?.toString() ?? '';
              final number = order['order_number']?.toString() ?? id;
              final status = order['status']?.toString() ?? 'Processing';
              final total = double.tryParse(
                    order['total_amount']?.toString() ?? '',
                  ) ??
                  0;
              return Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    child: Icon(Icons.inventory_2_outlined),
                  ),
                  title: Text('Order $number'),
                  subtitle: Text(
                    '$status · ${order['item_count'] ?? 0} item(s)',
                  ),
                  trailing: Text('PHP ${total.toStringAsFixed(2)}'),
                  onTap: id.isEmpty
                      ? null
                      : () => Navigator.pushNamed(context, '/my-orders/$id'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class BuyerOrderDetailsPage extends StatefulWidget {
  final String orderId;

  const BuyerOrderDetailsPage({
    super.key,
    required this.orderId,
  });

  @override
  State<BuyerOrderDetailsPage> createState() => _BuyerOrderDetailsPageState();
}

class _BuyerOrderDetailsPageState extends State<BuyerOrderDetailsPage> {
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
      appBar: AppBar(title: const Text('Order Details')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: orderFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _OrderMessage(
              message: snapshot.error.toString().replaceFirst('Exception: ', ''),
            );
          }

          final order = snapshot.data ?? const <String, dynamic>{};
          final items = order['items'] is List
              ? (order['items'] as List).whereType<Map>().toList()
              : const <Map>[];
          final sellerOrders = order['seller_orders'] is List
              ? (order['seller_orders'] as List).whereType<Map>().toList()
              : const <Map>[];
          final sellerOrderId = sellerOrders.isEmpty
              ? widget.orderId
              : sellerOrders.first['id']?.toString() ?? widget.orderId;
          final total = double.tryParse(
                order['total_amount']?.toString() ?? '',
              ) ??
              0;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: ListTile(
                  title: Text('Order ${order['order_number'] ?? widget.orderId}'),
                  subtitle: Text(
                    '${order['status'] ?? 'Processing'}\n${order['placed_at'] ?? ''}',
                  ),
                  isThreeLine: true,
                ),
              ),
              const SizedBox(height: 12),
              Text('Items', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 6),
              ...items.map((rawItem) {
                final item = Map<String, dynamic>.from(rawItem);
                return Card(
                  child: ListTile(
                    title: Text(item['name']?.toString() ?? 'Product'),
                    subtitle: Text('Quantity: ${item['quantity'] ?? 0}'),
                    trailing: Text('PHP ${item['line_total'] ?? item['price'] ?? 0}'),
                  ),
                );
              }),
              const SizedBox(height: 12),
              ListTile(
                title: const Text('Total'),
                trailing: Text('PHP ${total.toStringAsFixed(2)}'),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => Navigator.pushNamed(
                  context,
                  '/my-orders/${widget.orderId}/seller-orders/$sellerOrderId/track',
                ),
                icon: const Icon(Icons.local_shipping_outlined),
                label: const Text('Track order'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _OrderMessage extends StatelessWidget {
  final String title;
  final String message;
  final VoidCallback? onRetry;

  const _OrderMessage({
    this.title = 'Unable to load orders',
    required this.message,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.receipt_long_outlined, size: 44),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 14),
              FilledButton(onPressed: onRetry, child: const Text('Try again')),
            ],
          ],
        ),
      ),
    );
  }
}
