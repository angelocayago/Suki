import 'package:flutter/material.dart';

class RegisterStyle {
  // =========================================================
  // COLORS
  // =========================================================

  static const Color primary = Color(0xFF167561);

  static const Color primaryDark = Color(0xFF064E43);

  static const Color backgroundColor = Color(0xFFFCFEFD);

  static const Color titleColor = Color(0xFF071426);

  static const Color textColor = Color(0xFF526174);

  static const Color mutedText = Color(0xFF8B98AA);

  static const Color borderColor = Color(0xFFD9E0E6);

  static const Color iconColor = Color(0xFF9BA7B7);

  static const Color selectedBackground = Color(0xFFF0FAF6);

  static const Color dividerColor = Color(0xFFE4E9ED);

  // =========================================================
  // HEADER
  // =========================================================

  static BoxDecoration get logoContainer {
    return BoxDecoration(
      color: selectedBackground,
      borderRadius: BorderRadius.circular(18),
    );
  }

  static const TextStyle brandText = TextStyle(
    fontSize: 15,
    fontWeight: FontWeight.w800,
    letterSpacing: 0.8,
    color: primaryDark,
  );

  static const TextStyle smallHeading = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.7,
    color: primary,
  );

  static const TextStyle pageTitle = TextStyle(
    fontSize: 25,
    fontWeight: FontWeight.w800,
    color: titleColor,
    height: 1.15,
  );

  static const TextStyle subtitle = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w400,
    color: textColor,
    height: 1.4,
  );

  // =========================================================
  // SECTION
  // =========================================================

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: titleColor,
  );

  static const TextStyle label = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w500,
    color: titleColor,
  );

  static const TextStyle hint = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w400,
    color: mutedText,
  );

  static const TextStyle inputText = TextStyle(
    fontSize: 11.5,
    fontWeight: FontWeight.w400,
    color: titleColor,
  );

  // =========================================================
  // ROLE SELECTOR
  // =========================================================

  static BoxDecoration roleCard(bool selected) {
    return BoxDecoration(
      color: selected ? selectedBackground : Colors.white,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(
        color: selected ? primary : borderColor,
        width: selected ? 1.4 : 1,
      ),
    );
  }

  static const TextStyle roleText = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w500,
    color: mutedText,
  );

  static const TextStyle selectedRoleText = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    color: primary,
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
      fillColor: Colors.white,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 14),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: primary, width: 1.3),
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
      fillColor: Colors.white,

      isDense: true,

      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),

      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: borderColor, width: 1),
      ),

      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: primary, width: 1.3),
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

  static const TextStyle termsText = TextStyle(
    fontSize: 9.5,
    color: textColor,
    height: 1.4,
  );

  static const TextStyle linkText = TextStyle(
    fontSize: 9.5,
    fontWeight: FontWeight.w700,
    color: primary,
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

  static const TextStyle buttonText = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    color: Colors.white,
  );

  // =========================================================
  // BOTTOM
  // =========================================================

  static const TextStyle orText = TextStyle(fontSize: 9, color: mutedText);

  static const TextStyle bottomText = TextStyle(fontSize: 11, color: textColor);

  static const TextStyle bottomLink = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    color: primary,
  );

  static const TextStyle backText = TextStyle(fontSize: 10, color: mutedText);
}
