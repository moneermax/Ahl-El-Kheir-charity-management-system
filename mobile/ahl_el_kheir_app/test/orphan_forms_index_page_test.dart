import 'package:ahl_el_kheir_app/orphan_forms_index_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main() {
  testWidgets('orphan forms index searches and opens an authorized child form', (tester) async {
    int opened = 0;
    await tester.pumpWidget(MaterialApp(home: OrphanFormsIndexPage(
      english: true,
      loadForms: (query) async => {
        'children': [{'id': 9, 'child_name': 'Test Child', 'family_code': 'F-009', 'mother_name': 'Test Mother', 'form_serial': 'OF-009', 'family_status': 'active'}],
        'count': 1, 'can_edit': true, 'read_only': false,
      },
      openForm: (id) async { opened = id; },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Orphan forms'), findsOneWidget);
    expect(find.text('Test Child'), findsOneWidget);
    expect(find.textContaining('F-009'), findsOneWidget);
    await tester.tap(find.text('Test Child'));
    expect(opened, 9);
  });
}
