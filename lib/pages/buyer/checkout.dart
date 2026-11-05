import 'dart:async';

import 'package:flutter/material.dart';

import '../../services/order_service.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER CHECKOUT
// =========================================================
//
// Features:
// - Laravel checkout API integration
// - Saved delivery address selection
// - Cash on Delivery
// - Order summary
// - Stock and cart validation
// - Order confirmation
// - Duplicate submission prevention
// - Timeout and error handling
// - Order success navigation
//
// Uses existing:
// OrderService.getCheckoutData()
// OrderService.placeOrder()
//
// =========================================================

class BuyerCheckoutPage extends StatefulWidget {
  const BuyerCheckoutPage({super.key});

  @override
  State<BuyerCheckoutPage> createState() => _BuyerCheckoutPageState();
}

class _BuyerCheckoutPageState extends State<BuyerCheckoutPage> {
  // =========================================================
  // STATE
  // =========================================================

  BuyerCheckoutData? data;

  String? selectedAddressId;
  String? errorMessage;

  bool isLoading = true;
  bool isSubmitting = false;

  // Avoid another order submission when the result
  // of the previous request is uncertain.
  bool submissionUncertain = false;

  int _loadRequestId = 0;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    loadCheckout();
  }

  @override
  void dispose() {
    _loadRequestId++;
    super.dispose();
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
  // LOAD CHECKOUT DATA
  // =========================================================

  Future<void> loadCheckout() async {
    if (!mounted || isSubmitting) return;

    final requestId = ++_loadRequestId;

    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await OrderService.getCheckoutData().timeout(
        const Duration(seconds: 25),
      );

      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      // Select default address unless the previously
      // selected address is still available.
      String? nextAddressId = selectedAddressId;

      final addressStillExists = result.addresses.any(
        (address) => address.id == nextAddressId,
      );

      if (!addressStillExists) {
        nextAddressId = null;

        for (final address in result.addresses) {
          if (address.isDefault) {
            nextAddressId = address.id;
            break;
          }
        }

        if (nextAddressId == null && result.addresses.isNotEmpty) {
          nextAddressId = result.addresses.first.id;
        }
      }

      setState(() {
        data = result;
        selectedAddressId = nextAddressId;
        isLoading = false;
        errorMessage = null;
      });
    } on TimeoutException {
      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage =
            'The server took too long to load '
            'your checkout details.';
      });
    } catch (error) {
      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage = _errorText(error);
      });
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
        SnackBar(
          content: Text(message),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 5),
        ),
      );
  }

  // =========================================================
  // CURRENCY
  // =========================================================

  String _money(double value) {
    return '₱${value.toStringAsFixed(2)}';
  }

  // =========================================================
  // ADDRESS VALIDATION
  // =========================================================

  bool get _hasValidAddress {
    final checkout = data;
    final addressId = selectedAddressId;

    if (checkout == null || addressId == null || addressId.isEmpty) {
      return false;
    }

    return checkout.addresses.any((address) => address.id == addressId);
  }

  // =========================================================
  // CART VALIDATION
  // =========================================================

  String? _checkoutProblem() {
    final checkout = data;

    if (checkout == null) {
      return 'Checkout information is unavailable.';
    }

    if (!_hasValidAddress) {
      return 'Please select a valid delivery address.';
    }

    if (checkout.cart.items.isEmpty) {
      return 'Your cart is empty.';
    }

    for (final item in checkout.cart.items) {
      if (!item.available) {
        return '${item.name} is currently unavailable.';
      }

      if (item.quantity <= 0) {
        return 'Invalid quantity for ${item.name}.';
      }

      if (item.stock <= 0) {
        return '${item.name} is out of stock.';
      }

      if (item.quantity > item.stock) {
        return 'Only ${item.stock} pieces of '
            '${item.name} are available.';
      }
    }

    return null;
  }

  bool get _canSubmit {
    return !isLoading &&
        !isSubmitting &&
        !submissionUncertain &&
        _checkoutProblem() == null;
  }

  // =========================================================
  // ORDER CONFIRMATION
  // =========================================================

  Future<void> _confirmOrder() async {
    if (!_canSubmit) {
      _showMessage(_checkoutProblem() ?? 'Checkout is currently unavailable.');
      return;
    }

    final checkout = data!;

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Confirm Your Order'),
          content: Text(
            'Payment Method: Cash on Delivery\n'
            'Order Total: ${_money(checkout.cart.total)}\n\n'
            'Please confirm your delivery address '
            'and order details before placing '
            'your order.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(false);
              },
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.of(dialogContext).pop(true);
              },
              child: const Text('Place Order'),
            ),
          ],
        );
      },
    );

    if (!mounted || confirmed != true) return;

    // Check again after the dialog closes.
    if (!_canSubmit) return;

    await _placeOrder();
  }

  // =========================================================
  // PLACE ORDER USING LARAVEL
  // =========================================================

  Future<void> _placeOrder() async {
    if (!_canSubmit) return;

    final addressId = selectedAddressId!;

    // Prevent double submissions immediately.
    setState(() {
      isSubmitting = true;
    });

    try {
      final order = await OrderService.placeOrder(
        addressId: addressId,
      ).timeout(const Duration(seconds: 30));

      if (!mounted) return;

      // Do not claim success without an order
      // reference returned by the backend.
      final orderNumber = order['order_number']?.toString() ?? '';

      final orderId = order['id']?.toString() ?? '';

      if (orderNumber.isEmpty && orderId.isEmpty) {
        setState(() {
          submissionUncertain = true;
        });

        _showMessage(
          'The server did not return an order '
          'reference. Check My Orders before '
          'attempting another checkout.',
        );

        return;
      }

      // Only navigate after the Laravel API
      // confirms the order with a reference.
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/buyer/order-success',
        (route) => false,
        arguments: {'order_number': orderNumber, 'order_id': orderId},
      );
    } on TimeoutException {
      if (!mounted) return;

      setState(() {
        submissionUncertain = true;
      });

      _showMessage(
        'The order request timed out. '
        'Your order may have been received. '
        'Check My Orders before trying again.',
      );
    } catch (error) {
      if (!mounted) return;

      // After a request fails, the server may
      // already have accepted the order.
      // Do not permit automatic resubmission.
      setState(() {
        submissionUncertain = true;
      });

      _showMessage(
        '${_errorText(error)} '
        'Please check My Orders before trying again.',
      );
    } finally {
      if (mounted) {
        setState(() {
          isSubmitting = false;
        });
      }
    }
  }

  // =========================================================
  // ORDER HISTORY NAVIGATION
  // =========================================================

  void _openOrderHistory() {
    Navigator.pushNamed(context, '/my-orders');
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
        actions: [
          IconButton(
            tooltip: 'Refresh Checkout',
            onPressed: isLoading || isSubmitting ? null : loadCheckout,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),

      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage != null
          ? _errorView()
          : data == null
          ? _emptyView()
          : _checkoutBody(),

      bottomNavigationBar: !isLoading && errorMessage == null && data != null
          ? _bottomButton()
          : null,
    );
  }

  // =========================================================
  // ERROR VIEW
  // =========================================================

  Widget _errorView() {
    return RefreshIndicator(
      onRefresh: loadCheckout,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 100),

          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                Icon(
                  Icons.error_outline_rounded,
                  size: 55,
                  color: Theme.of(context).colorScheme.error,
                ),

                const SizedBox(height: 14),

                Text(
                  'Unable to load checkout',
                  style: Theme.of(context).textTheme.titleMedium,
                ),

                const SizedBox(height: 10),

                Text(
                  errorMessage ?? 'Please try again.',
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 18),

                FilledButton.icon(
                  onPressed: loadCheckout,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Try again'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // EMPTY VIEW
  // =========================================================

  Widget _emptyView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.shopping_bag_outlined, size: 55),

            const SizedBox(height: 14),

            const Text('Checkout is unavailable.'),

            const SizedBox(height: 14),

            OutlinedButton(
              onPressed: loadCheckout,
              child: const Text('Refresh'),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // CHECKOUT BODY
  // =========================================================

  Widget _checkoutBody() {
    final checkout = data!;

    return RefreshIndicator(
      onRefresh: loadCheckout,
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 700),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.all(16),
            children: [
              // =====================================
              // DELIVERY ADDRESS
              // =====================================
              Text(
                'Delivery address',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 9),

              if (checkout.addresses.isEmpty)
                const Card(
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'There are no saved delivery '
                      'addresses. Add an address to '
                      'your SUKI account, then '
                      'return here.',
                    ),
                  ),
                )
              else
                ...checkout.addresses.map(_addressTile),

              const SizedBox(height: 22),

              // =====================================
              // PAYMENT METHOD
              // =====================================
              Text(
                'Payment method',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 9),

              Card(
                child: ListTile(
                  leading: const Icon(Icons.payments_outlined),
                  title: const Text('Cash on Delivery'),
                  subtitle: const Text('Pay when your order arrives.'),
                  trailing: Icon(
                    Icons.check_circle,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
              ),

              const SizedBox(height: 22),

              // =====================================
              // ORDER SUMMARY
              // =====================================
              Text(
                'Order total',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 9),

              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      _totalRow('Subtotal', checkout.cart.subtotal),

                      const SizedBox(height: 8),

                      _totalRow('Shipping', checkout.cart.shipping),

                      if (checkout.cart.discount > 0) ...[
                        const SizedBox(height: 8),

                        _totalRow('Discount', -checkout.cart.discount),
                      ],

                      const Divider(height: 25),

                      _totalRow('Total', checkout.cart.total, bold: true),
                    ],
                  ),
                ),
              ),

              // =====================================
              // CHECKOUT VALIDATION MESSAGE
              // =====================================
              if (_checkoutProblem() != null && !submissionUncertain) ...[
                const SizedBox(height: 14),

                Text(
                  _checkoutProblem()!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],

              // =====================================
              // UNCERTAIN ORDER RESULT
              // =====================================
              if (submissionUncertain) _buildUncertainOrderNotice(),

              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // ADDRESS TILE
  // =========================================================

  Widget _addressTile(BuyerAddress address) {
    return Card(
      child: RadioListTile<String>(
        value: address.id,
        groupValue: selectedAddressId,
        onChanged: isSubmitting || submissionUncertain
            ? null
            : (value) {
                setState(() {
                  selectedAddressId = value;
                });
              },
        title: Text('${address.label} - ${address.name}'),
        subtitle: Text(
          '${address.formattedAddress}\n'
          '${address.phone}',
        ),
        isThreeLine: true,
      ),
    );
  }

  // =========================================================
  // TOTAL ROW
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

  // =========================================================
  // UNCERTAIN ORDER NOTICE
  // =========================================================

  Widget _buildUncertainOrderNotice() {
    return Padding(
      padding: const EdgeInsets.only(top: 18),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Check your order status',
                style: Theme.of(context).textTheme.titleMedium,
              ),

              const SizedBox(height: 10),

              const Text(
                'We could not confirm whether '
                'your order was created. '
                'Please check My Orders before '
                'placing another order.',
              ),

              const SizedBox(height: 14),

              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _openOrderHistory,
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: const Text('View My Orders'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // BOTTOM PLACE ORDER BUTTON
  // =========================================================

  Widget _bottomButton() {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 9, 16, 12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          border: Border(
            top: BorderSide(color: Theme.of(context).dividerColor),
          ),
        ),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 700),
          child: SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: _canSubmit ? _confirmOrder : null,
              child: isSubmitting
                  ? const SizedBox(
                      width: 19,
                      height: 19,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      submissionUncertain
                          ? 'Check Order Status'
                          : 'Place Order - Cash on Delivery',
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
