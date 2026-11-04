import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class LogisticsStyle {
  // =========================
  // COLORS
  // =========================

  static const Color primary = AppTheme.forestGreen;

  static Color get primaryDark =>
      AppTheme.isDark ? AppTheme.accentGreen : AppTheme.forestGreenDark;

  static Color get background => AppTheme.background;

  static Color get card => AppTheme.surface;

  static Color get textDark => AppTheme.text;

  static Color get textGray => AppTheme.mutedText;

  static Color get border => AppTheme.border;
  static Color get successBackground => AppTheme.softGreen;

  static Color get success => AppTheme.isDark
      ? AppTheme.accentGreen
      : const Color(0xFF16A34A);

  static const Color warning = Color(0xFFF59E0B);

  static const Color danger = Color(0xFFDC2626);

  // =========================
  // TEXT
  // =========================

  static TextStyle get appTitle => TextStyle(
    fontSize: 20,

    fontWeight: FontWeight.w800,

    color: primaryDark,
  );

  static TextStyle get greeting => TextStyle(fontSize: 12, color: textGray);

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 16,

    fontWeight: FontWeight.w700,

    color: textDark,
  );

  static TextStyle get cardTitle => TextStyle(
    fontSize: 13,

    fontWeight: FontWeight.w700,

    color: textDark,
  );

  static TextStyle get normalText => TextStyle(fontSize: 12, color: textGray);

  // =========================
  // CARD
  // =========================

  static BoxDecoration cardDecoration() {
    return BoxDecoration(
      color: card,

      borderRadius: BorderRadius.circular(15),

      border: Border.all(color: border),

      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.04),

          blurRadius: 6,

          offset: const Offset(0, 2),
        ),
      ],
    );
  }

  // =========================
  // BUTTON
  // =========================

  static ButtonStyle primaryButton() {
    return ElevatedButton.styleFrom(
      backgroundColor: primary,

      foregroundColor: Colors.white,

      elevation: 0,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    );
  }
}
