import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';

class DeliveryDetailsPage extends StatefulWidget {
  const DeliveryDetailsPage({super.key});

  @override
  State<DeliveryDetailsPage> createState() => _DeliveryDetailsPageState();
}

class _DeliveryDetailsPageState extends State<DeliveryDetailsPage> {
  final ImagePicker _imagePicker = ImagePicker();
  final TextEditingController _receivedBy = TextEditingController();
  final TextEditingController _deliveryNote = TextEditingController();
  Map<String, dynamic> _order = const {};
  XFile? _proofPhoto;
  bool _busy = false;
  bool _loadedArguments = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loadedArguments) return;
    _loadedArguments = true;
    final arguments = ModalRoute.of(context)?.settings.arguments;
    if (arguments is Map) {
      _order = arguments.map((key, value) => MapEntry(key.toString(), value));
      _receivedBy.text = _text(_order['customer_name'] ?? _order['buyer_name']);
    }
  }

  @override
  void dispose() {
    _receivedBy.dispose();
    _deliveryNote.dispose();
    super.dispose();
  }

  String _text(dynamic value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  String get _orderId => _text(_order['id']);
  String get _status => _text(_order['status']).toLowerCase();
  bool get _isCod => _text(_order['payment_method']).toLowerCase() == 'cod';

  Future<void> _pickProof() async {
    try {
      final photo = await _imagePicker.pickImage(
        source: ImageSource.camera,
        imageQuality: 82,
        maxWidth: 1800,
      );
      if (photo != null && mounted) setState(() => _proofPhoto = photo);
    } catch (error) {
      if (mounted) _showMessage(error.toString().replaceFirst('Exception: ', ''));
    }
  }

  Future<void> _updateStatus(String status) async {
    if (_orderId.isEmpty || _busy) return;
    await _perform(() => RiderService.updateStatus(_orderId, status));
  }

  Future<void> _submitProof() async {
    if (_orderId.isEmpty || _busy) return;
    if (_proofPhoto == null) {
      _showMessage('Take a delivery proof photo first.');
      return;
    }
    if (_receivedBy.text.trim().isEmpty) {
      _showMessage('Enter the name of the person who received the parcel.');
      return;
    }
    if (_isCod) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Confirm COD collection'),
          content: const Text('Confirm that you collected the cash-on-delivery amount before completing this delivery.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Confirm collected')),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }

    final photo = _proofPhoto!;
    await _perform(() async {
      final bytes = await photo.readAsBytes();
      final response = await RiderService.submitDeliveryProof(
        sellerOrderId: _orderId,
        receivedBy: _receivedBy.text,
        imageBytes: bytes,
        fileName: photo.name,
        note: _deliveryNote.text,
        codCollected: _isCod,
      );
      if (mounted) {
        final returnedOrder = response['order'];
        if (returnedOrder is Map) {
          setState(() => _order = returnedOrder.map((key, value) => MapEntry(key.toString(), value)));
        } else {
          setState(() => _order = {..._order, 'status': 'delivered'});
        }
      }
    }, successMessage: 'Delivery proof saved and delivery completed.');
  }

  Future<void> _recordFailure() async {
    const reasons = <String, String>{
      'buyer_unavailable': 'Buyer unavailable',
      'buyer_unreachable': 'Buyer unreachable',
      'incorrect_address': 'Incorrect address',
      'access_issue': 'Access issue',
      'buyer_requested_reschedule': 'Buyer requested reschedule',
      'buyer_refused': 'Buyer refused parcel',
      'other': 'Other',
    };
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: const Text('Record failed attempt'),
        children: reasons.entries
            .map((entry) => SimpleDialogOption(
                  onPressed: () => Navigator.pop(context, entry.key),
                  child: Text(entry.value),
                ))
            .toList(),
      ),
    );
    if (reason == null || !mounted) return;
    await _perform(() => RiderService.recordFailedDelivery(_orderId, reason: reason));
  }

  Future<void> _recordIssue() async {
    const categories = <String, String>{
      'buyer_unreachable': 'Buyer unreachable',
      'wrong_or_incomplete_address': 'Wrong or incomplete address',
      'damaged_parcel': 'Damaged parcel',
      'other': 'Other',
    };
    final category = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: const Text('Report a delivery issue'),
        children: categories.entries
            .map((entry) => SimpleDialogOption(
                  onPressed: () => Navigator.pop(context, entry.key),
                  child: Text(entry.value),
                ))
            .toList(),
      ),
    );
    if (category == null || !mounted) return;
    await _perform(() => RiderService.recordIssue(_orderId, category: category));
  }

  Future<void> _perform(
    Future<void> Function() action, {
    String successMessage = 'Delivery updated.',
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) {
        _showMessage(successMessage);
        Navigator.pop(context, true);
      }
    } catch (error) {
      if (mounted) _showMessage(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final canBegin = _status == 'assigned_to_rider';
    final canArriveAtSorting = _status == 'picked_up';
    final canDeliver = _status == 'out_for_delivery';
    final address = _text(_order['navigation_address'] ?? _order['address'], 'Address unavailable');

    return Scaffold(
      backgroundColor: RiderStyle.background,
      appBar: AppBar(title: Text('Delivery Details', style: RiderStyle.appTitle)),
      body: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Order ${_text(_order['order_number'], _orderId)}', style: RiderStyle.cardTitle),
                  const SizedBox(height: 10),
                  Text('Status: ${_status.replaceAll('_', ' ').toUpperCase()}'),
                  Text('Buyer: ${_text(_order['buyer_name'] ?? _order['customer_name'], '—')}'),
                  Text('Phone: ${_text(_order['contact'], '—')}'),
                  Text('Pickup: ${_text(_order['pickup_address'], '—')}'),
                  Text('Destination: $address'),
                  Text('Payment: ${_text(_order['payment_method'], '—').toUpperCase()}'),
                ],
              ),
            ),
          ),
          if (canBegin) ...[
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : () => _updateStatus('OUT_FOR_DELIVERY'),
              child: const Text('START DELIVERY'),
            ),
          ],
          if (canArriveAtSorting) ...[
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : () => _updateStatus('AT_SORTING_CENTER'),
              child: const Text('ARRIVED AT SORTING CENTER'),
            ),
          ],
          if (canDeliver) ...[
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Delivery confirmation', style: RiderStyle.sectionTitle),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _receivedBy,
                      decoration: const InputDecoration(labelText: 'Received by', border: OutlineInputBorder()),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _deliveryNote,
                      minLines: 2,
                      maxLines: 4,
                      decoration: const InputDecoration(labelText: 'Delivery note (optional)', border: OutlineInputBorder()),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: _busy ? null : _pickProof,
                      icon: const Icon(Icons.camera_alt_outlined),
                      label: Text(_proofPhoto == null ? 'Take proof photo' : 'Retake proof photo'),
                    ),
                    if (_proofPhoto != null) ...[
                      const SizedBox(height: 6),
                      Text('Selected: ${_proofPhoto!.name}'),
                    ],
                    if (_isCod) ...[
                      const SizedBox(height: 6),
                      const Text('The app will ask you to confirm COD collection when you complete delivery.'),
                    ],
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _busy ? null : _submitProof,
                        child: _busy
                            ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('MARK AS DELIVERED'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _busy ? null : _recordFailure,
              icon: const Icon(Icons.event_busy_outlined),
              label: const Text('Record failed attempt'),
            ),
            OutlinedButton.icon(
              onPressed: _busy ? null : _recordIssue,
              icon: const Icon(Icons.report_problem_outlined),
              label: const Text('Report delivery issue'),
            ),
          ],
        ],
      ),
    );
  }
}
