import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/incentive_policy_page.dart';

void main() {
  Future<void> pumpPolicy(
    WidgetTester tester, {
    required String role,
    required Map<String, dynamic> data,
  }) async {
    await tester.pumpWidget(MaterialApp(
      theme: ThemeData(colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF6D3CC8))),
      home: IncentivePolicyPage(
        english: true,
        roleCode: role,
        loadPage: () async => data,
        submitAction: (body) async => {'ok': true},
      ),
    ));
    await tester.pumpAndSettle();
  }

  testWidgets('HR Manager sees category and draft controls, not approval controls', (tester) async {
    await pumpPolicy(tester, role: 'hr_manager', data: {
      'can_manage': true,
      'can_review': false,
      'can_activate': false,
      'today': '2026-10-27',
      'currency_code': 'SDG',
      'types': [
        {'type_code': 'new_sponsor', 'name_en': 'New sponsor', 'is_active': 1, 'qualification_mode': 'automatic'},
      ],
      'policies': [],
      'rules': [],
      'custom_fields': [],
      'approval_history': [],
    });

    expect(find.text('Create draft'), findsOneWidget);
    expect(find.text('Add category'), findsOneWidget);
    expect(find.text('No policy versions are available for this role.'), findsOneWidget);
    expect(find.text('Approve'), findsNothing);
    expect(find.text('Activate policy'), findsNothing);
  });

  testWidgets('General Manager sees approval actions only for pending policies', (tester) async {
    await pumpPolicy(tester, role: 'general_manager', data: {
      'can_manage': false,
      'can_review': true,
      'can_activate': false,
      'currency_code': 'SDG',
      'policies': [
        {'id': 7, 'version_no': 2, 'policy_name': 'Reviewed version', 'status': 'pending_gm', 'effective_from': '2026-11-01', 'enabled_rules': 1},
      ],
      'rules': [],
      'custom_fields': [],
      'approval_history': [],
    });

    await tester.tap(find.text('V2 — Reviewed version'));
    await tester.pumpAndSettle();
    expect(find.text('Approve'), findsOneWidget);
    expect(find.text('Reject'), findsOneWidget);
    expect(find.text('Activate policy'), findsNothing);
  });

  testWidgets('Financial Manager can activate approved policies but not edit drafts', (tester) async {
    await pumpPolicy(tester, role: 'financial_manager', data: {
      'can_manage': false,
      'can_review': false,
      'can_activate': true,
      'currency_code': 'SDG',
      'policies': [
        {'id': 8, 'version_no': 3, 'policy_name': 'Approved version', 'status': 'approved', 'effective_from': '2026-11-01', 'enabled_rules': 1},
      ],
      'rules': [],
      'custom_fields': [],
      'approval_history': [],
    });

    await tester.tap(find.text('V3 — Approved version'));
    await tester.pumpAndSettle();
    expect(find.text('Activate policy'), findsOneWidget);
    expect(find.text('Create draft'), findsNothing);
    expect(find.text('Edit draft'), findsNothing);
  });

  testWidgets('active incentive policy is explicitly read-only', (tester) async {
    await pumpPolicy(tester, role: 'hr_manager', data: {
      'can_manage': false,
      'can_review': false,
      'can_activate': false,
      'currency_code': 'SDG',
      'policies': [
        {'id': 9, 'version_no': 4, 'policy_name': 'Active version', 'status': 'active', 'effective_from': '2026-10-15', 'enabled_rules': 1},
      ],
      'rules': [],
      'custom_fields': [],
      'approval_history': [],
    });

    await tester.tap(find.text('V4 — Active version'));
    await tester.pumpAndSettle();
    expect(find.text('Active policy is read-only'), findsOneWidget);
    expect(find.text('Edit draft'), findsNothing);
    expect(find.text('Activate policy'), findsNothing);
  });
}
