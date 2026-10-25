import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_departments_page.dart';

void main() {
  testWidgets('native departments screen lists and creates a department', (tester) async {
    final actions = <Map<String, dynamic>>[];
    await tester.pumpWidget(MaterialApp(
      home: AdminDepartmentsPage(
        english: true,
        loadDepartments: () async => {
          'items': [
            {
              'id': 3,
              'name_ar': 'الموارد البشرية',
              'name_en': 'Human Resources',
              'description': 'People operations',
              'users_count': 2,
              'employees_count': 5,
            },
          ],
          'total': 1,
        },
        submitAction: (payload) async {
          actions.add(payload);
          return {'message': 'Department created.'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Department management'), findsOneWidget);
    expect(find.text('Human Resources'), findsOneWidget);
    expect(find.text('2 users'), findsOneWidget);
    expect(find.text('5 employees'), findsOneWidget);

    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), 'الخدمات');
    await tester.enterText(fields.at(1), 'Services');
    await tester.enterText(fields.at(2), 'Support department');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(actions, hasLength(1));
    expect(actions.single['action'], 'create');
    expect(actions.single['name_ar'], 'الخدمات');
    expect(actions.single['name_en'], 'Services');
    expect(tester.takeException(), isNull);
  });

  testWidgets('native departments screen validates both names before sending', (tester) async {
    var called = false;
    await tester.pumpWidget(MaterialApp(
      home: AdminDepartmentsPage(
        english: true,
        loadDepartments: () async => {'items': [], 'total': 0},
        submitAction: (_) async {
          called = true;
          return {};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(called, isFalse);
    expect(find.text('Arabic and English department names are required.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
