import 'package:flutter/material.dart';

import '../../services/api_services.dart';
import '../../services/order_service.dart';
import '../../services/wishlist_service.dart';

class BuyerAddressesPage extends StatefulWidget {
  const BuyerAddressesPage({super.key});

  @override
  State<BuyerAddressesPage> createState() => _BuyerAddressesPageState();
}

class _BuyerAddressesPageState extends State<BuyerAddressesPage> {
  late Future<BuyerCheckoutData> addressesFuture;

  @override
  void initState() {
    super.initState();
    addressesFuture = OrderService.getCheckoutData();
  }

  void _reload() {
    setState(() => addressesFuture = OrderService.getCheckoutData());
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Addresses'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<BuyerCheckoutData>(
        future: addressesFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _PageMessage(
              message: snapshot.error.toString().replaceFirst('Exception: ', ''),
              onRetry: _reload,
            );
          }

          final addresses = snapshot.data?.addresses ?? const [];
          if (addresses.isEmpty) {
            return const _PageMessage(
              icon: Icons.location_on_outlined,
              title: 'No saved addresses',
              message: 'Add a delivery address from your SUKI account.',
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: addresses.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final address = addresses[index];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.location_on_outlined),
                  title: Text(
                    '${address.label}${address.isDefault ? ' · Default' : ''}',
                  ),
                  subtitle: Text(
                    [address.name, address.phone, address.formattedAddress]
                        .where((value) => value.trim().isNotEmpty)
                        .join('\n'),
                  ),
                  isThreeLine: true,
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class BuyerWishlistPage extends StatefulWidget {
  const BuyerWishlistPage({super.key});

  @override
  State<BuyerWishlistPage> createState() => _BuyerWishlistPageState();
}

class _BuyerWishlistPageState extends State<BuyerWishlistPage> {
  late Future<List<Map<String, dynamic>>> itemsFuture;

  @override
  void initState() {
    super.initState();
    itemsFuture = WishlistService.getItems();
  }

  void _reload() {
    setState(() => itemsFuture = WishlistService.getItems());
  }

  Future<void> _remove(String slug) async {
    try {
      await WishlistService.toggle(slug);
      if (mounted) _reload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Wishlist'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: itemsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _PageMessage(
              message: snapshot.error.toString().replaceFirst('Exception: ', ''),
              onRetry: _reload,
            );
          }

          final items = snapshot.data ?? const [];
          if (items.isEmpty) {
            return const _PageMessage(
              icon: Icons.favorite_border,
              title: 'Your wishlist is empty',
              message: 'Tap the heart on a product to save it here.',
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              final item = items[index];
              final slug = item['product_slug']?.toString() ?? '';
              final image = ApiServices.resolveMediaUrl(
                item['image']?.toString(),
              );
              final name = item['product_name']?.toString() ?? 'Product';
              return Card(
                clipBehavior: Clip.antiAlias,
                child: ListTile(
                  onTap: slug.isEmpty
                      ? null
                      : () => Navigator.pushNamed(
                            context,
                            '/product/${Uri.encodeComponent(slug)}',
                          ),
                  leading: SizedBox(
                    width: 52,
                    height: 52,
                    child: image.isEmpty
                        ? const Icon(Icons.image_outlined)
                        : Image.network(
                            image,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                const Icon(Icons.image_outlined),
                          ),
                  ),
                  title: Text(name, maxLines: 2, overflow: TextOverflow.ellipsis),
                  subtitle: Text('PHP ${item['price'] ?? '0.00'}'),
                  trailing: IconButton(
                    tooltip: 'Remove from wishlist',
                    onPressed: slug.isEmpty ? null : () => _remove(slug),
                    icon: const Icon(Icons.favorite, color: Colors.redAccent),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _PageMessage extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final VoidCallback? onRetry;

  const _PageMessage({
    this.icon = Icons.info_outline,
    this.title = 'Unable to load this page',
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
            Icon(icon, size: 44, color: Theme.of(context).colorScheme.primary),
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
