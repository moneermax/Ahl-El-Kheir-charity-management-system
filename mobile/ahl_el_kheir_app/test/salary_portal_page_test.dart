import 'package:ahl_el_kheir_app/salary_portal_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('financial manager sees role-appropriate portal and native routes', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: SalaryPortalPage(english: true, roleCode: 'financial_manager', onOpen: opened.add),
    ));

    expect(find.text('Payroll and incentives portal'), findsOneWidget);
    expect(find.text('Salary advances'), findsOneWidget);
    expect(find.text('Salary advance waivers'), findsOneWidget);
    expect(find.text('Payroll management'), findsOneWidget);
    expect(find.text('Employee incentive policy'), findsOneWidget);
    expect(find.text('IN APP'), findsWidgets);

    await tester.tap(find.text('Salary advances'));
    expect(opened, ['modules/hr/salary_advance_dashboard.php']);
    await tester.tap(find.text('Payroll management'));
    expect(opened, ['modules/hr/payroll.php']);
    await tester.pumpWidget(const SizedBox.shrink());
  });

  testWidgets('HR staff sees payroll only', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: SalaryPortalPage(english: true, roleCode: 'hr_staff', onOpen: opened.add),
    ));

    expect(find.text('Payroll management'), findsOneWidget);
    expect(find.text('Salary advances'), findsNothing);
    expect(find.text('Salary advance waivers'), findsNothing);
    expect(find.text('Employee incentive policy'), findsNothing);
    await tester.tap(find.text('Payroll management'));
    expect(opened, ['modules/hr/payroll.php']);
  });

  testWidgets('administrator cannot open incentive policy when its web guard denies access', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: SalaryPortalPage(english: true, roleCode: 'admin', onOpen: opened.add),
    ));

    expect(find.text('Employee incentive policy'), findsOneWidget);
    final policyCard = find.ancestor(of: find.text('Employee incentive policy'), matching: find.byType(Card));
    expect(find.descendant(of: policyCard, matching: find.text('NOT READY IN APP')), findsOneWidget);
    await tester.tap(find.text('Employee incentive policy'));
    expect(opened, isEmpty);
  });
}
