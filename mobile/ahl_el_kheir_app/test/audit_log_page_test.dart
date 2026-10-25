import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/audit_log_page.dart';

void main() {
  testWidgets('audit register renders events and supports paginated search', (tester) async {
    final calls = <String>[];
    await tester.pumpWidget(MaterialApp(home: AuditLogPage(
      english: true,
      loadPage: (page, query) async {
        calls.add('$page:$query');
        return {
          'items': [{
            'id': 41, 'action': 'USER_UPDATED', 'username': 'admin',
            'user_name': 'System Admin', 'entity_type': 'user', 'entity_id': 8,
            'created_at': '2026-10-25 10:30:00',
          }],
          'total': 51, 'page': page, 'pages': 2,
        };
      },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Audit log'), findsOneWidget);
    expect(find.text('USER_UPDATED'), findsOneWidget);
    expect(find.textContaining('System Admin · admin'), findsOneWidget);
    expect(find.text('Page 1 of 2'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.byIcon(Icons.chevron_right_rounded));
    await tester.pumpAndSettle();
    expect(calls.last, '2:');
    expect(find.text('Page 2 of 2'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'USER_CREATED');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    expect(calls.last, '1:USER_CREATED');
    expect(tester.takeException(), isNull);
  });
}
