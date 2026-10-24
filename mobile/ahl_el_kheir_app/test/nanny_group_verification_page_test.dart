import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/nanny_group_verification_page.dart';

void main() {
  testWidgets('nanny can open an assigned group and save individual verification', (tester) async {
    final actions = <Map<String, dynamic>>[];
    final loadedGroups = <int?>[];
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: NannyGroupVerificationPage(
          english: false,
          loadData: (month, groupId) async {
            loadedGroups.add(groupId);
            if (groupId == null) {
              return {
                'month': month,
                'groups': [{
                  'id': 5,
                  'group_name': 'مجموعة الحاضنة الأولى',
                  'description': '',
                  'verification_status': 'pending',
                  'can_bulk_verify': true,
                  'can_submit': true,
                }],
                'families': [],
                'counts': {},
              };
            }
            return {
              'month': month,
              'groups': [{
                'id': 5,
                'group_name': 'مجموعة الحاضنة الأولى',
                'description': '',
                'verification_status': 'pending',
                'can_bulk_verify': true,
                'can_submit': true,
              }],
              'selected_group': {
                'id': 5,
                'group_name': 'مجموعة الحاضنة الأولى',
                'verification_status': 'pending',
                'can_bulk_verify': true,
                'can_submit': true,
              },
              'families': [{
                'id': 21,
                'family_code': 'FAM-0021',
                'mother_name': 'أم الأسرة',
                'amount': 2500,
                'is_orphan_verified': true,
                'is_mother_contact_verified': false,
                'is_bank_verified': false,
                'verification_notes': '',
                'is_fully_verified': false,
              }],
              'counts': {
                'verified_count': 0,
                'unverified_count': 1,
                'verified_amount': 0,
                'unverified_amount': 2500,
              },
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
    expect(find.text('توثيق مجموعات الحاضنات'), findsOneWidget);
    expect(find.text('مجموعة الحاضنة الأولى'), findsOneWidget);

    await tester.tap(find.text('مجموعة الحاضنة الأولى').first);
    await tester.pumpAndSettle();
    expect(find.text('FAM-0021 · أم الأسرة'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.edit_outlined).first);
    await tester.pumpAndSettle();
    expect(find.text('توثيق الأسرة'), findsOneWidget);
    await tester.tap(find.text('حفظ').last);
    await tester.pumpAndSettle();

    expect(actions, hasLength(1));
    expect(actions.single['action'], 'verify_family');
    expect(actions.single['group_id'], 5);
    expect(actions.single['family_id'], 21);
    expect(actions.single['is_orphan_verified'], true);
    expect(actions.single['is_mother_contact_verified'], false);
    expect(loadedGroups, contains(5));
  });

  testWidgets('closed group hides bulk actions but permits individual verification', (tester) async {
    final actions = <Map<String, dynamic>>[];
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: NannyGroupVerificationPage(
          english: false,
          loadData: (month, groupId) async => {
            'month': month,
            'groups': [{
              'id': 8,
              'group_name': 'مجموعة مغلقة',
              'verification_status': 'closed',
              'can_bulk_verify': false,
              'can_submit': false,
            }],
            if (groupId != null) 'selected_group': {
              'id': 8,
              'group_name': 'مجموعة مغلقة',
              'verification_status': 'closed',
              'can_bulk_verify': false,
              'can_submit': false,
            },
            'families': groupId == null ? [] : [{
              'id': 22,
              'family_code': 'FAM-0022',
              'mother_name': 'وليّة الأمر',
              'amount': 1200,
              'is_orphan_verified': false,
              'is_mother_contact_verified': true,
              'is_bank_verified': true,
              'verification_notes': '',
              'is_fully_verified': false,
            }],
            'counts': {'verified_count': 0, 'unverified_count': 1, 'verified_amount': 0, 'unverified_amount': 1200},
          },
          submitAction: (body) async {
            actions.add(Map<String, dynamic>.from(body));
            return {'message': 'تم الحفظ'};
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('مجموعة مغلقة').first);
    await tester.pumpAndSettle();

    expect(find.text('توثيق الكل'), findsNothing);
    expect(find.text('إرسال للمحاسبة'), findsNothing);
    expect(find.text('FAM-0022 · وليّة الأمر'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.edit_outlined).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('حفظ').last);
    await tester.pumpAndSettle();

    expect(actions, hasLength(1));
    expect(actions.single['action'], 'verify_family');
    expect(actions.single['group_id'], 8);
    expect(actions.single['family_id'], 22);
  });

}
