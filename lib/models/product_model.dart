
import '../services/api_services.dart';

class Product {
  final int? id;
  final String name;
  final String? description;
  final double? price;
  final String? imageUrl;
  final int? categoryId;
  final String? categoryName;
  final int? stock;
  final bool? isAvailable;

  const Product({
    this.id,
    required this.name,
    this.description,
    this.price,
    this.imageUrl,
    this.categoryId,
    this.categoryName,
    this.stock,
    this.isAvailable,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    // Supports common Laravel price field names.
    final rawPrice = json['price'] ??
        json['selling_price'] ??
        json['unit_price'];

    double? parsedPrice;

    if (rawPrice is num) {
      parsedPrice = rawPrice.toDouble();
    } else if (rawPrice != null) {
      final cleanedPrice = rawPrice
          .toString()
          .replaceAll(',', '')
          .replaceAll('₱', '')
          .trim();

      parsedPrice = double.tryParse(cleanedPrice);
    }

    // Supports common image field names.
    final rawImage = json['image_url'] ??
        json['image'] ??
        json['image_path'] ??
        json['photo'] ??
        json['thumbnail'];

    String? parsedImage;

    if (rawImage is String && rawImage.trim().isNotEmpty) {
      parsedImage = rawImage.trim();
    } else if (rawImage is Map) {
      final imageMap = Map<String, dynamic>.from(rawImage);

      final nestedImage = imageMap['url'] ??
          imageMap['path'] ??
          imageMap['image_url'];

      if (nestedImage != null &&
          nestedImage.toString().trim().isNotEmpty) {
        parsedImage = nestedImage.toString().trim();
      }
    }

    // Category can be a related object or a category ID.
    final rawCategory = json['category'];
    int? parsedCategoryId;
    String? parsedCategoryName;

    if (rawCategory is Map) {
      final category = Map<String, dynamic>.from(rawCategory);

      final rawId = category['id'];
      if (rawId != null) {
        parsedCategoryId = int.tryParse(rawId.toString());
      }

      final rawName = category['name'] ??
          category['category_name'];

      if (rawName != null) {
        parsedCategoryName = rawName.toString();
      }
    }

    final categoryIdValue =
        json['category_id'] ?? json['categoryId'];

    parsedCategoryId ??= categoryIdValue == null
        ? null
        : int.tryParse(categoryIdValue.toString());

    final categoryNameValue =
        json['category_name'] ?? json['categoryName'];

    parsedCategoryName ??= categoryNameValue?.toString();

    // Supports common stock field names.
    final rawStock = json['stock'] ??
        json['quantity'] ??
        json['stock_quantity'];

    final parsedStock = rawStock == null
        ? null
        : int.tryParse(rawStock.toString());

    // Supports boolean and numeric availability values.
    final rawAvailability =
        json['is_available'] ?? json['isAvailable'];

    bool? parsedAvailability;

    if (rawAvailability is bool) {
      parsedAvailability = rawAvailability;
    } else if (rawAvailability is num) {
      parsedAvailability = rawAvailability != 0;
    } else if (rawAvailability is String) {
      final value = rawAvailability.toLowerCase().trim();

      if (['true', '1', 'yes', 'available'].contains(value)) {
        parsedAvailability = true;
      } else if (['false', '0', 'no', 'unavailable']
          .contains(value)) {
        parsedAvailability = false;
      }
    }

    final rawId = json['id'];

    return Product(
      id: rawId == null ? null : int.tryParse(rawId.toString()),
      name: (json['name'] ??
              json['product_name'] ??
              json['title'] ??
              'Unnamed product')
          .toString(),
      description: (
        json['description'] ?? json['details']
      )?.toString(),
      price: parsedPrice,
      imageUrl: parsedImage == null
          ? null
          : ApiServices.resolveMediaUrl(parsedImage),
      categoryId: parsedCategoryId,
      categoryName: parsedCategoryName,
      stock: parsedStock,
      isAvailable: parsedAvailability,
    );
  }

  // Converts the model into a Map when needed.
  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'name': name,
      if (description != null) 'description': description,
      if (price != null) 'price': price,
      if (imageUrl != null) 'image_url': imageUrl,
      if (categoryId != null) 'category_id': categoryId,
      if (categoryName != null) 'category_name': categoryName,
      if (stock != null) 'stock': stock,
      if (isAvailable != null) 'is_available': isAvailable,
    };
  }
}

