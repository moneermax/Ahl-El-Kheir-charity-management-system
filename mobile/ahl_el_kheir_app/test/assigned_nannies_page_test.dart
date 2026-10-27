import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/assigned_nannies_page.dart';
void main(){
 testWidgets('assigned nannies list opens month-specific family detail',(tester) async {
  int? selected;
  await tester.pumpWidget(MaterialApp(home:AssignedNanniesPage(english:true,loadPage:(month,{nannyId}) async {
   selected=nannyId;
   return {'month':month,'nannies':[{'id':16,'full_name':'Nanny A','phone':'12345','active_families':2,'verified_now':1,'batch_status':'approved'}],
    'detail':nannyId==16?{'id':16,'full_name':'Nanny A','phone':'12345'}:null,
    'families':nannyId==16?[{'id':51,'family_code':'FAM-051','mother_name':'Mother A','mother_phone':'555','bank_name':'Bank A','bank_account_number':'123456','is_orphan_verified':1,'is_mother_contact_verified':1,'is_bank_verified':1,'verification_notes':'Checked'}]:[]};
  })));
  await tester.pumpAndSettle();
  expect(find.text('Assigned nannies'),findsOneWidget);
  expect(find.text('Nanny A'),findsOneWidget);
  await tester.tap(find.widgetWithText(ListTile,'Nanny A')); await tester.pumpAndSettle();
  expect(selected,16); expect(find.text('Active families — Nanny A'),findsOneWidget);
  expect(find.text('FAM-051 — Mother A'),findsOneWidget); expect(find.text('Verification complete ✓'),findsOneWidget);
 });
 testWidgets('empty assigned scope has an empty state',(tester) async {
  await tester.pumpWidget(MaterialApp(home:AssignedNanniesPage(english:false,loadPage:(month,{nannyId}) async=>{'month':month,'nannies':[],'detail':null,'families':[]})));
  await tester.pumpAndSettle();
  expect(find.text('الحاضنات المعيّنات'),findsOneWidget); expect(find.text('لا توجد أخصائيات ضمن نطاقك.'),findsOneWidget);
 });
}
