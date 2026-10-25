import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/suspension_cases_page.dart';

void main() {
  Map<String, dynamic> sampleCase(String status) => {
    'id': 17,
    'entity_type': 'child',
    'entity_id': 9,
    'entity_name': 'أحمد محمد',
    'family_code': 'FAM-009',
    'reason': 'age_out',
    'notes': 'اختبار',
    'status': status,
    'suspended_at': '2026-10-20 10:00:00',
    'lifted_at': status == 'lifted' ? '2026-10-25 10:00:00' : '',
    'suspended_by_name': 'Admin',
    'lifted_by_name': status == 'lifted' ? 'Admin' : '',
  };

  testWidgets('authorized manager confirms lift and refreshes the case', (tester) async {
    var lifted = false;
    var liftCalls = 0;
    await tester.pumpWidget(MaterialApp(
      home: SuspensionCasesPage(
        english: true,
        canLift: true,
        loadCases: () async => {
          'items': [sampleCase(lifted ? 'lifted' : 'active')],
          'can_lift': true,
        },
        liftCase: (id) async {
          expect(id, 17);
          liftCalls++;
          lifted = true;
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Sponsorship suspensions'), findsOneWidget);
    expect(find.text('Reached legal age'), findsOneWidget);
    expect(find.text('Lift suspension'), findsOneWidget);

    await tester.tap(find.text('Lift suspension'));
    await tester.pumpAndSettle();
    expect(find.text('Lift suspension?'), findsOneWidget);
    await tester.tap(find.text('Confirm lift'));
    await tester.pumpAndSettle();

    expect(liftCalls, 1);
    expect(find.text('Lifted'), findsOneWidget);
    expect(find.text('Lift suspension'), findsNothing);
  });

  testWidgets('read-only role sees cases without a lift action', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SuspensionCasesPage(
        english: false,
        canLift: false,
        loadCases: () async => {'items': [sampleCase('active')], 'can_lift': false},
        liftCase: (_) async => fail('Read-only role must not call lift'),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('حالات تعليق الكفالات'), findsOneWidget);
    expect(find.text('رفع التعليق'), findsNothing);
    expect(find.text('بلوغ السن القانوني'), findsOneWidget);
  });
}
