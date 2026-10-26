import 'package:ahl_el_kheir_app/gm_payroll_report_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main() {
  testWidgets('GM payroll report shows completion and accounting evidence', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GmPayrollReportPage(
      english: true, month: 10, year: 2026,
      loadReport: (month, year) async => {
        'period': '2026-10', 'eligible_count': 1, 'paid_count': 1, 'complete': true, 'net': 1000, 'incentives': 50, 'total': 1050,
        'employees': [{'full_name': 'Test Employee', 'employee_code': 'EMP-TEST', 'completion_paid': true, 'basic_salary': 900, 'allowances': 100, 'overtime': 0, 'deductions': 0, 'attendance_deduction': 0, 'salary_advance_deduction': 0, 'net_salary': 1000, 'incentive_amount': 50, 'payment_date': '2026-10-25', 'accounting_entry_id': 42, 'proof_state': 'confirmed', 'completion_employee_notification': true}],
      },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payroll payment report'), findsOneWidget);
    expect(find.text('2026-10'), findsOneWidget);
    expect(find.text('Test Employee (EMP-TEST)'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.text('42'), findsOneWidget);
    expect(find.text('Recorded'), findsOneWidget);
  });
}
