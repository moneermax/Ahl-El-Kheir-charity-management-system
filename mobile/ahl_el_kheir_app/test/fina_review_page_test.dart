import 'package:ahl_el_kheir_app/fina_review_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('native Fina review shows pending/history and blocks self-review', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: FinaReviewPage(
        english: false,
        loadReview: () async => {
          'can_review': true,
          'current_user_id': 9,
          'pending': [
            {
              'id': 22,
              'created_by': 9,
              'source_type': 'person',
              'source_name': 'مصدر تجريبي',
              'amount': '200.00',
              'currency_code': 'SDG',
              'payment_method': 'cash',
              'collection_date': '2026-10-26',
              'status': 'pending',
              'receipt_path': null,
            },
          ],
          'history': [
            {
              'id': 21,
              'created_by': 8,
              'source_type': 'organization',
              'source_name': 'جهة سابقة',
              'amount': '100.00',
              'currency_code': 'SDG',
              'payment_method': 'bank_transfer',
              'collection_date': '2026-10-25',
              'status': 'approved',
              'reviewer_name': 'مراجع',
              'accounting_journal_id': 44,
            },
          ],
        },
        submitAction: (_) async => throw StateError('Self-review must not be submitted'),
      ),
    ));

    await tester.pumpAndSettle();

    expect(find.textContaining('مصدر تجريبي'), findsOneWidget);
    expect(find.text('لا يمكنك مراجعة تحصيل سجلته بنفسك.'), findsOneWidget);
    expect(find.text('اعتماد وترحيل'), findsNothing);
    await tester.scrollUntilVisible(find.textContaining('جهة سابقة'), 250);
    expect(find.textContaining('جهة سابقة'), findsOneWidget);
    expect(find.textContaining('القيد المحاسبي: #44'), findsOneWidget);
  });
}
