import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_services.dart';

// =========================================================
// SUKISHOP MOBILE - AUTHENTICATION SERVICE
// =========================================================
//
// Connected to existing Laravel API.
//
// Features:
// - Login
// - Buyer registration
// - Remember Me
// - Secure token storage
// - Session restoration
// - Role-based dashboard routing
// - Authenticated user retrieval
// - Logout
// - Session cleanup
// - Authentication error handling
//
// =========================================================

class AuthService {
  // =========================================================
  // SECURE STORAGE
  // =========================================================

  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  static const String _tokenKey = 'auth_token';

  static const String _rememberMeKey = 'remember_me_v2';

  static const String _rememberedLoginKey = 'remembered_login';

  static const String _rememberedHomeRouteKey = 'remembered_home_route';

  // Active token for the current app session.
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
      final String loginValue = login.trim();

      if (loginValue.isEmpty) {
        throw Exception('Please enter your email or phone number.');
      }

      if (password.isEmpty) {
        throw Exception('Please enter your password.');
      }

      // Authenticate using Laravel API.
      final dynamic response = await ApiServices.post('/login', {
        'login': loginValue,
        'password': password,
      });

      final Map<String, dynamic> result = _parseResponse(response);

      // Validate Laravel response.
      _ensureSuccess(result, 'Login failed. Please try again.');

      // Extract authentication token.
      final String token = _extractToken(result);

      if (token.isEmpty) {
        throw Exception('No authentication token received from server.');
      }

      // Determine account dashboard.
      // Unknown roles must not default to buyer.
      final String homeRoute = homeRouteForUser(result);

      // Clear previous local authentication data.
      await clearToken();

      // Store the Remember Me preference.
      await setRememberMe(rememberMe);

      // Keep the token for the active session.
      _sessionToken = token;

      // Persist token only when Remember Me is enabled.
      if (rememberMe) {
        await _storage.write(key: _tokenKey, value: token);

        await _storage.write(key: _rememberedLoginKey, value: loginValue);

        await _storage.write(key: _rememberedHomeRouteKey, value: homeRoute);
      }

      return result;
    } catch (error) {
      throw _handleError(error);
    }
  }

  // =========================================================
  // BUYER REGISTRATION
  // =========================================================

  static Future<Map<String, dynamic>> registerBuyer({
    required Map<String, dynamic> buyerData,
  }) async {
    try {
      final Map<String, dynamic> payload = {...buyerData, 'role': 'buyer'};

      final dynamic response = await ApiServices.post('/register', payload);

      final Map<String, dynamic> result = _parseResponse(response);

      _ensureSuccess(result, 'Buyer registration failed.');

      // Some Laravel registration endpoints return
      // a token immediately; others require login
      // or account verification first.
      //
      // Do not automatically create a persistent
      // session here. The registration screen can
      // redirect to Login after successful registration.
      //
      // This prevents an unverified registration from
      // accidentally restoring an authenticated session.

      return result;
    } catch (error) {
      throw _handleError(error);
    }
  }

  // =========================================================
  // VALIDATE API RESPONSE
  // =========================================================

  static Map<String, dynamic> _parseResponse(dynamic response) {
    if (response is! Map) {
      throw Exception('Invalid response received from Laravel server.');
    }

    try {
      return Map<String, dynamic>.from(response);
    } catch (_) {
      throw Exception('Invalid response format from Laravel server.');
    }
  }

  // =========================================================
  // CHECK API SUCCESS
  // =========================================================

  static void _ensureSuccess(
    Map<String, dynamic> response,
    String fallbackMessage,
  ) {
    // Explicit failure from the API.
    if (response['success'] == false) {
      throw Exception(response['message']?.toString() ?? fallbackMessage);
    }

    // Preserve the existing backend contract:
    // successful responses are expected to contain
    // success: true.
    if (response['success'] != true) {
      throw Exception(response['message']?.toString() ?? fallbackMessage);
    }
  }

  // =========================================================
  // EXTRACT AUTHENTICATION TOKEN
  // =========================================================

  static String _extractToken(Map<String, dynamic> response) {
    dynamic token = response['token'];

    // Support a response containing data.token.
    if (token == null && response['data'] is Map) {
      final Map<dynamic, dynamic> data = response['data'] as Map;

      token = data['token'];
    }

    return token?.toString().trim() ?? '';
  }

  // =========================================================
  // GET ACTIVE AUTHENTICATION TOKEN
  // =========================================================

  static Future<String?> getToken() async {
    // First check the current app session.
    final String? currentToken = _sessionToken;

    if (currentToken != null && currentToken.isNotEmpty) {
      return currentToken;
    }

    // Restore only when Remember Me is enabled.
    final bool rememberMe = await isRememberMeEnabled();

    if (!rememberMe) {
      return null;
    }

    final String? savedToken = await _storage.read(key: _tokenKey);

    if (savedToken == null || savedToken.trim().isEmpty) {
      return null;
    }

    _sessionToken = savedToken;

    return savedToken;
  }

  // =========================================================
  // CHECK REMEMBER ME SETTING
  // =========================================================

  static Future<bool> isRememberMeEnabled() async {
    final String? preference = await _storage.read(key: _rememberMeKey);

    if (preference != null) {
      return preference == 'true';
    }

    // Backward compatibility:
    // Older versions stored tokens without an
    // explicit Remember Me preference.
    final String? existingToken = await _storage.read(key: _tokenKey);

    if (existingToken != null && existingToken.isNotEmpty) {
      await _storage.write(key: _rememberMeKey, value: 'true');

      return true;
    }

    // Default checkbox preference.
    return true;
  }

  // =========================================================
  // UPDATE REMEMBER ME
  // =========================================================

  static Future<void> setRememberMe(bool enabled) async {
    await _storage.write(
      key: _rememberMeKey,
      value: enabled ? 'true' : 'false',
    );

    if (!enabled) {
      // Delete persisted credentials.
      // Keep the active in-memory session until
      // the user explicitly logs out.
      await _storage.delete(key: _tokenKey);

      await _storage.delete(key: _rememberedLoginKey);

      await _storage.delete(key: _rememberedHomeRouteKey);
    }
  }

  // =========================================================
  // GET REMEMBERED LOGIN
  // =========================================================

  static Future<String?> getRememberedLogin() async {
    final bool enabled = await isRememberMeEnabled();

    if (!enabled) return null;

    return _storage.read(key: _rememberedLoginKey);
  }

  // =========================================================
  // GET REMEMBERED DASHBOARD ROUTE
  // =========================================================

  static Future<String?> getRememberedHomeRoute() async {
    final bool enabled = await isRememberMeEnabled();

    if (!enabled) return null;

    final String? route = await _storage.read(key: _rememberedHomeRouteKey);

    if (route == '/buyer-home' ||
        route == '/rider-home' ||
        route == '/logistics-home') {
      return route;
    }

    return null;
  }

  // =========================================================
  // ROLE-BASED DASHBOARD NAVIGATION
  // =========================================================

  static String homeRouteForUser(Map<String, dynamic> response) {
    final Map<dynamic, dynamic> user = _extractUser(response);

    final String role =
        (user['role'] ??
                user['user_type'] ??
                user['account_type'] ??
                user['type'] ??
                response['role'] ??
                '')
            .toString()
            .trim()
            .toLowerCase();

    switch (role) {
      case 'buyer':
      case 'customer':
        return '/buyer-home';

      case 'rider':
      case 'driver':
        return '/rider-home';

      case 'logistics':
      case 'courier':
        return '/logistics-home';

      default:
        throw Exception(
          'This account role is not supported '
          'by SukiShop Mobile.',
        );
    }
  }

  // =========================================================
  // EXTRACT USER DETAILS
  // =========================================================

  static Map<dynamic, dynamic> _extractUser(Map<String, dynamic> response) {
    if (response['user'] is Map) {
      return response['user'] as Map;
    }

    final dynamic data = response['data'];

    if (data is Map) {
      if (data['user'] is Map) {
        return data['user'] as Map;
      }

      return data;
    }

    return response;
  }

  // =========================================================
  // GET AUTHENTICATED USER
  // =========================================================

  static Future<Map<String, dynamic>> getUser() async {
    try {
      final String? token = await getToken();

      if (token == null || token.isEmpty) {
        throw Exception('Token expired. Please log in again.');
      }

      // Verify current token with Laravel.
      final dynamic response = await ApiServices.get('/user', token: token);

      final Map<String, dynamic> result = _parseResponse(response);

      // Reject an explicit API failure.
      if (result['success'] == false) {
        throw Exception(
          result['message']?.toString() ?? 'Unable to verify your account.',
        );
      }

      // Ensure a supported role exists.
      homeRouteForUser(result);

      return result;
    } catch (error) {
      throw _handleError(error);
    }
  }

  // =========================================================
  // CHECK IF USER HAS A TOKEN
  // =========================================================

  static Future<bool> isLoggedIn() async {
    try {
      final String? token = await getToken();

      return token != null && token.isNotEmpty;
    } catch (_) {
      return false;
    }
  }

  // =========================================================
  // VALIDATE SESSION WITH SERVER
  // =========================================================

  static Future<bool> validateSession() async {
    try {
      final Map<String, dynamic> user = await getUser();

      homeRouteForUser(user);

      return true;
    } catch (_) {
      return false;
    }
  }

  // =========================================================
  // CLEAR LOCAL AUTHENTICATION TOKEN
  // =========================================================

  static Future<void> clearToken() async {
    _sessionToken = null;

    await _storage.delete(key: _tokenKey);

    await _storage.delete(key: _rememberedHomeRouteKey);
  }

  // =========================================================
  // CLEAR LOCAL SESSION DATA
  // =========================================================

  static Future<void> _clearLocalSession() async {
    _sessionToken = null;

    await _storage.delete(key: _tokenKey);

    await _storage.delete(key: _rememberedLoginKey);

    await _storage.delete(key: _rememberedHomeRouteKey);
  }

  // =========================================================
  // LOGOUT
  // =========================================================

  static Future<void> logout() async {
    try {
      final String? token = await getToken();

      if (token != null && token.isNotEmpty) {
        // Revoke token from Laravel when reachable.
        await ApiServices.post('/logout', <String, dynamic>{}, token: token);
      }
    } catch (_) {
      // If the API is unavailable, still clear the
      // local session so the user can sign out.
      //
      // Important:
      // Server-side token revocation is not guaranteed
      // when this request fails.
    } finally {
      await _clearLocalSession();
    }
  }

  // =========================================================
  // AUTHENTICATION ERROR HANDLER
  // =========================================================

  static Exception _handleError(Object error) {
    String message = error.toString().trim();

    if (message.startsWith('Exception: ')) {
      message = message.substring(11);
    }

    final String lower = message.toLowerCase();

    // Expired or invalid session.
    if (lower.contains('unauthenticated') ||
        lower.contains('session has expired') ||
        lower.contains('token expired') ||
        lower.contains('invalid token') ||
        lower.contains('token revoked') ||
        lower.contains('401')) {
      return Exception(
        'Token expired or invalid. '
        'Please log in again.',
      );
    }

    // Network errors.
    if (lower.contains('socketexception') ||
        lower.contains('failed host lookup') ||
        lower.contains('connection refused') ||
        lower.contains('cannot connect') ||
        lower.contains('network error')) {
      return Exception(
        'Unable to connect to SUKI server. '
        'Please check your internet connection.',
      );
    }

    // Timeout errors.
    if (lower.contains('timeout') || lower.contains('timed out')) {
      return Exception('Connection timed out. Please try again.');
    }

    // Invalid login credentials.
    if (lower.contains('invalid credentials') ||
        lower.contains('incorrect password') ||
        lower.contains('invalid login')) {
      return Exception('Incorrect email/phone or password.');
    }

    if (message.isEmpty) {
      return Exception('Something went wrong. Please try again.');
    }

    // Preserve Laravel validation and business errors.
    return Exception(message);
  }
}
