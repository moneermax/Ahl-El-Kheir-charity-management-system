import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/password_recovery_page.dart';

void main() {
  testWidgets('password recovery submits identity and shows generic confirmation', (tester) async {
    String? submittedIdentity;
    await tester.pumpWidget(MaterialApp(
      home: PasswordRecoveryPage(
        english: true,
        submitRequest: (identity) async {
          submittedIdentity = identity;
          return {'request_received': true, 'message': 'received'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField), '  employee@example.test  ');
    await tester.ensureVisible(find.text('Send recovery request'));
    await tester.tap(find.text('Send recovery request'));
    await tester.pumpAndSettle();

    expect(submittedIdentity, 'employee@example.test');
    expect(find.textContaining('The request was received.'), findsOneWidget);
    expect(find.text('Back to sign in'), findsOneWidget);
  });

  testWidgets('password recovery requires a username or email before sending', (tester) async {
    var submitted = false;
    await tester.pumpWidget(MaterialApp(
      home: PasswordRecoveryPage(
        english: true,
        submitRequest: (_) async {
          submitted = true;
          return {'request_received': true};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Send recovery request'));
    await tester.tap(find.text('Send recovery request'));
    await tester.pumpAndSettle();

    expect(submitted, isFalse);
    expect(find.text('Enter a username or email address.'), findsOneWidget);
  });
}
