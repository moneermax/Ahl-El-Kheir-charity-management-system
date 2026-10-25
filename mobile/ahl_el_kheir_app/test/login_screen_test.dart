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
            apiBaseUrl: 'http://192.168.1.10:8081/AhlElKheir/api/v1',
            onConfigureApi: () async {},
            onForgotPassword: () async {},
          ),
        ),
      ),
    );

    expect(find.text('تسجيل الدخول'), findsOneWidget);
    expect(find.text('اسم المستخدم'), findsOneWidget);
    expect(find.text('الخادم'), findsOneWidget);
    expect(find.text('http://192.168.1.10:8081/AhlElKheir/api/v1'), findsOneWidget);
  });
}

