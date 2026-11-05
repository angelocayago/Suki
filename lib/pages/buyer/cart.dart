import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/cart_service.dart';
import '../../theme/app_theme.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER CART PAGE
// =========================================================
//
// Features:
// - Laravel cart API integration
// - Display cart items
// - Update quantity
// - Remove cart items
// - Pull-to-refresh
// - Responsive layout
// - Loading and error handling
// - Stock validation
// - Order summary
// - Checkout navigation
//
// =========================================================

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

  int _requestId = 0;

  bool get _isUpdating => updatingItemId != null;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    loadCart();
  }

  @override
  void dispose() {
    // Ignore responses from requests started
    // before this page was disposed.
    _requestId++;

    super.dispose();
  }

  // =========================================================
  // LOAD CART FROM LARAVEL
  // =========================================================

  Future<void> loadCart() async {
    if (!mounted || _isUpdating) return;

    final requestId = ++_requestId;

    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await CartService.getCart().timeout(
        const Duration(seconds: 25),
      );

      if (!mounted || requestId != _requestId) {
        return;
      }

      setState(() {
        summary = result;
        errorMessage = null;
        isLoading = false;
      });
    } on TimeoutException {
      if (!mounted || requestId != _requestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage =
            'The server took too long to respond. '
            'Please try refreshing your cart.';
      });
    } catch (error) {
      if (!mounted || requestId != _requestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage = _errorText(error);
      });
    }
  }

  // =========================================================
  // ERROR FORMATTER
  // =========================================================

  String _errorText(Object error) {
    final message = error.toString().replaceFirst('Exception: ', '').trim();

    return message.isEmpty
        ? 'Something went wrong. Please try again.'
        : message;
  }

  // =========================================================
  // UPDATE / REMOVE CART ITEM
  // =========================================================

  Future<void> _updateQuantity(BuyerCartLine item, int newQuantity) async {
    // Only one cart mutation at a time.
    if (_isUpdating || isLoading) {
      return;
    }

    if (item.id.trim().isEmpty) {
      _showMessage('Invalid cart item.');
      return;
    }

    // Stock restrictions only apply to increasing.
    if (newQuantity > item.quantity) {
      if (!item.available || item.stock <= 0) {
        _showMessage('This product is currently unavailable.');
        return;
      }

      if (newQuantity > item.stock) {
        _showMessage('Maximum available stock reached.');
        return;
      }
    }

    // Invalidate any unfinished GET request.
    final requestId = ++_requestId;

    setState(() {
      updatingItemId = item.id;
    });

    try {
      final BuyerCartSummary result;

      if (newQuantity <= 0) {
        result = await CartService.removeItem(
          item.id,
        ).timeout(const Duration(seconds: 25));
      } else {
        result = await CartService.updateQuantity(
          itemId: item.id,
          quantity: newQuantity,
        ).timeout(const Duration(seconds: 25));
      }

      if (!mounted || requestId != _requestId) {
        return;
      }

      setState(() {
        summary = result;
        errorMessage = null;
      });

      if (newQuantity <= 0) {
        _showMessage('Product removed from your cart.');
      }
    } on TimeoutException {
      if (!mounted || requestId != _requestId) {
        return;
      }

      _showMessage(
        'Request timed out. Refresh your cart '
        'to check whether the change was saved.',
      );
    } catch (error) {
      if (!mounted || requestId != _requestId) {
        return;
      }

      _showMessage(_errorText(error));
    } finally {
      if (mounted && requestId == _requestId) {
        setState(() {
          updatingItemId = null;
        });
      }
    }
  }

  // =========================================================
  // SNACKBAR
  // =========================================================

  void _showMessage(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
  }

  // =========================================================
  // CURRENCY FORMAT
  // =========================================================

  String _money(double value) {
    return '₱${value.toStringAsFixed(2)}';
  }

  // =========================================================
  // CHECKOUT VALIDATION
  // =========================================================

  bool _canCheckout(BuyerCartSummary cart) {
    if (_isUpdating || isLoading) {
      return false;
    }

    if (cart.items.isEmpty) {
      return false;
    }

    for (final item in cart.items) {
      if (!item.available ||
          item.stock <= 0 ||
          item.quantity <= 0 ||
          item.quantity > item.stock) {
        return false;
      }
    }

    return true;
  }

  void _continueToCheckout() {
    final cart = summary;

    if (cart == null || !_canCheckout(cart)) {
      _showMessage(
        'Please check your cart items and '
        'available stock before checkout.',
      );
      return;
    }

    Navigator.pushNamed(context, '/buyer-checkout');
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    final cart = summary;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Cart'),
        actions: [
          IconButton(
            tooltip: 'Refresh Cart',
            onPressed: isLoading || _isUpdating ? null : loadCart,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage != null
          ? _errorView()
          : cart == null || cart.items.isEmpty
          ? _emptyView()
          : _cartList(cart),
    );
  }

  // =========================================================
  // CART LIST
  // =========================================================

  Widget _cartList(BuyerCartSummary cart) {
    return RefreshIndicator(
      onRefresh: loadCart,
      child: LayoutBuilder(
        builder: (context, constraints) {
          return Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 700),
              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                padding: const EdgeInsets.all(16),
                itemCount: cart.items.length + 1,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  if (index == cart.items.length) {
                    return _summaryCard(cart);
                  }

                  return _itemCard(cart.items[index]);
                },
              ),
            ),
          );
        },
      ),
    );
  }

  // =========================================================
  // ERROR STATE
  // =========================================================

  Widget _errorView() {
    final message = errorMessage ?? 'Unable to load cart.';

    final requiresLogin =
        message.toLowerCase().contains('log in') ||
        message.toLowerCase().contains('sign in') ||
        message.toLowerCase().contains('unauthorized');

    return RefreshIndicator(
      onRefresh: loadCart,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 90),

          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                Icon(
                  requiresLogin ? Icons.lock_outline : Icons.wifi_off_rounded,
                  size: 55,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),

                const SizedBox(height: 15),

                Text(
                  requiresLogin ? 'Sign in required' : 'Unable to load cart',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),

                const SizedBox(height: 9),

                Text(message, textAlign: TextAlign.center),

                const SizedBox(height: 16),

                FilledButton.icon(
                  onPressed: requiresLogin
                      ? () => Navigator.pushNamed(context, '/login')
                      : loadCart,
                  icon: Icon(requiresLogin ? Icons.login : Icons.refresh),
                  label: Text(requiresLogin ? 'Sign in' : 'Try again'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // EMPTY CART STATE
  // =========================================================

  Widget _emptyView() {
    return RefreshIndicator(
      onRefresh: loadCart,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 100),

          Column(
            children: [
              Icon(
                Icons.shopping_cart_outlined,
                size: 62,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),

              const SizedBox(height: 12),

              Text(
                'Your cart is empty.',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 8),

              const Text(
                'Explore SUKI products '
                'and add items to your cart.',
                textAlign: TextAlign.center,
              ),

              const SizedBox(height: 18),

              OutlinedButton.icon(
                onPressed: () {
                  if (Navigator.of(context).canPop()) {
                    Navigator.pop(context);
                  } else {
                    Navigator.pushReplacementNamed(context, '/products');
                  }
                },
                icon: const Icon(Icons.shopping_bag_outlined),
                label: const Text('Continue shopping'),
              ),

              const SizedBox(height: 8),

              TextButton.icon(
                onPressed: loadCart,
                icon: const Icon(Icons.refresh),
                label: const Text('Refresh Cart'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // =========================================================
  // CART ITEM CARD
  // =========================================================

  Widget _itemCard(BuyerCartLine item) {
    final busy = updatingItemId == item.id;

    // Prevent another mutation while one request
    // is still being processed.
    final disableActions = _isUpdating || isLoading;

    final hasEnoughStock = item.stock >= item.quantity && item.stock > 0;

    final isUnavailable = !item.available || !hasEnoughStock;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // =====================================
            // PRODUCT IMAGE
            // =====================================
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: SizedBox(
                width: 78,
                height: 78,
                child: _itemImage(item.image),
              ),
            ),

            const SizedBox(width: 12),

            // =====================================
            // PRODUCT DETAILS
            // =====================================
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

                  Text(
                    _money(item.price),
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),

                  if (isUnavailable)
                    Padding(
                      padding: const EdgeInsets.only(top: 5),
                      child: Text(
                        !item.available
                            ? 'Currently unavailable'
                            : item.stock <= 0
                            ? 'Out of stock'
                            : 'Only ${item.stock} available',
                        style: const TextStyle(color: Colors.red, fontSize: 12),
                      ),
                    ),

                  const SizedBox(height: 8),

                  // ===================================
                  // QUANTITY AND REMOVE CONTROLS
                  // ===================================
                  Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    spacing: 4,
                    runSpacing: 4,
                    children: [
                      IconButton(
                        tooltip: 'Decrease quantity',
                        visualDensity: VisualDensity.compact,
                        onPressed: disableActions
                            ? null
                            : () => _updateQuantity(item, item.quantity - 1),
                        icon: const Icon(Icons.remove_circle_outline),
                      ),

                      SizedBox(
                        width: 28,
                        child: Center(
                          child: busy
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  '${item.quantity}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                        ),
                      ),

                      IconButton(
                        tooltip: 'Increase quantity',
                        visualDensity: VisualDensity.compact,
                        onPressed:
                            disableActions ||
                                !item.available ||
                                item.quantity >= item.stock
                            ? null
                            : () => _updateQuantity(item, item.quantity + 1),
                        icon: const Icon(Icons.add_circle_outline),
                      ),

                      TextButton(
                        onPressed: disableActions
                            ? null
                            : () => _updateQuantity(item, 0),
                        child: const Text('Remove'),
                      ),
                    ],
                  ),

                  const SizedBox(height: 4),

                  // ===================================
                  // LINE TOTAL
                  // ===================================
                  Text(
                    'Item total: '
                    '${_money(item.lineTotal)}',
                    style: TextStyle(
                      fontSize: 11,
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // PRODUCT IMAGE
  // =========================================================

  Widget _itemImage(String image) {
    if (image.trim().isEmpty) {
      return _imagePlaceholder();
    }

    final uri = Uri.tryParse(image);

    if (uri == null ||
        !uri.hasAuthority ||
        (uri.scheme != 'http' && uri.scheme != 'https')) {
      return _imagePlaceholder();
    }

    return Image.network(
      image,
      fit: BoxFit.cover,
      errorBuilder: (context, error, stackTrace) {
        return _imagePlaceholder();
      },
    );
  }

  Widget _imagePlaceholder() {
    return ColoredBox(
      color: AppTheme.surfaceMuted,
      child: const Center(child: Icon(Icons.image_outlined, size: 32)),
    );
  }

  // =========================================================
  // ORDER SUMMARY CARD
  // =========================================================

  Widget _summaryCard(BuyerCartSummary cart) {
    final canCheckout = _canCheckout(cart);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Order Summary',
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 14),

            _totalRow('Subtotal', cart.subtotal),

            const SizedBox(height: 7),

            _totalRow('Shipping', cart.shipping),

            if (cart.discount > 0) ...[
              const SizedBox(height: 7),

              _totalRow('Discount', -cart.discount),
            ],

            const Divider(height: 24),

            _totalRow('Total', cart.total, bold: true),

            if (!canCheckout && !_isUpdating)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  'Please review unavailable items '
                  'or stock quantities before checkout.',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontSize: 12,
                  ),
                ),
              ),

            const SizedBox(height: 14),

            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: canCheckout ? _continueToCheckout : null,
                child: const Text('Continue to checkout'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // SUMMARY TOTAL ROW
  // =========================================================

  Widget _totalRow(String label, double value, {bool bold = false}) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.bold : FontWeight.normal,
      fontSize: bold ? 16 : 14,
    );

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: style),

        Text(_money(value), style: style),
      ],
    );
  }
}
