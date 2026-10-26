import 'dart:typed_data';
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
      loadDocument: (_) async => <String, dynamic>{},
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

  testWidgets('finance manager sees sponsor and monthly disbursement search backed by native APIs', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GlobalSearchPage(
      roleCode: 'financial_manager',
      english: true,
      loadSearch: (_, __, ___) async => throw StateError('unexpected'),
      loadDocument: (_) async => <String, dynamic>{},
      onOpenResult: (_, __) {},
    )));
    expect(find.text('Sponsors'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    expect(find.text('Monthly disbursements'), findsOneWidget);
    expect(find.text('The website and external pages will not be opened.'), findsNothing);
  });

  testWidgets('vice general manager sees the read-only monthly disbursement search scope', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GlobalSearchPage(
      roleCode: 'vice_general_manager',
      english: true,
      loadSearch: (_, __, ___) async => throw StateError('search should not run before user submits'),
      loadDocument: (_) async => <String, dynamic>{},
      onOpenResult: (_, __) {},
    )));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    expect(find.text('Monthly disbursements'), findsOneWidget);
  });

  testWidgets('monthly disbursement search opens the matching native register query',(tester)async{
    String? openedType;
    Map<String,dynamic>? openedItem;
    await tester.pumpWidget(MaterialApp(home:GlobalSearchPage(
      roleCode:'financial_manager',english:true,
      loadSearch:(type,query,page) async {
        expect(type,'payments'); expect(query,'42'); expect(page,1);
        return {'ok':true,'data':{'items':[{'id':42,'month':'2026-10','status':'transferred','total_amount':'190000.00','nanny_name':'Nanny One','group_name':'Group A'}],'total':1,'page':1,'pages':1}};
      },
      loadDocument: (_) async => <String, dynamic>{},
      onOpenResult:(type,item){openedType=type;openedItem=item;},
    )));
    await tester.enterText(find.byType(TextField),'42');
    await tester.tap(find.text('Search'));
    await tester.pumpAndSettle();
    expect(find.text('2026-10 · Batch #42'),findsOneWidget);
    await tester.tap(find.text('2026-10 · Batch #42'));
    expect(openedType,'payments'); expect(openedItem?['id'],42);
  });

  testWidgets('finance manager can search sponsors through its authorized native detail scope', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GlobalSearchPage(
      roleCode: 'financial_manager',
      english: true,
      loadSearch: (_, __, ___) async => throw StateError('search should not run before user submits'),
      loadDocument: (_) async => <String, dynamic>{},
      onOpenResult: (_, __) {},
    )));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    expect(find.text('Sponsors'), findsWidgets);
  });

  testWidgets('accountant can search sponsors through its authorized native detail scope', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GlobalSearchPage(
      roleCode: 'accountant',
      english: true,
      loadSearch: (_, __, ___) async => throw StateError('search should not run before user submits'),
      loadDocument: (_) async => <String, dynamic>{},
      onOpenResult: (_, __) {},
    )));
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    expect(find.text('Sponsors'), findsWidgets);
  });

  testWidgets('administrator aliases see categories backed by authorized native APIs',(tester)async{
    await tester.pumpWidget(MaterialApp(home:GlobalSearchPage(roleCode:'sudo',english:true,loadSearch:(_,__,___)async=>{'data':{'items':[],'total':0,'page':1,'pages':1}},loadDocument: (_) async => <String, dynamic>{},
      onOpenResult:(_,__){})));
    await tester.tap(find.byType(DropdownButtonFormField<String>)); await tester.pumpAndSettle();
    expect(find.text('Families & children'),findsWidgets);
    expect(find.text('Sponsors'),findsWidgets);
    expect(find.text('Sponsorships'),findsWidgets);
  });

  testWidgets('projects manager receives native archived document search and preview', (tester) async {
    await tester.pumpWidget(MaterialApp(home: GlobalSearchPage(
      roleCode: 'projects_manager',
      english: true,
      loadSearch: (type, query, page) async {
        expect(type, 'documents');
        return {'items': [
          {'id': 19, 'version_id': 19, 'document_id': 4, 'version_no': 2, 'is_current_version': true,
           'display_title': 'Project receipt', 'project_code': 'PRJ-001', 'project_name': 'Project One',
           'original_name': 'receipt.bin', 'mime_type': 'application/octet-stream'}
        ], 'total': 1, 'page': 1, 'pages': 1};
      },
      loadDocument: (versionId) async => {'bytes': Uint8List(0), 'mime': 'application/octet-stream', 'filename': 'receipt.bin'},
      onOpenResult: (_, __) {},
    )));
    expect(find.text('Archived project documents'), findsOneWidget);
    await tester.enterText(find.byType(TextField), 'receipt');
    await tester.tap(find.text('Search'));
    await tester.pumpAndSettle();
    expect(find.text('Project receipt · PRJ-001'), findsOneWidget);
    await tester.tap(find.text('Project receipt · PRJ-001'));
    await tester.pumpAndSettle();
    expect(find.textContaining('cannot be previewed in-app'), findsOneWidget);
    expect(find.text('Save document'), findsOneWidget);
  });
}
