import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/my_salary_statements_page.dart';

void main() {
  testWidgets('renders Arabic paid salary statement and expands salary details', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: MySalaryStatementsPage(
          english: false,
          loadPage: (page) async => {
            'employee_linked': true,
            'items': [{
              'id': 21,
              'month': 10,
              'year': 2026,
              'employee_code': 'EMP-0021',
              'employee_name': 'سارة علي',
              'basic_salary': 1500.0,
              'allowances': 100.0,
              'overtime': 0.0,
              'deductions': 50.0,
              'attendance_deduction': 25.0,
              'salary_advance_deduction': 100.0,
              'incentive_amount': 75.0,
              'net_salary': 1500.0,
              'payment_date': '2026-10-25',
              'salary_currency': 'SDG',
            }],
            'total': 1,
            'page': 1,
            'pages': 1,
          },
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('كشوف رواتبي'), findsOneWidget);
    expect(find.text('أكتوبر 2026'), findsOneWidget);
    expect(find.text('1500.00 SDG'), findsOneWidget);
    await tester.tap(find.text('أكتوبر 2026'));
    await tester.pumpAndSettle();
    expect(find.text('خصم السلف'), findsOneWidget);
    expect(find.text('100.00 SDG'), findsOneWidget);
    expect(find.text('تاريخ الصرف: 2026-10-25'), findsOneWidget);
  });

  testWidgets('explains when the account has no linked employee profile', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: MySalaryStatementsPage(
        english: true,
        loadPage: (page) async => {
          'employee_linked': false,
          'items': [],
          'total': 0,
          'page': 1,
          'pages': 1,
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.textContaining('No employee profile is linked'), findsOneWidget);
  });
}
