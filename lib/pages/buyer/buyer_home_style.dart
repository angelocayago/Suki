
import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class BuyerHomeStyle {
  BuyerHomeStyle._();

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

  // PAGE
  static const double horizontalPadding = 16;
  static const double sectionSpacing = 24;
  static const double cardRadius = 16;

  // TYPOGRAPHY
  static TextStyle get brand => TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w900,
    color: AppTheme.isDark ? AppTheme.accentGreen : darkGreen,
    letterSpacing: 0.5,
  );

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 18,
    fontWeight: FontWeight.w900,
    color: textPrimary,
  );

  static TextStyle get sectionSubtitle => TextStyle(
    fontSize: 12,
    color: textSecondary,
  );

  static TextStyle get eyebrow => TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.bold,
    letterSpacing: 1,
    color: AppTheme.brandText,
  );

  static TextStyle get productName => TextStyle(
    fontSize: 12,
    height: 1.4,
    color: AppTheme.text,
    fontWeight: FontWeight.w500,
  );

  static TextStyle get productPrice => TextStyle(
    color: AppTheme.brandText,
    fontWeight: FontWeight.w900,
    fontSize: 14,
  );

  // DECORATIONS
  static BoxDecoration get cardDecoration => BoxDecoration(
    color: white,
    borderRadius: BorderRadius.circular(cardRadius),
    border: Border.all(color: border),
  );

  static BoxDecoration get sectionDecoration => BoxDecoration(
    color: white,
    borderRadius: BorderRadius.circular(14),
    border: Border.all(color: border),
  );

  static BoxDecoration get promoDecoration => BoxDecoration(
    color: darkGreen,
    borderRadius: BorderRadius.circular(14),
  );

  static BoxDecoration get iconDecoration => BoxDecoration(
    color: lightGreen,
    shape: BoxShape.circle,
  );

  static BoxDecoration get heroDecoration => BoxDecoration(
    color: darkGreen,
    borderRadius: BorderRadius.circular(17),
  );

  // IMAGE PLACEHOLDER
  static Widget imagePlaceholder({
    double? width,
    double? height,
  }) {
    return Container(
      width: width,
      height: height,
      color: AppTheme.surfaceMuted,
      alignment: Alignment.center,
      child: Icon(
        Icons.image_outlined,
        color: textSecondary,
        size: 30,
      ),
    );
  }

  // IMAGE ERROR
  static Widget imageError() {
    return Container(
      color: AppTheme.surfaceMuted,
      alignment: Alignment.center,
      child: Icon(
        Icons.image_not_supported_outlined,
        color: textSecondary,
        size: 30,
      ),
    );
  }

  // BUTTON STYLE
  static ButtonStyle get primaryButton => ElevatedButton.styleFrom(
    backgroundColor: primary,
    foregroundColor: white,
    elevation: 0,
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 12,
    ),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(8),
    ),
  );
}
