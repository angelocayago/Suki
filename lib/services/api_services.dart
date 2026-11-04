
import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

class ApiServices {
  // =========================================================
  // API CONFIGURATION
  // =========================================================

  // Configure through --dart-define.
  //
  // Production: the deployed Laravel API behind Cloudflare.
  // For local Android emulator development, override with:
  // --dart-define=API_BASE_URL=http://10.0.2.2:8000/api

  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://sukishop.site/api',
  );

  static const Duration timeout = Duration(seconds: 20);

  /// Turns a Laravel photo path or URL into a URL the app can load.
  ///
  /// Localhost URLs are common when Laravel's APP_URL was set for local
  /// development. On a phone those URLs point back to the phone itself, so
  /// use the configured API host while keeping the photo's path.
  static String resolveMediaUrl(String? value) {
    final raw = value?.trim() ?? '';
    if (raw.isEmpty) return '';

    final apiUri = Uri.tryParse(baseUrl);
    if (apiUri == null || !apiUri.hasAuthority) return raw;

    final origin = Uri(
      scheme: apiUri.scheme,
      host: apiUri.host,
      port: apiUri.hasPort ? apiUri.port : null,
    );

    final candidate = raw.startsWith('//')
        ? Uri.tryParse('${apiUri.scheme}:$raw')
        : Uri.tryParse(raw);
    if (candidate == null) return raw;
    if (candidate.hasScheme && !candidate.hasAuthority) return raw;

    if (candidate.hasScheme && candidate.hasAuthority) {
      final isApiHost = candidate.host.toLowerCase() ==
          apiUri.host.toLowerCase();
      if (!_isLocalHost(candidate.host) && !isApiHost) {
        return candidate.toString();
      }

      final productPath = _productMediaPath(candidate.path);
      if (productPath != null) {
        return _apiProductMediaUrl(
          origin,
          apiUri,
          productPath,
          candidate,
        );
      }

      if (!_isLocalHost(candidate.host)) return candidate.toString();

      return origin
          .replace(
            path: candidate.path,
            query: candidate.hasQuery ? candidate.query : null,
            fragment: candidate.hasFragment ? candidate.fragment : null,
          )
          .toString();
    }

    final productPath = _productMediaPath(candidate.path);
    if (productPath != null) {
      return _apiProductMediaUrl(
        origin,
        apiUri,
        productPath,
        candidate,
      );
    }

    // Keep other Laravel public-disk paths on the conventional storage URL.
    var path = candidate.path;
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

  static String? _productMediaPath(String path) {
    var normalizedPath = path.startsWith('/') ? path.substring(1) : path;
    if (normalizedPath.startsWith('storage/')) {
      normalizedPath = normalizedPath.substring('storage/'.length);
    }
    if (!normalizedPath.startsWith('products/')) return null;

    return normalizedPath.substring('products/'.length);
  }

  static String _apiProductMediaUrl(
    Uri origin,
    Uri apiUri,
    String path,
    Uri source,
  ) {
    final apiPath = apiUri.path.replaceFirst(RegExp(r'/+$'), '');
    return origin
        .replace(
          path: '$apiPath/media/products/$path',
          query: source.hasQuery ? source.query : null,
          fragment: source.hasFragment ? source.fragment : null,
        )
        .toString();
  }

  static bool _isLocalHost(String host) {
    final normalizedHost = host.toLowerCase();
    return normalizedHost == 'localhost' ||
        normalizedHost == '127.0.0.1' ||
        normalizedHost == '0.0.0.0' ||
        normalizedHost == '::1';
  }

  // =========================================================
  // REQUEST HEADERS
  // =========================================================

  static Map<String, String> _headers({
    String? token,
  }) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',

      if (token != null && token.isNotEmpty)
        'Authorization': 'Bearer $token',
    };
  }

  // =========================================================
  // BUILD API URL
  // =========================================================

  static Uri _uri(String endpoint) {
    final normalizedBaseUrl = baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;

    final normalizedEndpoint = endpoint.startsWith('/')
        ? endpoint
        : '/$endpoint';

    return Uri.parse(
      '$normalizedBaseUrl$normalizedEndpoint',
    );
  }

  // =========================================================
  // HANDLE LARAVEL RESPONSE
  // =========================================================

  static dynamic _handleResponse(
    http.Response response,
  ) {
    final body = response.body.trim();

    // Handle empty response.
    if (body.isEmpty) {
      if (response.statusCode >= 200 &&
          response.statusCode < 300) {
        return <String, dynamic>{};
      }

      throw Exception(
        'Request failed. HTTP ${response.statusCode}',
      );
    }

    dynamic data;

    try {
      data = jsonDecode(body);
    } on FormatException {
      throw Exception(
        'Invalid server response. '
        'HTTP ${response.statusCode}. '
        'Please check your Laravel API.',
      );
    }

    // SUCCESS RESPONSE
    if (response.statusCode >= 200 &&
        response.statusCode < 300) {
      return data;
    }

    // ERROR RESPONSE
    String message =
        'Request failed (${response.statusCode}).';

    if (data is Map) {
      if (data['message'] != null) {
        message = data['message'].toString();
      }

      // LARAVEL VALIDATION ERRORS
      if (data['errors'] is Map) {
        final errors = data['errors'] as Map;

        if (errors.isNotEmpty) {
          final List<String> errorMessages = [];

          errors.forEach((field, messages) {
            if (messages is List) {
              for (final item in messages) {
                errorMessages.add(
                  item.toString(),
                );
              }
            } else {
              errorMessages.add(
                messages.toString(),
              );
            }
          });

          if (errorMessages.isNotEmpty) {
            message = errorMessages.join('\n');
          }
        }
      }
    }

    // COMMON HTTP STATUS MESSAGES
    switch (response.statusCode) {
      case 401:
        if (message == 'Unauthenticated.') {
          message = 'Your session has expired. Please log in again.';
        }
        break;

      case 403:
        if (message.startsWith('Request failed')) {
          message = 'You are not authorized to perform this action.';
        }
        break;

      case 404:
        if (message.startsWith('Request failed')) {
          message = 'API endpoint not found. Check your Laravel routes.';
        }
        break;

      case 422:
        if (message.startsWith('Request failed')) {
          message = 'Please check the information you submitted.';
        }
        break;

      case 500:
        if (message.startsWith('Request failed')) {
          message = 'Laravel server error. Check your backend logs.';
        }
        break;
    }

    throw Exception(message);
  }

  // =========================================================
  // HANDLE NETWORK ERRORS
  // =========================================================

  static Future<dynamic> _send(
    Future<http.Response> request,
  ) async {
    try {
      final response = await request.timeout(timeout);

      return _handleResponse(response);

    } on TimeoutException {
      throw Exception(
        'Connection timed out. '
        'Please check your Laravel server.',
      );

    } on SocketException {
      throw Exception(
        'Cannot connect to Laravel server. '
        'Check your API URL, IP address, and network.',
      );

    } on http.ClientException catch (e) {
      throw Exception(
        'Network error: ${e.message}',
      );

    } on FormatException {
      throw Exception(
        'Invalid API response format.',
      );
    }
  }

  // =========================================================
  // GET REQUEST
  // =========================================================

  static Future<dynamic> get(
    String endpoint, {
    String? token,
  }) async {
    return _send(
      http.get(
        _uri(endpoint),
        headers: _headers(token: token),
      ),
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
      http.post(
        _uri(endpoint),
        headers: _headers(token: token),
        body: jsonEncode(body),
      ),
    );
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
      http.put(
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
      http.patch(
        _uri(endpoint),
        headers: _headers(token: token),
        body: jsonEncode(body),
      ),
    );
  }

  // =========================================================
  // DELETE REQUEST
  // =========================================================

  static Future<dynamic> delete(
    String endpoint, {
    String? token,
  }) async {
    return _send(
      http.delete(
        _uri(endpoint),
        headers: _headers(token: token),
      ),
    );
  }

  // =========================================================
  // TEST LARAVEL API CONNECTION
  // =========================================================

  static Future<bool> testConnection() async {
    try {
      final apiUri = Uri.parse(baseUrl);

      // Test Laravel root URL instead of using
      // a separate hardcoded IP address.
      final serverUri = Uri(
        scheme: apiUri.scheme,
        host: apiUri.host,
        port: apiUri.port,
        path: '/',
      );

      final response = await http.get(
        serverUri,
        headers: {
          'Accept': '*/*',
        },
      ).timeout(timeout);

      return response.statusCode >= 200 &&
          response.statusCode < 400;

    } catch (_) {
      return false;
    }
  }
}
