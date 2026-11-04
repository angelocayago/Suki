import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Mobile Suki basic widget test', (WidgetTester tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: Center(child: Text('Mobile Suki'))),
      ),
    );

    expect(find.text('Mobile Suki'), findsOneWidget);
  });
}
