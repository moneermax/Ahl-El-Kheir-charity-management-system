import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  testWidgets('login screen keeps language and build label but hides diagnostics', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: LoginPage(
        english: true,
        toggle: () {},
        submit: (username, password) async {},
        buildRevision: 'abcdef1234567890',
        onForgotPassword: () async {},
      ),
    ));

    expect(find.text('العربية'), findsOneWidget);
    expect(find.text('Build: abcdef123456'), findsOneWidget);
    expect(find.text('API server'), findsNothing);
    expect(find.text('Test connection'), findsNothing);
    expect(find.text('Secure access to Ahl El Kheir'), findsNothing);
    expect(find.text('http://192.168.18.9:8081/AhlElKheir/api/v1'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
