import 'package:ahl_el_kheir_app/fm_review_queue_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('renders review queue and requires a reason before return', (tester) async {
    String? submittedAction;
    String? submittedNote;
    int? submittedId;

    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: FmReviewQueuePage(
          english: false,
          loadQueue: () async => {
            'pending': [
              {
                'id': 41,
                'purpose_label': 'كفالة شهرية',
                'amount': 12000.0,
                'currency_code': 'SDG',
                'supervisor_name': 'مشرف الاختبار',
                'sponsor_name': 'كفيل الاختبار',
                'child_name': 'يتيم الاختبار',
                'payment_period': 'October/2026',
                'created_at': '2026-10-24 12:00:00',
                'has_receipt': true,
                'has_unified_receipt': false,
                'status': 'pending',
              },
            ],
            'history': [
              {
                'id': 40,
                'purpose_label': 'تبرع عام',
                'amount': 500.0,
                'currency_code': 'SDG',
                'supervisor_name': 'مشرف سابق',
                'sponsor_name': 'كفيل سابق',
                'reviewer_name': 'مراجع',
                'reviewed_at': '2026-10-23 10:00:00',
                'status': 'returned',
                'return_note': 'يرجى إرفاق الإيصال',
              },
            ],
          },
          submitAction: (id, action, note) async {
            submittedId = id;
            submittedAction = action;
            submittedNote = note;
          },
        ),
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('طابور المراجعة المالية'), findsOneWidget);
    expect(find.textContaining('تحصيل #41'), findsOneWidget);
    expect(find.textContaining('12000.00 SDG'), findsOneWidget);
    expect(find.textContaining('يوجد إيصال مرفق'), findsOneWidget);
    expect(find.text('اعتماد وترحيل'), findsOneWidget);

    await tester.tap(find.text('إرجاع للتصحيح'));
    await tester.pumpAndSettle();
    expect(submittedAction, isNull);
    expect(find.text('اكتب سبب الإرجاع قبل إعادة التحصيل.'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'يرجى إرفاق إيصال أوضح');
    await tester.tap(find.text('إرجاع للتصحيح'));
    await tester.pumpAndSettle();
    expect(submittedId, 41);
    expect(submittedAction, 'return');
    expect(submittedNote, 'يرجى إرفاق إيصال أوضح');

    await tester.tap(find.text('سجل المراجعات'));
    await tester.pumpAndSettle();
    expect(find.textContaining('تحصيل #40'), findsOneWidget);
    expect(find.textContaining('يرجى إرفاق الإيصال'), findsOneWidget);
  });
}
