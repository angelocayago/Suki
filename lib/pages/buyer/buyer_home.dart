import 'dart:async';

import 'package:flutter/material.dart';

import 'buyer_home_style.dart';
import 'buyer_home_api.dart';
import 'buyer_home_repository.dart';
import 'buyer_profile.dart';
import 'product_details.dart';
import 'product_list.dart';
import '../../widgets/theme_toggle_button.dart';

class BuyerHomePage extends StatefulWidget {
  const BuyerHomePage({super.key});

  @override
  State<BuyerHomePage> createState() => _BuyerHomePageState();
}

class _BuyerHomePageState extends State<BuyerHomePage> {
  BuyerHomeData? homeData;

  bool isLoading = true;
  String? errorMessage;

  final PageController _heroController = PageController();
  Timer? _carouselTimer;

  int currentSlide = 0;
  int _loadRequestId = 0;

  // =========================================================
  // INITIALIZATION
  // =========================================================

  @override
  void initState() {
    super.initState();
    loadHomeData();
  }

  // =========================================================
  // LOAD HOMEPAGE DATA
  // =========================================================

  Future<void> loadHomeData() async {
    if (!mounted) return;

    final requestId = ++_loadRequestId;

    _carouselTimer?.cancel();

    setState(() {
      isLoading = true;
      errorMessage = null;
    });

    try {
      final result = await BuyerHomeRepository.getHomeData().timeout(
        const Duration(seconds: 25),
      );

      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      setState(() {
        homeData = result;
        isLoading = false;
        errorMessage = null;
        currentSlide = 0;
      });

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || requestId != _loadRequestId) {
          return;
        }

        if (_heroController.hasClients) {
          _heroController.jumpToPage(0);
        }

        _startCarousel();
      });
    } on TimeoutException {
      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      setState(() {
        isLoading = false;
        errorMessage =
            'The server took too long to respond. '
            'Please try again.';
      });
    } catch (error) {
      if (!mounted || requestId != _loadRequestId) {
        return;
      }

      final message = error.toString().replaceFirst('Exception: ', '').trim();

      setState(() {
        isLoading = false;
        errorMessage = message.isEmpty ? 'Unable to load homepage.' : message;
      });
    }
  }

  Future<void> _refresh() async {
    await loadHomeData();
  }

  // =========================================================
  // HERO CAROUSEL
  // =========================================================

  void _startCarousel() {
    _carouselTimer?.cancel();

    final slides = homeData?.heroSlides ?? [];

    if (!mounted || slides.length <= 1) {
      return;
    }

    _carouselTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      final currentSlides = homeData?.heroSlides ?? [];

      if (currentSlides.length <= 1) {
        timer.cancel();
        return;
      }

      if (!_heroController.hasClients) {
        return;
      }

      final nextIndex = (currentSlide + 1) % currentSlides.length;

      _heroController.animateToPage(
        nextIndex,
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeInOut,
      );
    });
  }

  void _changeSlide(int index) {
    final slides = homeData?.heroSlides ?? [];

    if (index < 0 || index >= slides.length || !_heroController.hasClients) {
      return;
    }

    _heroController.animateToPage(
      index,
      duration: const Duration(milliseconds: 350),
      curve: Curves.easeInOut,
    );

    _startCarousel();
  }

  @override
  void dispose() {
    _loadRequestId++;

    _carouselTimer?.cancel();
    _heroController.dispose();

    super.dispose();
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
  // NAVIGATION
  // =========================================================

  void _openProfile() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const BuyerProfilePage()),
    );
  }

  void _openCart() {
    Navigator.pushNamed(context, '/buyer-cart');
  }

  void _openNotifications() {
    _showMessage('Notifications will be connected later.');
  }

  void _openProduct(BuyerHomeItem product) {
    if (product.slug.trim().isEmpty) {
      _showMessage('Product information is unavailable.');
      return;
    }

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailsPage(
          product: ProductDetailsData.fromCatalogItem(product),
        ),
      ),
    );
  }

  void _openShop({String? category, String? categoryName}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerProductListPage(
          categorySlug: category,
          categoryName: categoryName,
        ),
      ),
    );
  }

  // =========================================================
  // MAIN BUILD
  // =========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerHomeStyle.background,
      body: SafeArea(
        child: RefreshIndicator(
          color: BuyerHomeStyle.primary,
          onRefresh: _refresh,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            slivers: [
              SliverToBoxAdapter(child: _buildHeader()),

              if (isLoading)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(
                    child: CircularProgressIndicator(
                      color: BuyerHomeStyle.primary,
                    ),
                  ),
                )
              else if (errorMessage != null)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _buildErrorState(),
                )
              else if (homeData != null) ...[
                if (homeData!.heroSlides.isNotEmpty)
                  SliverToBoxAdapter(child: _buildHeroSection()),

                if (homeData!.promotions.isNotEmpty)
                  SliverToBoxAdapter(child: _buildPromotions()),

                if (homeData!.benefits.isNotEmpty)
                  SliverToBoxAdapter(child: _buildBenefits()),

                if (homeData!.categories.isNotEmpty)
                  SliverToBoxAdapter(child: _buildCategories()),

                if (homeData!.flashDeals.isNotEmpty)
                  SliverToBoxAdapter(child: _buildFlashDeals()),

                if (homeData!.bestSellers.isNotEmpty)
                  SliverToBoxAdapter(child: _buildBestSellers()),

                if (homeData!.newArrivals.isNotEmpty)
                  SliverToBoxAdapter(child: _buildNewArrivals()),

                const SliverToBoxAdapter(child: SizedBox(height: 30)),
              ] else
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _buildEmptySection('No homepage data available.'),
                ),
            ],
          ),
        ),
      ),
    );
  }

  // =========================================================
  // ERROR STATE
  // =========================================================

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(25),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.error_outline,
              size: 55,
              color: BuyerHomeStyle.textSecondary,
            ),

            const SizedBox(height: 15),

            const Text(
              'Unable to load homepage',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
            ),

            const SizedBox(height: 8),

            Text(
              errorMessage ?? 'Please try again.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 11,
                color: BuyerHomeStyle.textSecondary,
              ),
            ),

            const SizedBox(height: 17),

            ElevatedButton.icon(
              style: BuyerHomeStyle.primaryButton,
              onPressed: loadHomeData,
              icon: const Icon(Icons.refresh, size: 17),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }

  // =========================================================
  // HEADER
  // =========================================================

  Widget _buildHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 13),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: BuyerHomeStyle.darkGreen,
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(
              Icons.shopping_bag_rounded,
              color: Colors.white,
              size: 24,
            ),
          ),

          const SizedBox(width: 9),

          Expanded(
            child: Text(
              'MOBILE SUKI',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: BuyerHomeStyle.brand,
            ),
          ),

          const ThemeToggleButton(),

          _headerIcon(
            Icons.notifications_none_rounded,
            'Notifications',
            _openNotifications,
          ),

          _headerIcon(Icons.shopping_cart_outlined, 'Shopping Cart', _openCart),

          _headerIcon(Icons.person_outline_rounded, 'Profile', _openProfile),
        ],
      ),
    );
  }

  Widget _headerIcon(IconData icon, String label, VoidCallback onTap) {
    return IconButton(
      tooltip: label,
      visualDensity: VisualDensity.compact,
      constraints: const BoxConstraints(minWidth: 34, minHeight: 40),
      padding: const EdgeInsets.symmetric(horizontal: 4),
      onPressed: onTap,
      icon: Icon(icon, color: BuyerHomeStyle.textPrimary, size: 21),
    );
  }

  // =========================================================
  // NETWORK IMAGE
  // =========================================================

  Widget _networkImage(String url, {BoxFit fit = BoxFit.cover}) {
    if (url.trim().isEmpty) {
      return BuyerHomeStyle.imageError();
    }

    final uri = Uri.tryParse(url);

    if (uri == null ||
        !uri.hasAuthority ||
        (uri.scheme != 'http' && uri.scheme != 'https')) {
      return BuyerHomeStyle.imageError();
    }

    return Image.network(
      url,
      fit: fit,
      errorBuilder: (context, error, stackTrace) {
        return BuyerHomeStyle.imageError();
      },
    );
  }

  // =========================================================
  // HERO SECTION
  // =========================================================

  Widget _buildHeroSection() {
    final slides = homeData!.heroSlides;

    return Padding(
      padding: const EdgeInsets.symmetric(
        horizontal: BuyerHomeStyle.horizontalPadding,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(17),
        child: SizedBox(
          height: 270,
          child: Stack(
            children: [
              PageView.builder(
                controller: _heroController,
                itemCount: slides.length,
                onPageChanged: (index) {
                  if (!mounted) return;

                  setState(() {
                    currentSlide = index;
                  });
                },
                itemBuilder: (context, index) {
                  return _buildHeroSlide(slides[index]);
                },
              ),

              if (slides.length > 1)
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 12,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(slides.length, (index) {
                      final active = currentSlide == index;

                      return GestureDetector(
                        onTap: () => _changeSlide(index),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 250),
                          margin: const EdgeInsets.symmetric(horizontal: 3),
                          width: active ? 23 : 7,
                          height: 7,
                          decoration: BoxDecoration(
                            color: active ? Colors.white : Colors.white54,
                            borderRadius: BorderRadius.circular(20),
                          ),
                        ),
                      );
                    }),
                  ),
                ),

              if (slides.length > 1)
                Positioned(
                  left: 5,
                  top: 0,
                  bottom: 0,
                  child: Center(
                    child: _carouselArrow(
                      Icons.chevron_left,
                      () => _changeSlide(
                        (currentSlide - 1 + slides.length) % slides.length,
                      ),
                    ),
                  ),
                ),

              if (slides.length > 1)
                Positioned(
                  right: 5,
                  top: 0,
                  bottom: 0,
                  child: Center(
                    child: _carouselArrow(
                      Icons.chevron_right,
                      () => _changeSlide((currentSlide + 1) % slides.length),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeroSlide(BuyerHomeItem slide) {
    return Stack(
      fit: StackFit.expand,
      children: [
        _networkImage(slide.image),

        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
              colors: [Color(0xE6000000), Color(0x99000000), Color(0x22000000)],
            ),
          ),
        ),

        Padding(
          padding: const EdgeInsets.fromLTRB(28, 22, 25, 35),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (slide.label.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Text(
                    slide.label,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 8,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),

              const SizedBox(height: 13),

              Text(
                slide.title,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  height: 1.15,
                ),
              ),

              const SizedBox(height: 9),

              Text(
                slide.description,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white70,
                  fontSize: 10,
                  height: 1.5,
                ),
              ),

              const SizedBox(height: 14),

              if (slide.button.isNotEmpty)
                ElevatedButton(
                  onPressed: () => _openShop(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: BuyerHomeStyle.primary,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 13,
                      vertical: 9,
                    ),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(7),
                    ),
                  ),
                  child: Text(
                    slide.button,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _carouselArrow(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.black38,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(3),
          child: Icon(icon, color: Colors.white, size: 22),
        ),
      ),
    );
  }

  // =========================================================
  // PROMOTIONS
  // =========================================================

  Widget _buildPromotions() {
    final promotions = homeData!.promotions;

    return Padding(
      padding: const EdgeInsets.fromLTRB(13, 12, 13, 0),
      child: Row(
        children: List.generate(promotions.length, (index) {
          final promo = promotions[index];

          return Expanded(
            child: Padding(
              padding: EdgeInsets.only(
                right: index < promotions.length - 1 ? 5 : 0,
                left: index > 0 ? 5 : 0,
              ),
              child: GestureDetector(
                onTap: () => _openShop(),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(11),
                  child: SizedBox(
                    height: 95,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        _networkImage(promo.image),

                        Container(color: Colors.black45),

                        Padding(
                          padding: const EdgeInsets.all(10),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.end,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                promo.subtitle,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 8,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white70,
                                ),
                              ),

                              const SizedBox(height: 3),

                              Text(
                                promo.title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.white,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  // =========================================================
  // BENEFITS
  // =========================================================

  IconData _benefitIcon(String name) {
    switch (name.toLowerCase()) {
      case 'shield-check':
        return Icons.verified_user_outlined;
      case 'truck':
        return Icons.local_shipping_outlined;
      case 'badge-percent':
        return Icons.local_offer_outlined;
      case 'headphones':
        return Icons.headset_mic_outlined;
      default:
        return Icons.check_circle_outline;
    }
  }

  Widget _buildBenefits() {
    final benefits = homeData!.benefits;

    return Container(
      margin: const EdgeInsets.fromLTRB(13, 17, 13, 0),
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 12),
      decoration: BuyerHomeStyle.sectionDecoration,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final columns = constraints.maxWidth >= 600 ? 3 : 2;

          return GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            padding: EdgeInsets.zero,
            itemCount: benefits.length,
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: columns,
              mainAxisExtent: 65,
              crossAxisSpacing: 5,
              mainAxisSpacing: 8,
            ),
            itemBuilder: (context, index) {
              final benefit = benefits[index];

              return Row(
                children: [
                  Container(
                    width: 33,
                    height: 33,
                    decoration: BuyerHomeStyle.iconDecoration,
                    child: Icon(
                      _benefitIcon(benefit.icon),
                      color: BuyerHomeStyle.primary,
                      size: 17,
                    ),
                  ),

                  const SizedBox(width: 7),

                  Expanded(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          benefit.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.bold,
                          ),
                        ),

                        const SizedBox(height: 3),

                        Text(
                          benefit.subtitle,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: BuyerHomeStyle.sectionSubtitle,
                        ),
                      ],
                    ),
                  ),
                ],
              );
            },
          );
        },
      ),
    );
  }

  // =========================================================
  // SECTION HEADER
  // =========================================================

  Widget _sectionHeader({
    required String eyebrow,
    required String title,
    required String subtitle,
    String action = 'See All',
    VoidCallback? onAction,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(eyebrow.toUpperCase(), style: BuyerHomeStyle.eyebrow),

                const SizedBox(height: 3),

                Text(title, style: BuyerHomeStyle.sectionTitle),

                const SizedBox(height: 3),

                Text(subtitle, style: BuyerHomeStyle.sectionSubtitle),
              ],
            ),
          ),

          const SizedBox(width: 8),

          InkWell(
            onTap: onAction ?? () => _openShop(),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                children: [
                  Text(
                    action,
                    style: TextStyle(
                      color: BuyerHomeStyle.primary,
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                    ),
                  ),

                  const SizedBox(width: 3),

                  Icon(
                    Icons.arrow_forward_rounded,
                    size: 13,
                    color: BuyerHomeStyle.primary,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // CATEGORIES
  // =========================================================

  Widget _buildCategories() {
    final categories = homeData!.categories;

    return Padding(
      padding: const EdgeInsets.fromLTRB(13, 26, 13, 0),
      child: Column(
        children: [
          _sectionHeader(
            eyebrow: 'Explore',
            title: 'Shop by Category',
            subtitle: 'Find what you need faster.',
            action: 'View All',
          ),

          SizedBox(
            height: 104,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: categories.length,
              separatorBuilder: (_, __) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final category = categories[index];

                return InkWell(
                  onTap: () {
                    _openShop(
                      category: category.slug,
                      categoryName: category.name,
                    );
                  },
                  child: SizedBox(
                    width: 67,
                    child: Column(
                      children: [
                        Container(
                          width: 57,
                          height: 57,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: BuyerHomeStyle.border),
                          ),
                          child: ClipOval(child: _networkImage(category.image)),
                        ),

                        const SizedBox(height: 6),

                        Text(
                          category.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w600,
                            color: BuyerHomeStyle.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // PRODUCT CARD
  // =========================================================

  Widget _buildProductCard(
    BuyerHomeItem product, {
    bool showDiscount = true,
    bool bestSeller = false,
  }) {
    return InkWell(
      onTap: () => _openProduct(product),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        decoration: BuyerHomeStyle.cardDecoration,
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AspectRatio(
              aspectRatio: 1,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  _networkImage(product.image),

                  if (showDiscount && product.discount.isNotEmpty)
                    Positioned(
                      top: 7,
                      left: 7,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: BuyerHomeStyle.accent,
                          borderRadius: BorderRadius.circular(5),
                        ),
                        child: Text(
                          '-${product.discount}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 8,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),

                  if (bestSeller)
                    Positioned(
                      top: 7,
                      left: 7,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: BuyerHomeStyle.primary,
                          borderRadius: BorderRadius.circular(5),
                        ),
                        child: const Text(
                          'BEST SELLER',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 7,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),

            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(9),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: BuyerHomeStyle.productName,
                    ),

                    const SizedBox(height: 4),

                    Text(
                      product.price.isEmpty
                          ? 'Price unavailable'
                          : '₱${product.price}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: BuyerHomeStyle.productPrice,
                    ),

                    if (showDiscount)
                      Padding(
                        padding: const EdgeInsets.only(top: 3),
                        child: Row(
                          children: [
                            if (product.oldPrice.isNotEmpty)
                              Flexible(
                                child: Text(
                                  '₱${product.oldPrice}',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 8,
                                    color: BuyerHomeStyle.textSecondary,
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                ),
                              ),

                            if (product.oldPrice.isNotEmpty)
                              const SizedBox(width: 5),

                            Expanded(
                              child: Text(
                                product.sold.isEmpty
                                    ? ''
                                    : '${product.sold} sold',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 8,
                                  color: BuyerHomeStyle.textSecondary,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                    const Spacer(),

                    Row(
                      children: [
                        Icon(
                          Icons.star_rounded,
                          color: BuyerHomeStyle.accent,
                          size: 13,
                        ),

                        const SizedBox(width: 3),

                        Flexible(
                          child: Text(
                            product.rating.isEmpty
                                ? 'No rating'
                                : product.rating,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 9,
                              color: BuyerHomeStyle.textSecondary,
                            ),
                          ),
                        ),
                      ],
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

  // =========================================================
  // RESPONSIVE PRODUCT GRID
  // =========================================================

  Widget _buildProductGrid(
    List<BuyerHomeItem> items, {
    bool showDiscount = true,
    bool bestSeller = false,
  }) {
    if (items.isEmpty) {
      return _buildEmptySection('No products available.');
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;

        final columns = width >= 900
            ? 5
            : width >= 600
            ? 3
            : 2;

        final cardWidth = (width - (columns - 1) * 9) / columns;

        // Images are square; reserve adequate room
        // for the product title, price and rating.
        final cardHeight = cardWidth + 115;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          itemCount: items.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 9,
            mainAxisSpacing: 11,
            mainAxisExtent: cardHeight,
          ),
          itemBuilder: (context, index) {
            return _buildProductCard(
              items[index],
              showDiscount: showDiscount,
              bestSeller: bestSeller,
            );
          },
        );
      },
    );
  }

  // =========================================================
  // EMPTY SECTION
  // =========================================================

  Widget _buildEmptySection(String message) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 25),
      child: Center(
        child: Text(message, style: BuyerHomeStyle.sectionSubtitle),
      ),
    );
  }

  // =========================================================
  // FLASH DEALS
  // =========================================================

  Widget _buildFlashDeals() {
    final products = homeData!.flashDeals;

    return Container(
      margin: const EdgeInsets.fromLTRB(13, 25, 13, 0),
      decoration: BuyerHomeStyle.sectionDecoration,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 8, 12),
            child: Row(
              children: [
                Icon(
                  Icons.bolt_rounded,
                  color: BuyerHomeStyle.accent,
                  size: 25,
                ),

                const SizedBox(width: 8),

                Expanded(
                  child: Text(
                    'Flash Deals',
                    style: BuyerHomeStyle.sectionTitle,
                  ),
                ),

                IconButton(
                  tooltip: 'View All Flash Deals',
                  visualDensity: VisualDensity.compact,
                  onPressed: () => _openShop(),
                  icon: Icon(
                    Icons.arrow_forward_rounded,
                    size: 17,
                    color: BuyerHomeStyle.primary,
                  ),
                ),
              ],
            ),
          ),

          Divider(height: 1, color: BuyerHomeStyle.border),

          Padding(
            padding: const EdgeInsets.all(9),
            child: _buildProductGrid(products, showDiscount: true),
          ),
        ],
      ),
    );
  }

  // =========================================================
  // BEST SELLERS
  // =========================================================

  Widget _buildBestSellers() {
    final products = homeData!.bestSellers;

    return Padding(
      padding: const EdgeInsets.fromLTRB(13, 27, 13, 0),
      child: Column(
        children: [
          _sectionHeader(
            eyebrow: 'Popular Now',
            title: 'Best Sellers',
            subtitle: 'Popular products shoppers love.',
          ),

          _buildProductGrid(products, showDiscount: false, bestSeller: true),
        ],
      ),
    );
  }

  // =========================================================
  // NEW ARRIVALS
  // =========================================================

  Widget _buildNewArrivals() {
    final products = homeData!.newArrivals;

    return Container(
      margin: const EdgeInsets.fromLTRB(13, 27, 13, 0),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: BuyerHomeStyle.lightGreen,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          _sectionHeader(
            eyebrow: 'Fresh Finds',
            title: 'New Arrivals',
            subtitle: 'Fresh products recently added to SUKI SHOP.',
            action: 'View All',
          ),

          _buildProductGrid(products, showDiscount: false),
        ],
      ),
    );
  }
}
