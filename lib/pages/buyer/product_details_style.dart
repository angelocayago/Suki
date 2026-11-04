
import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class ProductDetailsStyle {
  ProductDetailsStyle._();

  // COLORS
  static const Color primary = AppTheme.forestGreen;
  static const Color darkGreen = AppTheme.forestGreenDark;
  static Color get lightGreen => AppTheme.softGreen;
  static Color get background => AppTheme.background;
  static const Color accent = Color(0xFFF59E0B);

  static Color get textPrimary => AppTheme.text;
  static Color get textSecondary => AppTheme.mutedText;
  static Color get border => AppTheme.border;
  static Color get white => AppTheme.surface;
  static const Color error = Color(0xFFE5484D);

  // SIZING
  static const double horizontalPadding = 15;
  static const double cardRadius = 15;

  // TEXT STYLES
  static TextStyle get pageTitle => TextStyle(
    fontSize: 22,
    fontWeight: FontWeight.w800,
    color: textPrimary,
  );

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w800,
    color: textPrimary,
  );

  static TextStyle get productName => TextStyle(
    fontSize: 21,
    fontWeight: FontWeight.w800,
    color: textPrimary,
    height: 1.3,
  );

  static TextStyle get price => TextStyle(
    fontSize: 27,
    fontWeight: FontWeight.w900,
    color: AppTheme.brandText,
  );

  static TextStyle get label => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: textSecondary,
  );

  static TextStyle get body => TextStyle(
    fontSize: 12,
    color: textSecondary,
    height: 1.6,
  );

  // CARD DECORATION
  static BoxDecoration get cardDecoration => BoxDecoration(
    color: white,
    borderRadius: BorderRadius.circular(cardRadius),
    border: Border.all(color: border),
  );

  static BoxDecoration get priceDecoration => BoxDecoration(
    color: background,
    borderRadius: BorderRadius.circular(12),
  );

  // PRIMARY BUTTON
  static ButtonStyle get primaryButton => ElevatedButton.styleFrom(
    backgroundColor: primary,
    foregroundColor: white,
    elevation: 0,
    minimumSize: Size(0, 48),
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 12,
    ),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
  );

  // OUTLINED BUTTON
  static ButtonStyle get outlinedButton => OutlinedButton.styleFrom(
    foregroundColor: AppTheme.brandText,
    minimumSize: Size(0, 48),
    side: BorderSide(
      color: AppTheme.brandText,
      width: 1.5,
    ),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
  );

  // INPUT DECORATION
  static InputDecoration inputDecoration({
    required String hint,
  }) {
    return InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: white,
      contentPadding: const EdgeInsets.all(13),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(color: border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
      borderSide: BorderSide(color: border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(
          color: AppTheme.brandText,
          width: 1.5,
        ),
      ),
    );
  }
}
