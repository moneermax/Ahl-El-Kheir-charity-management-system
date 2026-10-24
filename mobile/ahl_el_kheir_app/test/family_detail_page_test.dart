import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/family_detail_page.dart';
void main(){testWidgets('renders Arabic family details, children and sponsorships',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:FamilyDetailPage(familyId:1,english:false,loadDetail:(_)async=>{'family':{'id':1,'family_code':'FAM-0001','mother_name':'فاطمة علي','mother_phone':'0911111111','status':'active','nanny_name':'مشرفة','supervisor_name':'مشرف','monthly_commitment':4000.0},'children':[{'id':1,'child_name':'أحمد علي','gender':'male','birth_date':'2015-01-01','match_status':'matched','is_critical':false}],'sponsorships':[{'id':1,'sponsor_name':'محمد أحمد','sponsor_code':'SP-000001','child_name':'أحمد علي','monthly_amount':2000.0,'start_date':'2026-01-01','status':'active'}]}))));
 await tester.pumpAndSettle();expect(find.text('تفاصيل الأسرة'),findsOneWidget);expect(find.text('فاطمة علي'),findsOneWidget);expect(find.text('الأطفال (1)'),findsOneWidget);expect(find.text('أحمد علي'),findsWidgets);expect(find.text('الكفالات'),findsOneWidget);
});}
