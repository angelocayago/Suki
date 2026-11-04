
import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class BuyerProfileStyle {
  BuyerProfileStyle._();

  // COLORS
  static const Color primary = AppTheme.forestGreen;
  static const Color darkGreen = AppTheme.forestGreenDark;
  static Color get lightGreen => AppTheme.softGreen;
  static Color get background => AppTheme.background;
  static Color get textPrimary => AppTheme.text;
  static Color get textSecondary => AppTheme.mutedText;
  static Color get border => AppTheme.border;
  static Color get white => AppTheme.surface;

  // SIZING
  static const double horizontalPadding = 16;
  static const double cardRadius = 16;

  // TEXT STYLES
  static TextStyle get pageTitle => TextStyle(
    fontSize: 23,
    fontWeight: FontWeight.w800,
    color: textPrimary,
  );

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w700,
    color: textPrimary,
  );

  static TextStyle get subtitle => TextStyle(
    fontSize: 11,
    color: textSecondary,
    height: 1.5,
  );

  static TextStyle get label => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: textSecondary,
  );

  static TextStyle get value => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: textPrimary,
  );

  // CARD DECORATION
  static BoxDecoration get cardDecoration => BoxDecoration(
    color: white,
    borderRadius: BorderRadius.circular(cardRadius),
    border: Border.all(color: border),
  );

  static BoxDecoration get iconDecoration => BoxDecoration(
    color: lightGreen,
    borderRadius: BorderRadius.circular(10),
  );

  // PRIMARY BUTTON
  static ButtonStyle get primaryButton => ElevatedButton.styleFrom(
    backgroundColor: primary,
    foregroundColor: white,
    elevation: 0,
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 13,
    ),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
  );

  // OUTLINED BUTTON
  static ButtonStyle get outlinedButton => OutlinedButton.styleFrom(
    foregroundColor: AppTheme.brandText,
    side: BorderSide(color: AppTheme.brandText),
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 12,
    ),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    ),
  );

  // INPUT DECORATION
  static InputDecoration inputDecoration({
    required String hint,
    IconData? icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(
        fontSize: 12,
        color: textSecondary,
      ),
      prefixIcon: icon == null
          ? null
          : Icon(
              icon,
              size: 18,
              color: textSecondary,
            ),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: white,
      contentPadding: const EdgeInsets.symmetric(
        horizontal: 14,
        vertical: 13,
      ),
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
          width: 1.4,
        ),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: Colors.red),
      ),
    );
  }
}
