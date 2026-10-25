import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_user_create_page.dart';

void main() {
  testWidgets('native account creation validates required identity fields', (tester) async {
    var submitted = false;
    await tester.pumpWidget(MaterialApp(
      home: AdminUserCreatePage(
        english: true,
        loadOptions: () async => {
          'roles': [
            {'id': 4, 'code': 'hr_staff', 'name_ar': 'موظف الموارد البشرية', 'name_en': 'HR Staff'},
          ],
          'departments': [],
          'managers': [],
        },
        submitAction: (_) async {
          submitted = true;
          return {'temporary_password': 'TEMP-123'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).at(0), 'New HR User');
    await tester.enterText(find.byType(TextField).at(1), 'x');
    await tester.tap(find.byType(DropdownButtonFormField<String>).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('HR Staff').last);
    await tester.pumpAndSettle();
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Create account and employee'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Create account and employee'));
    await tester.pumpAndSettle();

    expect(submitted, isFalse);
    expect(find.textContaining('Username must be'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
