import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class LoginStyle {
  // =========================================================
  // COLORS
  // =========================================================

  static const Color primary = AppTheme.forestGreen;
  static Color get primaryDark =>
      AppTheme.isDark ? AppTheme.accentGreen : AppTheme.forestGreenDark;

  static Color get leftBackground => AppTheme.softGreen;
  static Color get rightBackground => AppTheme.surface;

  static Color get titleColor => AppTheme.text;
  static Color get textColor => AppTheme.mutedText;
  static Color get mutedText => AppTheme.mutedText;

  static Color get borderColor => AppTheme.border;
  static Color get iconColor => AppTheme.mutedText;

  static Color get lightGreenBorder => AppTheme.border;
  static Color get lightGreenBackground => AppTheme.surface;

  // =========================================================
  // TEXT STYLES
  // =========================================================

  static TextStyle get welcomeText => TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w700,
    color: AppTheme.brandText,
    letterSpacing: 0.5,
  );

  static TextStyle get loginTitle => TextStyle(
    fontSize: 28,
    fontWeight: FontWeight.w800,
    color: titleColor,
    height: 1.2,
  );

  static TextStyle get subtitle => TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: textColor,
    height: 1.4,
  );

  static TextStyle get label => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: titleColor,
  );

  static TextStyle get hint => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w400,
    color: mutedText,
  );

  static TextStyle get forgotPassword => TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: AppTheme.brandText,
  );

  static TextStyle get buttonText => TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: Colors.white,
    letterSpacing: 0.2,
  );

  static TextStyle get heroTitle => TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.w800,
    color: primaryDark,
    height: 1.2,
  );

  static TextStyle get heroDescription => TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w400,
    color: textColor,
    height: 1.5,
  );

  // =========================================================
  // INPUT FIELD STYLE
  // =========================================================

  static InputDecoration inputDecoration({
    required String hint,
    required IconData icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: LoginStyle.hint,

      prefixIcon: Icon(icon, size: 20, color: iconColor),

      suffixIcon: suffixIcon,

      filled: true,
      fillColor: rightBackground,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: BorderSide(color: AppTheme.brandText, width: 1.4),
      ),

      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1),
      ),

      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.4),
      ),
    );
  }

  // =========================================================
  // LOGIN BUTTON
  // =========================================================

  static ButtonStyle get loginButton {
    return ElevatedButton.styleFrom(
      backgroundColor: primary,
      foregroundColor: Colors.white,

      elevation: 0,

      minimumSize: const Size(double.infinity, 48),

      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
    );
  }

  // =========================================================
  // LOGO CONTAINER
  // =========================================================

  static BoxDecoration get logoBox {
    return BoxDecoration(
      color: rightBackground,

      borderRadius: BorderRadius.circular(24),

      border: Border.all(color: borderColor, width: 1),

      boxShadow: [
        BoxShadow(
          color: Colors.black.withOpacity(0.05),
          blurRadius: 8,
          spreadRadius: 1,
          offset: const Offset(0, 3),
        ),
      ],
    );
  }

  // =========================================================
  // RIDER APPLICATION CARD
  // =========================================================

  static BoxDecoration get riderBox {
    return BoxDecoration(
      color: lightGreenBackground,

      borderRadius: BorderRadius.circular(12),

      border: Border.all(color: lightGreenBorder, width: 1),
    );
  }

  // =========================================================
  // SMALL FEATURE BADGE
  // =========================================================

  static BoxDecoration get smallBadge {
    return BoxDecoration(
      color: rightBackground,

      borderRadius: BorderRadius.circular(25),

      border: Border.all(color: borderColor, width: 1),
    );
  }
}
