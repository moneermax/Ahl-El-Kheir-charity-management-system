import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsor_detail_page.dart';
void main(){testWidgets('renders Arabic sponsor details and sponsorships',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:SponsorDetailPage(sponsorId:1,english:false,loadDetail:(_)async=>{'sponsor':{'id':1,'sponsor_code':'SP-000001','full_name':'محمد أحمد','phone':'0912345678','status':'active','gender':'male','letter':'م','supervisor_name':'مشرف','active_orphan_count':2},'sponsorships':[{'id':1,'sponsorship_code':'SH-000001','child_name':'أحمد علي','mother_name':'فاطمة علي','family_code':'FAM-0001','monthly_amount':2000.0,'start_date':'2026-01-01','status':'active'}]}))));
 await tester.pumpAndSettle();expect(find.text('تفاصيل الكفيل'),findsOneWidget);expect(find.text('محمد أحمد'),findsOneWidget);expect(find.text('الكفالات'),findsOneWidget);expect(find.text('أحمد علي'),findsOneWidget);
});}
