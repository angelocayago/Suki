import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class BuyerStyle {
  // =========================
  // COLORS
  // =========================

  static const Color primary = AppTheme.forestGreen;

  static Color get primaryDark =>
      AppTheme.isDark ? AppTheme.accentGreen : AppTheme.forestGreenDark;

  static Color get background => AppTheme.background;

  static Color get textDark => AppTheme.text;

  static Color get textGray => AppTheme.mutedText;

  static Color get border => AppTheme.border;

  static Color get card => AppTheme.surface;

  // =========================
  // TEXT STYLE
  // =========================

  static TextStyle get appTitle => TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w800,
    color: primaryDark,
  );

  static TextStyle get greeting => TextStyle(fontSize: 13, color: textGray);

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    color: textDark,
  );

  static TextStyle get productName => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: textDark,
  );

  static TextStyle get price => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w800,
    color: AppTheme.brandText,
  );

  // =========================
  // SEARCH FIELD
  // =========================

  static InputDecoration searchDecoration() {
    return InputDecoration(
      hintText: "Search products...",

      hintStyle: TextStyle(fontSize: 12, color: textGray),

      prefixIcon: Icon(Icons.search, color: textGray, size: 20),

      filled: true,

      fillColor: card,

      contentPadding: const EdgeInsets.symmetric(vertical: 12),

      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),

        borderSide: BorderSide.none,
      ),
    );
  }

  // =========================
  // PRODUCT CARD
  // =========================

  static BoxDecoration productCard() {
    return BoxDecoration(
      color: card,

      borderRadius: BorderRadius.circular(14),

      border: Border.all(color: border),

      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.04),

          blurRadius: 5,

          offset: const Offset(0, 2),
        ),
      ],
    );
  }

  // =========================
  // CATEGORY CARD
  // =========================

  static BoxDecoration categoryCard() {
    return BoxDecoration(
      color: card,

      borderRadius: BorderRadius.circular(12),

      border: Border.all(color: border),
    );
  }

  // =========================
  // BANNER
  // =========================

  static BoxDecoration banner() {
    return BoxDecoration(
      color: primary,

      borderRadius: BorderRadius.circular(16),
    );
  }
}
