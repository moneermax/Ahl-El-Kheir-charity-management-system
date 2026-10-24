import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsors_page.dart';
void main(){testWidgets('renders Arabic sponsor list and active sponsorship count',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:SponsorsPage(english:false,loadSponsors:(_,__,___,____)async=>{'items':[{'id':1,'sponsor_code':'SP-000001','full_name':'محمد أحمد','phone':'0912345678','status':'active','active_sponsorship_count':2}],'total':1,'page':1,'pages':1}))));
 await tester.pumpAndSettle();expect(find.text('الكفلاء'),findsOneWidget);expect(find.text('محمد أحمد'),findsOneWidget);expect(find.text('الكفالات النشطة: 2'),findsOneWidget);
});}
