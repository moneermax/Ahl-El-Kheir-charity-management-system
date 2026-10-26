import 'package:ahl_el_kheir_app/gm_payroll_history_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main() {
  testWidgets('GM payroll history displays period status and opens report', (tester) async {
    int monthOpened = 0, yearOpened = 0;
    await tester.pumpWidget(MaterialApp(home: GmPayrollHistoryPage(
      english: true,
      loadHistory: () async => {'periods': [{'month': 10, 'year': 2026, 'period': '2026-10', 'eligible_count': 4, 'paid_count': 3, 'complete': false, 'net': 1200, 'incentives': 50, 'total': 1250}]},
      openReport: (m, y) { monthOpened = m; yearOpened = y; },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payroll period history'), findsOneWidget);
    expect(find.text('October 2026'), findsOneWidget);
    expect(find.text('Incomplete'), findsOneWidget);
    expect(find.text('Paid: 3'), findsOneWidget);
    await tester.tap(find.text('Open report →'));
    expect(monthOpened, 10);
    expect(yearOpened, 2026);
  });
}
