import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/salary_advance_waiver_page.dart';

void main() {
  Future<void> pumpWaiver(
    WidgetTester tester, {
    required String role,
    required Map<String, dynamic> data,
    required Future<Map<String, dynamic>> Function(Map<String, dynamic>) submit,
  }) async {
    await tester.pumpWidget(MaterialApp(
      theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF6D3CC8))),
      home: SalaryAdvanceWaiverPage(
        english: true,
        roleCode: role,
        loadPage: (_) async => data,
        submitAction: submit,
      ),
    ));
    await tester.pumpAndSettle();
  }

  Map<String, dynamic> baseData({
    required bool fm,
    required bool gm,
    List<Map<String, dynamic>> decisions = const [],
  }) => {
    'tables_ready': true,
    'can_fm_prepare': fm,
    'can_gm_review': gm,
    'effective_month': '2026-10-01',
    'employees': [
      {'id': 8, 'employee_code': 'EMP-0008', 'full_name': 'Test Employee'},
    ],
    'refund_accounts': [
      {'id': 1, 'code': '1100', 'name_en': 'Cash', 'name_ar': 'نقدية'},
    ],
    'expense_accounts': [
      {'id': 2, 'code': '5500', 'name_en': 'Incentive expense', 'name_ar': 'مصروف'},
    ],
    'eligible_rows': [
      {'request_id': 41, 'request_no': 'SAR-2026-00041', 'employee_id': 8, 'employee_code': 'EMP-0008', 'employee_name': 'Test Employee', 'status': 'disbursed', 'outstanding_balance': 1000, 'current_period_repayment': 100},
    ],
    'decisions': decisions,
    'items': [
      {'id': 1, 'decision_id': 7, 'employee_id': 8, 'employee_code': 'EMP-0008', 'employee_name': 'Test Employee', 'request_no': 'SAR-2026-00041', 'previous_request_status': 'disbursed', 'balance_before': 1000, 'current_period_repayment': 100},
    ],
  };

  testWidgets('GM can approve or reject pending decisions but cannot prepare or execute', (tester) async {
    final submitted = <Map<String, dynamic>>[];
    await pumpWaiver(
      tester,
      role: 'general_manager',
      data: baseData(fm: false, gm: true, decisions: [
        {'id': 7, 'decision_no': 'SAW-2026-00007', 'decision_type': 'individual', 'effective_month': '2026-10-01', 'status': 'pending_gm', 'reason': 'Approved reason', 'item_count': 1, 'refund_preview': 100, 'waiver_preview': 1100, 'preparer_name': 'FM'},
      ]),
      submit: (body) async { submitted.add(body); return {'ok': true}; },
    );

    expect(find.text('Decisions awaiting review'), findsOneWidget);
    expect(find.text('Approve'), findsOneWidget);
    expect(find.text('Reject'), findsOneWidget);
    expect(find.text('Prepare a new waiver decision'), findsNothing);
    expect(find.text('Execute financial decision'), findsNothing);

    await tester.scrollUntilVisible(
      find.widgetWithText(FilledButton, 'Approve').last,
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Approve').first);
    await tester.pumpAndSettle();
    expect(find.text('Approve waiver decision?'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Approve').last);
    await tester.pumpAndSettle();

    expect(submitted, hasLength(1));
    expect(submitted.single['action'], 'approve');
    expect(submitted.single['decision_id'], 7);
  });

  testWidgets('FM sees preparation controls and executes only GM-approved decisions', (tester) async {
    final submitted = <Map<String, dynamic>>[];
    await pumpWaiver(
      tester,
      role: 'financial_manager',
      data: baseData(fm: true, gm: false, decisions: [
        {'id': 7, 'decision_no': 'SAW-2026-00007', 'decision_type': 'individual', 'effective_month': '2026-10-01', 'status': 'approved_by_gm', 'reason': 'Approved reason', 'item_count': 1, 'refund_preview': 100, 'waiver_preview': 1100, 'preparer_name': 'FM', 'refund_code': '1100', 'refund_name_en': 'Cash', 'expense_code': '5500', 'expense_name_en': 'Expense'},
      ]),
      submit: (body) async { submitted.add(body); return {'ok': true}; },
    );

    expect(find.text('Prepare a new waiver decision'), findsOneWidget);
    expect(find.text('Approve'), findsNothing);
    await tester.scrollUntilVisible(
      find.text('Execute financial decision'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Execute financial decision'), findsOneWidget);

    await tester.tap(find.text('Execute financial decision'));
    await tester.pumpAndSettle();
    expect(find.text('Execute waiver permanently?'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Execute').last);
    await tester.pumpAndSettle();

    expect(submitted, hasLength(1));
    expect(submitted.single['action'], 'execute');
    expect(submitted.single['decision_id'], 7);
  });

  testWidgets('missing migration displays a safe read-only notice', (tester) async {
    await pumpWaiver(
      tester,
      role: 'general_manager',
      data: {'tables_ready': false, 'can_fm_prepare': false, 'can_gm_review': true, 'decisions': [], 'items': []},
      submit: (_) async => {'ok': true},
    );
    expect(find.text('The waiver feature is not enabled in this database. No changes were made.'), findsOneWidget);
    expect(find.text('Approve'), findsNothing);
  });
}
