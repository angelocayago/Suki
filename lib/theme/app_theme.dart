import 'package:flutter/material.dart';

class AppTheme {
  static final ThemeData lightTheme = ThemeData(
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF167561),
      brightness: Brightness.light,
    ),
    useMaterial3: true,
  );
}
