import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'theme_controller.dart';

class AppTheme {
  static const Color forestGreen = Color(0xFF075F3E);
  static const Color forestGreenDark = Color(0xFF06442F);
  static const Color accentGreen = Color(0xFF8FDDA9);

  static const Color lightBackground = Color(0xFFFBF8F1);
  static const Color lightSurface = Colors.white;
  static const Color lightSurfaceMuted = Color(0xFFF6F1E7);
  static const Color lightSoftGreen = Color(0xFFEDF4EF);
  static const Color lightText = Color(0xFF111814);
  static const Color lightMutedText = Color(0xFF6E7772);
  static const Color lightBorder = Color(0xFFE6E9E6);

  static const Color darkBackground = Color(0xFF101713);
  static const Color darkSurface = Color(0xFF151E19);
  static const Color darkSurfaceMuted = Color(0xFF17201B);
  static const Color darkSoftGreen = Color(0xFF1C3328);
  static const Color darkText = Color(0xFFEDF4EF);
  static const Color darkMutedText = Color(0xFFA7B3AC);
  static const Color darkBorder = Color(0xFF324039);

  static bool get isDark => ThemeController.isDark;

  static Color get background => isDark ? darkBackground : lightBackground;
  static Color get surface => isDark ? darkSurface : lightSurface;
  static Color get surfaceMuted =>
      isDark ? darkSurfaceMuted : lightSurfaceMuted;
  static Color get softGreen => isDark ? darkSoftGreen : lightSoftGreen;
  static Color get text => isDark ? darkText : lightText;
  static Color get mutedText => isDark ? darkMutedText : lightMutedText;
  static Color get border => isDark ? darkBorder : lightBorder;
  static Color get brandText => isDark ? accentGreen : forestGreenDark;

  static SystemUiOverlayStyle get systemOverlayStyle => SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
    statusBarIconBrightness: isDark ? Brightness.light : Brightness.dark,
    systemNavigationBarColor: isDark ? darkBackground : lightBackground,
    systemNavigationBarIconBrightness:
        isDark ? Brightness.light : Brightness.dark,
  );

  static final ThemeData lightTheme = _buildTheme(Brightness.light);
  static final ThemeData darkTheme = _buildTheme(Brightness.dark);

  static ThemeData _buildTheme(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final colors = ColorScheme.fromSeed(
      seedColor: forestGreen,
      brightness: brightness,
    ).copyWith(
      primary: isDark ? accentGreen : forestGreen,
      onPrimary: isDark ? darkBackground : Colors.white,
      secondary: isDark ? accentGreen : forestGreenDark,
      onSecondary: isDark ? darkBackground : Colors.white,
      tertiary: isDark ? accentGreen : lightSoftGreen,
      surface: isDark ? darkSurface : lightSurface,
      onSurface: isDark ? darkText : lightText,
      outline: isDark ? darkBorder : lightBorder,
      error: const Color(0xFFB42318),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: colors,
      scaffoldBackgroundColor: isDark ? darkBackground : lightBackground,
      dividerColor: isDark ? darkBorder : lightBorder,
      appBarTheme: AppBarTheme(
        backgroundColor: isDark ? darkBackground : lightBackground,
        foregroundColor: isDark ? darkText : lightText,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarBrightness: isDark ? Brightness.dark : Brightness.light,
          statusBarIconBrightness:
              isDark ? Brightness.light : Brightness.dark,
          systemNavigationBarColor: isDark ? darkBackground : lightBackground,
          systemNavigationBarIconBrightness:
              isDark ? Brightness.light : Brightness.dark,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? darkSurfaceMuted : lightSurface,
        hintStyle: TextStyle(color: isDark ? darkMutedText : lightMutedText),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: isDark ? darkBorder : lightBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: isDark ? darkBorder : lightBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(
            color: isDark ? accentGreen : forestGreen,
            width: 1.5,
          ),
        ),
      ),
    );
  }
}
