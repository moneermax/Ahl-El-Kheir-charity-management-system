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

  testWidgets('shows retry action when role dashboard API fails', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: RoleDashboardPage(
            english: true,
            roleCode: 'general_manager',
            loadOverview: () async => throw Exception('API unavailable'),
          ),
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Dashboard data could not be loaded.'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
  });
}
