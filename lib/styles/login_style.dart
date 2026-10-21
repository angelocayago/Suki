import 'package:flutter/material.dart';

class LoginStyle {
  // =========================================================
  // COLORS
  // =========================================================

  static const Color primary = Color(0xFF167561);
  static const Color primaryDark = Color(0xFF064E43);

  static const Color leftBackground = Color(0xFFF0FAF6);
  static const Color rightBackground = Colors.white;

  static const Color titleColor = Color(0xFF08142B);
  static const Color textColor = Color(0xFF596579);
  static const Color mutedText = Color(0xFF8D99AC);

  static const Color borderColor = Color(0xFFD7DEE5);
  static const Color iconColor = Color(0xFFA4AFBF);

  static const Color lightGreenBorder = Color(0xFFD5ECE5);
  static const Color lightGreenBackground = Color(0xFFFAFFFD);

  // =========================================================
  // TEXT STYLES
  // =========================================================

  static const TextStyle welcomeText = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w700,
    color: primary,
    letterSpacing: 0.5,
  );

  static const TextStyle loginTitle = TextStyle(
    fontSize: 28,
    fontWeight: FontWeight.w800,
    color: titleColor,
    height: 1.2,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    color: textColor,
    height: 1.4,
  );

  static const TextStyle label = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: titleColor,
  );

  static const TextStyle hint = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w400,
    color: mutedText,
  );

  static const TextStyle forgotPassword = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w600,
    color: primary,
  );

  static const TextStyle buttonText = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: Colors.white,
    letterSpacing: 0.2,
  );

  static const TextStyle heroTitle = TextStyle(
    fontSize: 32,
    fontWeight: FontWeight.w800,
    color: primaryDark,
    height: 1.2,
  );

  static const TextStyle heroDescription = TextStyle(
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
      fillColor: Colors.white,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: primary, width: 1.4),
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
      color: Colors.white,

      borderRadius: BorderRadius.circular(24),

      border: Border.all(color: const Color(0xFFE1E9E5), width: 1),

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
      color: Colors.white,

      borderRadius: BorderRadius.circular(25),

      border: Border.all(color: const Color(0xFFDDE8E4), width: 1),
    );
  }
}
