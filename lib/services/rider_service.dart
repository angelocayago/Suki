import 'api_services.dart';
import 'auth_service.dart';

/// Authenticated mobile client for the Laravel rider API.
class RiderService {
  RiderService._();

  static Future<String> _token() async {
    final value = await AuthService.getToken();
    if (value == null || value.trim().isEmpty) {
      throw Exception('Please sign in to your rider account.');
    }
    return value.trim();
  }

  static Map<String, dynamic> _map(dynamic value) {
    if (value is! Map) {
      throw const FormatException('Invalid rider API response.');
    }
    return value.map((key, item) => MapEntry(key.toString(), item));
  }

  static Map<String, dynamic> _data(dynamic response) {
    final root = _map(response);
    if (root['success'] == false) {
      throw Exception(root['message']?.toString() ?? 'Rider request failed.');
    }
    final payload = root['data'];
    return payload is Map ? _map(payload) : root;
  }

  static List<Map<String, dynamic>> _items(dynamic value) {
    if (value is! List) return const [];
    return value.map(_map).toList(growable: false);
  }

  static Future<Map<String, dynamic>> getDashboard() async {
    final response = await ApiServices.get('/rider/dashboard', token: await _token());
    return _data(response);
  }

  static Future<List<Map<String, dynamic>>> getDeliveries() async {
    final response = await ApiServices.get('/rider/deliveries', token: await _token());
    final data = _data(response);
    return _items(data['orders'] ?? data['deliveries'] ?? data['data']);
  }

  static Future<List<Map<String, dynamic>>> getHistory() async {
    final response = await ApiServices.get('/rider/history', token: await _token());
    final data = _data(response);
    return _items(data['orders'] ?? data['deliveries'] ?? data['data']);
  }

  static Future<Map<String, dynamic>> getPerformance() async {
    final response = await ApiServices.get('/rider/earnings', token: await _token());
    return _data(response);
  }

  static Future<Map<String, dynamic>> getPageData(String endpoint) async {
    final response = await ApiServices.get(endpoint, token: await _token());
    return _data(response);
  }

  static Future<Map<String, dynamic>> getProfile() async {
    return getPageData('/rider/profile');
  }

  static Future<Map<String, dynamic>> updateProfile(
    Map<String, dynamic> changes,
  ) async {
    final response = await ApiServices.patch(
      '/rider/profile',
      changes,
      token: await _token(),
    );
    return _data(response);
  }

  static Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final response = await ApiServices.post(
      '/rider/profile/password',
      {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      },
      token: await _token(),
    );
    _data(response);
  }

  static Future<void> submitRemittance(
    String remittanceId, {
    required String reference,
  }) async {
    await ApiServices.post('/rider/cod-remittances/$remittanceId', {
      'reference': reference.trim(),
    }, token: await _token());
  }

  static Future<Map<String, dynamic>> updateAvailability(bool online) async {
    final response = await ApiServices.post('/rider/availability', {
      'availability': online ? 'ONLINE' : 'OFFLINE',
    }, token: await _token());
    return _data(response);
  }

  static Future<void> acceptPickup(String sellerOrderId) =>
      _action('/rider/erp-orders/$sellerOrderId/accept-pickup');

  static Future<void> declinePickup(String sellerOrderId) =>
      _action('/rider/erp-orders/$sellerOrderId/decline-pickup');

  static Future<void> acceptDelivery(String sellerOrderId) =>
      _action('/rider/erp-orders/$sellerOrderId/accept-delivery');

  static Future<void> declineDelivery(String sellerOrderId) =>
      _action('/rider/erp-orders/$sellerOrderId/decline-delivery');

  static Future<void> _action(String endpoint) async {
    await ApiServices.post(endpoint, const {}, token: await _token());
  }

  static Future<void> updateStatus(String sellerOrderId, String status) async {
    await ApiServices.post('/rider/erp-orders/$sellerOrderId/status', {
      'status': status,
    }, token: await _token());
  }

  static Future<void> recordFailedDelivery(
    String sellerOrderId, {
    required String reason,
    String notes = '',
  }) async {
    await ApiServices.post('/rider/erp-orders/$sellerOrderId/failed-delivery', {
      'failure_reason': reason,
      if (notes.trim().isNotEmpty) 'failure_notes': notes.trim(),
    }, token: await _token());
  }

  static Future<void> recordIssue(
    String sellerOrderId, {
    required String category,
    String details = '',
  }) async {
    await ApiServices.post('/rider/erp-orders/$sellerOrderId/delivery-issue', {
      'issue_category': category,
      if (details.trim().isNotEmpty) 'issue_details': details.trim(),
    }, token: await _token());
  }

  static Future<Map<String, dynamic>> resolveTrackingCode(String code) async {
    final response = await ApiServices.post('/rider/shipment-scanner/resolve', {
      'tracking_code': code.trim(),
    }, token: await _token());
    return _data(response);
  }

  static Future<Map<String, dynamic>> scannerAction(
    String shipmentId,
    String action,
  ) async {
    final response = await ApiServices.post(
      '/rider/shipment-scanner/$shipmentId/action',
      {'action': action},
      token: await _token(),
    );
    return _data(response);
  }

  static Future<Map<String, dynamic>> submitDeliveryProof({
    required String sellerOrderId,
    required String receivedBy,
    required List<int> imageBytes,
    required String fileName,
    String note = '',
    bool codCollected = false,
  }) async {
    final response = await ApiServices.postMultipart(
      '/rider/erp-orders/$sellerOrderId/delivery-proof',
      fields: {
        'received_by': receivedBy.trim(),
        if (note.trim().isNotEmpty) 'delivery_note': note.trim(),
        if (codCollected) 'cod_collected': '1',
      },
      fileField: 'proof_photo',
      fileName: fileName,
      fileBytes: imageBytes,
      token: await _token(),
    );
    return _data(response);
  }
}
