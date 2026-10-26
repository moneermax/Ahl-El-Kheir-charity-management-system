import 'package:ahl_el_kheir_app/payroll_integrity_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('payroll integrity renders read-only summary and issue details', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: PayrollIntegrityPage(
        english: true,
        loadChecks: () async => {
          'total_checks': 2,
          'passed_checks': 1,
          'failed_checks': 1,
          'read_only': true,
          'checks': [
            {'key': 'schema', 'title': 'Accounting columns', 'ok': true, 'summary': 'Required columns exist.', 'count': 0, 'rows': []},
            {'key': 'duplicates', 'title': 'Duplicate payroll periods', 'ok': false, 'summary': 'More than one payroll for the same employee and period.', 'count': 1, 'rows': [{'employee_id': 4, 'month': 10, 'year': 2026, 'row_count': 2}]},
          ],
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Payroll integrity'), findsOneWidget);
    expect(find.text('Read-only diagnostic'), findsOneWidget);
    expect(find.text('Passed'), findsOneWidget);
    expect(find.text('Needs attention'), findsOneWidget);
    expect(find.text('Duplicate payroll periods'), findsOneWidget);
    expect(find.textContaining('More than one payroll'), findsOneWidget);
    expect(find.text('employee_id'), findsOneWidget);
    expect(find.text('4'), findsOneWidget);
  });
}
