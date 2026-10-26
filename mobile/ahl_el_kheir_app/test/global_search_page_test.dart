import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/global_search_page.dart';

void main() {
  testWidgets('native search loads family results and opens detail', (tester) async {
    String? openedType;
    Map<String,dynamic>? openedItem;
    await tester.pumpWidget(MaterialApp(home:GlobalSearchPage(
      roleCode:'nanny',english:false,
      loadSearch:(type,query,page) async {
        expect(type,'families'); expect(query,'FAM-001'); expect(page,1);
        return {'ok':true,'data':{'items':[{'id':17,'family_code':'FAM-001','mother_name':'أم أحمد','mother_phone':'0900000000','status':'active','children_count':2}],'total':1,'page':1,'pages':1}};
      },
      onOpenResult:(type,item){openedType=type;openedItem=item;},
    )));
    expect(find.text('الأسر والأيتام'),findsOneWidget);
    await tester.enterText(find.byType(TextField),'FAM-001');
    await tester.tap(find.text('بحث'));
    await tester.pumpAndSettle();
    expect(find.text('أم أحمد · FAM-001'),findsOneWidget);
    expect(find.textContaining('أم أحمد'),findsOneWidget);
    await tester.tap(find.text('أم أحمد · FAM-001'));
    expect(openedType,'families'); expect(openedItem?['id'],17);
  });

  testWidgets('finance manager sees no categories denied by existing native APIs',(tester)async{
    await tester.pumpWidget(MaterialApp(home:GlobalSearchPage(roleCode:'financial_manager',english:true,loadSearch:(_,__,___)async=>throw StateError('unexpected'),onOpenResult:(_,__){})));
    expect(find.text('No native search categories are available for this role yet.'),findsOneWidget);
    expect(find.text('The website and external pages will not be opened.'),findsOneWidget);
  });

  testWidgets('administrator aliases see categories backed by authorized native APIs',(tester)async{
    await tester.pumpWidget(MaterialApp(home:GlobalSearchPage(roleCode:'sudo',english:true,loadSearch:(_,__,___)async=>{'data':{'items':[],'total':0,'page':1,'pages':1}},onOpenResult:(_,__){})));
    await tester.tap(find.byType(DropdownButtonFormField<String>)); await tester.pumpAndSettle();
    expect(find.text('Families & children'),findsWidgets);
    expect(find.text('Sponsors'),findsWidgets);
    expect(find.text('Sponsorships'),findsWidgets);
  });
}
