import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/nanny_pre_verification_page.dart';

void main() {
  testWidgets('shows preliminary families and saves month-scoped individual checks', (tester) async {
    final actions = <Map<String, dynamic>>[];
    final months = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: NannyPreVerificationPage(
          english: false,
          loadData: (month) async {
            months.add(month);
            return {
              'month': month,
              'counts': {'total': 1, 'verified': 0, 'partial': 1, 'not_started': 0},
              'items': [{
                'id': 31,
                'family_code': 'FAM-0031',
                'mother_name': 'أم الأسرة',
                'group_names': 'المجموعة الأولى، المجموعة الثانية',
                'is_orphan_verified': true,
                'is_mother_contact_verified': false,
                'is_bank_verified': false,
                'verification_notes': '',
                'verified_checks': 1,
                'verification_status': 'partial',
              }],
            };
          },
          submitAction: (body) async {
            actions.add(Map<String, dynamic>.from(body));
            return {'message': 'تم الحفظ'};
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('التحقق المسبق للأسر'), findsOneWidget);
    expect(find.text('FAM-0031 · أم الأسرة'), findsOneWidget);
    expect(find.textContaining('المجموعة الأولى'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.edit_outlined).first);
    await tester.pumpAndSettle();
    expect(find.text('التوثيق المسبق للأسرة'), findsOneWidget);
    await tester.tap(find.text('حفظ').last);
    await tester.pumpAndSettle();

    expect(actions, hasLength(1));
    expect(actions.single['action'], 'verify_family');
    expect(actions.single['family_id'], 31);
    expect(actions.single['month'], matches(RegExp(r'^\d{4}-(0[1-9]|1[0-2])$')));
    expect(months, isNotEmpty);
  });

  testWidgets('bulk verification submits selected family IDs and operation', (tester) async {
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: NannyPreVerificationPage(
          english: false,
          loadData: (month) async => {
            'counts': {'total': 1, 'verified': 0, 'partial': 0, 'not_started': 1},
            'items': [{
              'id': 44,
              'family_code': 'FAM-0044',
              'mother_name': 'أسرة الاختبار',
              'group_names': 'مجموعة الاختبار',
              'is_orphan_verified': false,
              'is_mother_contact_verified': false,
              'is_bank_verified': false,
              'verified_checks': 0,
              'verification_status': 'not_started',
            }],
          },
          submitAction: (body) async {
            submitted = Map<String, dynamic>.from(body);
            return {'message': 'تم الحفظ'};
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.byType(Checkbox).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('توثيق الكل'));
    await tester.pumpAndSettle();

    expect(submitted?['action'], 'bulk_verify');
    expect(submitted?['operation'], 'all');
    expect(submitted?['family_ids'], [44]);
  });
}
