import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/families_page.dart';
void main(){testWidgets('renders Arabic family list summary',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:FamiliesPage(english:false,loadFamilies:(_,__,___)async=>{'items':[{'id':1,'family_code':'FAM-0001','mother_name':'فاطمة علي','mother_phone':'0911111111','status':'active','children_count':3,'active_sponsorships':2,'monthly_commitment':4000.0,'nanny_name':'المشرفة'}],'total':1,'page':1,'pages':1}))));
 await tester.pumpAndSettle();expect(find.text('الأسر'),findsOneWidget);expect(find.text('فاطمة علي'),findsOneWidget);expect(find.textContaining('الكفالات النشطة: 2'),findsOneWidget);
});}
