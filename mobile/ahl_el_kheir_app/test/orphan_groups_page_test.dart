import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/orphan_groups_page.dart';

Map<String, dynamic> groupData({
  List<Map<String, dynamic>> groups = const [],
  Map<String, dynamic>? selected,
  List<Map<String, dynamic>> members = const [],
  bool locked = false,
  bool canChange = false,
}) => {
  'month': '2026-10',
  'groups': groups,
  'selected_group': selected,
  'members': members,
  'nanny_history': [],
  'usage': {'member_history': 0, 'nanny_history': 0, 'disbursement_history': 0},
  'nannies': [{'id': 16, 'full_name': 'Test nanny'}],
  'eligible_orphans': [{'id': 4, 'child_name': 'Test child', 'family_name': 'Test family', 'family_code': 'F-TEST', 'sponsor_name': 'Test sponsor'}],
  'target_groups': [{'id': 8, 'group_name': 'Target group', 'orphan_count': 2}],
  'can_change_members': canChange,
  'has_current_disbursement': locked,
};

void main() {
  testWidgets('renders VGM group list and selected member details', (tester) async {
    var selectedId = 0;
    await tester.pumpWidget(MaterialApp(
      home: OrphanGroupsPage(
        english: true,
        loadPage: (id) async {
          selectedId = id ?? 0;
          if (id == 7) {
            return groupData(
              groups: [{'id': 7, 'group_name': 'North group', 'status': 'active', 'orphan_count': 1, 'nanny_name': 'Test nanny'}],
              selected: {'id': 7, 'group_name': 'North group', 'description': 'North region', 'status': 'active'},
              members: [{'child_id': 4, 'child_name': 'Test child', 'family_name': 'Test family', 'joined_date': '2026-10-01', 'age': 8}],
              canChange: true,
            );
          }
          return groupData(groups: [
            {'id': 7, 'group_name': 'North group', 'status': 'active', 'orphan_count': 1, 'nanny_name': 'Test nanny'},
          ]);
        },
        submitAction: (_) async => {'message': 'ok'},
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Orphan groups'), findsWidgets);
    await tester.tap(find.text('North group').first);
    await tester.pumpAndSettle();
    expect(selectedId, 7);
    expect(find.text('Current members'), findsWidgets);
    expect(find.text('Test child'), findsOneWidget);
    expect(find.text('Membership history'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('creates a draft group through the authenticated action callback', (tester) async {
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(
      home: OrphanGroupsPage(
        english: true,
        loadPage: (id) async => groupData(
          groups: id == 9
              ? [{'id': 9, 'group_name': 'New mobile group', 'status': 'draft', 'orphan_count': 0, 'nanny_name': ''}]
              : [],
          selected: id == 9 ? {'id': 9, 'group_name': 'New mobile group', 'description': '', 'status': 'draft'} : null,
        ),
        submitAction: (body) async {
          submitted = Map<String, dynamic>.from(body);
          return {'group_id': 9, 'message': 'Group created as a draft.'};
        },
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Create group'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).at(0), 'New mobile group');
    await tester.enterText(find.byType(TextField).at(1), 'Created from Flutter');
    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();
    expect(submitted?['action'], 'create_group');
    expect(submitted?['group_name'], 'New mobile group');
    expect(submitted?['description'], 'Created from Flutter');
    expect(find.text('New mobile group'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('current-month disbursement disables member mutations', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: OrphanGroupsPage(
        english: false,
        loadPage: (id) async => groupData(
          groups: [{'id': 7, 'group_name': 'مجموعة الشمال', 'status': 'active', 'orphan_count': 1, 'nanny_name': 'حاضنة'}],
          selected: {'id': 7, 'group_name': 'مجموعة الشمال', 'description': '', 'status': 'active'},
          members: [{'child_id': 4, 'child_name': 'طفل تجريبي', 'family_name': 'أسرة تجريبية', 'joined_date': '2026-10-01', 'age': 8}],
          locked: true,
          canChange: true,
        ),
        submitAction: (_) async => {'message': 'ok'},
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('تعديل الأعضاء مقفل لوجود صرف للشهر الحالي.'), findsOneWidget);
    final addButton = tester.widget<OutlinedButton>(find.widgetWithText(OutlinedButton, 'إضافة أيتام مؤهلين (1)'));
    expect(addButton.onPressed, isNull);
    expect(tester.takeException(), isNull);
  });
}
