import 'package:ahl_el_kheir_app/fina_collection_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('native Fina form submits an external-source collection payload', (tester) async {
    Map<String, dynamic>? submitted;

    await tester.pumpWidget(MaterialApp(
      home: FinaCollectionPage(
        english: false,
        loadSponsors: (_) async => {'sponsors': <Map<String, dynamic>>[]},
        submitCollection: (payload) async {
          submitted = Map<String, dynamic>.from(payload);
          return {'id': 123, 'status': 'pending', 'receipt_supported': true, 'receipt_attached': false};
        },
      ),
    ));

    await tester.enterText(find.widgetWithText(TextField, 'اسم مصدر الأموال *'), 'متبرع تجريبي');
    await tester.enterText(find.widgetWithText(TextField, 'المبلغ *'), '125.50');
    await tester.tap(find.widgetWithText(FilledButton, 'إرسال للمراجعة المالية'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(submitted, isNotNull);
    expect(submitted!['source_type'], 'person');
    expect(submitted!['source_name'], 'متبرع تجريبي');
    expect(submitted!['amount'], '125.50');
    expect(submitted!['method'], 'cash');
    expect(submitted!['receipt_path'], isNull);
    expect(find.text('تم تسجيل التحصيل وإرساله للمراجعة.'), findsOneWidget);
  });
}
