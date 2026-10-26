import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/journal_page.dart';

void main() {
  testWidgets('journal list loads with date filters and opens a read-only entry detail', (tester) async {
    String? lastFrom;
    String? lastTo;
    await tester.pumpWidget(MaterialApp(
      home: JournalPage(
        english: true,
        loadEntries: ({required from, required to}) async {
          lastFrom = from;
          lastTo = to;
          return {
            'entries': [
              {
                'id': 7,
                'entry_code': 'JE-000007',
                'entry_date': '2026-10-26',
                'description': 'Test balanced journal entry',
                'reference_type': 'manual',
                'status': 'posted',
                'creator': 'Finance Manager',
                'total_debit': 150.0,
                'total_credit': 150.0,
                'line_count': 2,
                'is_balanced': true,
                'voided_at': null,
              },
            ],
          };
        },
        loadEntry: (id) async => {
          'entry': {
            'id': id,
            'entry_code': 'JE-000007',
            'entry_date': '2026-10-26',
            'description': 'Test balanced journal entry',
            'reference_type': 'manual',
            'status': 'posted',
            'creator': 'Finance Manager',
            'total_debit': 150.0,
            'total_credit': 150.0,
            'line_count': 2,
            'is_balanced': true,
            'voided_at': null,
          },
          'lines': [
            {
              'id': 1,
              'account_id': 1,
              'account_code': '1100',
              'account_name_ar': 'الصندوق',
              'account_name_en': 'Cash',
              'debit': 150.0,
              'credit': 0.0,
              'description': 'Cash debit',
            },
            {
              'id': 2,
              'account_id': 2,
              'account_code': '4000',
              'account_name_ar': 'الإيرادات',
              'account_name_en': 'Revenue',
              'debit': 0.0,
              'credit': 150.0,
              'description': 'Revenue credit',
            },
          ],
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('JE-000007'), findsOneWidget);
    expect(find.text('Balanced'), findsOneWidget);
    expect(lastFrom, isEmpty);
    expect(lastTo, isEmpty);
    await tester.tap(find.text('JE-000007'));
    await tester.pumpAndSettle();

    expect(find.text('Journal lines'), findsOneWidget);
    expect(find.textContaining('1100'), findsOneWidget);
    expect(find.textContaining('4000'), findsOneWidget);
    expect(find.text('The entry is balanced.'), findsOneWidget);
    expect(lastTo, isEmpty);
  });
}
