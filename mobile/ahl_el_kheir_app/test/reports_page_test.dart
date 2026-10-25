import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/reports_page.dart';

void main() {
  testWidgets('reports center renders metrics and opens only native reports', (tester) async {
    String? opened;
    await tester.pumpWidget(MaterialApp(home: ReportsPage(
      english: true,
      loadOverview: () async => {
        'stats': {'sponsors': 12, 'families': 8, 'active_sponsorships': 10, 'monthly_commitment': 1500.5, 'orphaned_families': 3},
        'reports': [
          {'key': 'orphaned', 'label': 'تقرير الأسر غير المكفولة', 'description': 'Uncovered families', 'native': true},
          {'key': 'financial', 'label': 'التقارير المالية', 'description': 'Financial', 'native': false},
        ],
      },
      openReport: (key) => opened = key,
    )));
    await tester.pumpAndSettle();
    expect(find.text('Reports center'), findsOneWidget);
    expect(find.text('1,500.50'), findsNothing);
    expect(find.text('1500.50'), findsOneWidget);
    expect(find.text('Uncovered families report'), findsOneWidget);
    expect(find.text('Not implemented natively yet'), findsOneWidget);
    await tester.tap(find.text('Uncovered families report'));
    expect(opened, 'orphaned');
    expect(tester.takeException(), isNull);
  });
}
