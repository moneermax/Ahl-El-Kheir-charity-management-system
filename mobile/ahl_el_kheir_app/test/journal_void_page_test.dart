import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/journal_page.dart';

void main() {
  testWidgets('authorized different reviewer can void a manual entry and see the reversal result', (tester) async {
    var voided = false;
    int? submittedId;
    String? submittedReason;
    await tester.pumpWidget(MaterialApp(
      home: JournalEntryDetailPage(
        english: true,
        entryId: 7,
        roleCode: 'admin',
        currentUserId: 1,
        loadEntry: (id) async => {
          'entry': {
            'id': id,
            'entry_code': 'JE-000007',
            'entry_date': '2026-10-26',
            'description': 'Manual test entry',
            'reference_type': 'manual',
            'status': 'posted',
            'created_by': 2,
            'creator': 'Finance Manager',
            'total_debit': 125.0,
            'total_credit': 125.0,
            'line_count': 2,
            'is_balanced': true,
            'voided_at': voided ? '2026-10-26 12:00:00' : null,
            'void_reason': voided ? 'Reviewed correction' : null,
          },
          'lines': [
            {'id': 1, 'account_id': 1, 'account_code': '1100', 'account_name_en': 'Cash', 'account_name_ar': 'الصندوق', 'debit': 125.0, 'credit': 0.0, 'description': 'Debit'},
            {'id': 2, 'account_id': 2, 'account_code': '4100', 'account_name_en': 'Revenue', 'account_name_ar': 'الإيرادات', 'debit': 0.0, 'credit': 125.0, 'description': 'Credit'},
          ],
        },
        submitVoid: (id, reason) async {
          submittedId = id;
          submittedReason = reason;
          voided = true;
          return {'result': {'entry_id': id, 'reversal_entry_code': 'JE-VOID-MANUAL-7'}};
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Void entry'), findsOneWidget);
    await tester.tap(find.text('Void entry'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Reviewed correction');
    await tester.tap(find.text('Confirm void'));
    await tester.pumpAndSettle();

    expect(submittedId, 7);
    expect(submittedReason, 'Reviewed correction');
    expect(find.text('The entry was voided and a balanced reversal was created.'), findsOneWidget);
    expect(find.text('Voided: Reviewed correction'), findsOneWidget);
    expect(find.text('Void entry'), findsNothing);
  });

  testWidgets('journal creator cannot see the void action for their own entry', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: JournalEntryDetailPage(
        english: true,
        entryId: 7,
        roleCode: 'financial_manager',
        currentUserId: 2,
        loadEntry: (id) async => {
          'entry': {
            'id': id,
            'entry_code': 'JE-000007',
            'entry_date': '2026-10-26',
            'description': 'Own manual entry',
            'reference_type': 'manual',
            'status': 'posted',
            'created_by': 2,
            'creator': 'Finance Manager',
            'total_debit': 125.0,
            'total_credit': 125.0,
            'line_count': 2,
            'is_balanced': true,
            'voided_at': null,
            'void_reason': null,
          },
          'lines': <Map<String, dynamic>>[],
        },
        submitVoid: (_, __) async => <String, dynamic>{},
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Void entry'), findsNothing);
  });
}
