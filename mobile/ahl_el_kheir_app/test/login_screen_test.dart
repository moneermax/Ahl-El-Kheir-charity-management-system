import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  testWidgets('shows the Arabic sign-in form', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: LoginPage(
            english: false,
            toggle: () {},
            submit: (_, __) async {},
          ),
        ),
      ),
    );

    expect(find.text('تسجيل الدخول'), findsOneWidget);
    expect(find.text('اسم المستخدم'), findsOneWidget);
  });
}
