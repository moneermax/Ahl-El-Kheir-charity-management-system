import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/leave_requests_page.dart';
void main(){testWidgets('renders Arabic leave request and status', (tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:LeaveRequestsPage(english:false,roleCode:'hr_manager',submitAction:(_,__) async => {'message':'Saved'},loadLeaves:(_,__,___)async=>{
 'items':[{'id':4,'employee_name':'سارة علي','department_name':'الموارد البشرية','leave_type':'annual','start_date':'2026-10-26','end_date':'2026-10-28','days_count':3,'reason':'ظرف عائلي','status':'pending','created_at':'2026-10-24'}],
 'total':1,'page':1,'pages':1
 }))));
 await tester.pumpAndSettle();
 await tester.tap(find.text('طلبات للمراجعة'));
 await tester.pumpAndSettle();
 expect(find.text('طلبات الإجازة'),findsOneWidget);expect(find.text('طلباتي'),findsOneWidget);expect(find.text('سنوية'),findsOneWidget);expect(find.text('قيد المراجعة'),findsOneWidget);expect(find.text('سارة علي'),findsOneWidget);
});

 testWidgets('hides leave request action when account has no linked employee', (tester) async {
 await tester.pumpWidget(MaterialApp(home:Directionality(
  textDirection:TextDirection.rtl,
  child:LeaveRequestsPage(
   english:false,
   roleCode:'staff',
   submitAction:(_,__) async => {'message':'Saved'},
   loadLeaves:(_,__,___) async => {
    'items':[],
    'total':0,
    'page':1,
    'pages':1,
    'employee_linked':false,
   },
  ),
 )));
 await tester.pumpAndSettle();
 expect(find.text('يلزم ربط حسابك بملف موظف لتقديم طلب إجازة. يرجى التواصل مع الموارد البشرية.'),findsOneWidget);
 expect(find.byType(FloatingActionButton),findsNothing);
});
}
