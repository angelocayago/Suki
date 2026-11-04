import 'package:flutter/material.dart';

import '../theme/theme_controller.dart';

class ThemeToggleButton extends StatelessWidget {
  final bool showLabel;

  const ThemeToggleButton({
    super.key,
    this.showLabel = false,
  });

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<ThemeMode>(
      valueListenable: ThemeController.mode,
      builder: (context, _, _) {
        final dark = Theme.of(context).brightness == Brightness.dark;
        final icon = dark ? Icons.light_mode_outlined : Icons.dark_mode_outlined;
        final label = dark ? 'Switch to light mode' : 'Switch to dark mode';

        if (showLabel) {
          return TextButton.icon(
            onPressed: ThemeController.toggle,
            icon: Icon(icon),
            label: Text(dark ? 'Light mode' : 'Dark mode'),
          );
        }

        return IconButton(
          tooltip: label,
          onPressed: ThemeController.toggle,
          icon: Icon(icon),
        );
      },
    );
  }
}
