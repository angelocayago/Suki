import 'package:flutter/material.dart';

import 'buyer_home_api.dart';
import 'product_details.dart';
import '../../theme/app_theme.dart';

class BuyerProductListPage extends StatefulWidget {
  final String? categorySlug;
  final String? categoryName;
  final String? sellerSlug;

  const BuyerProductListPage({
    super.key,
    this.categorySlug,
    this.categoryName,
    this.sellerSlug,
  });

  @override
  State<BuyerProductListPage> createState() => _BuyerProductListPageState();
}

class _BuyerProductListPageState extends State<BuyerProductListPage> {
  final TextEditingController searchController = TextEditingController();
  List<BuyerHomeItem> products = const [];
  String? errorMessage;
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    loadProducts();
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  Future<void> loadProducts({String? query}) async {
    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await BuyerCatalogApi.getProducts(
        category: widget.categorySlug,
        seller: widget.sellerSlug,
        query: query,
      );
      if (!mounted) return;
      setState(() {
        products = result;
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

  void _openProduct(BuyerHomeItem item) {
    if (item.slug.isEmpty) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailsPage(
          product: ProductDetailsData.fromCatalogItem(item),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    final title = widget.categoryName?.trim().isNotEmpty == true
        ? widget.categoryName!
        : widget.sellerSlug == null
            ? 'Shop'
            : 'Store';

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: TextField(
              controller: searchController,
              textInputAction: TextInputAction.search,
              onSubmitted: (value) => loadProducts(query: value),
              decoration: InputDecoration(
                hintText: 'Search products',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  tooltip: 'Search',
                  onPressed: () => loadProducts(query: searchController.text),
                  icon: const Icon(Icons.arrow_forward),
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
          Expanded(child: _content()),
        ],
      ),
    );
  }

  Widget _content() {
    if (isLoading) return const Center(child: CircularProgressIndicator());

    if (errorMessage != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(errorMessage!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => loadProducts(query: searchController.text),
                child: const Text('Try again'),
              ),
            ],
          ),
        ),
      );
    }

    if (products.isEmpty) {
      return const Center(child: Text('No products found.'));
    }

    return RefreshIndicator(
      onRefresh: () => loadProducts(query: searchController.text),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final columns = constraints.maxWidth >= 600 ? 3 : 2;
          return GridView.builder(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            itemCount: products.length,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: 0.66,
            ),
            itemBuilder: (context, index) => _productCard(products[index]),
          );
        },
      ),
    );
  }

  Widget _productCard(BuyerHomeItem item) {
    final image = item.image.isNotEmpty
        ? item.image
        : item.images.isNotEmpty
            ? item.images.first
            : '';
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openProduct(item),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: SizedBox(
                width: double.infinity,
                child: image.isEmpty
                    ? ColoredBox(
                        color: AppTheme.surfaceMuted,
                        child: Icon(Icons.image_outlined, size: 42),
                      )
                    : Image.network(
                        image,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => ColoredBox(
                          color: AppTheme.surfaceMuted,
                          child: Icon(Icons.image_outlined, size: 42),
                        ),
                      ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 9, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 5),
                  Text('PHP ${item.price}'),
                  const SizedBox(height: 3),
                  Text(
                    item.stock > 0 ? '${item.stock} in stock' : 'Out of stock',
                    style: TextStyle(
                      fontSize: 11,
                      color: item.stock > 0
                          ? Theme.of(context).colorScheme.onSurfaceVariant
                          : Colors.red,
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
}
