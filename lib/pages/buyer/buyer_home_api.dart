import '../../services/api_services.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER API AND DATA MODELS
// =========================================================
//
// Architecture:
//
// buyer_home.dart
//       |
// buyer_home_repository.dart
//       |
// buyer_home_api.dart
//       |
// api_services.dart
//       |
// Laravel REST API
//
// Existing models and public methods are preserved.
// =========================================================

// =========================================================
// RESPONSE PARSING HELPERS
// =========================================================

class _BuyerParser {
  _BuyerParser._();

  static String text(dynamic value) {
    if (value == null) return '';
    return value.toString().trim();
  }

  static String money(dynamic value) {
    if (value == null) return '';

    if (value is num) {
      return value.toStringAsFixed(2);
    }

    return value.toString().trim();
  }

  static int integer(dynamic value) {
    if (value is int) return value;

    if (value is num) {
      return value.toInt();
    }

    final raw = text(value).replaceAll(',', '');

    return int.tryParse(raw) ?? double.tryParse(raw)?.toInt() ?? 0;
  }

  static Map<String, dynamic> object(dynamic value) {
    if (value is! Map) {
      return <String, dynamic>{};
    }

    final result = <String, dynamic>{};

    value.forEach((key, item) {
      result[key.toString()] = item;
    });

    return result;
  }

  static String image(dynamic value) {
    if (value == null) return '';

    if (value is Map) {
      for (final key in [
        'url',
        'image_url',
        'path',
        'src',
        'image',
        'thumbnail',
      ]) {
        final result = image(value[key]);

        if (result.isNotEmpty) {
          return result;
        }
      }

      return '';
    }

    final raw = text(value);

    if (raw.isEmpty) return '';

    return ApiServices.resolveMediaUrl(raw);
  }

  static List<String> images(dynamic value) {
    if (value is! List) {
      return const <String>[];
    }

    final result = <String>[];
    final seen = <String>{};

    for (final item in value) {
      final url = image(item);

      if (url.isNotEmpty && seen.add(url)) {
        result.add(url);
      }
    }

    return result;
  }

  static List<T> modelList<T>(
    dynamic value,
    T Function(Map<String, dynamic>) parse,
  ) {
    if (value is! List) {
      return <T>[];
    }

    final result = <T>[];

    for (final item in value) {
      if (item is Map) {
        result.add(parse(object(item)));
      }
    }

    return result;
  }

  static dynamic unwrapData(dynamic value) {
    if (value is! Map) return value;

    final map = object(value);

    if (map['success'] == false) {
      throw Exception(
        text(map['message']).isNotEmpty
            ? text(map['message'])
            : 'The SUKI API request failed.',
      );
    }

    return map['data'] ?? map;
  }
}

// =========================================================
// PRODUCT / HOMEPAGE ITEM MODEL
// =========================================================

class BuyerHomeItem {
  final String id;
  final String name;
  final String image;
  final List<String> images;
  final String slug;

  final String price;
  final String oldPrice;
  final String discount;
  final String sold;
  final String rating;

  final String title;
  final String subtitle;
  final String description;
  final String label;
  final String button;

  final String category;

  final String seller;
  final String sellerSlug;
  final String sellerLogo;
  final String sellerRating;
  final String sellerProducts;

  final int stock;

  final List<BuyerHomeVariant> variants;

  final Map<String, List<String>> mediaByColor;

  // Preserve the existing constructor for all
  // buyer pages using BuyerHomeItem.
  const BuyerHomeItem({
    this.id = '',
    this.name = '',
    this.image = '',
    this.images = const [],
    this.slug = '',
    this.price = '',
    this.oldPrice = '',
    this.discount = '',
    this.sold = '',
    this.rating = '',
    this.title = '',
    this.subtitle = '',
    this.description = '',
    this.label = '',
    this.button = '',
    this.category = '',
    this.seller = '',
    this.sellerSlug = '',
    this.sellerLogo = '',
    this.sellerRating = '',
    this.sellerProducts = '',
    this.stock = 0,
    this.variants = const [],
    this.mediaByColor = const {},
  });

  // =======================================================
  // PRODUCT JSON PARSER
  // =======================================================

  factory BuyerHomeItem.fromJson(Map<String, dynamic> json) {
    // Product gallery.
    final List<String> galleryImages = _BuyerParser.images(json['images']);

    // Primary product image candidates.
    final imageCandidates = [
      json['image'],
      json['image_url'],
      json['image_path'],
      json['photo'],
      json['thumbnail'],
    ];

    String primaryImage = '';

    for (final candidate in imageCandidates) {
      final imageUrl = _BuyerParser.image(candidate);

      if (imageUrl.isNotEmpty) {
        primaryImage = imageUrl;
        break;
      }
    }

    if (primaryImage.isEmpty && galleryImages.isNotEmpty) {
      primaryImage = galleryImages.first;
    }

    // Keep gallery order from Laravel.
    final allImages = <String>[...galleryImages];

    // Ensure the primary image is available in
    // the image gallery without duplicating it.
    if (primaryImage.isNotEmpty && !allImages.contains(primaryImage)) {
      allImages.insert(0, primaryImage);
    }

    // =====================================================
    // COLOR-BASED MEDIA
    // =====================================================

    final mediaByColor = <String, List<String>>{};

    final rawMediaByColor = json['media_by_color'];

    if (rawMediaByColor is Map) {
      rawMediaByColor.forEach((key, value) {
        final color = _BuyerParser.text(key);

        if (color.isEmpty) return;

        mediaByColor[color] = _BuyerParser.images(value);
      });
    }

    // =====================================================
    // PRODUCT VARIANTS
    // =====================================================

    final variants = _BuyerParser.modelList<BuyerHomeVariant>(
      json['variants'],
      BuyerHomeVariant.fromJson,
    );

    // =====================================================
    // PRICES
    // =====================================================

    final price = _BuyerParser.money(json['price']);

    final originalPrice = _BuyerParser.money(json['old_price']);

    // Avoid displaying a crossed-out price
    // equal to the current price.
    final oldPrice = originalPrice == price ? '' : originalPrice;

    // =====================================================
    // DISCOUNT
    // =====================================================

    final rawDiscount = _BuyerParser.text(json['discount']);

    final normalizedDiscount = rawDiscount.replaceAll('%', '').trim();

    final discountValue = double.tryParse(normalizedDiscount);

    final discount =
        rawDiscount.isEmpty || (discountValue != null && discountValue <= 0)
        ? ''
        : rawDiscount;

    // =====================================================
    // PRODUCT STOCK
    // =====================================================

    final stock = _BuyerParser.integer(json['stock']);

    // =====================================================
    // BUILD PRODUCT ITEM
    // =====================================================

    return BuyerHomeItem(
      id: _BuyerParser.text(json['id']),
      name: _BuyerParser.text(json['name']),
      image: primaryImage,
      images: allImages,
      slug: _BuyerParser.text(json['slug']),

      price: price,
      oldPrice: oldPrice,
      discount: discount,

      sold: _BuyerParser.text(json['sold']),
      rating: _BuyerParser.text(json['rating']),

      title: _BuyerParser.text(json['title']),
      subtitle: _BuyerParser.text(json['subtitle']),
      description: _BuyerParser.text(json['description']),
      label: _BuyerParser.text(json['label']),
      button: _BuyerParser.text(json['button']),

      category: _BuyerParser.text(json['category']),

      seller: _BuyerParser.text(json['seller']),
      sellerSlug: _BuyerParser.text(json['seller_slug']),
      sellerLogo: _BuyerParser.image(json['seller_logo']),
      sellerRating: _BuyerParser.text(json['seller_rating']),
      sellerProducts: _BuyerParser.text(json['seller_products']),

      stock: stock,
      variants: variants,
      mediaByColor: mediaByColor,
    );
  }
}

// =========================================================
// PRODUCT VARIANT MODEL
// =========================================================

class BuyerHomeVariant {
  final String id;
  final String name;
  final int stock;
  final Map<String, dynamic> options;

  const BuyerHomeVariant({
    required this.id,
    required this.name,
    required this.stock,
    required this.options,
  });

  factory BuyerHomeVariant.fromJson(Map<String, dynamic> json) {
    return BuyerHomeVariant(
      id: _BuyerParser.text(json['id']),
      name: _BuyerParser.text(json['name']),
      stock: _BuyerParser.integer(json['stock']),
      options: _BuyerParser.object(json['options']),
    );
  }
}

// =========================================================
// BUYER HOMEPAGE BENEFIT MODEL
// =========================================================

class BuyerHomeBenefit {
  final String title;
  final String subtitle;
  final String icon;

  const BuyerHomeBenefit({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  factory BuyerHomeBenefit.fromJson(Map<String, dynamic> json) {
    return BuyerHomeBenefit(
      title: _BuyerParser.text(json['title']),
      subtitle: _BuyerParser.text(json['subtitle']),
      icon: _BuyerParser.text(json['icon']),
    );
  }
}

// =========================================================
// BUYER HOMEPAGE RESPONSE MODEL
// =========================================================

class BuyerHomeData {
  final List<BuyerHomeItem> heroSlides;
  final List<BuyerHomeItem> promotions;
  final List<BuyerHomeBenefit> benefits;
  final List<BuyerHomeItem> categories;
  final List<BuyerHomeItem> flashDeals;
  final List<BuyerHomeItem> bestSellers;
  final List<BuyerHomeItem> newArrivals;

  const BuyerHomeData({
    required this.heroSlides,
    required this.promotions,
    required this.benefits,
    required this.categories,
    required this.flashDeals,
    required this.bestSellers,
    required this.newArrivals,
  });

  // =======================================================
  // PARSE HOMEPAGE DATA
  // =======================================================

  factory BuyerHomeData.fromJson(Map<String, dynamic> json) {
    return BuyerHomeData(
      heroSlides: _BuyerParser.modelList<BuyerHomeItem>(
        json['hero_slides'],
        BuyerHomeItem.fromJson,
      ),

      promotions: _BuyerParser.modelList<BuyerHomeItem>(
        json['promotions'],
        BuyerHomeItem.fromJson,
      ),

      benefits: _BuyerParser.modelList<BuyerHomeBenefit>(
        json['benefits'],
        BuyerHomeBenefit.fromJson,
      ),

      categories: _BuyerParser.modelList<BuyerHomeItem>(
        json['categories'],
        BuyerHomeItem.fromJson,
      ),

      flashDeals: _BuyerParser.modelList<BuyerHomeItem>(
        json['flash_deals'],
        BuyerHomeItem.fromJson,
      ),

      bestSellers: _BuyerParser.modelList<BuyerHomeItem>(
        json['best_sellers'],
        BuyerHomeItem.fromJson,
      ),

      newArrivals: _BuyerParser.modelList<BuyerHomeItem>(
        json['new_arrivals'],
        BuyerHomeItem.fromJson,
      ),
    );
  }

  // =======================================================
  // CHECK IF HOMEPAGE HAS DISPLAY CONTENT
  // =======================================================

  bool get hasContent {
    return heroSlides.isNotEmpty ||
        promotions.isNotEmpty ||
        benefits.isNotEmpty ||
        categories.isNotEmpty ||
        flashDeals.isNotEmpty ||
        bestSellers.isNotEmpty ||
        newArrivals.isNotEmpty;
  }
}

// =========================================================
// BUYER HOMEPAGE API SERVICE
// =========================================================

class BuyerHomeApi {
  BuyerHomeApi._();

  // Preserve the current endpoint.
  //
  // Laravel:
  // GET /api/buyer/home
  //
  // Actual route availability must be
  // confirmed during backend testing.

  static const String endpoint = '/buyer/home';

  // =======================================================
  // FETCH HOMEPAGE DATA
  // =======================================================

  static Future<BuyerHomeData> getHomeData() async {
    final dynamic response = await ApiServices.get(endpoint);

    if (response is! Map) {
      throw const FormatException('Invalid homepage API response.');
    }

    final Map<String, dynamic> result = _BuyerParser.object(response);

    // Check explicit API failure.
    if (result['success'] == false) {
      throw Exception(
        _BuyerParser.text(result['message']).isEmpty
            ? 'Unable to load buyer homepage.'
            : _BuyerParser.text(result['message']),
      );
    }

    // Laravel supports:
    //
    // {
    //   "hero_slides": [...]
    // }
    //
    // or:
    //
    // {
    //   "success": true,
    //   "data": {
    //      "hero_slides": [...]
    //   }
    // }

    final dynamic payload = _BuyerParser.unwrapData(result);

    if (payload is! Map) {
      throw const FormatException('Homepage data is missing or invalid.');
    }

    final homeJson = _BuyerParser.object(payload);

    return BuyerHomeData.fromJson(homeJson);
  }
}

// =========================================================
// BUYER PRODUCT CATALOG API
// =========================================================

class BuyerCatalogApi {
  BuyerCatalogApi._();

  // =======================================================
  // GET SINGLE PRODUCT
  // =======================================================

  static Future<BuyerHomeItem> getProduct(String slug) async {
    final cleanSlug = slug.trim();

    if (cleanSlug.isEmpty) {
      throw ArgumentError('Product slug cannot be empty.');
    }

    // Keep the existing Laravel product endpoint.
    //
    // GET /api/products/{slug}

    final dynamic response = await ApiServices.get(
      '/products/${Uri.encodeComponent(cleanSlug)}',
    );

    if (response is! Map) {
      throw const FormatException('Invalid product API response.');
    }

    final result = _BuyerParser.object(response);

    // Check an explicit backend failure.
    if (result['success'] == false) {
      throw Exception(
        _BuyerParser.text(result['message']).isEmpty
            ? 'Unable to load product.'
            : _BuyerParser.text(result['message']),
      );
    }

    // Existing API expects a data wrapper.
    //
    // Support:
    // { "data": { product fields } }
    //
    // and:
    // { "data": { "product": { ... } } }
    //
    // and direct product objects.

    dynamic payload = result['data'] ?? result;

    if (payload is Map && payload['product'] is Map) {
      payload = payload['product'];
    }

    if (payload is! Map) {
      throw const FormatException('Product was not found in the API response.');
    }

    final productJson = _BuyerParser.object(payload);

    if (productJson.isEmpty) {
      throw const FormatException('Product data is empty.');
    }

    return BuyerHomeItem.fromJson(productJson);
  }

  // =======================================================
  // GET PRODUCT LIST
  // =======================================================

  static Future<List<BuyerHomeItem>> getProducts({
    String? category,
    String? seller,
    String? query,
    int page = 1,
  }) async {
    // Prevent invalid pagination.
    final int safePage = page < 1 ? 1 : page;

    final String categoryFilter = category?.trim() ?? '';

    final String sellerFilter = seller?.trim() ?? '';

    final String searchQuery = query?.trim() ?? '';

    // =====================================================
    // QUERY PARAMETERS
    // =====================================================

    final queryParameters = <String, String>{
      'page': safePage.toString(),

      if (categoryFilter.isNotEmpty) 'category': categoryFilter,

      if (sellerFilter.isNotEmpty) 'seller': sellerFilter,

      if (searchQuery.isNotEmpty) 'q': searchQuery,
    };

    final queryString = Uri(queryParameters: queryParameters).query;

    // =====================================================
    // REQUEST
    // =====================================================

    final dynamic response = await ApiServices.get('/products?$queryString');

    // Some APIs return a top-level list.
    if (response is List) {
      return _BuyerParser.modelList<BuyerHomeItem>(
        response,
        BuyerHomeItem.fromJson,
      );
    }

    if (response is! Map) {
      throw const FormatException('Invalid products API response.');
    }

    final result = _BuyerParser.object(response);

    if (result['success'] == false) {
      throw Exception(
        _BuyerParser.text(result['message']).isEmpty
            ? 'Unable to load products.'
            : _BuyerParser.text(result['message']),
      );
    }

    // =====================================================
    // EXTRACT PRODUCT COLLECTION
    // =====================================================

    // Supported Laravel patterns:
    //
    // { "data": [ ... ] }
    //
    // { "products": [ ... ] }
    //
    // {
    //   "data": {
    //     "data": [ ... ],
    //     "current_page": 1
    //   }
    // }
    //
    // {
    //   "data": {
    //     "products": [ ... ]
    //   }
    // }

    dynamic payload = result['data'] ?? result['products'];

    if (payload is Map) {
      final dataMap = _BuyerParser.object(payload);

      payload = dataMap['data'] ?? dataMap['products'];
    }

    if (payload is Map) {
      final nested = _BuyerParser.object(payload);

      payload = nested['data'] ?? nested['products'];
    }

    if (payload is! List) {
      throw const FormatException(
        'Product list was not found '
        'in the Laravel response.',
      );
    }

    // =====================================================
    // CONVERT JSON INTO PRODUCT MODELS
    // =====================================================

    return _BuyerParser.modelList<BuyerHomeItem>(
      payload,
      BuyerHomeItem.fromJson,
    );
  }
}
