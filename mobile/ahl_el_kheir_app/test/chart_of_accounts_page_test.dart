import 'package:ahl_el_kheir_app/chart_of_accounts_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main() {
 final account=<String,dynamic>{'id':12,'code':'101','name_ar':'الصندوق','name_en':'Cash','account_type':'asset','is_active':1,'balance':250.75};
 testWidgets('managers can add and toggle while seeing posted balance',(tester) async {
  var added=<String,dynamic>{}; var toggled=0;
  await tester.pumpWidget(MaterialApp(home:ChartOfAccountsPage(english:true,roleCode:'financial_manager',
   loadAccounts:()async=>{'accounts':[account],'can_manage':true},
   addAccount:(input)async{added=input;return {'saved':true};},toggleAccount:(id)async{toggled=id;return {'saved':true};})));
  await tester.pumpAndSettle();
  expect(find.text('250.75'),findsOneWidget);
  await tester.enterText(find.byType(TextField).at(0),'102');
  await tester.enterText(find.byType(TextField).at(1),'البنك');
  await tester.enterText(find.byType(TextField).at(2),'Bank');
  await tester.tap(find.widgetWithText(FilledButton,'Add account')); await tester.pumpAndSettle();
  expect(added['code'],'102'); expect(added['name_ar'],'البنك');
  await tester.tap(find.text('Deactivate account')); await tester.pumpAndSettle(); expect(toggled,12);
 });
 testWidgets('read-only roles have no account mutation controls',(tester) async {
  await tester.pumpWidget(MaterialApp(home:ChartOfAccountsPage(english:true,roleCode:'general_manager',
   loadAccounts:()async=>{'accounts':[account],'can_manage':false},
   addAccount:(_)async=>throw StateError('unexpected add'),toggleAccount:(_)async=>throw StateError('unexpected toggle'))));
  await tester.pumpAndSettle();
  expect(find.text('Add account'),findsNothing); expect(find.text('Deactivate account'),findsNothing); expect(find.text('250.75'),findsOneWidget);
 });
}
