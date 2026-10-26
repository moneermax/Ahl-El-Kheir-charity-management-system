import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  test('validates and normalizes the full API base URL', () {
    expect(
      normalizeApiBaseUrl(' http://192.168.1.10:8081/AhlElKheir/api/v1/ '),
      'http://192.168.1.10:8081/AhlElKheir/api/v1',
    );
    expect(normalizeApiBaseUrl('192.168.1.10:8081/AhlElKheir/api/v1'), isNull);
    expect(normalizeApiBaseUrl('http://192.168.1.10:8081/AhlElKheir'), isNull);
    expect(normalizeApiBaseUrl('https://example.com/api/v1?redirect=bad'), isNull);
  });

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
            buildRevision: 'test-revision-123456',
            onConfigureApi: () async {},
            onTestApi: () async {},
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

