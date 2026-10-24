import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/leave_requests_page.dart';
void main(){testWidgets('renders Arabic leave request and status', (tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:LeaveRequestsPage(english:false,roleCode:'hr_manager',loadLeaves:(_,__,___)async=>{
 'items':[{'id':4,'employee_name':'سارة علي','department_name':'الموارد البشرية','leave_type':'annual','start_date':'2026-10-26','end_date':'2026-10-28','days_count':3,'reason':'ظرف عائلي','status':'pending','created_at':'2026-10-24'}],
 'total':1,'page':1,'pages':1
 }))));
 await tester.pumpAndSettle();
 expect(find.text('طلبات الإجازة'),findsOneWidget);expect(find.text('طلباتي'),findsOneWidget);expect(find.text('سنوية'),findsOneWidget);expect(find.text('قيد المراجعة'),findsOneWidget);expect(find.text('سارة علي'),findsOneWidget);
});}
