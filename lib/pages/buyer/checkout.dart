import 'package:flutter/material.dart';

import '../../services/order_service.dart';

class BuyerCheckoutPage extends StatefulWidget {
  const BuyerCheckoutPage({super.key});

  @override
  State<BuyerCheckoutPage> createState() => _BuyerCheckoutPageState();
}

class _BuyerCheckoutPageState extends State<BuyerCheckoutPage> {
  BuyerCheckoutData? data;
  String? selectedAddressId;
  String? errorMessage;
  bool isLoading = true;
  bool isSubmitting = false;

  @override
  void initState() {
    super.initState();
    loadCheckout();
  }

  Future<void> loadCheckout() async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });
    try {
      final result = await OrderService.getCheckoutData();
      if (!mounted) return;
      final defaultAddresses =
          result.addresses.where((address) => address.isDefault);
      setState(() {
        data = result;
        if (selectedAddressId == null && result.addresses.isNotEmpty) {
          selectedAddressId = defaultAddresses.isNotEmpty
              ? defaultAddresses.first.id
              : result.addresses.first.id;
        }
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

  Future<void> _placeOrder() async {
    final addressId = selectedAddressId;
    if (addressId == null || addressId.isEmpty) {
      _showMessage('Add a delivery address to your SUKI account first.');
      return;
    }

    setState(() => isSubmitting = true);
    try {
      final order = await OrderService.placeOrder(addressId: addressId);
      if (!mounted) return;
      final orderNumber = order['order_number']?.toString() ?? '';
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/buyer/order-success',
        (route) => false,
        arguments: {
          'order_number': orderNumber,
          'order_id': order['id']?.toString() ?? '',
        },
      );
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => isSubmitting = false);
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
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: isLoading
          ? const Center(child: CircularProgressIndicator())
          : errorMessage != null
              ? _errorView()
              : _checkoutBody(),
      bottomNavigationBar: !isLoading && errorMessage == null
          ? _bottomButton()
          : null,
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
            FilledButton(onPressed: loadCheckout, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }

  Widget _checkoutBody() {
    final checkout = data!;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Delivery address', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 9),
        if (checkout.addresses.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'There are no saved delivery addresses. Add an address to your SUKI account on the website, then return here.',
              ),
            ),
          )
        else
          ...checkout.addresses.map(_addressTile),
        const SizedBox(height: 22),
        Text('Payment method', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 9),
        const Card(
          child: ListTile(
            leading: Icon(Icons.payments_outlined),
            title: Text('Cash on delivery'),
            subtitle: Text('Pay when your order arrives.'),
            trailing: Icon(Icons.check_circle, color: Colors.green),
          ),
        ),
        const SizedBox(height: 22),
        Text('Order total', style: Theme.of(context).textTheme.titleMedium),
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
      ],
    );
  }

  Widget _addressTile(BuyerAddress address) {
    return Card(
      child: RadioListTile<String>(
        value: address.id,
        groupValue: selectedAddressId,
        onChanged: isSubmitting
            ? null
            : (value) => setState(() => selectedAddressId = value),
        title: Text('${address.label} - ${address.name}'),
        subtitle: Text('${address.formattedAddress}\n${address.phone}'),
        isThreeLine: true,
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

  Widget _bottomButton() {
    final disabled = isSubmitting ||
        selectedAddressId == null ||
        (data?.cart.items.isEmpty ?? true) ||
        (data?.cart.items.any((item) => !item.available) ?? true);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 9, 16, 12),
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(
            onPressed: disabled ? null : _placeOrder,
            child: isSubmitting
                ? const SizedBox(
                    width: 19,
                    height: 19,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Place order - Cash on delivery'),
          ),
        ),
      ),
    );
  }
}
