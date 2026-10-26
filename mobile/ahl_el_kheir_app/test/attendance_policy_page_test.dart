import 'package:ahl_el_kheir_app/attendance_policy_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('attendance policy shows active policy and submits a valid new version', (tester) async {
    final policies = <Map<String, dynamic>>[];
    Future<Map<String, dynamic>> load() async {
      final active = policies.where((p) => p['effective_from'] == '2026-10-26').toList();
      return {'today':'2026-10-26','active_policy':active.isEmpty?null:active.first,'policies':policies.map((p)=>{...p,'can_manage':true,'locked_by_attendance':false}).toList()};
    }
    Future<Map<String, dynamic>> submit(Map<String, dynamic> body) async {
      if(body['action']=='create') policies.add({'id':1,'version_no':1,'policy_name':body['policy_name'],'effective_from':body['effective_from'],'working_start_time':body['working_start_time'],'working_end_time':body['working_end_time'],'working_days':body['working_days'],'attendance_cutoff_time':body['attendance_cutoff_time'],'absence_finalization_time':body['absence_finalization_time'],'auto_login_attendance':body['auto_login_attendance'],'auto_absence_enabled':body['auto_absence_enabled'],'default_work_mode':body['default_work_mode'],'notes':body['notes']});
      return {'message':'Policy saved'};
    }
    await tester.pumpWidget(MaterialApp(home:AttendancePolicyPage(english:true,loadPage:load,submitAction:submit)));
    await tester.pumpAndSettle();
    expect(find.text('No active policy. Automatic attendance remains unavailable until a policy is issued.'),findsOneWidget);
    await tester.enterText(find.byType(TextFormField).at(0),'Mobile test policy');
    await tester.tap(find.byType(TextFormField).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save version'));
    await tester.pumpAndSettle();
    expect(find.textContaining('V1 · Mobile test policy'),findsOneWidget);
    expect(policies,hasLength(1));
  });
}
