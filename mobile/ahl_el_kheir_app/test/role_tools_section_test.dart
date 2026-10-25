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
    expect(find.text('WEB'), findsWidgets);
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
    expect(find.text('Record Fina collection'), findsNothing);
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
