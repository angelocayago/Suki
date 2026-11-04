import 'auth_service.dart';
import 'api_services.dart';

class WishlistService {
  WishlistService._();

  static Future<List<Map<String, dynamic>>> getItems() async {
    final response = await ApiServices.get(
      '/buyer/wishlist',
      token: await _requiredToken(),
    );
    final data = _dataMap(response);
    final rawItems = data['items'];
    if (rawItems is! List) return const [];

    return rawItems
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  static Future<bool> contains(String slug) async {
    final response = await ApiServices.get(
      '/buyer/wishlist',
      token: await _requiredToken(),
    );
    final data = _dataMap(response);
    final slugs = data['slugs'];
    return slugs is List && slugs.any((item) => item.toString() == slug);
  }

  static Future<bool> toggle(String slug) async {
    final response = await ApiServices.post(
      '/buyer/wishlist/${Uri.encodeComponent(slug)}/toggle',
      const {},
      token: await _requiredToken(),
    );
    final data = _dataMap(response);
    return data['is_wishlisted'] == true;
  }

  static Future<String> _requiredToken() async {
    final token = await AuthService.getToken();
    if (token == null || token.isEmpty) {
      throw Exception('Please log in to use your wishlist.');
    }
    return token;
  }

  static Map<String, dynamic> _dataMap(dynamic response) {
    if (response is! Map) {
      throw const FormatException('Invalid wishlist API response.');
    }
    final rawData = response['data'];
    return Map<String, dynamic>.from(rawData is Map ? rawData : response);
  }
}
