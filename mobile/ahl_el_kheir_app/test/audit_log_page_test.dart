import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/audit_log_page.dart';

void main() {
  testWidgets('audit activity renders recent events from the admin overview', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AuditLogPage(
        english: true,
        loadOverview: () async => {
          'recent_audit': [
            {
              'id': 41,
              'action': 'USER_UPDATED',
              'username': 'admin',
              'entity_type': 'user',
              'entity_id': 8,
              'created_at': '2026-10-25 10:30:00',
            },
          ],
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Recent audit activity'), findsOneWidget);
    expect(find.text('USER_UPDATED'), findsOneWidget);
    expect(find.textContaining('admin · user #8'), findsOneWidget);
    expect(find.textContaining('complete audit register is not yet available natively'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
