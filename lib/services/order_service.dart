import 'auth_service.dart';
import 'api_services.dart';
import 'cart_service.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER ORDER SERVICE
// =========================================================
//
// Handles:
// - Checkout data
// - Saved delivery addresses
// - Cash on Delivery order submission
// - Order history
// - Individual order details
//
// Uses centralized ApiServices and AuthService.
// Existing public methods and models are preserved.
// =========================================================

// =========================================================
// SHARED ORDER PARSING HELPERS
// =========================================================

class _OrderParser {
  _OrderParser._();

  static String text(dynamic value) {
    return value?.toString().trim() ?? '';
  }

  static bool boolean(dynamic value) {
    if (value is bool) return value;

    if (value is num) return value != 0;

    final normalized = text(value).toLowerCase();

    return normalized == 'true' || normalized == '1' || normalized == 'yes';
  }

  static Map<String, dynamic> object(dynamic value) {
    if (value is! Map) {
      throw const FormatException(
        'Expected an object in the Laravel API response.',
      );
    }

    return value.map((key, item) => MapEntry(key.toString(), item));
  }

  static Map<String, dynamic> unwrap(dynamic response) {
    final root = object(response);

    if (root['success'] == false) {
      final message = text(root['message']);

      throw Exception(message.isEmpty ? 'The order request failed.' : message);
    }

    final payload = root['data'];

    if (payload == null) {
      return root;
    }

    if (payload is! Map) {
      throw const FormatException('Invalid order API data format.');
    }

    return object(payload);
  }

  static List<Map<String, dynamic>> objectList(dynamic value) {
    if (value is! List) {
      throw const FormatException(
        'Expected a list in the Laravel API response.',
      );
    }

    return value.map((item) => object(item)).toList();
  }
}

// =========================================================
// BUYER DELIVERY ADDRESS MODEL
// =========================================================

class BuyerAddress {
  final String id;
  final String name;
  final String phone;
  final String label;
  final String street;
  final String barangay;
  final String municipality;
  final String province;
  final String postalCode;
  final bool isDefault;

  const BuyerAddress({
    required this.id,
    required this.name,
    required this.phone,
    required this.label,
    required this.street,
    required this.barangay,
    required this.municipality,
    required this.province,
    required this.postalCode,
    required this.isDefault,
  });

  factory BuyerAddress.fromJson(Map<String, dynamic> json) {
    final label = _OrderParser.text(json['label']);

    return BuyerAddress(
      id: _OrderParser.text(json['id']),
      name: _OrderParser.text(json['name']),
      phone: _OrderParser.text(json['phone']),
      label: label.isEmpty ? 'Address' : label,
      street: _OrderParser.text(json['street']),
      barangay: _OrderParser.text(json['barangay']),
      municipality: _OrderParser.text(json['municipality']),
      province: _OrderParser.text(json['province']),
      postalCode: _OrderParser.text(json['postal_code']),
      isDefault: _OrderParser.boolean(json['is_default']),
    );
  }

  String get formattedAddress {
    return [
      street,
      barangay,
      municipality,
      province,
      postalCode,
    ].where((part) => part.trim().isNotEmpty).join(', ');
  }
}

// =========================================================
// BUYER CHECKOUT DATA MODEL
// =========================================================

class BuyerCheckoutData {
  final BuyerCartSummary cart;
  final List<BuyerAddress> addresses;

  const BuyerCheckoutData({required this.cart, required this.addresses});

  factory BuyerCheckoutData.fromJson(Map<String, dynamic> json) {
    final rawAddresses = json['addresses'];

    if (rawAddresses is! List) {
      throw const FormatException(
        'Delivery addresses are missing '
        'or invalid in the checkout response.',
      );
    }

    final addresses = rawAddresses
        .map((item) => BuyerAddress.fromJson(_OrderParser.object(item)))
        .toList();

    // Supported formats:
    //
    // {
    //   "cart": [...],
    //   "subtotal": 100,
    //   "shipping": 30,
    //   "total": 130,
    //   "addresses": [...]
    // }
    //
    // or:
    //
    // {
    //   "cart": {
    //     "items": [...],
    //     "subtotal": 100,
    //     "shipping": 30,
    //     "total": 130
    //   },
    //   "addresses": [...]
    // }

    Map<String, dynamic> cartData = json;

    final rawCart = json['cart'];

    if (rawCart is Map) {
      cartData = _OrderParser.object(rawCart);
    } else if (rawCart is! List && json['items'] is! List) {
      throw const FormatException(
        'Cart information is missing '
        'from the checkout response.',
      );
    }

    final cartItems = cartData['cart'] ?? cartData['items'];

    if (cartItems is! List) {
      throw const FormatException('Invalid checkout cart items.');
    }

    return BuyerCheckoutData(
      cart: BuyerCartSummary.fromJson(cartData),
      addresses: addresses,
    );
  }
}

// =========================================================
// SUKISHOP ORDER SERVICE
// =========================================================

class OrderService {
  OrderService._();

  // =======================================================
  // REQUIRE AUTHENTICATION
  // =======================================================

  static Future<String> _requiredToken() async {
    final token = await AuthService.getToken();

    if (token == null || token.trim().isEmpty) {
      throw Exception('Please log in before checkout.');
    }

    return token.trim();
  }

  // =======================================================
  // GET ORDER HISTORY
  // =======================================================

  static Future<List<Map<String, dynamic>>> getOrders() async {
    final token = await _requiredToken();

    final response = await ApiServices.get('/buyer/orders', token: token);

    // Laravel may return:
    //
    // { "orders": [...] }
    // { "data": { "orders": [...] } }
    // { "data": [...] }
    // { "data": { "orders": { "data": [...] } } }
    //
    // The last shape represents a paginator.

    if (response is List) {
      return _OrderParser.objectList(response);
    }

    final root = _OrderParser.object(response);

    if (root['success'] == false) {
      throw Exception(
        _OrderParser.text(root['message']).isEmpty
            ? 'Unable to load orders.'
            : _OrderParser.text(root['message']),
      );
    }

    dynamic orders = root['data'] ?? root;

    if (orders is Map) {
      orders = orders['orders'] ?? orders['data'];
    }

    if (orders is Map) {
      orders = orders['data'] ?? orders['orders'];
    }

    return _OrderParser.objectList(orders);
  }

  // =======================================================
  // GET INDIVIDUAL ORDER
  // =======================================================

  static Future<Map<String, dynamic>> getOrder(String orderId) async {
    final cleanId = orderId.trim();

    if (cleanId.isEmpty) {
      throw ArgumentError('Order ID cannot be empty.');
    }

    final token = await _requiredToken();

    final response = await ApiServices.get(
      '/buyer/orders/'
      '${Uri.encodeComponent(cleanId)}',
      token: token,
    );

    final data = _OrderParser.unwrap(response);

    // Support:
    // { "data": { "order": {...} } }
    // { "data": {...} }
    // { "order": {...} }

    final nestedOrder = data['order'];

    if (nestedOrder is Map) {
      return _OrderParser.object(nestedOrder);
    }

    if (data.isEmpty) {
      throw const FormatException('Order details were not found.');
    }

    return data;
  }

  // =======================================================
  // GET CHECKOUT DATA
  // =======================================================

  static Future<BuyerCheckoutData> getCheckoutData() async {
    final token = await _requiredToken();

    final response = await ApiServices.get('/buyer/checkout', token: token);

    final data = _OrderParser.unwrap(response);

    return BuyerCheckoutData.fromJson(data);
  }

  // =======================================================
  // PLACE ORDER
  // =======================================================

  static Future<Map<String, dynamic>> placeOrder({
    required String addressId,
  }) async {
    final cleanAddressId = addressId.trim();

    if (cleanAddressId.isEmpty) {
      throw ArgumentError('Please select a delivery address.');
    }

    final token = await _requiredToken();

    // Existing backend contract:
    //
    // POST /api/buyer/checkout
    //
    // {
    //   "address_id": 1,
    //   "payment_method": "cod"
    // }
    //
    // The Laravel backend must independently
    // validate stock, price, address ownership,
    // and final order total.

    final response = await ApiServices.post('/buyer/checkout', {
      'address_id': int.tryParse(cleanAddressId) ?? cleanAddressId,
      'payment_method': 'cod',
    }, token: token);

    final data = _OrderParser.unwrap(response);

    // Laravel may wrap the created order:
    //
    // {
    //   "data": {
    //     "order": {
    //       "id": 123,
    //       "order_number": "SUKI-123"
    //     }
    //   }
    // }
    //
    // Normalize it so checkout.dart can read:
    //
    // order['id']
    // order['order_number']

    final nestedOrder = data['order'];

    final order = nestedOrder is Map ? _OrderParser.object(nestedOrder) : data;

    final orderId = _OrderParser.text(order['id']);

    final orderNumber = _OrderParser.text(order['order_number']);

    // An order reference is required before
    // the checkout page can confirm success.
    //
    // If neither value exists, return the
    // response to checkout.dart, which will
    // mark the submission as uncertain.
    //
    // Do not automatically retry this POST.

    if (orderId.isEmpty && orderNumber.isEmpty) {
      return order;
    }

    return order;
  }
}
