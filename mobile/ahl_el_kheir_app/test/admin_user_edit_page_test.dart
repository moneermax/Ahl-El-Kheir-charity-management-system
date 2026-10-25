import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_user_edit_page.dart';

void main() {
  testWidgets('native user edit submits identity and administrative assignments', (tester) async {
    Map<String, dynamic>? submitted;
    final user = <String, dynamic>{
      'id': 8,
      'username': 'hr.user',
      'full_name': 'Old Name',
      'email': 'old@example.test',
      'phone': '555-0100',
      'gender': 'female',
      'role_code': 'hr_staff',
      'department_id': 2,
      'manager_id': 1,
      'is_active': true,
    };
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: FilledButton(
              onPressed: () => Navigator.of(context).push<bool>(MaterialPageRoute<bool>(
                builder: (_) => AdminUserEditPage(
                  english: true,
                  user: user,
                  loadOptions: () async => {
                    'roles': [
                      {'id': 4, 'code': 'hr_staff', 'name_ar': 'موظف الموارد البشرية', 'name_en': 'HR Staff'},
                    ],
                    'departments': [
                      {'id': 2, 'name_ar': 'الموارد البشرية', 'name_en': 'Human Resources'},
                    ],
                    'managers': [
                      {'id': 1, 'full_name': 'HR Manager', 'username': 'hr.manager'},
                    ],
                  },
                  submitAction: (payload) async {
                    submitted = payload;
                    return {'message': 'Saved'};
                  },
                ),
              )),
              child: const Text('Open editor'),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('Open editor'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).at(0), 'Updated Name');
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Save changes'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Save changes'));
    await tester.pumpAndSettle();

    expect(submitted?['action'], 'update');
    expect(submitted?['user_id'], 8);
    expect(submitted?['full_name'], 'Updated Name');
    expect(submitted?['role_code'], 'hr_staff');
    expect(submitted?['department_id'], 2);
    expect(submitted?['manager_id'], 1);
    expect(submitted?['is_active'], true);
    expect(tester.takeException(), isNull);
  });
}
