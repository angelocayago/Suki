
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_services.dart';

class AuthService {
  static const FlutterSecureStorage _storage =
      FlutterSecureStorage();
  static const String _tokenKey = 'auth_token';
  static const String _rememberMeKey = 'remember_me_v2';
  static const String _rememberedLoginKey = 'remembered_login';
  static const String _rememberedHomeRouteKey = 'remembered_home_route';

  static String? _sessionToken;

  // =========================================================
  // LOGIN
  // =========================================================

  static Future<Map<String, dynamic>> login({
    required String login,
    required String password,
    bool rememberMe = false,
  }) async {
    try {
      final response = await ApiServices.post('/login', {
        'login': login.trim(),
        'password': password,
      });

      if (response is! Map) {
        throw Exception('Invalid server response.');
      }

      if (response['success'] != true) {
        throw Exception(
          response['message'] ?? 'Login failed.',
        );
      }

      final token = response['token']?.toString();

      if (token == null || token.isEmpty) {
        throw Exception(
          'No authentication token received.',
        );
      }

      _sessionToken = token;
      await setRememberMe(rememberMe);

      if (rememberMe) {
        await _storage.write(key: _tokenKey, value: token);
        await _storage.write(
          key: _rememberedLoginKey,
          value: login.trim(),
        );
        await _storage.write(
          key: _rememberedHomeRouteKey,
          value: homeRouteForUser(Map<String, dynamic>.from(response)),
        );
      }

      return Map<String, dynamic>.from(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  // =========================================================
  // BUYER REGISTRATION
  // =========================================================

  static Future<Map<String, dynamic>> registerBuyer({
    required Map<String, dynamic> buyerData,
  }) async {
    try {
      final Map<String, dynamic> payload = {
        ...buyerData,
        'role': 'buyer',
      };

      final response = await ApiServices.post(
        '/register',
        payload,
      );

      if (response is! Map) {
        throw Exception('Invalid server response.');
      }

      if (response['success'] != true) {
        throw Exception(
          response['message'] ??
              'Buyer registration failed.',
        );
      }

      // Store token only if Laravel returns one.
      final token = response['token']?.toString();

      if (token != null && token.isNotEmpty) {
        _sessionToken = token;
        await _storage.write(
          key: _tokenKey,
          value: token,
        );
      }

      return Map<String, dynamic>.from(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  // =========================================================
  // GET SAVED TOKEN
  // =========================================================

  static Future<String?> getToken() async {
    final sessionToken = _sessionToken;
    if (sessionToken != null && sessionToken.isNotEmpty) {
      return sessionToken;
    }
    return _storage.read(key: _tokenKey);
  }

  static Future<bool> isRememberMeEnabled() async {
    final savedPreference = await _storage.read(key: _rememberMeKey);
    if (savedPreference != null) return savedPreference == 'true';

    // Existing app versions persisted every login token. Keep those sessions
    // signed in once while migrating them to the explicit remember setting.
    final existingToken = await _storage.read(key: _tokenKey);
    if (existingToken != null && existingToken.isNotEmpty) {
      await _storage.write(key: _rememberMeKey, value: 'true');
      return true;
    }

    // Keep users signed in by default; unchecking the login option stores an
    // explicit false value and disables startup restoration.
    return true;
  }

  static Future<String?> getRememberedLogin() async =>
      _storage.read(key: _rememberedLoginKey);

  static Future<String?> getRememberedHomeRoute() async =>
      _storage.read(key: _rememberedHomeRouteKey);

  static Future<void> setRememberMe(bool enabled) async {
    await _storage.write(
      key: _rememberMeKey,
      value: enabled ? 'true' : 'false',
    );
    if (!enabled) {
      await _storage.delete(key: _tokenKey);
      await _storage.delete(key: _rememberedLoginKey);
      await _storage.delete(key: _rememberedHomeRouteKey);
    }
  }

  static String homeRouteForUser(Map<String, dynamic> response) {
    final dynamic nestedUser = response['user'] ?? response['data'];
    final Map<dynamic, dynamic> user = nestedUser is Map
        ? nestedUser
        : response;
    final role = (user['role'] ??
            user['user_type'] ??
            user['account_type'] ??
            user['type'] ??
            '')
        .toString()
        .toLowerCase();

    if (role.contains('rider') || role.contains('driver')) {
      return '/rider-home';
    }
    if (role.contains('logistics') || role.contains('courier')) {
      return '/logistics-home';
    }
    return '/buyer-home';
  }

  // =========================================================
  // GET LOGGED-IN USER DETAILS
  // =========================================================

  static Future<Map<String, dynamic>> getUser() async {
    try {
      final token = await getToken();

      if (token == null || token.isEmpty) {
        throw Exception('Please log in first.');
      }

      final response = await ApiServices.get(
        '/user',
        token: token,
      );

      if (response is! Map) {
        throw Exception('Invalid user response.');
      }

      return Map<String, dynamic>.from(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  // =========================================================
  // LOGOUT
  // =========================================================

  static Future<void> logout() async {
    try {
      final token = await getToken();
      if (token != null && token.isNotEmpty) {
        await ApiServices.post(
          '/logout',
          {},
          token: token,
        );
      }
    } finally {
      _sessionToken = null;
      await _storage.delete(key: _tokenKey);
      await _storage.delete(key: _rememberMeKey);
      await _storage.delete(key: _rememberedLoginKey);
      await _storage.delete(key: _rememberedHomeRouteKey);
    }
  }

  // =========================================================
  // CHECK IF USER IS LOGGED IN
  // =========================================================

  static Future<bool> isLoggedIn() async {
    final token = await getToken();

    return token != null && token.isNotEmpty;
  }

  // =========================================================
  // CLEAR TOKEN
  // =========================================================

  static Future<void> clearToken() async {
    _sessionToken = null;
    await _storage.delete(key: _tokenKey);
  }

  // =========================================================
  // ERROR HANDLER
  // =========================================================

  static Exception _handleError(dynamic error) {
    String message = error.toString();

    if (message.startsWith('Exception: ')) {
      message = message.substring(11);
    }

    return Exception(message);
  }
}
