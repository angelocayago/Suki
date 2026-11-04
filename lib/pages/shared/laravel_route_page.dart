import 'package:flutter/material.dart';

/// Native landing screen used for Laravel buyer/rider routes that do not yet
/// have a dedicated data-backed Flutter screen.
class LaravelRoutePage extends StatelessWidget {
  final String title;
  final String description;
  final String emptyTitle;
  final String emptyMessage;
  final IconData icon;
  final String? identifier;
  final String? actionLabel;
  final String? actionRoute;

  const LaravelRoutePage({
    super.key,
    required this.title,
    required this.description,
    required this.emptyTitle,
    required this.emptyMessage,
    required this.icon,
    this.identifier,
    this.actionLabel,
    this.actionRoute,
  });

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            description,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          if (identifier != null && identifier!.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              'Reference: $identifier',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
          const SizedBox(height: 24),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  Icon(icon, size: 42, color: Theme.of(context).colorScheme.primary),
                  const SizedBox(height: 14),
                  Text(
                    emptyTitle,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    emptyMessage,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  if (actionLabel != null && actionRoute != null) ...[
                    const SizedBox(height: 18),
                    FilledButton(
                      onPressed: () => Navigator.pushNamed(context, actionRoute!),
                      child: Text(actionLabel!),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
