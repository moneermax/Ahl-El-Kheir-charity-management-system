import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/attendance_page.dart';
void main(){testWidgets('shows Arabic attendance and keeps missing attendance distinct', (tester) async {
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:AttendancePage(english:false,loadAttendance:(_) async=>{
 'date':'2026-10-24','total':2,'counts':{'present':1,'absent':0,'on_leave':0,'missing':1},
 'items':[{'employee_id':1,'full_name':'أحمد حسين','department_name':'المشروعات','status':'present','check_in':'08:00','check_out':'','work_mode':'onsite'},{'employee_id':2,'full_name':'سارة علي','department_name':'الموارد البشرية','status':'missing','check_in':'','check_out':'','work_mode':''}]
 }))));
 await tester.pumpAndSettle();
 expect(find.text('الحضور والانصراف'),findsOneWidget);expect(find.text('أحمد حسين'),findsOneWidget);expect(find.text('حاضر'),findsOneWidget);expect(find.text('لا يوجد سجل'),findsWidgets);
});}
