
import 'auth_service.dart';
import 'api_services.dart';

class BuyerCartLine {
  final String id;
  final String name;
  final String slug;
  final String image;
  final double price;
  final int quantity;
  final int stock;
  final bool available;

  const BuyerCartLine({
    required this.id,
    required this.name,
    required this.slug,
    required this.image,
    required this.price,
    required this.quantity,
    required this.stock,
    required this.available,
  });

  factory BuyerCartLine.fromJson(Map<String, dynamic> json) {
    return BuyerCartLine(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Product',
      slug: json['slug']?.toString() ?? '',
      image: ApiServices.resolveMediaUrl(json['image']?.toString()),
      price: double.tryParse(json['price']?.toString() ?? '') ?? 0,
      quantity: int.tryParse(json['quantity']?.toString() ?? '') ?? 0,
      stock: int.tryParse(json['stock']?.toString() ?? '') ?? 0,
      available: json['available'] == true,
    );
  }

  double get lineTotal => price * quantity;
}

class BuyerCartSummary {
  final List<BuyerCartLine> items;
  final double subtotal;
  final double shipping;
  final double discount;
  final double total;

  const BuyerCartSummary({
    required this.items,
    required this.subtotal,
    required this.shipping,
    required this.discount,
    required this.total,
  });

  factory BuyerCartSummary.fromJson(Map<String, dynamic> json) {
    final rawItems = json['cart'] ?? json['items'];
    final items = rawItems is List
        ? rawItems
            .whereType<Map>()
            .map(
              (item) => BuyerCartLine.fromJson(
                Map<String, dynamic>.from(item),
              ),
            )
            .toList()
        : <BuyerCartLine>[];

    double number(String key) =>
        double.tryParse(json[key]?.toString() ?? '') ?? 0;

    return BuyerCartSummary(
      items: items,
      subtotal: number('subtotal'),
      shipping: number('shipping'),
      discount: number('discount'),
      total: number('total'),
    );
  }
}

class CartService {
  CartService._();

  static Future<BuyerCartSummary> getCart() async {
    final response = await ApiServices.get(
      '/buyer/cart',
      token: await _requiredToken(),
    );
    return BuyerCartSummary.fromJson(_dataMap(response));
  }

  static Future<BuyerCartSummary> addItem({
    required String variantId,
    required int quantity,
  }) async {
    final response = await ApiServices.post(
      '/buyer/cart/items',
      {
        'product_variant_id': int.tryParse(variantId) ?? variantId,
        'quantity': quantity,
      },
      token: await _requiredToken(),
    );
    return BuyerCartSummary.fromJson(_dataMap(response));
  }

  static Future<BuyerCartSummary> updateQuantity({
    required String itemId,
    required int quantity,
  }) async {
    final response = await ApiServices.patch(
      '/buyer/cart/items/$itemId',
      {'quantity': quantity},
      token: await _requiredToken(),
    );
    return BuyerCartSummary.fromJson(_dataMap(response));
  }

  static Future<BuyerCartSummary> removeItem(String itemId) async {
    final response = await ApiServices.delete(
      '/buyer/cart/items/$itemId',
      token: await _requiredToken(),
    );
    return BuyerCartSummary.fromJson(_dataMap(response));
  }

  static Future<String> _requiredToken() async {
    final token = await AuthService.getToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please log in to use your cart.');
    }
    return token;
  }

  static Map<String, dynamic> _dataMap(dynamic response) {
    if (response is! Map) {
      throw const FormatException('Invalid cart API response.');
    }

    final rawData = response['data'];
    final data = rawData is Map ? rawData : response;
    return Map<String, dynamic>.from(data);
  }
}
