import 'package:ahl_el_kheir_app/employment_states_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main() {
  testWidgets('employment states page renders state catalog and current employee history', (tester) async {
    await tester.pumpWidget(MaterialApp(home: EmploymentStatesPage(english: true, loadPage: () async => {
      'metrics': {'defined_states': 2, 'linked_employees': 1, 'current_history_rows': 1},
      'states': [{'code':'active','name_ar':'نشط','name_en':'Active','category':'working'},{'code':'suspended','name_ar':'موقوف','name_en':'Suspended','category':'non_working'}],
      'employees': [{'employee_code':'EMP-01','full_name':'Test Employee','status':'active','state_code':'active','state_name':'نشط','state_name_en':'Active','history_from':'2026-10-01','history_reason':'Hire'}],
      'read_only': true,
    })));
    await tester.pumpAndSettle();
    expect(find.text('Employment states'), findsOneWidget);
    expect(find.text('Employee lifecycle reference — read only'), findsOneWidget);
    expect(find.text('Active'), findsWidgets);
    expect(find.text('Test Employee'), findsOneWidget);
    expect(find.text('EMP-01'), findsOneWidget);
    expect(find.text('Hire'), findsOneWidget);
  });
}
