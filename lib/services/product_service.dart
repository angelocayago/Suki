
import '../models/product_model.dart';
import 'api_services.dart';

class ProductService {
  // Fetch all products from the Laravel API.
  static Future<List<Product>> getProducts({
    String? token,
  }) async {
    final response = await ApiServices.get(
      'products',
      token: token,
    );

    final items = _extractProducts(response);

    return items
        .map((item) => Product.fromJson(item))
        .toList();
  }

  // Fetch one product using its ID.
  static Future<Product> getProductById(
    int id, {
    String? token,
  }) async {
    final response = await ApiServices.get(
      'products/$id',
      token: token,
    );

    final productData = _extractSingleProduct(response);

    return Product.fromJson(productData);
  }

  // Handle common Laravel response formats:
  // [...]
  // {"data": [...]}
  // {"products": [...]}
  // {"data": {"products": [...]}}
  static List<Map<String, dynamic>> _extractProducts(
    dynamic response,
  ) {
    dynamic data = response;

    if (data is Map) {
      if (data['products'] is List) {
        data = data['products'];
      } else if (data['data'] is List) {
        data = data['data'];
      } else if (data['data'] is Map) {
        final nested = data['data'];

        if (nested['products'] is List) {
          data = nested['products'];
        } else if (nested['data'] is List) {
          data = nested['data'];
        } else {
          throw const FormatException(
            'Unexpected products API response format.',
          );
        }
      } else {
        throw const FormatException(
          'Products list was not found in the API response.',
        );
      }
    }

    if (data is! List) {
      throw const FormatException(
        'Expected a list of products from the API.',
      );
    }

    return data
        .whereType<Map>()
        .map(
          (item) => Map<String, dynamic>.from(item),
        )
        .toList();
  }

  // Handle a single product response.
  static Map<String, dynamic> _extractSingleProduct(
    dynamic response,
  ) {
    dynamic data = response;

    if (data is Map && data['data'] is Map) {
      data = data['data'];
    }

    if (data is Map && data['product'] is Map) {
      data = data['product'];
    }

    if (data is! Map) {
      throw const FormatException(
        'Invalid product details API response.',
      );
    }

    return Map<String, dynamic>.from(data);
  }
}

