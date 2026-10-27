import 'package:ahl_el_kheir_app/trial_balance_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shows posted account balances and independent trial totals', (tester) async {
    var calls = 0;
    final requestedRanges = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: TrialBalancePage(
        english: true,
        loadReport: (from, to) async {
          calls++;
          requestedRanges.add('$from|$to');
          return {
            'from': from,
            'to': to,
            'accounts': [
              {
                'id': 1,
                'code': '1100',
                'name_ar': 'النقدية',
                'name_en': 'Cash',
                'account_type': 'asset',
                'debit_total': 1000.0,
                'credit_total': 0.0,
              },
            ],
            'totals': {
              'total_debit': 1000.0,
              'total_credit': 1000.0,
              'difference': 0.0,
              'is_balanced': true,
              'journal_count': 2,
              'line_count': 4,
            },
          };
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(calls, 1);
    expect(requestedRanges, ['|']);
    expect(find.text('Trial balance'), findsOneWidget);
    expect(find.text('Total debit'), findsOneWidget);
    expect(find.text('Total credit'), findsOneWidget);
    expect(find.text('Balanced'), findsOneWidget);
    final listScrollable = find.descendant(
      of: find.byType(ListView).first,
      matching: find.byType(Scrollable),
    ).first;
    await tester.scrollUntilVisible(find.text('1100'), 300, scrollable: listScrollable);
    expect(find.text('1100'), findsOneWidget);
    expect(find.text('Cash'), findsOneWidget);
    expect(find.text('1,000.00'), findsNothing);
    expect(find.text('1000.00'), findsWidgets);
    await tester.scrollUntilVisible(
      find.textContaining('Journal entries: 2'),
      300,
      scrollable: listScrollable,
    );
    expect(find.textContaining('Journal entries: 2'), findsOneWidget);

    await tester.dragUntilVisible(find.text('Apply filters'), listScrollable, const Offset(0, 500));
    await tester.tap(find.text('Apply filters'));
    await tester.pumpAndSettle();
    expect(calls, 2);
    expect(requestedRanges.last, '|');
  });

  testWidgets('allows retry after the report API fails', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
      home: TrialBalancePage(
        english: false,
        loadReport: (from, to) async {
          calls++;
          if (calls == 1) throw Exception('offline');
          return {
            'accounts': [],
            'totals': {
              'total_debit': 0,
              'total_credit': 0,
              'difference': 0,
              'is_balanced': true,
              'journal_count': 0,
              'line_count': 0,
            },
          };
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('إعادة المحاولة'), findsOneWidget);
    await tester.tap(find.text('إعادة المحاولة'));
    await tester.pumpAndSettle();
    expect(calls, 2);
    final listScrollable = find.descendant(
      of: find.byType(ListView).first,
      matching: find.byType(Scrollable),
    ).first;
    await tester.scrollUntilVisible(
      find.text('لا توجد حركات مُرحّلة ضمن الفترة المحددة.'),
      300,
      scrollable: listScrollable,
    );
    expect(find.text('لا توجد حركات مُرحّلة ضمن الفترة المحددة.'), findsOneWidget);
  });
}
