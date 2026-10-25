import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/orphaned_families_report_page.dart';

void main() {
  testWidgets('uncovered family report filters and opens family details natively', (tester) async {
    final calls = <String>[];
    int? openedFamily;
    await tester.pumpWidget(MaterialApp(home: OrphanedFamiliesReportPage(
      english: true,
      loadReport: (city, status) async {
        calls.add('$city:$status');
        return {
          'items': [{
            'id': 7, 'family_code': 'FAM-0007', 'mother_name': 'Test Mother',
            'city': 'Omdurman', 'monthly_need_amount': 1200,
            'status': status == 'all' ? 'pending' : status,
            'actual_children_count': 2, 'active_children': 1,
          }],
          'cities': ['Omdurman', 'Khartoum'],
          'total': 1,
          'active_children_waiting': 1,
        };
      },
      openFamily: (id) => openedFamily = id,
    )));
    await tester.pumpAndSettle();
    expect(find.text('Uncovered families report'), findsOneWidget);
    expect(find.text('Test Mother'), findsOneWidget);
    expect(calls.first, ':active');

    await tester.tap(find.byTooltip('View family'));
    expect(openedFamily, 7);
    expect(tester.takeException(), isNull);
  });
}
