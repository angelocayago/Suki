import 'package:flutter/material.dart';

import '../../services/order_service.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER ORDER HISTORY
// =========================================================
//
// Features:
// - Laravel order history
// - Order details
// - Pull-to-refresh
// - Retry on API errors
// - Safe order data parsing
// - Seller order tracking navigation
//
// Existing OrderService and routes are preserved.
// =========================================================

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

  Future<void> _reload() async {
    final request = OrderService.getOrders();

    if (mounted) {
      setState(() {
        ordersFuture = request;
      });
    }

    try {
      await request;
    } catch (_) {
      // FutureBuilder displays API errors.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Orders'),
        actions: [
          IconButton(
            tooltip: 'Refresh orders',
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
              message: _OrderHelpers.error(snapshot.error),
              onRetry: _reload,
            );
          }

          final orders = snapshot.data ?? <Map<String, dynamic>>[];

          if (orders.isEmpty) {
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 100),
                  _OrderMessage(
                    title: 'No orders yet',
                    message:
                        'Your SUKI orders will appear '
                        'here after checkout.',
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _reload,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 750),
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  padding: const EdgeInsets.all(16),
                  itemCount: orders.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    return _orderCard(orders[index]);
                  },
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  // =========================================================
  // ORDER CARD
  // =========================================================

  Widget _orderCard(Map<String, dynamic> order) {
    final id = _OrderHelpers.text(order['id']);

    final orderNumber = _OrderHelpers.text(order['order_number']);

    final displayNumber = orderNumber.isNotEmpty ? orderNumber : id;

    final status = _OrderHelpers.text(order['status']).isEmpty
        ? 'Processing'
        : _OrderHelpers.text(order['status']);

    final total = _OrderHelpers.amount(order['total_amount'] ?? order['total']);

    final itemCount = _OrderHelpers.text(order['item_count']);

    final date = _OrderHelpers.text(order['placed_at'] ?? order['created_at']);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: id.isEmpty
            ? null
            : () async {
                await Navigator.pushNamed(
                  context,
                  '/my-orders/${Uri.encodeComponent(id)}',
                );

                if (mounted) {
                  _reload();
                }
              },
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const CircleAvatar(child: Icon(Icons.inventory_2_outlined)),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order ${displayNumber.isEmpty ? '—' : displayNumber}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),

                    const SizedBox(height: 5),

                    Text(
                      status,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),

                    if (itemCount.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        '$itemCount item(s)',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],

                    if (date.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        date,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _OrderHelpers.money(total),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),

                  if (id.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    const Icon(Icons.chevron_right, size: 20),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// =========================================================
// BUYER ORDER DETAILS PAGE
// =========================================================

class BuyerOrderDetailsPage extends StatefulWidget {
  final String orderId;

  const BuyerOrderDetailsPage({super.key, required this.orderId});

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
  void didUpdateWidget(covariant BuyerOrderDetailsPage oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.orderId != widget.orderId) {
      orderFuture = OrderService.getOrder(widget.orderId);
    }
  }

  Future<void> _reload() async {
    final request = OrderService.getOrder(widget.orderId);

    if (mounted) {
      setState(() {
        orderFuture = request;
      });
    }

    try {
      await request;
    } catch (_) {
      // FutureBuilder displays API errors.
    }
  }

  // =========================================================
  // TRACK SELLER ORDER
  // =========================================================

  void _trackSellerOrder(String sellerOrderId) {
    final id = sellerOrderId.trim();

    if (id.isEmpty) return;

    Navigator.pushNamed(
      context,
      '/my-orders/'
      '${Uri.encodeComponent(widget.orderId)}'
      '/seller-orders/'
      '${Uri.encodeComponent(id)}'
      '/track',
    );
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Order Details'),
        actions: [
          IconButton(
            tooltip: 'Refresh order',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: orderFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return _OrderMessage(
              message: _OrderHelpers.error(snapshot.error),
              onRetry: _reload,
            );
          }

          final order = snapshot.data ?? <String, dynamic>{};

          if (order.isEmpty) {
            return _OrderMessage(
              title: 'Order not found',
              message: 'No order information was returned.',
              onRetry: _reload,
            );
          }

          return _buildOrderDetails(order);
        },
      ),
    );
  }

  // =========================================================
  // ORDER DETAILS BODY
  // =========================================================

  Widget _buildOrderDetails(Map<String, dynamic> order) {
    final orderNumber = _OrderHelpers.text(order['order_number']);

    final displayNumber = orderNumber.isNotEmpty ? orderNumber : widget.orderId;

    final status = _OrderHelpers.text(order['status']);

    final placedAt = _OrderHelpers.text(
      order['placed_at'] ?? order['created_at'],
    );

    final total = _OrderHelpers.amount(order['total_amount'] ?? order['total']);

    final items = _OrderHelpers.mapList(order['items']);

    final sellerOrders = _OrderHelpers.mapList(order['seller_orders']);

    return RefreshIndicator(
      onRefresh: _reload,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 750),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.all(16),
            children: [
              // =====================================
              // ORDER INFORMATION
              // =====================================
              Card(
                child: ListTile(
                  leading: const Icon(Icons.receipt_long_outlined),
                  title: Text('Order $displayNumber'),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 5),

                      Text(status.isEmpty ? 'Processing' : status),

                      if (placedAt.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(placedAt),
                      ],
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // =====================================
              // ITEMS
              // =====================================
              Text('Items', style: Theme.of(context).textTheme.titleMedium),

              const SizedBox(height: 8),

              if (items.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'No item details were returned '
                      'for this order.',
                    ),
                  ),
                )
              else
                ...items.map(_itemCard),

              const SizedBox(height: 18),

              // =====================================
              // ORDER TOTAL
              // =====================================
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),

                      Text(
                        _OrderHelpers.money(total),
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // =====================================
              // SELLER ORDER TRACKING
              // =====================================
              Text(
                'Order Tracking',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 8),

              if (sellerOrders.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'Tracking details are not '
                      'available for this order yet.',
                    ),
                  ),
                )
              else
                ...sellerOrders.asMap().entries.map((entry) {
                  final sellerOrder = entry.value;

                  final sellerOrderId = _OrderHelpers.text(sellerOrder['id']);

                  final sellerOrderStatus = _OrderHelpers.text(
                    sellerOrder['status'],
                  );

                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Seller Order ${entry.key + 1}',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),

                          if (sellerOrderStatus.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 5),
                              child: Text(sellerOrderStatus),
                            ),

                          const SizedBox(height: 10),

                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton.icon(
                              onPressed: sellerOrderId.isEmpty
                                  ? null
                                  : () => _trackSellerOrder(sellerOrderId),
                              icon: const Icon(Icons.local_shipping_outlined),
                              label: const Text('Track order'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }),

              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // ORDER ITEM CARD
  // =========================================================

  Widget _itemCard(Map<String, dynamic> item) {
    final name = _OrderHelpers.text(item['name']);

    final quantity = _OrderHelpers.text(item['quantity']);

    final rawLineTotal = item['line_total'] ?? item['price'];

    final lineTotal = _OrderHelpers.amount(rawLineTotal);

    return Card(
      child: ListTile(
        leading: const Icon(Icons.shopping_bag_outlined),
        title: Text(name.isEmpty ? 'Product' : name),
        subtitle: Text('Quantity: ${quantity.isEmpty ? '—' : quantity}'),
        trailing: Text(
          _OrderHelpers.money(lineTotal),
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}

// =========================================================
// SHARED ORDER HELPERS
// =========================================================

class _OrderHelpers {
  _OrderHelpers._();

  static String text(dynamic value) {
    return value?.toString().trim() ?? '';
  }

  static double amount(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    final cleaned = text(value)
        .replaceAll(',', '')
        .replaceAll('₱', '')
        .replaceAll(RegExp(r'^PHP\s*', caseSensitive: false), '');

    return double.tryParse(cleaned) ?? 0;
  }

  static String money(double value) {
    return '₱${value.toStringAsFixed(2)}';
  }

  static String error(Object? value) {
    final message = (value?.toString() ?? '')
        .replaceFirst('Exception: ', '')
        .trim();

    return message.isEmpty ? 'Unable to load order information.' : message;
  }

  static List<Map<String, dynamic>> mapList(dynamic value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map(
          (item) => item.map((key, value) => MapEntry(key.toString(), value)),
        )
        .toList();
  }
}

// =========================================================
// EMPTY / ERROR MESSAGE
// =========================================================

class _OrderMessage extends StatelessWidget {
  final String title;
  final String message;
  final Future<void> Function()? onRetry;

  const _OrderMessage({
    this.title = 'Unable to load orders',
    required this.message,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.receipt_long_outlined, size: 48),

            const SizedBox(height: 12),

            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),

            const SizedBox(height: 8),

            Text(message, textAlign: TextAlign.center),

            if (onRetry != null) ...[
              const SizedBox(height: 14),

              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Try again'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
