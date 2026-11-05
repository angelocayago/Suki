import 'auth_service.dart';
import 'api_services.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER CART SERVICE
// =========================================================
//
// Shared API architecture:
//
// Buyer Pages
//     ↓
// CartService
//     ↓
// ApiServices + AuthService
//     ↓
// Laravel Backend
//
// Existing public models and methods are preserved.
//
// =========================================================

// =========================================================
// CART PARSING HELPERS
// =========================================================

class _CartParser {
  _CartParser._();

  static String text(dynamic value) {
    if (value == null) return '';
    return value.toString().trim();
  }

  static double decimal(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }

    final raw = text(value)
        .replaceAll(',', '')
        .replaceAll('₱', '')
        .replaceAll(RegExp(r'^PHP\s*', caseSensitive: false), '');

    return double.tryParse(raw) ?? 0.0;
  }

  static int integer(dynamic value) {
    if (value is int) return value;

    if (value is num) {
      return value.toInt();
    }

    final raw = text(value).replaceAll(',', '');

    return int.tryParse(raw) ?? double.tryParse(raw)?.toInt() ?? 0;
  }

  static bool boolean(dynamic value) {
    if (value is bool) return value;

    if (value is num) {
      return value != 0;
    }

    final raw = text(value).toLowerCase();

    return raw == 'true' || raw == '1' || raw == 'yes';
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
    if (value is Map) {
      for (final key in ['url', 'image_url', 'path', 'src']) {
        final nested = image(value[key]);

        if (nested.isNotEmpty) {
          return nested;
        }
      }

      return '';
    }

    final raw = text(value);

    if (raw.isEmpty) return '';

    return ApiServices.resolveMediaUrl(raw);
  }

  static List<BuyerCartLine> lines(dynamic value) {
    if (value is! List) {
      return <BuyerCartLine>[];
    }

    final items = <BuyerCartLine>[];

    for (final entry in value) {
      if (entry is Map) {
        items.add(BuyerCartLine.fromJson(object(entry)));
      }
    }

    return items;
  }

  static void checkFailure(dynamic response) {
    if (response is! Map) return;

    final result = object(response);

    if (result['success'] == false) {
      final message = text(result['message']);

      throw Exception(message.isEmpty ? 'The cart request failed.' : message);
    }
  }
}

// =========================================================
// BUYER CART ITEM MODEL
// =========================================================

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
      id: _CartParser.text(json['id']),
      name: _CartParser.text(json['name']).isEmpty
          ? 'Product'
          : _CartParser.text(json['name']),
      slug: _CartParser.text(json['slug']),
      image: _CartParser.image(json['image']),
      price: _CartParser.decimal(json['price']),
      quantity: _CartParser.integer(json['quantity']),
      stock: _CartParser.integer(json['stock']),
      available: _CartParser.boolean(json['available']),
    );
  }

  double get lineTotal => price * quantity;

  bool get hasEnoughStock => available && stock >= quantity;

  bool get isOutOfStock => !available || stock <= 0;
}

// =========================================================
// BUYER CART SUMMARY MODEL
// =========================================================

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

    final items = _CartParser.lines(rawItems);

    return BuyerCartSummary(
      items: items,
      subtotal: _CartParser.decimal(json['subtotal']),
      shipping: _CartParser.decimal(json['shipping']),
      discount: _CartParser.decimal(json['discount']),
      total: _CartParser.decimal(json['total']),
    );
  }

  bool get isEmpty => items.isEmpty;

  int get itemCount {
    return items.fold<int>(0, (sum, item) => sum + item.quantity);
  }

  int get uniqueItemCount => items.length;

  bool get hasUnavailableItems {
    return items.any((item) => !item.hasEnoughStock);
  }
}

// =========================================================
// CART SERVICE
// =========================================================

class CartService {
  CartService._();

  // =======================================================
  // REQUIRED AUTHENTICATION TOKEN
  // =======================================================

  static Future<String> _requiredToken() async {
    final token = await AuthService.getToken();

    if (token == null || token.trim().isEmpty) {
      throw Exception('Please log in to use your cart.');
    }

    return token.trim();
  }

  // =======================================================
  // EXTRACT CART SUMMARY FROM LARAVEL RESPONSE
  // =======================================================

  static Map<String, dynamic>? _cartData(dynamic response) {
    _CartParser.checkFailure(response);

    if (response is! Map) {
      throw const FormatException('Invalid cart API response.');
    }

    final result = _CartParser.object(response);

    // Possible Laravel response:
    //
    // {
    //   "success": true,
    //   "data": {
    //     "cart": [...],
    //     "subtotal": 100,
    //     "total": 100
    //   }
    // }
    //
    // Or a direct cart summary:
    //
    // {
    //   "items": [...],
    //   "subtotal": 100,
    //   "total": 100
    // }

    Map<String, dynamic> data = result;

    final rawData = result['data'];

    if (rawData is Map) {
      data = _CartParser.object(rawData);
    }

    // Accept a collection even when empty.
    if (data['cart'] is List || data['items'] is List) {
      return data;
    }

    // Some backends wrap the summary once more.
    final nestedCart = data['cart'];

    if (nestedCart is Map) {
      final nested = _CartParser.object(nestedCart);

      if (nested['cart'] is List || nested['items'] is List) {
        return nested;
      }
    }

    // A successful request may return only:
    //
    // { "success": true, "message": "Updated" }
    //
    // In this case, fetch the latest cart instead
    // of incorrectly treating it as an empty cart.

    return null;
  }

  // =======================================================
  // FETCH CART
  // =======================================================

  static Future<BuyerCartSummary> getCart() async {
    final token = await _requiredToken();

    final response = await ApiServices.get('/buyer/cart', token: token);

    final data = _cartData(response);

    if (data == null) {
      throw const FormatException(
        'Cart data was not found in the API response.',
      );
    }

    return BuyerCartSummary.fromJson(data);
  }

  // =======================================================
  // HANDLE CART MUTATION RESPONSE
  // =======================================================

  static Future<BuyerCartSummary> _mutationResult(dynamic response) async {
    final data = _cartData(response);

    if (data != null) {
      return BuyerCartSummary.fromJson(data);
    }

    // If Laravel only returns an acknowledgment,
    // retrieve the current cart from the server.
    return getCart();
  }

  // =======================================================
  // ADD ITEM TO CART
  // =======================================================

  static Future<BuyerCartSummary> addItem({
    required String variantId,
    required int quantity,
  }) async {
    final cleanVariantId = variantId.trim();

    if (cleanVariantId.isEmpty) {
      throw ArgumentError('Please select a product variant.');
    }

    if (quantity <= 0) {
      throw ArgumentError('Quantity must be greater than zero.');
    }

    final token = await _requiredToken();

    final response = await ApiServices.post('/buyer/cart/items', {
      'product_variant_id': int.tryParse(cleanVariantId) ?? cleanVariantId,
      'quantity': quantity,
    }, token: token);

    return _mutationResult(response);
  }

  // =======================================================
  // UPDATE CART ITEM QUANTITY
  // =======================================================

  static Future<BuyerCartSummary> updateQuantity({
    required String itemId,
    required int quantity,
  }) async {
    final cleanItemId = itemId.trim();

    if (cleanItemId.isEmpty) {
      throw ArgumentError('Cart item ID is required.');
    }

    if (quantity <= 0) {
      throw ArgumentError(
        'Quantity must be greater than zero. '
        'Use removeItem() to delete a cart item.',
      );
    }

    final token = await _requiredToken();

    final response = await ApiServices.patch(
      '/buyer/cart/items/'
      '${Uri.encodeComponent(cleanItemId)}',
      {'quantity': quantity},
      token: token,
    );

    return _mutationResult(response);
  }

  // =======================================================
  // REMOVE ITEM FROM CART
  // =======================================================

  static Future<BuyerCartSummary> removeItem(String itemId) async {
    final cleanItemId = itemId.trim();

    if (cleanItemId.isEmpty) {
      throw ArgumentError('Cart item ID is required.');
    }

    final token = await _requiredToken();

    final response = await ApiServices.delete(
      '/buyer/cart/items/'
      '${Uri.encodeComponent(cleanItemId)}',
      token: token,
    );

    return _mutationResult(response);
  }
}
