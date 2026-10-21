import 'package:flutter/material.dart';

class BuyerStyle {
  // =========================
  // COLORS
  // =========================

  static const Color primary = Color(0xFF167561);

  static const Color primaryDark = Color(0xFF064E43);

  static const Color background = Color(0xFFF8FAF9);

  static const Color textDark = Color(0xFF08142B);

  static const Color textGray = Color(0xFF64748B);

  static const Color border = Color(0xFFE2E8F0);

  static const Color card = Colors.white;

  // =========================
  // TEXT STYLE
  // =========================

  static const TextStyle appTitle = TextStyle(
    fontSize: 20,
    fontWeight: FontWeight.w800,
    color: primaryDark,
  );

  static const TextStyle greeting = TextStyle(fontSize: 13, color: textGray);

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 16,
    fontWeight: FontWeight.w700,
    color: textDark,
  );

  static const TextStyle productName = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: textDark,
  );

  static const TextStyle price = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w800,
    color: primary,
  );

  // =========================
  // SEARCH FIELD
  // =========================

  static InputDecoration searchDecoration() {
    return InputDecoration(
      hintText: "Search products...",

      hintStyle: const TextStyle(fontSize: 12, color: textGray),

      prefixIcon: const Icon(Icons.search, color: textGray, size: 20),

      filled: true,

      fillColor: Colors.white,

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
      color: Colors.white,

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
