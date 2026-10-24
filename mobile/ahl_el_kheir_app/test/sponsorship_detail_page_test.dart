import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsorship_detail_page.dart';
void main(){testWidgets('renders Arabic sponsorship details and payment history',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:SponsorshipDetailPage(sponsorshipId:1,english:false,loadDetail:(_)async=>{'sponsorship':{'id':1,'sponsorship_code':'SH-000001','monthly_amount':2000.0,'status':'active','start_date':'2026-01-01','end_date':'','sponsor_name':'محمد أحمد','sponsor_code':'SP-000001','child_name':'أحمد علي','mother_name':'فاطمة علي','family_code':'FAM-0001','payment_count':1,'total_paid':1500.0},'payments':[{'id':1,'transaction_code':'TX-0001','amount':1500.0,'transaction_date':'2026-10-01','payment_method':'cash','status':'posted','receipt_number':'R-1'}]}))));
 await tester.pumpAndSettle();expect(find.text('تفاصيل الكفالة'),findsOneWidget);expect(find.text('SH-000001'),findsOneWidget);expect(find.text('سجل الدفعات'),findsOneWidget);expect(find.text('TX-0001'),findsOneWidget);
});}