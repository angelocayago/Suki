import 'package:flutter/material.dart';

class LogisticsStyle {
  // =========================
  // COLORS
  // =========================

  static const Color primary = Color(0xFF167561);

  static const Color primaryDark = Color(0xFF064E43);

  static const Color background = Color(0xFFF7FAF8);

  static const Color card = Colors.white;

  static const Color textDark = Color(0xFF08142B);

  static const Color textGray = Color(0xFF64748B);

  static const Color border = Color(0xFFE2E8F0);

  static const Color success = Color(0xFF16A34A);

  static const Color warning = Color(0xFFF59E0B);

  static const Color danger = Color(0xFFDC2626);

  // =========================
  // TEXT
  // =========================

  static const TextStyle appTitle = TextStyle(
    fontSize: 20,

    fontWeight: FontWeight.w800,

    color: primaryDark,
  );

  static const TextStyle greeting = TextStyle(fontSize: 12, color: textGray);

  static const TextStyle sectionTitle = TextStyle(
    fontSize: 16,

    fontWeight: FontWeight.w700,

    color: textDark,
  );

  static const TextStyle cardTitle = TextStyle(
    fontSize: 13,

    fontWeight: FontWeight.w700,

    color: textDark,
  );

  static const TextStyle normalText = TextStyle(fontSize: 12, color: textGray);

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
