
import 'auth_service.dart';
import 'api_services.dart';
import 'cart_service.dart';

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
    return BuyerAddress(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      label: json['label']?.toString() ?? 'Address',
      street: json['street']?.toString() ?? '',
      barangay: json['barangay']?.toString() ?? '',
      municipality: json['municipality']?.toString() ?? '',
      province: json['province']?.toString() ?? '',
      postalCode: json['postal_code']?.toString() ?? '',
      isDefault: json['is_default'] == true,
    );
  }

  String get formattedAddress => [
        street,
        barangay,
        municipality,
        province,
        postalCode,
      ].where((part) => part.trim().isNotEmpty).join(', ');
}

class BuyerCheckoutData {
  final BuyerCartSummary cart;
  final List<BuyerAddress> addresses;

  const BuyerCheckoutData({required this.cart, required this.addresses});

  factory BuyerCheckoutData.fromJson(Map<String, dynamic> json) {
    final rawAddresses = json['addresses'];
    return BuyerCheckoutData(
      cart: BuyerCartSummary.fromJson(json),
      addresses: rawAddresses is List
          ? rawAddresses
              .whereType<Map>()
              .map(
                (item) => BuyerAddress.fromJson(
                  Map<String, dynamic>.from(item),
                ),
              )
              .toList()
          : <BuyerAddress>[],
    );
  }
}

class OrderService {
  OrderService._();

  static Future<List<Map<String, dynamic>>> getOrders() async {
    final response = await ApiServices.get(
      '/buyer/orders',
      token: await _requiredToken(),
    );
    final data = _dataMap(response);
    final rawOrders = data['orders'];
    if (rawOrders is! List) return const [];

    return rawOrders
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  static Future<Map<String, dynamic>> getOrder(String orderId) async {
    final response = await ApiServices.get(
      '/buyer/orders/${Uri.encodeComponent(orderId)}',
      token: await _requiredToken(),
    );
    return _dataMap(response);
  }

  static Future<BuyerCheckoutData> getCheckoutData() async {
    final response = await ApiServices.get(
      '/buyer/checkout',
      token: await _requiredToken(),
    );
    return BuyerCheckoutData.fromJson(_dataMap(response));
  }

  static Future<Map<String, dynamic>> placeOrder({
    required String addressId,
  }) async {
    final response = await ApiServices.post(
      '/buyer/checkout',
      {
        'address_id': int.tryParse(addressId) ?? addressId,
        'payment_method': 'cod',
      },
      token: await _requiredToken(),
    );
    return _dataMap(response);
  }

  static Future<String> _requiredToken() async {
    final token = await AuthService.getToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please log in before checkout.');
    }
    return token;
  }

  static Map<String, dynamic> _dataMap(dynamic response) {
    if (response is! Map) {
      throw const FormatException('Invalid checkout API response.');
    }
    final rawData = response['data'];
    return Map<String, dynamic>.from(rawData is Map ? rawData : response);
  }
}
