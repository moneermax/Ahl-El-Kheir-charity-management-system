import 'package:ahl_el_kheir_app/native_report_detail_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main(){
 testWidgets('native report detail renders filter, summary and data rows',(tester)async{
  await tester.pumpWidget(MaterialApp(home:NativeReportDetailPage(reportKey:'financial',english:true,loadReport:(report,type,from,to)async=>{
   'summary':{'total_income':1250.0,'total_expense':250.0},
   'rows':[{'month':'2026-10','total_income':1250.0,'total_expense':250.0}],
  })));
  await tester.pumpAndSettle();
  expect(find.text('Financial reports'),findsOneWidget);
  expect(find.text('Income and expenses'),findsOneWidget);
  expect(find.text('Total income'),findsWidgets);
  expect(find.text('2026-10'),findsOneWidget);
  expect(tester.takeException(),isNull);
 });
}
