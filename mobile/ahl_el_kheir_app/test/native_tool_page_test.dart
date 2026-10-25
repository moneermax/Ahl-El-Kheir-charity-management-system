import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/native_tool_page.dart';

void main() {
  testWidgets('unsupported workflow stays inside app and explains native status', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: NativeToolPage(
        english: true,
        title: 'User management',
        detail: 'id=12',
      ),
    ));

    expect(find.text('User management'), findsOneWidget);
    expect(find.text('Native mobile workflow pending'), findsOneWidget);
    expect(find.textContaining('will not hand off to the website'), findsOneWidget);
    expect(find.text('Requested record: id=12'), findsOneWidget);
  });
}
