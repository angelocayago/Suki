import 'package:flutter/material.dart';

import '../../services/rider_service.dart';

class RiderApiPage extends StatefulWidget {
  const RiderApiPage({
    super.key,
    required this.title,
    required this.endpoint,
    this.recordsKey,
    this.codRemittances = false,
  });

  final String title;
  final String endpoint;
  final String? recordsKey;
  final bool codRemittances;

  @override
  State<RiderApiPage> createState() => _RiderApiPageState();
}

class _RiderApiPageState extends State<RiderApiPage> {
  late Future<Map<String, dynamic>> _data;
  final Set<String> _submitting = {};

  @override
  void initState() {
    super.initState();
    _data = RiderService.getPageData(widget.endpoint);
  }

  Future<void> _reload() async {
    final request = RiderService.getPageData(widget.endpoint);
    setState(() => _data = request);
    try {
      await request;
    } catch (_) {}
  }

  List<Map<String, dynamic>> _records(Map<String, dynamic> data) {
    final key = widget.recordsKey ??
        (widget.codRemittances ? 'remittances' : 'orders');
    final value = data[key] ?? data['data'];
    if (value is! List) return const [];
    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  String _value(dynamic value) {
    if (value == null) return '—';
    if (value is Map || value is List) return '';
    return value.toString();
  }

  Future<void> _submit(Map<String, dynamic> row) async {
    final id = row['id']?.toString() ?? '';
    if (id.isEmpty || _submitting.contains(id)) return;
    final reference = TextEditingController();
    final form = await showDialog<Map<String, String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Submit COD remittance'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: reference, decoration: const InputDecoration(labelText: 'Payment reference (optional)')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(context, {'reference': reference.text}),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    reference.dispose();
    if (form == null || !mounted) return;
    setState(() => _submitting.add(id));
    try {
      await RiderService.submitRemittance(
        id,
        reference: form['reference'] ?? '',
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Remittance submitted.')));
        await _reload();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.toString().replaceFirst('Exception: ', ''))),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: Text(widget.title),
          actions: [IconButton(onPressed: _reload, icon: const Icon(Icons.refresh))],
        ),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _data,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return _empty(snapshot.error.toString().replaceFirst('Exception: ', ''), retry: true);
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            final records = _records(data);
            final summary = data['summary'] is Map
                ? Map<String, dynamic>.from(data['summary'] as Map)
                : const <String, dynamic>{};
            if (records.isEmpty && summary.isEmpty) return _empty('No ${widget.title.toLowerCase()} to show.');
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  if (summary.isNotEmpty) ...[
                    ...summary.entries.where((e) => _value(e.value).isNotEmpty).map(
                          (entry) => Card(
                            child: ListTile(
                              title: Text(_label(entry.key)),
                              trailing: Text(_value(entry.value)),
                            ),
                          ),
                        ),
                    const SizedBox(height: 8),
                  ],
                  ...records.map(_recordCard),
                  if (records.isEmpty && summary.isNotEmpty)
                    const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('No records yet.', textAlign: TextAlign.center),
                    ),
                ],
              ),
            );
          },
        ),
      );

  Widget _recordCard(Map<String, dynamic> row) {
    final title = row['order_number']?.toString() ??
        row['reference']?.toString() ??
        row['id']?.toString() ??
        widget.title;
    final excluded = {'id', 'order_number', 'reference', 'items', 'shipment'};
    final fields = row.entries
        .where((entry) => !excluded.contains(entry.key) && _value(entry.value).isNotEmpty)
        .take(6)
        .toList();
    final canSubmit = widget.codRemittances &&
        (row['status']?.toString().toUpperCase() == 'OUTSTANDING');
    final id = row['id']?.toString() ?? '';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 6),
            ...fields.map((entry) => Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text('${_label(entry.key)}: ${_value(entry.value)}'),
                )),
            if (canSubmit) ...[
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _submitting.contains(id) ? null : () => _submit(row),
                  child: Text(_submitting.contains(id) ? 'Submitting…' : 'Submit remittance'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _label(String key) => key
      .replaceAllMapped(RegExp(r'([a-z])([A-Z])'), (match) => '${match[1]} ${match[2]}')
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');

  Widget _empty(String message, {bool retry = false}) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(message, textAlign: TextAlign.center),
              if (retry) ...[
                const SizedBox(height: 12),
                FilledButton(onPressed: _reload, child: const Text('Try again')),
              ],
            ],
          ),
        ),
      );
}
