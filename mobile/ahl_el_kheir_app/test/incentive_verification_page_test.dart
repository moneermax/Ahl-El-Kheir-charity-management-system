import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/incentive_verification_page.dart';

void main(){
  testWidgets('HR Manager sees only eligible pending events as actionable',(tester)async{
    tester.view.physicalSize=const Size(1000,1800);
    tester.view.devicePixelRatio=1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(MaterialApp(home:IncentiveVerificationPage(
      english:true,
      loadEvents:()async=>{'events':[
        {'id':1,'incentive_type':'new_sponsor','incentive_name_en':'New sponsor','incentive_name_ar':'كفيل جديد','source_table':'sponsor_requests','source_record_id':4,'occurred_at':'2026-10-20 10:00:00','recipient_user_id':2,'recipient_employee_id':8,'recipient_name':'Employee A','employment_category':'working','employment_state_active':true,'user_active':true,'reward_amount':2000,'reward_currency_code':'SDG','is_enabled':true,'status':'pending_verification','can_verify':true,'action_message':''},
        {'id':2,'incentive_type':'collection','incentive_name_en':'Collection','incentive_name_ar':'تحصيل','source_table':'sponsorships','source_record_id':5,'occurred_at':'2026-10-20 10:00:00','recipient_user_id':2,'recipient_employee_id':8,'recipient_name':'Employee A','employment_category':'working','employment_state_active':true,'user_active':true,'reward_amount':1000,'reward_currency_code':'SDG','is_enabled':true,'status':'pending_verification','can_verify':false,'action_message':'Routine collection incentives are disabled; the event remains preserved without an award.'}
      ]},
      submitAction:(_)async=>{'message':'Achievement verified.'},
    )));
    await tester.pumpAndSettle();
    expect(find.text('Incentive verification'),findsOneWidget);
    expect(find.text('New sponsor'),findsOneWidget);
    expect(find.text('Collection'),findsOneWidget);
    expect(find.text('Routine collection incentives are disabled; the event remains preserved without an award.'),findsOneWidget);
    expect(find.text('Verify and issue award'),findsOneWidget);
    await tester.tap(find.text('Verified'));
    await tester.pumpAndSettle();
    expect(find.text('No events in this filter.'),findsOneWidget);
    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();
    expect(find.text('Collection'),findsOneWidget);
  });

  testWidgets('incentive verification confirmation submits note and refreshes',(tester)async{
    var submitted=false;
    Map<String,dynamic> event={'id':1,'incentive_type':'new_sponsor','incentive_name_en':'New sponsor','incentive_name_ar':'كفيل جديد','source_table':'sponsor_requests','source_record_id':4,'occurred_at':'2026-10-20 10:00:00','recipient_user_id':2,'recipient_employee_id':8,'recipient_name':'Employee A','employment_category':'working','employment_state_active':true,'user_active':true,'reward_amount':2000,'reward_currency_code':'SDG','is_enabled':true,'status':'pending_verification','can_verify':true,'action_message':''};
    await tester.pumpWidget(MaterialApp(home:IncentiveVerificationPage(
      english:true,
      loadEvents:()async=>{'events':[event]},
      submitAction:(body)async{submitted=true;expect(body['action'],'verify_event');expect(body['verification_note'],'checked');return {'message':'Achievement verified.'};},
    )));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Verify and issue award'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Verify and issue award'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last,'checked');
    await tester.tap(find.text('Confirm verification'));
    await tester.pumpAndSettle();
    expect(submitted,isTrue);
    expect(find.text('Achievement verified.'),findsOneWidget);
  });
}
