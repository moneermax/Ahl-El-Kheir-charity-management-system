import 'package:ahl_el_kheir_app/orphan_form_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
void main(){
 testWidgets('orphan form loads role-scoped data and submits editable fields',(tester)async{
  Map<String,dynamic>? submitted;
  await tester.pumpWidget(MaterialApp(home:OrphanFormPage(childId:12,english:true,
   loadForm:(id)async=>{'can_edit':true,'child':{'id':12,'child_name':'Test child','form_serial':'AK-00012','nationality':'Sudanese','health_status':'سليم','psychological_state':'سليم','guardian_name':'','guardian_relationship':''},
    'family':{'family_code':'F-12','mother_name':'Test mother','mother_phone':'123','city':'Khartoum','address':'Test address','mother_marital_status':'أرملة'},
    'sponsorships':[],'options':{'health':['سليم','أخرى'],'psych':['سليم','أخرى'],'marital':['أرملة'],'relationships':['الأم']}},
   saveForm:(id,values)async{submitted=Map<String,dynamic>.from(values);return {'saved':true};},
  )));
  await tester.pumpAndSettle();
  expect(find.text('Orphan form'),findsOneWidget);
  expect(find.text('Test child'),findsOneWidget);
  await tester.enterText(find.byType(TextFormField).first,'Sudanese');
  await tester.dragUntilVisible(find.text('Save form'), find.byType(ListView), const Offset(0, -300));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Save form'));
  await tester.pumpAndSettle();
  expect(submitted?['nationality'],'Sudanese');
  expect(tester.takeException(),isNull);
 });
}
