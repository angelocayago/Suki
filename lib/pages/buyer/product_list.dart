import 'dart:async';

import 'package:flutter/material.dart';

import 'buyer_home_api.dart';
import 'product_details.dart';
import '../../theme/app_theme.dart';
import '../../widgets/product_photo_carousel.dart';
import '../../widgets/buyer_bottom_nav.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER PRODUCT LIST
// =========================================================
//
// Features:
// - Laravel product API integration
// - Product search
// - Category filtering
// - Seller filtering
// - Pull-to-refresh
// - Responsive product cards
// - Loading, empty and error states
// - Product details navigation
// - Protection from outdated API responses
//
// API:
// BuyerCatalogApi.getProducts()
//
// =========================================================

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
  // =========================================================
  // CONTROLLERS AND STATE
  // =========================================================

  final TextEditingController searchController = TextEditingController();

  final FocusNode _searchFocusNode = FocusNode();

  List<BuyerHomeItem> products = const [];

  String? errorMessage;

  bool isLoading = true;

  Timer? _searchDebounce;

  int _requestId = 0;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();

    loadProducts();
  }

  // =========================================================
  // RELOAD WHEN FILTERS CHANGE
  // =========================================================

  @override
  void didUpdateWidget(covariant BuyerProductListPage oldWidget) {
    super.didUpdateWidget(oldWidget);

    final categoryChanged = oldWidget.categorySlug != widget.categorySlug;

    final sellerChanged = oldWidget.sellerSlug != widget.sellerSlug;

    if (categoryChanged || sellerChanged) {
      _searchDebounce?.cancel();

      searchController.clear();

      loadProducts(query: '');
    }
  }

  // =========================================================
  // DISPOSE
  // =========================================================

  @override
  void dispose() {
    _requestId++;

    _searchDebounce?.cancel();

    searchController.dispose();
    _searchFocusNode.dispose();

    super.dispose();
  }

  // =========================================================
  // LOAD PRODUCTS
  // =========================================================

  Future<void> loadProducts({String? query}) async {
    if (!mounted) return;

    final currentRequestId = ++_requestId;

    final searchQuery = (query ?? searchController.text).trim();

    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await BuyerCatalogApi.getProducts(
        category: widget.categorySlug,
        seller: widget.sellerSlug,
        query: searchQuery.isEmpty ? null : searchQuery,
      ).timeout(const Duration(seconds: 25));

      if (!mounted || currentRequestId != _requestId) {
        return;
      }

      setState(() {
        products = result;
        isLoading = false;
        errorMessage = null;
      });
    } on TimeoutException {
      if (!mounted || currentRequestId != _requestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage =
            'The server took too long to respond. '
            'Please try again.';
      });
    } catch (error) {
      if (!mounted || currentRequestId != _requestId) {
        return;
      }

      final message = error.toString().replaceFirst('Exception: ', '').trim();

      setState(() {
        isLoading = false;
        errorMessage = message.isEmpty ? 'Unable to load products.' : message;
      });
    }
  }

  // =========================================================
  // SEARCH
  // =========================================================

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();

    // Invalidate any earlier request immediately.
    ++_requestId;

    _searchDebounce = Timer(const Duration(milliseconds: 500), () {
      if (!mounted) return;

      loadProducts(query: value);
    });
  }

  void _submitSearch() {
    _searchDebounce?.cancel();

    _searchFocusNode.unfocus();

    loadProducts(query: searchController.text);
  }

  void _clearSearch() {
    _searchDebounce?.cancel();

    searchController.clear();

    _searchFocusNode.unfocus();

    loadProducts(query: '');
  }

  // =========================================================
  // REFRESH
  // =========================================================

  Future<void> _refreshProducts() async {
    _searchDebounce?.cancel();

    await loadProducts(query: searchController.text);
  }

  // =========================================================
  // NAVIGATION
  // =========================================================

  void _openProduct(BuyerHomeItem item) {
    if (item.slug.trim().isEmpty) {
      _showMessage('Product information is unavailable.');
      return;
    }

    _searchFocusNode.unfocus();

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailsPage(
          product: ProductDetailsData.fromCatalogItem(item),
        ),
      ),
    );
  }

  // =========================================================
  // MESSAGE
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
  // PAGE TITLE
  // =========================================================

  String get _pageTitle {
    final categoryName = widget.categoryName?.trim() ?? '';

    if (categoryName.isNotEmpty) {
      return categoryName;
    }

    if (widget.sellerSlug?.trim().isNotEmpty == true) {
      return 'Store';
    }

    return 'Shop';
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_pageTitle)),
      bottomNavigationBar: BuyerBottomNav(
        selectedIndex: 1,
        onSelected: (index) {
          switch (index) {
            case 0:
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/buyer-home',
                (route) => false,
              );
              break;
            case 2:
              Navigator.pushNamed(context, '/buyer-cart');
              break;
            case 3:
              Navigator.pushNamed(context, '/buyer-profile');
              break;
          }
        },
      ),

      body: Column(
        children: [
          // =====================================
          // SEARCH BAR
          // =====================================
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: _buildSearchBar(),
          ),

          // =====================================
          // PRODUCT CONTENT
          // =====================================
          Expanded(child: _content()),
        ],
      ),
    );
  }

  // =========================================================
  // SEARCH BAR
  // =========================================================

  Widget _buildSearchBar() {
    return TextField(
      controller: searchController,
      focusNode: _searchFocusNode,
      textInputAction: TextInputAction.search,
      onChanged: _onSearchChanged,
      onSubmitted: (_) => _submitSearch(),
      decoration: InputDecoration(
        hintText: 'Search products',

        prefixIcon: const Icon(Icons.search),

        suffixIcon: ValueListenableBuilder<TextEditingValue>(
          valueListenable: searchController,
          builder: (context, value, child) {
            if (value.text.isNotEmpty) {
              return IconButton(
                tooltip: 'Clear search',
                onPressed: _clearSearch,
                icon: const Icon(Icons.close),
              );
            }

            return IconButton(
              tooltip: 'Search',
              onPressed: _submitSearch,
              icon: const Icon(Icons.arrow_forward),
            );
          },
        ),

        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  // =========================================================
  // CONTENT
  // =========================================================

  Widget _content() {
    if (isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (errorMessage != null) {
      return _buildErrorState();
    }

    if (products.isEmpty) {
      return _buildEmptyState();
    }

    return RefreshIndicator(
      onRefresh: _refreshProducts,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final availableWidth = constraints.maxWidth;

          // ===================================
          // RESPONSIVE COLUMN COUNT
          // ===================================

          final columns = availableWidth >= 900
              ? 4
              : availableWidth >= 600
              ? 3
              : 2;

          const horizontalPadding = 14.0;
          const crossSpacing = 10.0;

          final gridWidth = availableWidth - (horizontalPadding * 2);

          final cardWidth =
              (gridWidth - ((columns - 1) * crossSpacing)) / columns;

          // Reserve space for:
          // - Square product image
          // - Product title
          // - Price
          // - Stock information
          final cardHeight = cardWidth + 112;

          return GridView.builder(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),

            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),

            itemCount: products.length,

            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              crossAxisSpacing: crossSpacing,
              mainAxisSpacing: 10,
              mainAxisExtent: cardHeight,
            ),

            itemBuilder: (context, index) {
              return _productCard(products[index]);
            },
          );
        },
      ),
    );
  }

  // =========================================================
  // ERROR STATE
  // =========================================================

  Widget _buildErrorState() {
    return RefreshIndicator(
      onRefresh: _refreshProducts,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 85),

          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.wifi_off_rounded,
                  size: 55,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),

                const SizedBox(height: 16),

                Text(
                  'Unable to load products',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),

                const SizedBox(height: 9),

                Text(
                  errorMessage ?? 'Please try again.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),

                const SizedBox(height: 17),

                FilledButton.icon(
                  onPressed: _refreshProducts,
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
  // EMPTY STATE
  // =========================================================

  Widget _buildEmptyState() {
    final hasSearch = searchController.text.trim().isNotEmpty;

    return RefreshIndicator(
      onRefresh: _refreshProducts,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 85),

          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              children: [
                Icon(
                  Icons.inventory_2_outlined,
                  size: 58,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),

                const SizedBox(height: 15),

                Text(
                  hasSearch ? 'No matching products' : 'No products available',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),

                const SizedBox(height: 9),

                Text(
                  hasSearch
                      ? 'Try another product name '
                            'or search keyword.'
                      : 'Products will appear here '
                            'when they become available.',
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 18),

                if (hasSearch)
                  OutlinedButton.icon(
                    onPressed: _clearSearch,
                    icon: const Icon(Icons.clear),
                    label: const Text('Clear Search'),
                  ),

                const SizedBox(height: 10),

                TextButton.icon(
                  onPressed: _refreshProducts,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Refresh Products'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // PRODUCT IMAGE
  // =========================================================

  Widget _productImage(BuyerHomeItem item) {
    return ProductPhotoCarousel(
      images: item.images.isEmpty ? [item.image] : item.images,
      placeholder: _imagePlaceholder(),
    );
  }

  Widget _imagePlaceholder() {
    return ColoredBox(
      color: AppTheme.surfaceMuted,
      child: const Center(child: Icon(Icons.image_outlined, size: 42)),
    );
  }

  // =========================================================
  // PRICE DISPLAY
  // =========================================================

  String _displayPrice(String price) {
    final value = price.trim();

    if (value.isEmpty) {
      return 'Price unavailable';
    }

    // Avoid showing the peso symbol twice.
    if (value.startsWith('₱')) {
      return value;
    }

    if (value.toUpperCase().startsWith('PHP')) {
      return value;
    }

    return '₱$value';
  }

  // =========================================================
  // PRODUCT CARD
  // =========================================================

  Widget _productCard(BuyerHomeItem item) {
    final inStock = item.stock > 0;

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _openProduct(item),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ===================================
            // PRODUCT IMAGE
            // ===================================
            AspectRatio(aspectRatio: 1, child: _productImage(item)),

            // ===================================
            // PRODUCT INFORMATION
            // ===================================
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 9, 10, 10),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name.isEmpty ? 'Unnamed Product' : item.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),

                    const SizedBox(height: 5),

                    Text(
                      _displayPrice(item.price),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),

                    const Spacer(),

                    Text(
                      inStock ? '${item.stock} in stock' : 'Out of stock',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 11,
                        color: inStock
                            ? Theme.of(context).colorScheme.onSurfaceVariant
                            : Colors.red,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
