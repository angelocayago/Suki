import 'package:flutter/material.dart';

import '../../services/cart_service.dart';
import '../../theme/app_theme.dart';

class BuyerCartPage extends StatefulWidget {
  const BuyerCartPage({super.key});

  @override
  State<BuyerCartPage> createState() => _BuyerCartPageState();
}

class _BuyerCartPageState extends State<BuyerCartPage> {
  BuyerCartSummary? summary;
  String? errorMessage;
  String? updatingItemId;
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    loadCart();
  }

  Future<void> loadCart() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await CartService.getCart();
      if (!mounted) return;
      setState(() {
        summary = result;
        isLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        errorMessage = error.toString().replaceFirst('Exception: ', '');
        isLoading = false;
      });
    }
  }

  Future<void> _updateQuantity(BuyerCartLine item, int quantity) async {
    setState(() => updatingItemId = item.id);
    try {
      final result = quantity < 1
          ? await CartService.removeItem(item.id)
          : await CartService.updateQuantity(
              itemId: item.id,
              quantity: quantity,
            );
      if (mounted) setState(() => summary = result);
    } catch (error) {
      if (mounted) _showMessage(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => updatingItemId = null);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String _money(double value) => 'PHP ${value.toStringAsFixed(2)}';

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    final cart = summary;
    return Scaffold(
      appBar: AppBar(title: const Text('My Cart')),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage != null
              ? _errorView()
              : cart == null || cart.items.isEmpty
                  ? _emptyView()
                  : RefreshIndicator(
                      onRefresh: loadCart,
                      child: ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: cart.items.length + 1,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          if (index == cart.items.length) {
                            return _summaryCard(cart);
                          }
                          final item = cart.items[index];
                          return _itemCard(item);
                        },
                      ),
                    ),
    );
  }

  Widget _errorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(errorMessage!, textAlign: TextAlign.center),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: errorMessage!.toLowerCase().contains('log in')
                  ? () => Navigator.pushNamed(context, '/login')
                  : loadCart,
              child: Text(
                errorMessage!.toLowerCase().contains('log in')
                    ? 'Sign in'
                    : 'Try again',
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _emptyView() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.shopping_cart_outlined, size: 56),
          const SizedBox(height: 12),
          const Text('Your cart is empty.'),
          const SizedBox(height: 14),
          OutlinedButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Continue shopping'),
          ),
        ],
      ),
    );
  }

  Widget _itemCard(BuyerCartLine item) {
    final busy = updatingItemId == item.id;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: SizedBox(
                width: 78,
                height: 78,
                child: item.image.isEmpty
                    ? ColoredBox(
                        color: AppTheme.surfaceMuted,
                        child: Icon(Icons.image_outlined),
                      )
                    : Image.network(
                        item.image,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => ColoredBox(
                          color: AppTheme.surfaceMuted,
                          child: Icon(Icons.image_outlined),
                        ),
                      ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 6),
                  Text(_money(item.price)),
                  if (!item.available)
                    const Padding(
                      padding: EdgeInsets.only(top: 5),
                      child: Text(
                        'Currently unavailable',
                        style: TextStyle(color: Colors.red, fontSize: 12),
                      ),
                    ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        onPressed: busy
                            ? null
                            : () => _updateQuantity(item, item.quantity - 1),
                        icon: const Icon(Icons.remove_circle_outline),
                      ),
                      busy
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text('${item.quantity}'),
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        onPressed: busy || item.quantity >= item.stock
                            ? null
                            : () => _updateQuantity(item, item.quantity + 1),
                        icon: const Icon(Icons.add_circle_outline),
                      ),
                      const Spacer(),
                      TextButton(
                        onPressed: busy ? null : () => _updateQuantity(item, 0),
                        child: const Text('Remove'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summaryCard(BuyerCartSummary cart) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            _totalRow('Subtotal', cart.subtotal),
            const SizedBox(height: 7),
            _totalRow('Shipping', cart.shipping),
            if (cart.discount > 0) ...[
              const SizedBox(height: 7),
              _totalRow('Discount', -cart.discount),
            ],
            const Divider(height: 24),
            _totalRow('Total', cart.total, bold: true),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: cart.items.any((item) => !item.available)
                    ? null
                    : () => Navigator.pushNamed(context, '/buyer-checkout'),
                child: const Text('Continue to checkout'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _totalRow(String label, double value, {bool bold = false}) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.bold : FontWeight.normal,
      fontSize: bold ? 16 : 14,
    );
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [Text(label, style: style), Text(_money(value), style: style)],
    );
  }
}
