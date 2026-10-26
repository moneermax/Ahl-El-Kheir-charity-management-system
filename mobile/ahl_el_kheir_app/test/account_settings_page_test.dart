import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/account_settings_page.dart';

void main() {
  testWidgets('account settings submits current and new password then shows success', (tester) async {
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(
      home: AccountSettingsPage(
        english: true,
        submitPasswordChange: (body) async {
          submitted = Map<String, dynamic>.from(body);
          return {'password_changed': true, 'message': 'Password changed successfully.'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'old-password');
    await tester.enterText(find.byType(TextFormField).at(1), 'new-password');
    await tester.enterText(find.byType(TextFormField).at(2), 'new-password');
    await tester.ensureVisible(find.text('Save password'));
    await tester.tap(find.text('Save password'));
    await tester.pumpAndSettle();

    expect(submitted, {
      'current_password': 'old-password',
      'new_password': 'new-password',
      'confirm_password': 'new-password',
    });
    expect(find.text('Password changed successfully.'), findsOneWidget);
  });

  testWidgets('account settings blocks mismatched confirmation locally', (tester) async {
    var submitted = false;
    await tester.pumpWidget(MaterialApp(
      home: AccountSettingsPage(
        english: true,
        submitPasswordChange: (_) async {
          submitted = true;
          return {'password_changed': true};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).at(0), 'old-password');
    await tester.enterText(find.byType(TextFormField).at(1), 'new-password');
    await tester.enterText(find.byType(TextFormField).at(2), 'different-password');
    await tester.ensureVisible(find.text('Save password'));
    await tester.tap(find.text('Save password'));
    await tester.pumpAndSettle();

    expect(submitted, isFalse);
    expect(find.text('The passwords do not match.'), findsOneWidget);
  });
}
