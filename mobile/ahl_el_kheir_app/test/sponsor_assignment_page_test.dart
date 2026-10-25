import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsor_assignment_page.dart';

void main() {
  testWidgets('assigns an unassigned sponsor to an active supervisor', (tester) async {
    final assignments = <List<int>>[];
    await tester.pumpWidget(MaterialApp(
      home: SponsorAssignmentPage(
        english: true,
        loadOptions: () async => {
          'unassigned_sponsors': [
            {'id': 10, 'full_name': 'Sponsor A', 'sponsor_code': 'SP-000010', 'phone': '123'},
          ],
          'active_supervisors': [
            {'id': 21, 'full_name': 'Supervisor One'},
          ],
        },
        assignSponsor: (sponsorId, supervisorId) async {
          assignments.add([sponsorId, supervisorId]);
          return {'sponsor_id': sponsorId, 'supervisor_id': supervisorId};
        },
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Sponsor assignment'), findsOneWidget);
    expect(find.text('Sponsor A'), findsOneWidget);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Supervisor One').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Assign sponsor'));
    await tester.pumpAndSettle();

    expect(assignments, [[10, 21]]);
    expect(find.text('Sponsor A'), findsNothing);
    expect(find.text('Sponsor assigned successfully.'), findsOneWidget);
  });

  testWidgets('shows an empty state when no unassigned sponsors exist', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SponsorAssignmentPage(
        english: false,
        loadOptions: () async => {
          'unassigned_sponsors': <Map<String, dynamic>>[],
          'active_supervisors': <Map<String, dynamic>>[],
        },
        assignSponsor: (_, __) async => <String, dynamic>{},
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('إسناد الكفلاء'), findsOneWidget);
    expect(find.text('لا يوجد كفلاء غير مسندين.'), findsOneWidget);
  });
}
