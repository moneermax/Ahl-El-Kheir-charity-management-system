import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/transactions_page.dart';
void main(){testWidgets('renders Arabic transaction register and posted total',(tester)async{
 await tester.pumpWidget(MaterialApp(home:Directionality(textDirection:TextDirection.rtl,child:TransactionsPage(english:false,loadTransactions:(_,__,___)async=>{'items':[{'id':1,'transaction_code':'TX-0001','transaction_date':'2026-10-24','transaction_type':'donation','amount':5000.0,'payment_method':'cash','receipt_number':'R-1','status':'posted','sponsor_name':'محمد أحمد','family_name':'فاطمة علي'}],'total':1,'page':1,'pages':1,'posted_total':5000.0}))));
 await tester.pumpAndSettle();expect(find.text('المعاملات'),findsOneWidget);expect(find.text('TX-0001'),findsOneWidget);expect(find.textContaining('إجمالي المرحّل: 5000.00 SDG'),findsOneWidget);expect(find.text('مرحّلة'),findsOneWidget);
});}
