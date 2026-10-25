import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/role_tools_section.dart';

void main() {
  testWidgets('GM tools expose executive reporting and approval routes', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: RoleToolsSection(
          roleCode: 'general_manager',
          english: true,
          onOpen: (_) {},
        ),
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
        body: RoleToolsSection(
          roleCode: 'nanny',
          english: false,
          onOpen: (_) {},
        ),
      ),
    ));

    expect(find.text('إعادة صرف المبالغ المرتجعة'), findsOneWidget);
    expect(find.text('مركز التقارير'), findsOneWidget);
    expect(find.text('بناء العقود'), findsNothing);
    expect(find.text('سياسة الرواتب'), findsNothing);
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
