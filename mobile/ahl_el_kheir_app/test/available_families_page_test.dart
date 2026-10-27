import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/available_families_page.dart';

void main() {
  testWidgets('lists eligible families and opens the selected family in-app', (tester) async {
    final opened = <int>[];
    await tester.pumpWidget(MaterialApp(home: AvailableFamiliesPage(
      english: true,
      loadFamilies: () async => {'total':1,'items':[{
        'id':42,'family_code':'FAM-042','mother_name':'Mother A','children_count':3,
        'monthly_need_amount':12500,'city':'Omdurman',
      }]},
      openFamily: opened.add,
    )));
    await tester.pumpAndSettle();
    expect(find.text('Families available for sponsorship'), findsOneWidget);
    expect(find.text('Mother A'), findsOneWidget);
    expect(find.textContaining('FAM-042'), findsOneWidget);
    expect(find.textContaining('Children: 3'), findsOneWidget);
    await tester.tap(find.text('Mother A'));
    expect(opened, [42]);
  });

  testWidgets('shows empty state for no eligible families', (tester) async {
    await tester.pumpWidget(MaterialApp(home: AvailableFamiliesPage(
      english: false,
      loadFamilies: () async => {'total':0,'items':[]},
      openFamily: (_) => fail('No family should open in the empty state'),
    )));
    await tester.pumpAndSettle();
    expect(find.text('الأسر المتاحة للكفالة'), findsOneWidget);
    expect(find.text('لا توجد أسر متاحة حالياً.'), findsOneWidget);
  });
}
