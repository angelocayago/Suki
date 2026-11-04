import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ThemeController {
  ThemeController._();

  static const FlutterSecureStorage _storage = FlutterSecureStorage();
  static const String _storageKey = 'suki_theme_mode';

  static final ValueNotifier<ThemeMode> mode =
      ValueNotifier<ThemeMode>(ThemeMode.system);

  static bool get isDark {
    switch (mode.value) {
      case ThemeMode.dark:
        return true;
      case ThemeMode.light:
        return false;
      case ThemeMode.system:
        return WidgetsBinding.instance.platformDispatcher.platformBrightness ==
            Brightness.dark;
    }
  }

  static Future<void> load() async {
    try {
      final savedMode = await _storage.read(key: _storageKey);
      if (savedMode == 'dark') {
        mode.value = ThemeMode.dark;
      } else if (savedMode == 'light') {
        mode.value = ThemeMode.light;
      }
    } catch (_) {
      // Keep the system appearance if secure storage is unavailable.
    }
  }

  static Future<void> toggle() async {
    await setMode(isDark ? ThemeMode.light : ThemeMode.dark);
  }

  static Future<void> setMode(ThemeMode value) async {
    mode.value = value;
    try {
      if (value == ThemeMode.system) {
        await _storage.delete(key: _storageKey);
      } else {
        await _storage.write(
          key: _storageKey,
          value: value == ThemeMode.dark ? 'dark' : 'light',
        );
      }
    } catch (_) {
      // The theme still changes for the current app session.
    }
  }
}
