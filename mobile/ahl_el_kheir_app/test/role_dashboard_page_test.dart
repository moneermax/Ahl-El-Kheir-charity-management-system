import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/role_dashboard_page.dart';

void main() {
  testWidgets('renders role-specific metrics with Arabic labels', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleDashboardPage(
            english: false,
            roleCode: 'nanny',
            loadOverview: () async => {
              'metrics': [
                {'key': 'total_families', 'label_ar': 'الأسر المسندة', 'label_en': 'Assigned families', 'value': 12},
                {'key': 'pending_batches', 'label_ar': 'دفعات محولة بانتظار التأكيد', 'label_en': 'Transferred batches awaiting confirmation', 'value': 3},
              ],
            },
            onOpenTool: (_) {},
          ),
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('مؤشرات لوحة التحكم'), findsOneWidget);
    expect(find.text('الأسر المسندة'), findsOneWidget);
    expect(find.text('دفعات محولة بانتظار التأكيد'), findsOneWidget);
    expect(find.text('12'), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    expect(tester.takeException(), isNull, reason: 'Metric cards must fit without RenderFlex overflow.');
  });


  testWidgets('GM attention queue shows positive native indicators and opens the in-app route', (tester) async {
    final opened = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleDashboardPage(
            english: true,
            roleCode: 'general_manager',
            loadOverview: () async => {
              'metrics': [
                {'key': 'pending_families', 'label_ar': 'أسر بانتظار الإجراء', 'label_en': 'Families awaiting action', 'value': 6},
                {'key': 'uncovered_families', 'label_ar': 'أسر بلا كفالة نشطة', 'label_en': 'Families without active sponsorship', 'value': 4},
                {'key': 'paused_sponsorships', 'label_ar': 'كفالات متوقفة', 'label_en': 'Paused sponsorships', 'value': 0},
                {'key': 'open_winback', 'label_ar': 'متابعات استعادة الكفلاء', 'label_en': 'Open sponsor win-back', 'value': 2},
                {'key': 'unassigned_letters', 'label_ar': 'حروف غير مسندة', 'label_en': 'Unassigned letters', 'value': 0},
                {'key': 'open_sponsor_requests', 'label_ar': 'طلبات رعاية مفتوحة', 'label_en': 'Open sponsor requests', 'value': 3},
              ],
            },
            onOpenTool: opened.add,
          ),
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Needs your attention'), findsOneWidget);
    expect(find.ancestor(of: find.text('Families awaiting action'), matching: find.byType(ListTile)), findsOneWidget);
    expect(find.ancestor(of: find.text('Families without active sponsorship'), matching: find.byType(ListTile)), findsOneWidget);
    expect(find.ancestor(of: find.text('Open sponsor win-back cases'), matching: find.byType(ListTile)), findsOneWidget);
    expect(find.ancestor(of: find.text('Sponsor requests awaiting follow-up'), matching: find.byType(ListTile)), findsOneWidget);
    expect(find.ancestor(of: find.text('Paused sponsorships'), matching: find.byType(ListTile)), findsNothing);
    expect(find.ancestor(of: find.text('Unassigned supervisor letters'), matching: find.byType(ListTile)), findsNothing);

    await tester.ensureVisible(find.text('Families awaiting action'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Families awaiting action'));
    expect(opened, contains('modules/reports/operational.php'));

    await tester.ensureVisible(find.text('Open sponsor win-back cases'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Open sponsor win-back cases'));
    expect(opened, contains('modules/administration/winback.php'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('non-GM role dashboard does not show GM attention queue', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleDashboardPage(
            english: true,
            roleCode: 'nanny',
            loadOverview: () async => {
              'metrics': [
                {'key': 'uncovered_families', 'label_ar': 'أسر بلا كفالة نشطة', 'label_en': 'Families without active sponsorship', 'value': 4},
              ],
            },
            onOpenTool: (_) {},
          ),
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Needs your attention'), findsNothing);
  });

  testWidgets('shows retry action when role dashboard API fails', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleDashboardPage(
            english: true,
            roleCode: 'general_manager',
            loadOverview: () async => throw Exception('API unavailable'),
            onOpenTool: (_) {},
          ),
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Dashboard data could not be loaded.'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
  });
}
