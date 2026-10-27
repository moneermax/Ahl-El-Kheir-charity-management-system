import 'package:ahl_el_kheir_app/salary_advance_dashboard_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shows status counts and processed history without external actions', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SalaryAdvanceDashboardPage(
        english: true,
        loadDashboard: () async => {
          'counts': {'fm_pending': 2, 'accounting_pending': 3, 'disbursed': 4, 'settled': 5},
          'history': [
            {
              'request_no': 'SAR-2026-00001',
              'employee_name': 'Test Employee',
              'employee_code': 'EMP-001',
              'approved_amount': '2500.00',
              'outstanding_balance': '1000.00',
              'status': 'disbursed',
              'disbursed_at': '2026-10-20 12:00:00',
              'settled_at': null,
            },
          ],
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Salary-advance dashboard'), findsOneWidget);
    expect(find.text('Pending FM review'), findsOneWidget);
    expect(find.text('Pending accounting'), findsOneWidget);
    expect(find.text('SAR-2026-00001'), findsOneWidget);
    expect(find.text('Test Employee'), findsOneWidget);
    expect(find.text('Disbursed'), findsOneWidget);
    expect(find.text('2500.00 SDG'), findsOneWidget);
    expect(find.text('Policy management, request processing and rescheduling are not available in-app yet. This screen does not open external pages.'), findsOneWidget);
    expect(find.byType(OutlinedButton), findsNothing);
  });

  testWidgets('allows retry when the dashboard API fails', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
      home: SalaryAdvanceDashboardPage(
        english: false,
        loadDashboard: () async {
          calls++;
          if (calls == 1) throw Exception('offline');
          return {'counts': {'fm_pending': 0, 'accounting_pending': 0, 'disbursed': 0, 'settled': 0}, 'history': []};
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('إعادة المحاولة'), findsOneWidget);
    await tester.tap(find.text('إعادة المحاولة'));
    await tester.pumpAndSettle();
    expect(calls, 2);
    expect(find.text('لا توجد طلبات سابقة في السجل.'), findsOneWidget);
  });
}
