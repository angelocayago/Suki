import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

// =========================================================
// SUKISHOP MOBILE - API SERVICES
// =========================================================
//
// Laravel REST API integration.
//
// Existing features preserved:
// - API base URL configuration
// - Product image URL resolution
// - GET requests
// - POST requests
// - PUT requests
// - PATCH requests
// - DELETE requests
// - Bearer token authentication
// - Laravel validation errors
// - API connection checking
//
// Improvements:
// - Safer API URL construction
// - Improved error handling
// - Better network timeout handling
// - HTTP response validation
// - UTF-8 response decoding
// - Protection against malformed server responses
//
// =========================================================

class ApiServices {
  // =========================================================
  // API CONFIGURATION
  // =========================================================

  // Production:
  // https://sukishop.site/api
  //
  // Android Emulator:
  // flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
  //
  // Physical Android device:
  // Use your computer's LAN IP for local Laravel.
  //
  // Example:
  // flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://sukishop.site/api',
  );

  static const Duration timeout = Duration(seconds: 20);

  // =========================================================
  // RESOLVE LARAVEL MEDIA URL
  // =========================================================

  /// Converts Laravel image paths or URLs into URLs
  /// that Flutter can access.
  ///
  /// Examples:
  ///
  /// products/photo.jpg
  /// storage/products/photo.jpg
  /// /storage/products/photo.jpg
  /// http://localhost:8000/storage/products/photo.jpg
  ///
  /// Existing product image behavior is preserved:
  ///
  /// /api/media/products/{filename}

  static String resolveMediaUrl(String? value) {
    final String raw = value?.trim() ?? '';

    if (raw.isEmpty) {
      return '';
    }

    final Uri? apiUri = Uri.tryParse(baseUrl);

    if (apiUri == null ||
        !apiUri.hasAuthority ||
        (apiUri.scheme != 'http' && apiUri.scheme != 'https')) {
      return raw;
    }

    final Uri origin = Uri(
      scheme: apiUri.scheme,
      host: apiUri.host,
      port: apiUri.hasPort ? apiUri.port : null,
    );

    final Uri? candidate = raw.startsWith('//')
        ? Uri.tryParse('${apiUri.scheme}:$raw')
        : Uri.tryParse(raw);

    if (candidate == null) {
      return raw;
    }

    if (candidate.hasScheme && !candidate.hasAuthority) {
      return raw;
    }

    // =====================================================
    // ABSOLUTE IMAGE URL
    // =====================================================

    if (candidate.hasScheme && candidate.hasAuthority) {
      final bool isApiHost =
          candidate.host.toLowerCase() == apiUri.host.toLowerCase();

      // Preserve external image/CDN URLs.
      if (!_isLocalHost(candidate.host) && !isApiHost) {
        return candidate.toString();
      }

      // Product images from Laravel storage.
      final String? productPath = _productMediaPath(candidate.path);

      if (productPath != null) {
        return _apiProductMediaUrl(origin, apiUri, productPath, candidate);
      }

      // Preserve valid image URLs from the
      // configured production API server.
      if (!_isLocalHost(candidate.host)) {
        return candidate.toString();
      }

      // Rewrite localhost URLs using the configured
      // API host so images can load on Android.
      return origin
          .replace(
            path: candidate.path,
            query: candidate.hasQuery ? candidate.query : null,
            fragment: candidate.hasFragment ? candidate.fragment : null,
          )
          .toString();
    }

    // =====================================================
    // RELATIVE IMAGE URL
    // =====================================================

    final String? productPath = _productMediaPath(candidate.path);

    if (productPath != null) {
      return _apiProductMediaUrl(origin, apiUri, productPath, candidate);
    }

    // Preserve other Laravel public storage paths.
    String path = candidate.path;

    if (!path.startsWith('/')) {
      path = path.startsWith('storage/') ? '/$path' : '/storage/$path';
    }

    return origin
        .replace(
          path: path,
          query: candidate.hasQuery ? candidate.query : null,
          fragment: candidate.hasFragment ? candidate.fragment : null,
        )
        .toString();
  }

  // =========================================================
  // EXTRACT PRODUCT MEDIA PATH
  // =========================================================

  static String? _productMediaPath(String path) {
    String normalizedPath = path.startsWith('/') ? path.substring(1) : path;

    if (normalizedPath.startsWith('storage/')) {
      normalizedPath = normalizedPath.substring('storage/'.length);
    }

    if (!normalizedPath.startsWith('products/')) {
      return null;
    }

    return normalizedPath.substring('products/'.length);
  }

  // =========================================================
  // PRODUCT MEDIA API URL
  // =========================================================

  static String _apiProductMediaUrl(
    Uri origin,
    Uri apiUri,
    String path,
    Uri source,
  ) {
    final String apiPath = apiUri.path.replaceFirst(RegExp(r'/+$'), '');

    return origin
        .replace(
          path: '$apiPath/media/products/$path',
          query: source.hasQuery ? source.query : null,
          fragment: source.hasFragment ? source.fragment : null,
        )
        .toString();
  }

  // =========================================================
  // CHECK LOCAL HOSTNAME
  // =========================================================

  static bool _isLocalHost(String host) {
    final String normalizedHost = host.toLowerCase();

    return normalizedHost == 'localhost' ||
        normalizedHost == '127.0.0.1' ||
        normalizedHost == '0.0.0.0' ||
        normalizedHost == '::1';
  }

  // =========================================================
  // REQUEST HEADERS
  // =========================================================

  static Map<String, String> _headers({String? token}) {
    final String? cleanToken = token?.trim();

    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',

      if (cleanToken != null && cleanToken.isNotEmpty)
        'Authorization': 'Bearer $cleanToken',
    };
  }

  // =========================================================
  // BUILD API URL
  // =========================================================

  static Uri _uri(String endpoint) {
    final String normalizedBaseUrl = baseUrl.trim().replaceFirst(
      RegExp(r'/+$'),
      '',
    );

    if (normalizedBaseUrl.isEmpty) {
      throw Exception('Laravel API base URL is not configured.');
    }

    final Uri? apiUri = Uri.tryParse(normalizedBaseUrl);

    if (apiUri == null ||
        !apiUri.hasAuthority ||
        (apiUri.scheme != 'http' && apiUri.scheme != 'https')) {
      throw Exception('Invalid Laravel API base URL.');
    }

    final String normalizedEndpoint = endpoint.trim().replaceFirst(
      RegExp(r'^/+'),
      '',
    );

    if (normalizedEndpoint.isEmpty) {
      return apiUri;
    }

    final Uri? endpointUri = Uri.tryParse(normalizedEndpoint);

    if (endpointUri == null ||
        endpointUri.hasScheme ||
        endpointUri.hasAuthority) {
      throw Exception('Invalid Laravel API endpoint.');
    }

    final String basePath = apiUri.path.replaceFirst(RegExp(r'/+$'), '');

    return apiUri.replace(
      path: '$basePath/${endpointUri.path}',
      query: endpointUri.hasQuery ? endpointUri.query : null,
      fragment: null,
    );
  }

  // =========================================================
  // HANDLE LARAVEL API RESPONSE
  // =========================================================

  static dynamic _handleResponse(http.Response response) {
    final int statusCode = response.statusCode;

    final bool isSuccess = statusCode >= 200 && statusCode < 300;

    final String body = utf8
        .decode(response.bodyBytes, allowMalformed: true)
        .trim();

    // =====================================================
    // EMPTY RESPONSE
    // =====================================================

    if (body.isEmpty) {
      if (isSuccess) {
        return <String, dynamic>{};
      }

      throw Exception(_defaultHttpMessage(statusCode));
    }

    dynamic data;

    // =====================================================
    // JSON DECODING
    // =====================================================

    try {
      data = jsonDecode(body);
    } on FormatException {
      if (isSuccess) {
        throw Exception('Invalid JSON response from Laravel server.');
      }

      throw Exception(_defaultHttpMessage(statusCode));
    }

    // =====================================================
    // SUCCESS RESPONSE
    // =====================================================

    if (isSuccess) {
      return data;
    }

    // =====================================================
    // ERROR RESPONSE
    // =====================================================

    String message = _defaultHttpMessage(statusCode);

    if (data is Map) {
      final dynamic serverMessage = data['message'];

      if (serverMessage is String && serverMessage.trim().isNotEmpty) {
        message = serverMessage.trim();
      }

      // ===================================================
      // LARAVEL VALIDATION ERRORS
      // ===================================================

      if (data['errors'] is Map) {
        final Map<dynamic, dynamic> errors = data['errors'] as Map;

        final List<String> errorMessages = [];

        for (final dynamic value in errors.values) {
          if (value is List) {
            for (final dynamic item in value) {
              final String errorText = item.toString().trim();

              if (errorText.isNotEmpty) {
                errorMessages.add(errorText);
              }
            }
          } else if (value != null) {
            final String errorText = value.toString().trim();

            if (errorText.isNotEmpty) {
              errorMessages.add(errorText);
            }
          }
        }

        if (errorMessages.isNotEmpty) {
          message = errorMessages.join('\n');
        }
      }
    }

    // =====================================================
    // AUTHENTICATION ERROR
    // =====================================================

    if (statusCode == 401) {
      if (message.toLowerCase() == 'unauthenticated.' ||
          message.toLowerCase() == 'unauthenticated') {
        message =
            'Unauthenticated. Your session has expired. '
            'Please log in again.';
      }
    }

    throw Exception(message);
  }

  // =========================================================
  // DEFAULT HTTP ERROR MESSAGES
  // =========================================================

  static String _defaultHttpMessage(int statusCode) {
    switch (statusCode) {
      case 400:
        return 'Invalid request. '
            'Please check your information.';

      case 401:
        return 'Unauthenticated. '
            'Please log in again.';

      case 403:
        return 'You are not authorized to perform '
            'this action.';

      case 404:
        return 'API endpoint not found. '
            'Check your Laravel routes.';

      case 405:
        return 'Request method not allowed.';

      case 408:
        return 'Request timed out. '
            'Please try again.';

      case 409:
        return 'This request conflicts with '
            'existing information.';

      case 413:
        return 'The uploaded file is too large.';

      case 419:
        return 'Session expired. '
            'Please try again.';

      case 422:
        return 'Please check the information '
            'you submitted.';

      case 429:
        return 'Too many requests. '
            'Please try again later.';

      case 500:
        return 'Laravel server error. '
            'Please try again later.';

      case 502:
        return 'The server is temporarily '
            'unavailable.';

      case 503:
        return 'The SUKI service is temporarily '
            'unavailable.';

      case 504:
        return 'The server took too long '
            'to respond.';

      default:
        return 'Request failed. '
            'HTTP $statusCode.';
    }
  }

  // =========================================================
  // HANDLE NETWORK ERRORS
  // =========================================================

  static Future<dynamic> _send(Future<http.Response> Function() request) async {
    try {
      final http.Response response = await request().timeout(timeout);

      return _handleResponse(response);
    } on TimeoutException {
      throw Exception(
        'Connection timed out. '
        'Please check your internet connection '
        'and Laravel server.',
      );
    } on SocketException {
      throw Exception(
        'Cannot connect to SUKI server. '
        'Check your network and API URL.',
      );
    } on HandshakeException {
      throw Exception(
        'Secure connection failed. '
        'Please check the server certificate.',
      );
    } on http.ClientException catch (error) {
      throw Exception('Network error: ${error.message}');
    } on FormatException {
      throw Exception('Invalid API response format.');
    }
  }

  // =========================================================
  // GET REQUEST
  // =========================================================

  static Future<dynamic> get(String endpoint, {String? token}) async {
    return _send(
      () => http.get(_uri(endpoint), headers: _headers(token: token)),
    );
  }

  // =========================================================
  // POST REQUEST
  // =========================================================

  static Future<dynamic> post(
    String endpoint,
    Map<String, dynamic> body, {
    String? token,
  }) async {
    return _send(
      () => http.post(
        _uri(endpoint),
        headers: _headers(token: token),
        body: jsonEncode(body),
      ),
    );
  }

  /// Uploads multipart form data to Laravel while keeping the same timeout,
  /// response parsing, and bearer-token behavior as the JSON helpers.
  static Future<dynamic> postMultipart(
    String endpoint, {
    required Map<String, String> fields,
    required String fileField,
    required String fileName,
    required List<int> fileBytes,
    String? token,
  }) async {
    return _send(() async {
      final request = http.MultipartRequest('POST', _uri(endpoint));
      request.headers['Accept'] = 'application/json';
      if (token != null && token.trim().isNotEmpty) {
        request.headers['Authorization'] = 'Bearer ${token.trim()}';
      }
      request.fields.addAll(fields);
      request.files.add(
        http.MultipartFile.fromBytes(
          fileField,
          fileBytes,
          filename: fileName,
        ),
      );
      final streamed = await request.send();
      return http.Response.fromStream(streamed);
    });
  }

  // =========================================================
  // PUT REQUEST
  // =========================================================

  static Future<dynamic> put(
    String endpoint,
    Map<String, dynamic> body, {
    String? token,
  }) async {
    return _send(
      () => http.put(
        _uri(endpoint),
        headers: _headers(token: token),
        body: jsonEncode(body),
      ),
    );
  }

  // =========================================================
  // PATCH REQUEST
  // =========================================================

  static Future<dynamic> patch(
    String endpoint,
    Map<String, dynamic> body, {
    String? token,
  }) async {
    return _send(
      () => http.patch(
        _uri(endpoint),
        headers: _headers(token: token),
        body: jsonEncode(body),
      ),
    );
  }

  // =========================================================
  // DELETE REQUEST
  // =========================================================

  static Future<dynamic> delete(String endpoint, {String? token}) async {
    return _send(
      () => http.delete(_uri(endpoint), headers: _headers(token: token)),
    );
  }

  // =========================================================
  // TEST LARAVEL CONNECTION
  // =========================================================

  static Future<bool> testConnection() async {
    try {
      final Uri apiUri = Uri.parse(baseUrl);

      // Keep existing behavior:
      // Test Laravel website root instead
      // of assuming an API health endpoint exists.
      final Uri serverUri = Uri(
        scheme: apiUri.scheme,
        host: apiUri.host,
        port: apiUri.hasPort ? apiUri.port : null,
        path: '/',
      );

      final http.Response response = await http
          .get(serverUri, headers: {'Accept': '*/*'})
          .timeout(timeout);

      return response.statusCode >= 200 && response.statusCode < 400;
    } catch (_) {
      return false;
    }
  }
}
