import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/projects_page.dart';

void main() {
  testWidgets('shows Arabic project portfolio and summary values', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: ProjectsPage(
          english: false,
          loadDetail: (_) async => {'project': {}, 'team': [], 'milestones': [], 'history': []},
          loadPage: (_) async => {
            'items': [{
              'id': 24,
              'project_code': 'PRJ-0024',
              'name': 'الإيواء والتأهيل السكني',
              'current_status': 'closed',
              'approval_status': 'approved',
              'budget': 10000.0,
              'funded': 10000.0,
              'expenses': 10000.0,
              'beneficiary_count': 2,
              'supervisor_name': 'مشرف المشروع',
            }],
            'total': 1,
            'page': 1,
            'pages': 1,
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('المشروعات'), findsOneWidget);
    expect(find.text('PRJ-0024'), findsOneWidget);
    expect(find.text('الإيواء والتأهيل السكني'), findsOneWidget);
    expect(find.text('مغلق'), findsOneWidget);
    expect(find.text('الميزانية'), findsOneWidget);
    expect(find.text('المصروفات'), findsOneWidget);
  });
}
