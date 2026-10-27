import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/fina_report_page.dart';

void main() {
  testWidgets('Fina report renders review totals, currency, methods and sources', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: FinaReportPage(
        english: true,
        loadReport: (from, to) async => {
          'from': from,
          'to': to,
          'currency_code': 'SDG',
          'supervisor_own_only': true,
          'reviewed': {
            'total_count': 4,
            'approved_count': 2,
            'returned_count': 1,
            'approved_amount': '350.00',
            'returned_amount': '50.00',
          },
          'summary': [
            {
              'currency_code': 'SDG',
              'total_count': 4,
              'total_amount': '500.00',
              'pending_count': 1,
              'pending_amount': '100.00',
              'approved_count': 2,
              'approved_amount': '350.00',
              'returned_count': 1,
              'returned_amount': '50.00',
            },
          ],
          'methods': [
            {
              'payment_method': 'cash',
              'total_count': 3,
              'total_amount': '300.00',
              'pending_count': 1,
              'approved_count': 1,
              'returned_count': 1,
            },
          ],
          'sources': [
            {
              'source_type': 'person',
              'total_count': 4,
              'total_amount': '350.00',
              'approved_count': 2,
              'returned_count': 1,
            },
          ],
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Fina collection report'), findsOneWidget);
    expect(find.text('This report includes only collections you registered.'), findsOneWidget);
    expect(find.textContaining('Total records: 4'), findsOneWidget);
    expect(find.textContaining('Approved total: 350.00 SDG'), findsOneWidget);
    expect(find.text('Currency summary SDG'), findsOneWidget);
    expect(find.text('By payment method'), findsOneWidget);
    expect(find.text('Cash'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('By funding source'), 300);
    expect(find.text('By funding source'), findsOneWidget);
    expect(find.text('External person'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Fina report exposes retry after an API error', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
      home: FinaReportPage(
        english: true,
        loadReport: (from, to) async {
          calls++;
          if (calls == 1) throw StateError('API unavailable');
          return {
            'from': from,
            'to': to,
            'currency_code': 'SDG',
            'reviewed': {'total_count': 0, 'approved_count': 0, 'returned_count': 0, 'approved_amount': 0, 'returned_amount': 0},
            'summary': [],
            'methods': [],
            'sources': [],
          };
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('API unavailable'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(calls, 2);
    expect(find.text('No data for this period.'), findsNWidgets(2));
    expect(tester.takeException(), isNull);
  });
}
