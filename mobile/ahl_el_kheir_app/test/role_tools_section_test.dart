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

  testWidgets('VGM sees orphan-group management', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleToolsSection(
            roleCode: 'vice_general_manager',
            english: true,
            onOpen: (_) {},
          ),
        ),
      ),
    ));

    expect(find.text('Orphan groups'), findsOneWidget);
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
    expect(find.text('GM reconciliation'), findsOneWidget);
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
}

void _ignorePath(String path) {}
