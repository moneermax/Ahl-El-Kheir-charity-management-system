import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_recovery_page.dart';

void main() {
  testWidgets('native recovery approval shows the temporary password once', (tester) async {
    var processed = false;
    final actions = <Map<String, dynamic>>[];
    await tester.pumpWidget(MaterialApp(
      home: AdminRecoveryPage(
        english: true,
        loadRequests: () async => {
          'items': [
            {
              'id': 17,
              'user_id': 9,
              'username': 'staff.user',
              'full_name': 'Staff User',
              'status': processed ? 'approved' : 'pending',
              'requested_at': '2026-10-25 10:00:00',
              'reviewer_name': '',
              'expires_at': '',
            },
          ],
        },
        submitAction: (payload) async {
          actions.add(payload);
          processed = true;
          return {
            'message': 'Recovery approved.',
            'temporary_password': 'a1b2c3d4e5f6',
            'full_name': 'Staff User',
          };
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Password recovery'), findsOneWidget);
    expect(find.text('Staff User'), findsOneWidget);
    expect(find.text('Pending'), findsOneWidget);

    await tester.tap(find.text('Approve').first);
    await tester.pumpAndSettle();
    expect(find.text('Approve recovery request?'), findsOneWidget);
    await tester.tap(find.text('Approve').last);
    await tester.pumpAndSettle();

    expect(actions, hasLength(1));
    expect(actions.single['action'], 'approve');
    expect(actions.single['request_id'], 17);
    expect(find.text('Temporary password for Staff User'), findsOneWidget);
    expect(find.text('a1b2c3d4e5f6'), findsOneWidget);
    expect(find.text('Approved'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
