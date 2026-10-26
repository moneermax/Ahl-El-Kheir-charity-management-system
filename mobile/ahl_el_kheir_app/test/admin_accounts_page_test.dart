import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_accounts_page.dart';

void main(){
  testWidgets('chart of accounts renders balances and submits validated account form payload', (tester)async{
    Map<String,dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(home:AdminAccountsPage(
      english:true,
      loadAccounts:()async=>{'total':1,'items':[{'id':7,'code':'1100','name_ar':'Cash','name_en':'Cash account','account_type':'asset','is_active':1,'balance':1234.5}]},
      submitAction:(payload)async{submitted=payload;return {'id':8};},
    )));
    await tester.pumpAndSettle();
    expect(find.text('Chart of accounts'),findOneWidget);
    expect(find.text('1100 — Cash'),findOneWidget);
    expect(find.textContaining('1,234.50'),findOneWidget);
    await tester.enterText(find.byKey(const Key('account-code-input')),'654321');
    await tester.enterText(find.byKey(const Key('account-name-ar-input')),'Test account');
    await tester.enterText(find.byKey(const Key('account-name-en-input')),'Test account English');
    await tester.ensureVisible(find.text('Create account'));
    await tester.tap(find.text('Create account'));
    await tester.pumpAndSettle();
    expect(submitted?['action'],'create');
    expect(submitted?['code'],'654321');
    expect(submitted?['name_ar'],'Test account');
    expect(submitted?['name_en'],'Test account English');
    expect(submitted?['account_type'],'asset');
    expect(tester.takeException(),isNull);
  });
}
