import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  test('uses the verified phone-facing port 8081 and migrates only the known stale local API endpoint from port 881', () {
    expect(defaultMobileApiBaseUrl, 'http://192.168.18.9:8081/AhlElKheir/api/v1');
    expect(
      migrateKnownLegacyApiBaseUrl('http://192.168.18.9:8081/AhlElKheir/api/v1'),
      'http://192.168.18.9:8081/AhlElKheir/api/v1',
    );
    expect(
      migrateKnownLegacyApiBaseUrl('http://192.168.18.9:881/AhlElKheir/api/v1'),
      'http://192.168.18.9:8081/AhlElKheir/api/v1',
    );
    expect(
      migrateKnownLegacyApiBaseUrl('http://other-host:881/AhlElKheir/api/v1'),
      'http://other-host:881/AhlElKheir/api/v1',
    );
  });

  test('API probe rejects a malformed base URL without network access', () async {
    final result = await probeApiEndpoint('http://example.com', english: false);
    expect(result.success, isFalse);
    expect(result.message, contains('/api/v1'));
  });

  testWidgets('login screen exposes an in-app API connectivity check', (tester) async {
    var testPressed = false;
    await tester.pumpWidget(MaterialApp(
      home: LoginPage(
        english: true,
        toggle: () {},
        submit: (username, password) async {},
        apiBaseUrl: 'http://192.168.18.9:881/AhlElKheir/api/v1',
        onConfigureApi: () async {},
        onTestApi: () async { testPressed = true; },
        onForgotPassword: () async {},
      ),
    ));
    expect(find.text('Test connection'), findsOneWidget);
    await tester.tap(find.text('Test connection'));
    await tester.pump();
    expect(testPressed, isTrue);
  });
}
