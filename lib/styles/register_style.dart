import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class RegisterStyle {
  // =========================================================
  // COLORS
  // =========================================================

  static const Color primary = AppTheme.forestGreen;

  static Color get primaryDark =>
      AppTheme.isDark ? AppTheme.accentGreen : AppTheme.forestGreenDark;

  static Color get backgroundColor => AppTheme.background;

  static Color get titleColor => AppTheme.text;

  static Color get textColor => AppTheme.mutedText;

  static Color get mutedText => AppTheme.mutedText;

  static Color get borderColor => AppTheme.border;

  static Color get iconColor => AppTheme.mutedText;

  static Color get selectedBackground => AppTheme.softGreen;

  static Color get dividerColor => AppTheme.border;

  // =========================================================
  // HEADER
  // =========================================================

  static BoxDecoration get logoContainer {
    return BoxDecoration(
      color: selectedBackground,
      borderRadius: BorderRadius.circular(18),
    );
  }

  static TextStyle get brandText => TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    letterSpacing: 0.8,
    color: primaryDark,
  );

  static TextStyle get smallHeading => TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.7,
    color: AppTheme.brandText,
  );

  static TextStyle get pageTitle => TextStyle(
    fontSize: 25,
    fontWeight: FontWeight.w800,
    color: titleColor,
    height: 1.15,
  );

  static TextStyle get subtitle => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    color: textColor,
    height: 1.4,
  );

  // =========================================================
  // SECTION
  // =========================================================

  static TextStyle get sectionTitle => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: titleColor,
  );

  static TextStyle get label => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w500,
    color: titleColor,
  );

  static TextStyle get hint => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w400,
    color: mutedText,
  );

  static TextStyle get inputText => TextStyle(
    fontSize: 11.5,
    fontWeight: FontWeight.w400,
    color: titleColor,
  );

  // =========================================================
  // ROLE SELECTOR
  // =========================================================

  static BoxDecoration roleCard(bool selected) {
    return BoxDecoration(
      color: selected ? selectedBackground : AppTheme.surface,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(
        color: selected ? AppTheme.brandText : borderColor,
        width: selected ? 1.4 : 1,
      ),
    );
  }

  static TextStyle get roleText => TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w500,
    color: mutedText,
  );

  static TextStyle get selectedRoleText => TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    color: AppTheme.brandText,
  );

  // =========================================================
  // INPUT
  // =========================================================

  static InputDecoration inputDecoration({
    required String hint,
    IconData? icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: RegisterStyle.hint,

      prefixIcon: icon != null ? Icon(icon, size: 18, color: iconColor) : null,

      suffixIcon: suffixIcon,

      filled: true,
      fillColor: AppTheme.surface,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 14),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: AppTheme.brandText, width: 1.3),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.2),
      ),

      errorStyle: const TextStyle(fontSize: 9, height: 0.7),
    );
  }

  static InputDecoration dropdownDecoration() {
    return InputDecoration(
      filled: true,
      fillColor: AppTheme.surface,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: AppTheme.brandText, width: 1.3),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.redAccent),
      ),

      errorStyle: const TextStyle(fontSize: 9, height: 0.7),
    );
  }

  // =========================================================
  // TERMS
  // =========================================================

  static TextStyle get termsText => TextStyle(
    fontSize: 9.5,
    color: textColor,
    height: 1.4,
  );

  static TextStyle get linkText => TextStyle(
    fontSize: 9.5,
    fontWeight: FontWeight.w700,
    color: AppTheme.brandText,
  );

  // =========================================================
  // BUTTON
  // =========================================================

  static ButtonStyle get primaryButton {
    return ElevatedButton.styleFrom(
      backgroundColor: primary,
      foregroundColor: Colors.white,

      elevation: 0,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),

      padding: const EdgeInsets.symmetric(vertical: 14),
    );
  }

  static TextStyle get buttonText => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    color: Colors.white,
  );

  // =========================================================
  // BOTTOM
  // =========================================================

  static TextStyle get orText => TextStyle(fontSize: 9, color: mutedText);

  static TextStyle get bottomText => TextStyle(fontSize: 11, color: textColor);

  static TextStyle get bottomLink => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    color: AppTheme.brandText,
  );

  static TextStyle get backText => TextStyle(fontSize: 10, color: mutedText);
}
