import 'package:flutter/material.dart';

import 'buyer_home_api.dart';
import 'product_details_style.dart';
import '../../services/auth_service.dart';
import '../../services/cart_service.dart';
import '../../services/wishlist_service.dart';
import '../../theme/app_theme.dart';

// =========================================================
// PRODUCT DATA MODEL
// =========================================================

class ProductDetailsData {
  final String id;
  final String slug;
  final String defaultVariantId;
  final String name;
  final String category;

  final String price;
  final String oldPrice;
  final String discount;

  final String rating;
  final String ratings;
  final String sold;
  final int stock;

  final String description;

  final String seller;
  final String sellerLogo;
  final String sellerRating;
  final String sellerProducts;
  final String sellerSlug;

  final List<String> images;
  final String? videoUrl;

  final List<String> colors;
  final List<String> sizes;

  final List<ProductFeature> features;
  final List<ProductReview> reviews;
  final List<RelatedProduct> relatedProducts;

  final List<ProductVariant> variants;
  final Map<String, List<String>> mediaByColor;

  ProductDetailsData({
    required this.id,
    required this.slug,
    this.defaultVariantId = '',
    required this.name,
    required this.category,
    required this.price,
    this.oldPrice = '',
    this.discount = '',
    this.rating = '0.0',
    this.ratings = '0',
    this.sold = '0',
    this.stock = 0,
    this.description = '',
    this.seller = '',
    this.sellerLogo = '',
    this.sellerRating = '0.0',
    this.sellerProducts = '0',
    this.sellerSlug = '',
    this.images = const [],
    this.videoUrl,
    this.colors = const [],
    this.sizes = const [],
    this.features = const [],
    this.reviews = const [],
    this.relatedProducts = const [],
    this.variants = const [],
    this.mediaByColor = const {},
  });

  factory ProductDetailsData.fromCatalogItem(BuyerHomeItem item) {
    final images = item.images.isNotEmpty
        ? item.images
        : item.image.isNotEmpty
        ? [item.image]
        : const <String>[];

    return ProductDetailsData(
      id: item.id,
      slug: item.slug,
      defaultVariantId: item.variants.isNotEmpty
          ? (item.variants.where((variant) => variant.stock > 0).isNotEmpty
                ? item.variants.firstWhere((variant) => variant.stock > 0).id
                : item.variants.first.id)
          : '',
      name: item.name,
      category: item.category,
      price: item.price,
      oldPrice: item.oldPrice,
      discount: item.discount,
      rating: item.rating,
      sold: item.sold,
      stock: item.stock,
      seller: item.seller,
      sellerSlug: item.sellerSlug,
      sellerLogo: item.sellerLogo,
      sellerRating: item.sellerRating,
      sellerProducts: item.sellerProducts,
      images: images,
      colors: item.variants
          .map((variant) => variant.options['Color']?.toString() ?? '')
          .where((value) => value.isNotEmpty)
          .toSet()
          .toList(),
      sizes: item.variants
          .map((variant) => variant.options['Size']?.toString() ?? '')
          .where((value) => value.isNotEmpty)
          .toSet()
          .toList(),
      variants: item.variants
          .map(
            (variant) => ProductVariant(
              id: variant.id,
              color: variant.options['Color']?.toString() ?? '',
              size: variant.options['Size']?.toString() ?? '',
              stock: variant.stock,
              image: variant.image,
            ),
          )
          .toList(),
      mediaByColor: item.mediaByColor,
    );
  }
}

class ProductFeature {
  final String name;

  ProductFeature({required this.name});
}

class ProductReview {
  final String rating;
  final String body;
  final String sellerResponse;

  ProductReview({
    required this.rating,
    required this.body,
    this.sellerResponse = '',
  });
}

class RelatedProduct {
  final String id;
  final String slug;
  final String name;
  final String price;
  final String image;

  RelatedProduct({
    required this.id,
    required this.slug,
    required this.name,
    required this.price,
    required this.image,
  });
}

class ProductVariant {
  final String id;
  final String color;
  final String size;
  final int stock;
  final String image;

  ProductVariant({
    required this.id,
    this.color = '',
    this.size = '',
    required this.stock,
    this.image = '',
  });
}

// =========================================================
// PRODUCT DETAILS PAGE
// =========================================================

class ProductDetailsPage extends StatefulWidget {
  final ProductDetailsData product;

  ProductDetailsPage({super.key, required this.product});

  @override
  State<ProductDetailsPage> createState() => _ProductDetailsPageState();
}

class BuyerProductRoutePage extends StatefulWidget {
  final String slug;

  BuyerProductRoutePage({super.key, required this.slug});

  @override
  State<BuyerProductRoutePage> createState() => _BuyerProductRoutePageState();
}

class _BuyerProductRoutePageState extends State<BuyerProductRoutePage> {
  late Future<BuyerHomeItem> productFuture;

  @override
  void initState() {
    super.initState();
    productFuture = BuyerCatalogApi.getProduct(widget.slug);
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return FutureBuilder<BuyerHomeItem>(
      future: productFuture,
      builder: (context, snapshot) {
        if (snapshot.hasData) {
          return ProductDetailsPage(
            product: ProductDetailsData.fromCatalogItem(snapshot.data!),
          );
        }
        if (snapshot.hasError) {
          return Scaffold(
            appBar: AppBar(title: Text('Product')),
            body: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  snapshot.error.toString().replaceFirst('Exception: ', ''),
                  textAlign: TextAlign.center,
                ),
              ),
            ),
          );
        }
        return Scaffold(body: Center(child: CircularProgressIndicator()));
      },
    );
  }
}

class _ProductDetailsPageState extends State<ProductDetailsPage> {
  int currentMediaIndex = 0;
  int quantity = 1;

  String? selectedColor;
  String? selectedSize;

  String? selectedVariantId;

  bool isWishlisted = false;
  bool isUpdatingWishlist = false;
  bool showReviews = false;
  bool isSavingCart = false;

  ProductDetailsData get product => widget.product;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();

    // Start with a real, available combination rather than picking
    // independently from color and size lists.
    if (product.variants.isNotEmpty) {
      final available = product.variants.where((v) => v.stock > 0);
      final initial = available.isNotEmpty
          ? available.first
          : product.variants.first;
      selectedColor = initial.color.isEmpty ? null : initial.color;
      selectedSize = initial.size.isEmpty ? null : initial.size;
    } else {
      selectedColor = product.colors.isNotEmpty ? product.colors.first : null;
      selectedSize = product.sizes.isNotEmpty ? product.sizes.first : null;
    }

    _resolveVariant();
    _loadWishlistState();
  }

  Future<void> _loadWishlistState() async {
    if (product.slug.isEmpty) return;
    try {
      final token = await AuthService.getToken();
      if (!mounted || token == null || token.isEmpty) return;
      final saved = await WishlistService.contains(product.slug);
      if (mounted) setState(() => isWishlisted = saved);
    } catch (_) {
      // Keep the product details usable when wishlist status cannot load.
    }
  }

  // =========================================================
  // PRODUCT MEDIA
  // =========================================================

  int get mediaCount {
    return product.images.length +
        (product.videoUrl != null && product.videoUrl!.isNotEmpty ? 1 : 0);
  }

  bool get hasVideo {
    return product.videoUrl != null && product.videoUrl!.isNotEmpty;
  }

  bool get isVideoSelected {
    return hasVideo && currentMediaIndex == product.images.length;
  }

  String get currentImage {
    if (product.images.isEmpty) return '';

    return product.images[currentMediaIndex.clamp(
      0,
      product.images.length - 1,
    )];
  }

  void _changeMedia(int index) {
    if (mediaCount == 0) return;

    setState(() {
      currentMediaIndex = index.clamp(0, mediaCount - 1);
    });
  }

  void _nextMedia() {
    if (mediaCount <= 1) return;

    setState(() {
      currentMediaIndex = (currentMediaIndex + 1) % mediaCount;
    });
  }

  void _previousMedia() {
    if (mediaCount <= 1) return;

    setState(() {
      currentMediaIndex = (currentMediaIndex - 1 + mediaCount) % mediaCount;
    });
  }

  // =========================================================
  // IMAGE WIDGET
  // =========================================================

  Widget _productImage(String url, {BoxFit fit = BoxFit.contain}) {
    if (url.isEmpty) {
      return _imagePlaceholder();
    }

    return Image.network(
      url,
      fit: fit,
      width: double.infinity,
      errorBuilder: (context, error, stackTrace) {
        return _imagePlaceholder();
      },
    );
  }

  Widget _imagePlaceholder() {
    return Container(
      color: AppTheme.surfaceMuted,
      alignment: Alignment.center,
      child: Icon(
        Icons.image_not_supported_outlined,
        size: 45,
        color: ProductDetailsStyle.textSecondary,
      ),
    );
  }

  // =========================================================
  // VARIANT SELECTION
  // =========================================================

  ProductVariant? get _matchingVariant {
    for (final variant in product.variants) {
      final colorMatches =
          selectedColor == null || variant.color == selectedColor;
      final sizeMatches = selectedSize == null || variant.size == selectedSize;
      if (colorMatches && sizeMatches) return variant;
    }
    return null;
  }

  void _resolveVariant() {
    selectedVariantId = _matchingVariant?.id;
  }

  int get availableStock {
    if (product.variants.isEmpty) return product.stock;
    return _matchingVariant?.stock ?? 0;
  }

  void _selectColor(String color) {
    setState(() {
      selectedColor = color;
      quantity = 1;

      // If the previous size doesn't exist for the new color,
      // select a real available size within that color.
      final matches = product.variants.where((v) => v.color == color).toList();
      if (matches.isNotEmpty && !matches.any((v) => v.size == selectedSize)) {
        final stocked = matches.where((v) => v.stock > 0);
        final selected = stocked.isNotEmpty ? stocked.first : matches.first;
        selectedSize = selected.size.isEmpty ? null : selected.size;
      }

      final images = product.mediaByColor[color];
      if (images != null && images.isNotEmpty) {
        final index = product.images.indexOf(images.first);
        if (index >= 0) currentMediaIndex = index;
      }
      _resolveVariant();
    });
  }

  void _selectSize(String size) {
    setState(() {
      selectedSize = size;
      quantity = 1;
      // Likewise, avoid choosing an impossible color/size pair.
      final matches = product.variants.where((v) => v.size == size).toList();
      if (matches.isNotEmpty && !matches.any((v) => v.color == selectedColor)) {
        final stocked = matches.where((v) => v.stock > 0);
        final selected = stocked.isNotEmpty ? stocked.first : matches.first;
        selectedColor = selected.color.isEmpty ? null : selected.color;
      }
      _resolveVariant();
    });
  }

  // =========================================================
  // QUANTITY
  // =========================================================

  void _increaseQuantity() {
    if (quantity < availableStock) {
      setState(() {
        quantity++;
      });
    } else {
      _showMessage('Maximum available stock reached.');
    }
  }

  void _decreaseQuantity() {
    if (quantity > 1) {
      setState(() {
        quantity--;
      });
    }
  }

  // =========================================================
  // WISHLIST
  // =========================================================

  Future<void> _toggleWishlist() async {
    final token = await AuthService.getToken();
    if (!mounted) return;
    if (token == null || token.isEmpty) {
      Navigator.pushNamed(context, '/login');
      return;
    }

    setState(() => isUpdatingWishlist = true);
    try {
      final saved = await WishlistService.toggle(product.slug);
      if (!mounted) return;
      setState(() => isWishlisted = saved);
      _showMessage(
        saved
            ? 'Added to your SUKI wishlist.'
            : 'Removed from your SUKI wishlist.',
      );
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString().replaceFirst('Exception: ', ''));
      }
    } finally {
      if (mounted) setState(() => isUpdatingWishlist = false);
    }
  }

  // =========================================================
  // CART
  // =========================================================

  Future<void> _addToCart({bool continueToCheckout = false}) async {
    if (isSavingCart) return;

    if (availableStock <= 0) {
      _showMessage('This product is currently unavailable.');
      return;
    }
    if (quantity < 1 || quantity > availableStock) {
      _showMessage('Please choose a quantity within available stock.');
      return;
    }

    // If variants exist, the selected ID must match the selected
    // combination. Never fall back to another variant silently.
    final variantId = product.variants.isNotEmpty
        ? (selectedVariantId ?? '')
        : product.defaultVariantId;
    if (variantId.isEmpty) {
      _showMessage('An available product variant is required for checkout.');
      return;
    }

    setState(() => isSavingCart = true);
    try {
      final token = await AuthService.getToken();
      if (!mounted) return;
      if (token == null || token.isEmpty) {
        Navigator.pushNamed(context, '/login');
        return;
      }

      await CartService.addItem(variantId: variantId, quantity: quantity);
      if (!mounted) return;

      if (continueToCheckout) {
        Navigator.pushNamed(context, '/buyer-checkout');
      } else {
        _showMessage('${product.name} was added to your cart.');
      }
    } catch (error) {
      if (!mounted) return;
      _showMessage(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => isSavingCart = false);
    }
  }

  // =========================================================
  // NAVIGATION
  // =========================================================

  void _openShop() {
    Navigator.pushNamed(context, '/products');
  }

  void _openSellerShop() {
    final slug = product.sellerSlug.trim();
    if (slug.isEmpty) {
      _showMessage('Seller shop is not available.');
      return;
    }
    Navigator.pushNamed(context, '/stores/${Uri.encodeComponent(slug)}');
  }

  void _openRelatedProduct(RelatedProduct related) {
    final slug = related.slug.trim();
    if (slug.isEmpty) {
      _showMessage('This product is currently unavailable.');
      return;
    }
    Navigator.pushNamed(context, '/product/${Uri.encodeComponent(slug)}');
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
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: ProductDetailsStyle.background,

      appBar: AppBar(
        backgroundColor: ProductDetailsStyle.white,
        elevation: 0,
        scrolledUnderElevation: 0,

        leading: IconButton(
          onPressed: _openShop,
          icon: Icon(Icons.arrow_back, color: ProductDetailsStyle.textPrimary),
        ),

        title: Text('Product Details', style: ProductDetailsStyle.sectionTitle),

        actions: [
          IconButton(
            tooltip: 'Wishlist',
            onPressed: isUpdatingWishlist ? null : _toggleWishlist,
            icon: isUpdatingWishlist
                ? SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Icon(
                    isWishlisted ? Icons.favorite : Icons.favorite_border,
                    color: isWishlisted
                        ? Colors.red
                        : ProductDetailsStyle.textPrimary,
                  ),
          ),
        ],
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          physics: BouncingScrollPhysics(),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildBreadcrumb(),

              _buildProductSection(),

              _buildSellerSection(),

              _buildDescriptionSection(),

              _buildRelatedProducts(),

              SizedBox(height: 30),
            ],
          ),
        ),
      ),

      bottomNavigationBar: _buildBottomActions(),
    );
  }

  // =========================================================
  // BREADCRUMB
  // =========================================================

  Widget _buildBreadcrumb() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(15, 15, 15, 12),

      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 5,

        children: [
          GestureDetector(
            onTap: _openShop,
            child: Text('Home', style: ProductDetailsStyle.label),
          ),

          Icon(
            Icons.chevron_right,
            size: 15,
            color: ProductDetailsStyle.textSecondary,
          ),

          GestureDetector(
            onTap: _openShop,
            child: Text('Shop', style: ProductDetailsStyle.label),
          ),

          Icon(
            Icons.chevron_right,
            size: 15,
            color: ProductDetailsStyle.textSecondary,
          ),

          Text(
            product.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11,
              color: ProductDetailsStyle.textPrimary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // PRODUCT SECTION
  // =========================================================

  Widget _buildProductSection() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12),

      padding: const EdgeInsets.all(13),

      decoration: ProductDetailsStyle.cardDecoration,

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          _buildMediaGallery(),

          SizedBox(height: 20),

          _buildProductInformation(),

          SizedBox(height: 17),

          _buildPriceCard(),

          SizedBox(height: 17),

          _buildShipping(),

          if (product.colors.isNotEmpty) ...[
            SizedBox(height: 18),
            _buildColorSelector(),
          ],

          if (product.sizes.isNotEmpty) ...[
            SizedBox(height: 18),
            _buildSizeSelector(),
          ],

          SizedBox(height: 20),

          _buildQuantitySelector(),

          SizedBox(height: 12),

          _buildStockInformation(),
        ],
      ),
    );
  }

  // =========================================================
  // MEDIA GALLERY
  // =========================================================

  Widget _buildMediaGallery() {
    return Column(
      children: [
        AspectRatio(
          aspectRatio: 1,

          child: ClipRRect(
            borderRadius: BorderRadius.circular(12),

            child: Stack(
              fit: StackFit.expand,

              children: [
                if (isVideoSelected)
                  Container(
                    color: ProductDetailsStyle.textPrimary,
                    alignment: Alignment.center,

                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.play_circle_outline,
                          color: Colors.white,
                          size: 65,
                        ),
                        SizedBox(height: 8),
                        Text(
                          'Product video preview',
                          style: TextStyle(color: Colors.white, fontSize: 12),
                        ),
                        Text(
                          'Video playback will be enabled later.',
                          style: TextStyle(color: Colors.white70, fontSize: 10),
                        ),
                      ],
                    ),
                  )
                else
                  _productImage(currentImage, fit: BoxFit.contain),

                if (mediaCount > 1) ...[
                  Positioned(
                    left: 9,
                    top: 0,
                    bottom: 0,

                    child: Center(
                      child: _mediaArrow(Icons.chevron_left, _previousMedia),
                    ),
                  ),

                  Positioned(
                    right: 9,
                    top: 0,
                    bottom: 0,

                    child: Center(
                      child: _mediaArrow(Icons.chevron_right, _nextMedia),
                    ),
                  ),
                ],

                Positioned(
                  top: 10,
                  right: 10,

                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),

                    decoration: BoxDecoration(
                      color: ProductDetailsStyle.textSecondary,
                      borderRadius: BorderRadius.circular(20),
                    ),

                    child: Text(
                      mediaCount == 0
                          ? '0/0'
                          : '${currentMediaIndex + 1}/$mediaCount',
                      style: TextStyle(color: Colors.white, fontSize: 10),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),

        if (mediaCount > 1) ...[
          SizedBox(height: 12),

          SizedBox(
            height: 76,

            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: mediaCount,

              separatorBuilder: (_, _) => SizedBox(width: 9),

              itemBuilder: (context, index) {
                final isSelected = currentMediaIndex == index;
                final isVideo = hasVideo && index == product.images.length;

                return GestureDetector(
                  onTap: () => _changeMedia(index),

                  child: Container(
                    width: 76,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(
                        color: isSelected
                            ? ProductDetailsStyle.primary
                            : ProductDetailsStyle.border,
                        width: isSelected ? 2 : 1,
                      ),
                    ),

                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(7),

                      child: isVideo
                          ? Container(
                              color: ProductDetailsStyle.textPrimary,
                              child: Icon(
                                Icons.play_circle_outline,
                                color: Colors.white,
                                size: 30,
                              ),
                            )
                          : _productImage(
                              product.images[index],
                              fit: BoxFit.cover,
                            ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }

  Widget _mediaArrow(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withValues(alpha: 0.9),
      shape: CircleBorder(),

      child: InkWell(
        onTap: onTap,
        customBorder: CircleBorder(),

        child: Padding(
          padding: const EdgeInsets.all(7),

          child: Icon(icon, size: 22, color: ProductDetailsStyle.textPrimary),
        ),
      ),
    );
  }

  // =========================================================
  // PRODUCT INFORMATION
  // =========================================================

  Widget _buildProductInformation() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text(
          product.category,
          style: TextStyle(
            color: ProductDetailsStyle.primary,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),

        SizedBox(height: 7),

        Text(product.name, style: ProductDetailsStyle.productName),

        SizedBox(height: 12),

        Wrap(
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 10,
          runSpacing: 7,

          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.star_rounded,
                  color: ProductDetailsStyle.accent,
                  size: 18,
                ),

                SizedBox(width: 4),

                Text(
                  product.rating,
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                ),
              ],
            ),

            Text(
              '${product.ratings} Ratings',
              style: TextStyle(
                fontSize: 11,
                color: ProductDetailsStyle.textSecondary,
                decoration: TextDecoration.underline,
              ),
            ),

            Text('${product.sold} Sold', style: ProductDetailsStyle.label),
          ],
        ),
      ],
    );
  }

  // =========================================================
  // PRICE
  // =========================================================

  Widget _buildPriceCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(15),
      decoration: ProductDetailsStyle.priceDecoration,

      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 10,
        runSpacing: 7,

        children: [
          Text('₱${product.price}', style: ProductDetailsStyle.price),

          if (product.oldPrice.isNotEmpty)
            Text(
              '₱${product.oldPrice}',
              style: TextStyle(
                fontSize: 13,
                color: ProductDetailsStyle.textSecondary,
                decoration: TextDecoration.lineThrough,
              ),
            ),

          if (product.discount.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),

              decoration: BoxDecoration(
                color: ProductDetailsStyle.accent,
                borderRadius: BorderRadius.circular(5),
              ),

              child: Text(
                product.discount,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
        ],
      ),
    );
  }

  // =========================================================
  // SHIPPING
  // =========================================================

  Widget _buildShipping() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 15),

      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: ProductDetailsStyle.border)),
      ),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.local_shipping_outlined,
            color: ProductDetailsStyle.primary,
            size: 22,
          ),

          SizedBox(width: 13),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Shipping',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                ),

                SizedBox(height: 5),

                Text(
                  'Choose your preferred delivery option at checkout.',
                  style: ProductDetailsStyle.body,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // COLOR SELECTOR
  // =========================================================

  Widget _buildColorSelector() {
    return _buildOptionSelector(
      label: 'Color',
      options: product.colors,
      selected: selectedColor,
      onSelected: _selectColor,
    );
  }

  // =========================================================
  // SIZE SELECTOR
  // =========================================================

  Widget _buildSizeSelector() {
    return _buildOptionSelector(
      label: 'Size',
      options: product.sizes,
      selected: selectedSize,
      onSelected: _selectSize,
    );
  }

  Widget _buildOptionSelector({
    required String label,
    required List<String> options,
    required String? selected,
    required ValueChanged<String> onSelected,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        SizedBox(
          width: 58,
          child: Padding(
            padding: const EdgeInsets.only(top: 9),
            child: Text(label, style: ProductDetailsStyle.label),
          ),
        ),

        Expanded(
          child: Wrap(
            spacing: 8,
            runSpacing: 8,

            children: options.map((option) {
              final active = selected == option;

              return GestureDetector(
                onTap: () => onSelected(option),

                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 13,
                    vertical: 9,
                  ),

                  decoration: BoxDecoration(
                    color: active
                        ? ProductDetailsStyle.background
                        : ProductDetailsStyle.white,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: active
                          ? ProductDetailsStyle.primary
                          : ProductDetailsStyle.border,
                      width: active ? 2 : 1,
                    ),
                  ),

                  child: Text(
                    option,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: active
                          ? ProductDetailsStyle.primary
                          : ProductDetailsStyle.textPrimary,
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  // =========================================================
  // QUANTITY
  // =========================================================

  Widget _buildQuantitySelector() {
    return Row(
      children: [
        SizedBox(
          width: 58,
          child: Text('Quantity', style: ProductDetailsStyle.label),
        ),

        Container(
          decoration: BoxDecoration(
            border: Border.all(color: ProductDetailsStyle.border),
            borderRadius: BorderRadius.circular(9),
          ),

          child: Row(
            children: [
              _quantityButton(Icons.remove, _decreaseQuantity),

              SizedBox(
                width: 35,
                child: Text(
                  '$quantity',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                ),
              ),

              _quantityButton(Icons.add, _increaseQuantity),
            ],
          ),
        ),

        SizedBox(width: 10),

        Expanded(
          child: Text(
            '$availableStock pieces available',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 9,
              color: ProductDetailsStyle.textSecondary,
            ),
          ),
        ),
      ],
    );
  }

  Widget _quantityButton(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,

      child: SizedBox(width: 36, height: 37, child: Icon(icon, size: 17)),
    );
  }

  Widget _buildStockInformation() {
    if (availableStock <= 0) {
      return Text(
        'Out of stock',
        style: TextStyle(
          color: ProductDetailsStyle.error,
          fontSize: 11,
          fontWeight: FontWeight.bold,
        ),
      );
    }

    return const SizedBox.shrink();
  }

  // =========================================================
  // BOTTOM ACTIONS
  // =========================================================

  Widget _buildBottomActions() {
    final unavailable = availableStock <= 0;

    return SafeArea(
      top: false,

      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        decoration: BoxDecoration(
          color: ProductDetailsStyle.white,
          border: Border(top: BorderSide(color: ProductDetailsStyle.border)),
        ),

        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                style: ProductDetailsStyle.outlinedButton,
                onPressed: unavailable || isSavingCart ? null : _addToCart,
                icon: isSavingCart
                    ? SizedBox(
                        width: 15,
                        height: 15,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(Icons.shopping_cart_outlined, size: 17),
                label: Text('Add to Cart', style: TextStyle(fontSize: 11)),
              ),
            ),

            SizedBox(width: 9),

            Expanded(
              child: ElevatedButton(
                style: ProductDetailsStyle.primaryButton,
                onPressed: unavailable || isSavingCart
                    ? null
                    : () => _addToCart(continueToCheckout: true),
                child: isSavingCart
                    ? SizedBox(
                        width: 17,
                        height: 17,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(
                        'Buy Now',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // SELLER SECTION
  // =========================================================

  Widget _buildSellerSection() {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 17, 12, 0),
      padding: const EdgeInsets.all(15),

      decoration: ProductDetailsStyle.cardDecoration,

      child: Column(
        children: [
          Row(
            children: [
              ClipOval(
                child: SizedBox(
                  width: 53,
                  height: 53,

                  child: product.sellerLogo.isNotEmpty
                      ? _productImage(product.sellerLogo, fit: BoxFit.cover)
                      : Container(
                          color: ProductDetailsStyle.lightGreen,
                          child: Icon(
                            Icons.store_outlined,
                            color: ProductDetailsStyle.primary,
                            size: 25,
                          ),
                        ),
                ),
              ),

              SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      product.seller.isEmpty
                          ? 'SUKI SHOP Seller'
                          : product.seller,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),

                    SizedBox(height: 5),

                    Text('Seller information', style: ProductDetailsStyle.body),

                    SizedBox(height: 6),

                    Wrap(
                      spacing: 9,
                      children: [
                        Text(
                          '${product.sellerRating} Store Rating',
                          style: TextStyle(
                            color: ProductDetailsStyle.primary,
                            fontSize: 9,
                          ),
                        ),

                        Text(
                          '${product.sellerProducts} Products',
                          style: ProductDetailsStyle.label.copyWith(
                            fontSize: 9,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),

          SizedBox(height: 14),

          SizedBox(
            width: double.infinity,

            child: OutlinedButton(
              style: ProductDetailsStyle.outlinedButton,
              onPressed: _openSellerShop,
              child: Text('View Shop'),
            ),
          ),

          SizedBox(height: 8),

          SizedBox(
            width: double.infinity,

            child: ElevatedButton.icon(
              style: ProductDetailsStyle.primaryButton,
              onPressed: () {
                _showMessage('Seller chat will be connected later.');
              },
              icon: Icon(Icons.chat_outlined, size: 17),
              label: Text('Chat with Seller'),
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // DESCRIPTION AND REVIEWS
  // =========================================================

  Widget _buildDescriptionSection() {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 17, 12, 0),

      decoration: ProductDetailsStyle.cardDecoration,

      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _tabButton('Product Description', !showReviews, () {
                  setState(() {
                    showReviews = false;
                  });
                }),
              ),

              Expanded(
                child: _tabButton(
                  'Reviews (${product.ratings})',
                  showReviews,
                  () {
                    setState(() {
                      showReviews = true;
                    });
                  },
                ),
              ),
            ],
          ),

          Padding(
            padding: const EdgeInsets.all(16),

            child: showReviews ? _buildReviews() : _buildDescription(),
          ),
        ],
      ),
    );
  }

  Widget _tabButton(String title, bool active, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,

      child: Container(
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 5),

        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: active ? ProductDetailsStyle.primary : Colors.transparent,
              width: 2,
            ),
          ),
        ),

        child: Text(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            fontSize: 10,
            fontWeight: active ? FontWeight.bold : FontWeight.w500,
            color: active
                ? ProductDetailsStyle.primary
                : ProductDetailsStyle.textSecondary,
          ),
        ),
      ),
    );
  }

  Widget _buildDescription() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        Text('About this product', style: ProductDetailsStyle.sectionTitle),

        SizedBox(height: 12),

        Text(
          product.description.isEmpty
              ? 'No product description available.'
              : product.description,
          style: ProductDetailsStyle.body,
        ),

        if (product.features.isNotEmpty) ...[
          SizedBox(height: 17),

          ...product.features.map((feature) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),

              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Icon(
                    Icons.check_circle,
                    color: ProductDetailsStyle.primary,
                    size: 15,
                  ),

                  SizedBox(width: 9),

                  Expanded(
                    child: Text(feature.name, style: ProductDetailsStyle.body),
                  ),
                ],
              ),
            );
          }),
        ],
      ],
    );
  }

  Widget _buildReviews() {
    if (product.reviews.isEmpty) {
      return Padding(
        padding: EdgeInsets.symmetric(vertical: 25),

        child: Center(
          child: Text('No reviews yet.', style: ProductDetailsStyle.body),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,

      children: [
        ...product.reviews.map((review) {
          return Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 13),

            decoration: BoxDecoration(
              border: Border(
                bottom: BorderSide(color: ProductDetailsStyle.border),
              ),
            ),

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Row(
                  children: [
                    Icon(
                      Icons.star_rounded,
                      color: ProductDetailsStyle.accent,
                      size: 17,
                    ),

                    SizedBox(width: 4),

                    Text(
                      '${review.rating}/5',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),

                SizedBox(height: 8),

                Text(review.body, style: ProductDetailsStyle.body),

                if (review.sellerResponse.isNotEmpty) ...[
                  SizedBox(height: 10),

                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(10),

                    decoration: BoxDecoration(
                      color: ProductDetailsStyle.background,
                      borderRadius: BorderRadius.circular(8),
                    ),

                    child: Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: 'Seller response: ',
                            style: TextStyle(fontWeight: FontWeight.bold),
                          ),
                          TextSpan(text: review.sellerResponse),
                        ],
                      ),
                      style: ProductDetailsStyle.body,
                    ),
                  ),
                ],
              ],
            ),
          );
        }),
      ],
    );
  }

  // =========================================================
  // RELATED PRODUCTS
  // =========================================================

  Widget _buildRelatedProducts() {
    final related = product.relatedProducts
        .where((item) => item.slug != product.slug)
        .toList();

    if (related.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 25, 12, 0),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          Text('You May Also Like', style: ProductDetailsStyle.sectionTitle),

          SizedBox(height: 15),

          GridView.builder(
            shrinkWrap: true,
            physics: NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            itemCount: related.length > 4 ? 4 : related.length,

            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 11,
              mainAxisSpacing: 12,
              childAspectRatio: 0.72,
            ),

            itemBuilder: (context, index) {
              final item = related[index];

              return GestureDetector(
                onTap: () => _openRelatedProduct(item),

                child: Container(
                  clipBehavior: Clip.antiAlias,
                  decoration: ProductDetailsStyle.cardDecoration,

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      AspectRatio(
                        aspectRatio: 1,

                        child: _productImage(item.image, fit: BoxFit.cover),
                      ),

                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.all(9),

                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,

                            children: [
                              Text(
                                item.name,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),

                              Spacer(),

                              Text(
                                '₱${item.price}',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w900,
                                  color: ProductDetailsStyle.primary,
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
            },
          ),

          SizedBox(height: 12),

          SizedBox(
            width: double.infinity,

            child: OutlinedButton(
              style: ProductDetailsStyle.outlinedButton,
              onPressed: _openShop,
              child: Text('Back to Shop'),
            ),
          ),
        ],
      ),
    );
  }
}
