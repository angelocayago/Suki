import 'package:flutter/material.dart';

import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';

class RiderScannerPage extends StatefulWidget {
  const RiderScannerPage({super.key});

  @override
  State<RiderScannerPage> createState() => _RiderScannerPageState();
}

class _RiderScannerPageState extends State<RiderScannerPage> {
  final TextEditingController _trackingCode = TextEditingController();
  Map<String, dynamic>? _result;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _trackingCode.dispose();
    super.dispose();
  }

  String _text(dynamic value, [String fallback = '—']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  Future<void> _resolve() async {
    if (_trackingCode.text.trim().isEmpty || _busy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
      _result = null;
    });
    try {
      final data = await RiderService.resolveTrackingCode(_trackingCode.text);
      if (mounted) setState(() => _result = data);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _runAction(String action) async {
    final shipment = _result?['shipment'];
    final shipmentId = shipment is Map ? shipment['id']?.toString() ?? '' : '';
    if (shipmentId.isEmpty || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final updated = await RiderService.scannerAction(shipmentId, action);
      if (mounted) setState(() => _result = updated);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final shipment = _result?['shipment'];
    final shipmentData = shipment is Map ? Map<String, dynamic>.from(shipment) : null;
    final allowedAction = _text(_result?['allowed_action'], '');

    return Scaffold(
      backgroundColor: RiderStyle.background,
      appBar: AppBar(title: Text('Scan Parcel', style: RiderStyle.appTitle), centerTitle: true),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.qr_code_scanner, size: 42),
                  const SizedBox(height: 12),
                  const Text('Enter the parcel tracking code'),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _trackingCode,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                      labelText: 'Tracking code',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.confirmation_number_outlined),
                    ),
                    onSubmitted: (_) => _resolve(),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: _busy ? null : _resolve,
                      icon: _busy
                          ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.search),
                      label: const Text('LOOK UP PARCEL'),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: TextStyle(color: RiderStyle.danger)),
          ],
          if (shipmentData != null) ...[
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Order ${_text(shipmentData['order_number'])}', style: RiderStyle.cardTitle),
                    const SizedBox(height: 10),
                    Text('Tracking: ${_text(shipmentData['tracking_code'])}'),
                    Text('Status: ${_text(shipmentData['status']).replaceAll('_', ' ')}'),
                    Text('Pickup: ${_text(shipmentData['pickup_address'])}'),
                    Text('Destination: ${_text(shipmentData['address'])}'),
                    if (allowedAction.isNotEmpty) ...[
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: _busy ? null : () => _runAction(allowedAction),
                          child: Text(_label(allowedAction)),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _label(String action) => switch (action) {
        'accept_pickup' => 'Accept pickup',
        'confirm_pickup' => 'Confirm pickup',
        'accept_delivery' => 'Accept delivery',
        'begin_delivery' => 'Begin delivery',
        _ => action.replaceAll('_', ' ').toUpperCase(),
      };
}
