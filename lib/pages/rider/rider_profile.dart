import 'package:flutter/material.dart';

import '../../services/auth_service.dart';
import '../../services/rider_service.dart';
import '../../styles/rider_style.dart';
import '../../widgets/theme_toggle_button.dart';

class RiderProfilePage extends StatefulWidget {
  const RiderProfilePage({super.key});

  @override
  State<RiderProfilePage> createState() => _RiderProfilePageState();
}

class _RiderProfilePageState extends State<RiderProfilePage> {
  late Future<Map<String, dynamic>> _profile;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _profile = RiderService.getProfile();
  }

  Future<void> _reload() async {
    final request = RiderService.getProfile();
    setState(() => _profile = request);
    try {
      await request;
    } catch (_) {}
  }

  String _text(dynamic value, [String fallback = '—']) {
    final result = value?.toString().trim() ?? '';
    return result.isEmpty ? fallback : result;
  }

  Future<void> _edit(Map<String, dynamic> profile) async {
    final firstName = TextEditingController(text: _text(profile['first_name'], ''));
    final lastName = TextEditingController(text: _text(profile['last_name'], ''));
    final phone = TextEditingController(text: _text(profile['phone'], ''));
    final email = TextEditingController(text: _text(profile['email'], ''));
    final plate = TextEditingController(text: _text(profile['plate_number'], ''));
    final vehicle = TextEditingController(text: _text(profile['vehicle_type'], ''));
    final values = await showDialog<Map<String, String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Edit rider profile'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: firstName, decoration: const InputDecoration(labelText: 'First name')),
              TextField(controller: lastName, decoration: const InputDecoration(labelText: 'Last name')),
              TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone')),
              TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email')),
              TextField(controller: vehicle, decoration: const InputDecoration(labelText: 'Vehicle type')),
              TextField(controller: plate, decoration: const InputDecoration(labelText: 'Plate number')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(context, {
              'first_name': firstName.text.trim(),
              'last_name': lastName.text.trim(),
              'phone': phone.text.trim(),
              'email': email.text.trim(),
              'vehicle_type': vehicle.text.trim(),
              'plate_number': plate.text.trim(),
            }),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    firstName.dispose();
    lastName.dispose();
    phone.dispose();
    email.dispose();
    plate.dispose();
    vehicle.dispose();
    if (values == null || !mounted) return;
    await _perform(() => RiderService.updateProfile(values));
  }

  Future<void> _changePassword() async {
    final current = TextEditingController();
    final next = TextEditingController();
    final confirmation = TextEditingController();
    final values = await showDialog<List<String>>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change password'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: current, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
            TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'New password')),
            TextField(controller: confirmation, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm new password')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, [current.text, next.text, confirmation.text]), child: const Text('Update')),
        ],
      ),
    );
    current.dispose();
    next.dispose();
    confirmation.dispose();
    if (values == null || !mounted) return;
    if (values[1].length < 8 || values[1] != values[2]) {
      _message('Use at least 8 characters and make both new password fields match.');
      return;
    }
    await _perform(() => RiderService.changePassword(currentPassword: values[0], newPassword: values[1]));
  }

  Future<void> _perform(Future<dynamic> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) {
        _message('Profile saved.');
        await _reload();
      }
    } catch (error) {
      if (mounted) _message(error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() async {
    setState(() => _busy = true);
    try {
      await AuthService.logout();
    } catch (_) {}
    if (mounted) {
      Navigator.of(context).pushNamedAndRemoveUntil('/login', (route) => false);
    }
  }

  void _message(String value) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: RiderStyle.background,
        appBar: AppBar(
          title: Text('Rider Profile', style: RiderStyle.appTitle),
          actions: [const ThemeToggleButton(), IconButton(onPressed: _reload, icon: const Icon(Icons.refresh))],
        ),
        body: FutureBuilder<Map<String, dynamic>>(
          future: _profile,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(snapshot.error.toString().replaceFirst('Exception: ', ''), textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _reload, child: const Text('Try again')),
                    ],
                  ),
                ),
              );
            }
            final data = snapshot.data ?? const <String, dynamic>{};
            final profile = data['profile'] is Map
                ? Map<String, dynamic>.from(data['profile'] as Map)
                : data;
            return ListView(
              padding: const EdgeInsets.all(18),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        CircleAvatar(radius: 40, backgroundColor: RiderStyle.primary, child: const Icon(Icons.person, size: 42, color: Colors.white)),
                        const SizedBox(height: 10),
                        Text('${_text(profile['first_name'], '')} ${_text(profile['last_name'], '')}'.trim(), style: RiderStyle.cardTitle),
                        Text('Rider ID: ${_text(profile['rider_id'])}'),
                      ],
                    ),
                  ),
                ),
                _section('Personal information', [
                  _info(Icons.phone_outlined, 'Phone', profile['phone']),
                  _info(Icons.email_outlined, 'Email', profile['email']),
                  _info(Icons.verified_user_outlined, 'Account', profile['status']),
                ]),
                _section('Vehicle information', [
                  _info(Icons.two_wheeler_outlined, 'Vehicle type', profile['vehicle_type']),
                  _info(Icons.confirmation_number_outlined, 'Plate number', profile['plate_number']),
                  _info(Icons.badge_outlined, 'License status', profile['license_status']),
                ]),
                const SizedBox(height: 8),
                FilledButton.icon(
                  onPressed: _busy ? null : () => _edit(profile),
                  icon: const Icon(Icons.edit_outlined),
                  label: const Text('Edit profile'),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _changePassword,
                  icon: const Icon(Icons.lock_outline),
                  label: const Text('Change password'),
                ),
                OutlinedButton.icon(
                  onPressed: _busy ? null : _logout,
                  icon: const Icon(Icons.logout),
                  label: const Text('Log out'),
                ),
              ],
            );
          },
        ),
      );

  Widget _section(String title, List<Widget> children) => Padding(
        padding: const EdgeInsets.only(top: 18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: RiderStyle.sectionTitle),
            const SizedBox(height: 8),
            Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(children: children))),
          ],
        ),
      );

  Widget _info(IconData icon, String title, dynamic value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Icon(icon, size: 18, color: RiderStyle.primary),
            const SizedBox(width: 10),
            Expanded(child: Text(title)),
            Flexible(child: Text(_text(value), textAlign: TextAlign.end)),
          ],
        ),
      );
}
