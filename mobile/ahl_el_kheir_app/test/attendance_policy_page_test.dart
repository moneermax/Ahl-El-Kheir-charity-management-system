import 'package:ahl_el_kheir_app/attendance_policy_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('attendance policy renders active state, weekly schedule and version history', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AttendancePolicyPage(
        english: true,
        loadPage: () async => {
          'today': '2026-10-26',
          'active_policy': {
            'id': 1, 'version_no': 1, 'policy_name': 'Current policy',
            'effective_from': '2026-10-01', 'working_start_time': '07:00:00',
            'working_end_time': '16:00:00', 'working_days': '1,2,3,4,5',
            'attendance_cutoff_time': '16:00:00', 'absence_finalization_time': '15:30:00',
            'auto_login_attendance': true, 'auto_absence_enabled': false,
            'default_work_mode': 'remote', 'notes': '', 'can_manage': false,
            'locked_by_attendance': false,
          },
          'policies': [
            {
              'id': 1, 'version_no': 1, 'policy_name': 'Current policy',
              'effective_from': '2026-10-01', 'working_start_time': '07:00:00',
              'working_end_time': '16:00:00', 'working_days': '1,2,3,4,5',
              'attendance_cutoff_time': '16:00:00', 'absence_finalization_time': '15:30:00',
              'auto_login_attendance': true, 'auto_absence_enabled': false,
              'default_work_mode': 'remote', 'notes': '', 'can_manage': false,
              'locked_by_attendance': false,
            },
          ],
        },
        submitAction: (body) async => {'message': 'saved'},
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Attendance policy'), findsOneWidget);
    expect(find.text('Active policy'), findsOneWidget);
    expect(find.textContaining('V1 · Current policy'), findsWidgets);
    expect(find.text('Weekly working days'), findsOneWidget);
    expect(find.text('Auto check-in on login'), findsOneWidget);
    expect(find.text('Automatic absence finalization'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Policy version history'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Policy version history'), findsOneWidget);
    expect(find.textContaining('Current policy'), findsWidgets);
  });
}
