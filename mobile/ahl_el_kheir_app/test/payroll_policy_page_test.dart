import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/payroll_policy_page.dart';

void main() {
  testWidgets('native payroll policy shows active policy and version history',(tester) async {
    final policy=<String,dynamic>{
      'id':1,'version_no':1,'effective_from':'2026-10-05','is_active':true,'is_future':false,
      'absence_enabled':true,'absence_deduction_percent':100,'unpaid_leave_enabled':true,
      'unpaid_leave_deduction_percent':100,'paid_leave_deduction_percent':0,'late_enabled':false,
      'early_departure_enabled':false,'overtime_enabled':false,'overtime_multiplier':1,
      'rounding_decimals':2,'notes':'Initial policy','created_by_name':'HR Manager',
    };
    await tester.pumpWidget(MaterialApp(home:PayrollPolicyPage(
      english:true,
      loadPage:() async=>{'today':'2026-10-26','active_policy':policy,'next_policy':null,'policies':[policy]},
      submitAction:(_) async=>{'message':'Payroll policy version created.'},
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payroll policy'),findsOneWidget);
    expect(find.text('Current active policy'),findsOneWidget);
    expect(find.text('Create a future version'),findsOneWidget);
    await tester.drag(find.byType(ListView).first,const Offset(0,-1000));
    await tester.pumpAndSettle();
    expect(find.text('Policy version history'),findsOneWidget);
    expect(find.textContaining('V1 · 2026-10-05'),findsWidgets);
    expect(find.text('Active now'),findsWidgets);
    expect(find.text('Previous versions remain unchanged; approved payrolls are not recalculated.'),findsOneWidget);
  });

  testWidgets('native payroll policy requires an effective date before submitting',(tester) async {
    var submitted=false;
    await tester.pumpWidget(MaterialApp(home:PayrollPolicyPage(
      english:true,
      loadPage:() async=>{'today':'2026-10-26','active_policy':null,'next_policy':null,'policies':[]},
      submitAction:(_) async {submitted=true;return {'message':'created'};},
    )));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Create policy version'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Create policy version'));
    await tester.pumpAndSettle();
    expect(submitted,isFalse);
    expect(find.text('Choose an effective date.'),findsOneWidget);
  });
}
