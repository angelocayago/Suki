
import '../../services/api_services.dart';

// =========================================================
// HOMEPAGE DATA MODELS
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

  factory BuyerHomeItem.fromJson(
    Map<String, dynamic> json,
  ) {
    String parseImage(dynamic value) {
      if (value is Map) {
        for (final key in ['url', 'image_url', 'path', 'src']) {
          final nested = parseImage(value[key]);
          if (nested.isNotEmpty) return nested;
        }
        return '';
      }

      return ApiServices.resolveMediaUrl(value?.toString());
    }

    List<String> parseStrings(dynamic value) {
      if (value is! List) return const [];
      return value
          .map(parseImage)
          .where((url) => url.isNotEmpty)
          .toList();
    }

    final images = parseStrings(json['images']);
    final imageCandidates = [
      json['image'],
      json['image_url'],
      json['image_path'],
      json['photo'],
      json['thumbnail'],
    ].map(parseImage).where((url) => url.isNotEmpty).toList();
    final image = imageCandidates.isNotEmpty
        ? imageCandidates.first
        : images.isNotEmpty
            ? images.first
            : '';

    final rawMediaByColor = json['media_by_color'];
    final mediaByColor = <String, List<String>>{};
    if (rawMediaByColor is Map) {
      rawMediaByColor.forEach((key, value) {
        mediaByColor[key.toString()] = parseStrings(value);
      });
    }

    final rawVariants = json['variants'];
    final variants = rawVariants is List
        ? rawVariants
            .whereType<Map>()
            .map(
              (item) => BuyerHomeVariant.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList()
        : const <BuyerHomeVariant>[];
    final parsedPrice = _stringValue(json['price']);
    final parsedOldPrice = _stringValue(json['old_price']);

    return BuyerHomeItem(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      image: image,
      images: images,
      slug: json['slug']?.toString() ?? '',
      price: parsedPrice,
      oldPrice: parsedOldPrice == parsedPrice ? '' : parsedOldPrice,
      discount: json['discount']?.toString() == '0%'
          ? ''
          : json['discount']?.toString() ?? '',
      sold: json['sold']?.toString() ?? '',
      rating: json['rating']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      subtitle: json['subtitle']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      button: json['button']?.toString() ?? '',
      category: json['category']?.toString() ?? '',
      seller: json['seller']?.toString() ?? '',
      sellerSlug: json['seller_slug']?.toString() ?? '',
      sellerLogo: parseImage(json['seller_logo']),
      sellerRating: json['seller_rating']?.toString() ?? '',
      sellerProducts: json['seller_products']?.toString() ?? '',
      stock: int.tryParse(json['stock']?.toString() ?? '') ?? 0,
      variants: variants,
      mediaByColor: mediaByColor,
    );
  }

  static String _stringValue(dynamic value) {
    if (value == null) return '';
    if (value is num) return value.toStringAsFixed(2);
    return value.toString();
  }
}

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
    final rawOptions = json['options'];
    return BuyerHomeVariant(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      stock: int.tryParse(json['stock']?.toString() ?? '') ?? 0,
      options: rawOptions is Map
          ? Map<String, dynamic>.from(rawOptions)
          : const {},
    );
  }
}

// =========================================================
// BENEFIT MODEL
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

  factory BuyerHomeBenefit.fromJson(
    Map<String, dynamic> json,
  ) {
    return BuyerHomeBenefit(
      title: json['title']?.toString() ?? '',
      subtitle: json['subtitle']?.toString() ?? '',
      icon: json['icon']?.toString() ?? '',
    );
  }
}

// =========================================================
// HOMEPAGE RESPONSE MODEL
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

  factory BuyerHomeData.fromJson(
    Map<String, dynamic> json,
  ) {
    List<BuyerHomeItem> parseItems(dynamic value) {
      if (value is! List) return [];

      return value
          .whereType<Map>()
          .map(
            (item) => BuyerHomeItem.fromJson(
              Map<String, dynamic>.from(item),
            ),
          )
          .toList();
    }

    List<BuyerHomeBenefit> parseBenefits(dynamic value) {
      if (value is! List) return [];

      return value
          .whereType<Map>()
          .map(
            (item) => BuyerHomeBenefit.fromJson(
              Map<String, dynamic>.from(item),
            ),
          )
          .toList();
    }

    return BuyerHomeData(
      heroSlides: parseItems(json['hero_slides']),
      promotions: parseItems(json['promotions']),
      benefits: parseBenefits(json['benefits']),
      categories: parseItems(json['categories']),
      flashDeals: parseItems(json['flash_deals']),
      bestSellers: parseItems(json['best_sellers']),
      newArrivals: parseItems(json['new_arrivals']),
    );
  }
}

// =========================================================
// BUYER HOME API SERVICE
// =========================================================

class BuyerHomeApi {
  BuyerHomeApi._();

  // Change this path to match your Laravel API route.
  static const String endpoint = '/buyer/home';

  // =========================================================
  // GET HOMEPAGE DATA
  // =========================================================

  static Future<BuyerHomeData> getHomeData() async {
    final response = await ApiServices.get(endpoint);

    if (response is! Map) {
      throw Exception('Invalid homepage API response.');
    }

    final Map<String, dynamic> data =
        Map<String, dynamic>.from(response);

    // Supports both direct response and Laravel data wrapper.
    final dynamic payload = data['data'];

    final Map<String, dynamic> homeJson =
        payload is Map
            ? Map<String, dynamic>.from(payload)
            : data;

    return BuyerHomeData.fromJson(homeJson);
  }
}

class BuyerCatalogApi {
  BuyerCatalogApi._();

  static Future<BuyerHomeItem> getProduct(String slug) async {
    final response = await ApiServices.get(
      '/products/${Uri.encodeComponent(slug)}',
    );
    if (response is! Map) {
      throw const FormatException('Invalid product API response.');
    }

    final payload = response['data'];
    if (payload is! Map) {
      throw const FormatException('Product was not found in the API response.');
    }

    return BuyerHomeItem.fromJson(Map<String, dynamic>.from(payload));
  }

  static Future<List<BuyerHomeItem>> getProducts({
    String? category,
    String? seller,
    String? query,
    int page = 1,
  }) async {
    final queryParameters = <String, String>{
      'page': page.toString(),
      if (category != null && category.isNotEmpty) 'category': category,
      if (seller != null && seller.isNotEmpty) 'seller': seller,
      if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
    };
    final queryString = Uri(queryParameters: queryParameters).query;
    final response = await ApiServices.get('/products?$queryString');

    if (response is! Map) {
      throw const FormatException('Invalid products API response.');
    }

    dynamic payload = response['data'] ?? response['products'];
    if (payload is Map) {
      payload = payload['data'] ?? payload['products'];
    }
    if (payload is! List) {
      throw const FormatException('Product list was not found in the API response.');
    }

    return payload
        .whereType<Map>()
        .map(
          (item) => BuyerHomeItem.fromJson(
            Map<String, dynamic>.from(item),
          ),
        )
        .toList();
  }
}
