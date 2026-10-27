import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/role_tools_section.dart';

void main() {
  testWidgets('GM tools expose executive reporting and approval routes', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(child: RoleToolsSection(
          roleCode: 'general_manager',
          english: true,
          onOpen: (_) {},
        )),
      ),
    ));

    expect(find.text('Additional role tools'), findsOneWidget);
    expect(find.text('Report center'), findsOneWidget);
    expect(find.text('Incentive policy'), findsOneWidget);
    expect(find.text('GM reconciliation'), findsOneWidget);
    expect(find.text('Monthly disbursements'), findsOneWidget);
    expect(find.text('Payroll period history'), findsOneWidget);
    expect(find.text('Review payroll payment periods and employee reports in-app'), findsOneWidget);
    expect(find.text('IN APP'), findsWidgets);
    expect(find.text('WEB'), findsNothing);
  });


  testWidgets('assigned nannies is native for accountant and financial-manager roles', (tester) async {
    for (final role in ['accountant', 'accountant_staff', 'financial_manager']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(home: Scaffold(body: SingleChildScrollView(
        child: RoleToolsSection(roleCode: role, english: true, onOpen: opened.add),
      ))));
      expect(find.text('Assigned nannies'), findsOneWidget);
      expect(find.text('IN APP'), findsWidgets);
      await tester.tap(find.text('Assigned nannies'));
      expect(opened, ['modules/accounting/my_nannies.php']);
    }
  });

  testWidgets('Incentive policy is native for HR Manager, GM and FM roles', (tester) async {
    for (final role in ['hr_manager', 'general_manager', 'financial_manager']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(roleCode: role, english: true, onOpen: opened.add),
          ),
        ),
      ));
      expect(find.text('Incentive policy'), findsOneWidget, reason: role);
      final card = find.ancestor(of: find.text('Incentive policy'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: role);
      await tester.ensureVisible(find.text('Incentive policy'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Incentive policy'));
      expect(opened, contains('modules/hr/incentive_policy.php'), reason: role);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });

  testWidgets('Sponsor win-back is native for roles allowed by the web page guard', (tester) async {
    for (final role in ['admin', 'general_manager', 'vice_general_manager', 'administration']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(
              roleCode: role,
              english: true,
              onOpen: opened.add,
            ),
          ),
        ),
      ));

      expect(find.text('Sponsor win-back'), findsOneWidget, reason: role);
      final card = find.ancestor(of: find.text('Sponsor win-back'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: role);
      await tester.ensureVisible(find.text('Sponsor win-back'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sponsor win-back'));
      expect(opened, contains('modules/administration/winback.php'), reason: role);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });

  testWidgets('GM report subpages are marked in-app and dispatch to native report routes', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'general_manager',
          english: true,
          onOpen: opened.add,
        ),
      ),
    ));

    const reports = <String, String>{
      'Financial report': 'modules/reports/financial.php',
      'Sponsorship report': 'modules/reports/sponsorship.php',
      'Operational report': 'modules/reports/operational.php',
      'HR report': 'modules/reports/hr.php',
    };
    for (final entry in reports.entries) {
      final card = find.ancestor(of: find.text(entry.key), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: entry.key);
    }

    await tester.ensureVisible(find.text('Financial report'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Financial report'));
    expect(opened, contains('modules/reports/financial.php'));
  });

  testWidgets('legacy finance aliases do not see disbursements denied by the web guard', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'fm',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Fina Al-Khair dashboard'), findsOneWidget);
    expect(find.text('Monthly disbursements'), findsNothing);
  });

  testWidgets('nanny tools expose returned-fund follow-up but not HR controls', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(child: RoleToolsSection(
          roleCode: 'nanny',
          english: false,
          onOpen: (_) {},
        )),
      ),
    ));

    expect(find.text('إعادة صرف المبالغ المرتجعة'), findsOneWidget);
    expect(find.text('مركز التقارير'), findsOneWidget);
    expect(find.text('بناء العقود'), findsNothing);
    expect(find.text('سياسة الرواتب'), findsNothing);
  });

  testWidgets('staff sees request queues and profile without Administration-only tools', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'staff',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('New sponsor requests'), findsOneWidget);
    expect(find.text('Contacted sponsor requests'), findsOneWidget);
    expect(find.text('My profile'), findsOneWidget);
    expect(find.text('Families available for sponsorship'), findsNothing);
    expect(find.text('Sponsor win-back'), findsNothing);
  });

  testWidgets('available families is native for Administration role', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: SingleChildScrollView(
      child: RoleToolsSection(roleCode: 'administration', english: true, onOpen: opened.add),
    ))));
    expect(find.text('Families available for sponsorship'), findsOneWidget);
    await tester.tap(find.text('Families available for sponsorship'));
    expect(opened, contains('modules/administration/available_families.php'));
  });

  testWidgets('HR workspace exposes reporting and the audited HR tool list', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'hr_manager',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Report center'), findsOneWidget);
    expect(find.text('Payroll integrity'), findsOneWidget);
    expect(find.text('Review current employment states and history (read-only)'), findsOneWidget);
    expect(find.text('Read-only checks for payroll and accounting consistency'), findsOneWidget);
    expect(find.text('Password recovery requests'), findsOneWidget);
    expect(find.text('Contract builder'), findsOneWidget);
    expect(find.text('My profile'), findsNothing);
  });

  testWidgets('Accountant Staff group disbursements alias is native', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'accountant_staff',
          english: true,
          onOpen: opened.add,
        ),
      ),
    ));

    expect(find.text('Group disbursements'), findsOneWidget);
    final card = find.ancestor(of: find.text('Group disbursements'), matching: find.byType(Card));
    expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget);
    await tester.ensureVisible(find.text('Group disbursements'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Group disbursements'));
    expect(opened, contains('modules/accounting/group_disbursements.php'));
  });

  testWidgets('accountant role does not see disbursement routes denied by the web guard', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'accountant',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Report center'), findsOneWidget);
    expect(find.text('GM reconciliation'), findsNothing);
    expect(find.text('Monthly disbursements'), findsNothing);
    expect(find.text('Group disbursements'), findsNothing);
  });

  testWidgets('HR staff sees operational tools but not HR-manager-only controls', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'hr_staff',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Employee management'), findsOneWidget);
    expect(find.text('Attendance management'), findsOneWidget);
    expect(find.text('Employment states'), findsNothing);
    expect(find.text('Incentive policy'), findsNothing);
    expect(find.text('Payroll reversal'), findsNothing);
    expect(find.text('Incentive verification'), findsNothing);
    expect(find.text('Attendance policy'), findsNothing);
    expect(find.text('Payroll integrity'), findsNothing);
    expect(find.text('Password recovery requests'), findsNothing);
  });

  testWidgets('supervisor tools do not offer the FM-only Fina collection creation page', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'supervisor',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Review Fina collections'), findsOneWidget);
    expect(find.text('Monthly disbursements'), findsNothing);
    expect(find.text('Record Fina collection'), findsNothing);
  });

  testWidgets('finance manager gets native Fina registration and review tools', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'financial_manager',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Fina Al-Khair dashboard'), findsOneWidget);
    expect(find.text('Record Fina collection'), findsOneWidget);
    expect(find.text('Review Fina collections'), findsOneWidget);
    expect(find.text('Monthly disbursements'), findsOneWidget);
  });

  testWidgets('VGM suspension cases are labelled as a native app workflow', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SingleChildScrollView(
        child: RoleToolsSection(
          roleCode: 'vice_general_manager',
          english: true,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('Manage sponsorship suspensions'), findsOneWidget);
    expect(find.text('APP'), findsOneWidget);
  });

  testWidgets('GM reconciliation is native for the roles allowed by the web report registry', (tester) async {
    for (final role in ['general_manager', 'vice_general_manager', 'financial_manager']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(
              roleCode: role,
              english: true,
              onOpen: opened.add,
            ),
          ),
        ),
      ));

      expect(find.text('GM reconciliation'), findsOneWidget, reason: role);
      final card = find.ancestor(of: find.text('GM reconciliation'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: role);
      await tester.ensureVisible(find.text('GM reconciliation'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('GM reconciliation'));
      expect(opened, contains('modules/accounting/gm_reconciliation.php'), reason: role);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });

  testWidgets('GM sees the salary-advance waiver workflow from the web sidebar', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'gm',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Salary advance waivers'), findsOneWidget);
  });

  testWidgets('VGM orphan-group management is native and dispatches to the in-app workflow', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'vice_general_manager',
            english: true,
            onOpen: opened.add,
          ),
        ),
      ),
    ));

    expect(find.text('Orphan groups'), findsOneWidget);
    final card = find.ancestor(of: find.text('Orphan groups'), matching: find.byType(Card));
    expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget);
    await tester.ensureVisible(find.text('Orphan groups'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Orphan groups'));
    expect(opened, contains('modules/deputy_gm/groups.php'));
  });

  testWidgets('FM sees opening balance and assigned nannies', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'financial_manager',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Opening balance'), findsOneWidget);
    expect(find.text('Assigned nannies'), findsOneWidget);
  });

  testWidgets('accountant sees permitted vouchers and assigned-nanny tools but not denied disbursements', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'accountant',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Vouchers'), findsNothing);
    expect(find.text('Assigned nannies'), findsOneWidget);
    expect(find.text('GM reconciliation'), findsNothing);
    expect(find.text('Monthly disbursements'), findsNothing);
    expect(find.text('Group disbursements'), findsNothing);
  });

  testWidgets('accountant staff sees vouchers, assigned nannies and orphan forms', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'accountant_staff',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Vouchers'), findsOneWidget);
    expect(find.text('Assigned nannies'), findsOneWidget);
    expect(find.text('Orphan forms'), findsOneWidget);
  });

  testWidgets('supervisor and nanny roles see their permitted orphan forms tool', (tester) async {
    for (final role in ['supervisor', 'nanny']) {
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(
              roleCode: role,
              english: true,
              onOpen: (_) {},
            ),
          ),
        ),
      ));
      expect(find.text('Orphan forms'), findsOneWidget, reason: role);
    }
  });

  testWidgets('staff profile tool is labelled in-app and opens the native profile route', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'staff',
            english: true,
            onOpen: opened.add,
          ),
        ),
      ),
    ));

    final card = find.ancestor(of: find.text('My profile'), matching: find.byType(Card));
    expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget);
    await tester.ensureVisible(find.text('My profile'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('My profile'));
    expect(opened, contains('modules/users/profile.php'));
  });

  testWidgets('roles without extra website tools render no empty section', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(
        body: RoleToolsSection(
          roleCode: 'unknown_role',
          english: true,
          onOpen: _ignorePath,
        ),
      ),
    ));

    expect(find.text('Additional role tools'), findsNothing);
  });

  testWidgets('salary-advance waiver is marked native for the existing GM and FM roles', (tester) async {
    for (final entry in <String, String>{
      'general_manager': 'modules/hr/salary_advance_waiver_gm.php',
      'financial_manager': 'modules/hr/salary_advance_waiver_fm.php',
      'fm': 'modules/hr/salary_advance_waiver_fm.php',
    }.entries) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(
              roleCode: entry.key,
              english: true,
              onOpen: opened.add,
            ),
          ),
        ),
      ));
      expect(find.text('Salary advance waivers'), findsOneWidget, reason: entry.key);
      final card = find.ancestor(of: find.text('Salary advance waivers'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: entry.key);
      await tester.ensureVisible(find.text('Salary advance waivers'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Salary advance waivers'));
      expect(opened, contains(entry.value), reason: entry.key);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });


  testWidgets('ordinary financial transaction review is marked native for Financial Manager', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'financial_manager',
            english: true,
            onOpen: opened.add,
          ),
        ),
      ),
    ));
    expect(find.text('Financial transaction review'), findsOneWidget);
    final card = find.ancestor(of: find.text('Financial transaction review'), matching: find.byType(Card));
    expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget);
    await tester.ensureVisible(find.text('Financial transaction review'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Financial transaction review'));
    expect(opened, contains('modules/accounting/fm_transaction_review.php'));
  });


  testWidgets('salary portal is marked native for its existing web roles', (tester) async {
    for (final role in ['financial_manager', 'admin', 'fm', 'hr_manager', 'hr_staff']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(roleCode: role, english: true, onOpen: opened.add),
          ),
        ),
      ));
      expect(find.text('Payroll and incentives portal'), findsOneWidget, reason: role);
      final card = find.ancestor(of: find.text('Payroll and incentives portal'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: role);
      await tester.ensureVisible(find.text('Payroll and incentives portal'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Payroll and incentives portal'));
      expect(opened, contains('modules/hr/salary_portal.php'), reason: role);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });

  testWidgets('Trial balance is native only for the exact web-authorized roles', (tester) async {
    for (final role in ['admin', 'financial_manager']) {
      final opened = <String>[];
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(roleCode: role, english: true, onOpen: opened.add),
          ),
        ),
      ));
      expect(find.text('Trial balance'), findsOneWidget, reason: role);
      final card = find.ancestor(of: find.text('Trial balance'), matching: find.byType(Card));
      expect(find.descendant(of: card, matching: find.text('IN APP')), findsOneWidget, reason: role);
      await tester.ensureVisible(find.text('Trial balance'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Trial balance'));
      expect(opened, contains('modules/accounting/trial_balance.php'), reason: role);
      await tester.pumpWidget(const SizedBox.shrink());
    }

    for (final role in ['fm', 'finance', 'general_manager', 'vice_general_manager']) {
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: RoleToolsSection(roleCode: role, english: true, onOpen: (_) {}),
          ),
        ),
      ));
      expect(find.text('Trial balance'), findsNothing, reason: role);
    }
  });

}

void _ignorePath(String path) {}
