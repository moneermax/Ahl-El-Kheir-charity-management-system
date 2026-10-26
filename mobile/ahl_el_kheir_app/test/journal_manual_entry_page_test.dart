import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/journal_manual_entry_page.dart';

void main() {
  final accounts = <Map<String, dynamic>>[
    {'id': 1, 'code': '1100', 'name_ar': 'الصندوق', 'name_en': 'Cash'},
    {'id': 2, 'code': '4100', 'name_ar': 'الإيرادات', 'name_en': 'Revenue'},
  ];

  Future<void> openForm(WidgetTester tester, Future<Map<String, dynamic>> Function(Map<String, dynamic>) submit) async {
    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: TextButton(
              onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(
                builder: (_) => JournalManualEntryPage(
                  english: true,
                  loadAccounts: () async => {'accounts': accounts},
                  submitEntry: submit,
                ),
              )),
              child: const Text('Open manual entry'),
            ),
          ),
        ),
      ),
    ));
    await tester.tap(find.text('Open manual entry'));
    await tester.pumpAndSettle();
  }

  testWidgets('manual journal submits balanced distinct-account lines', (tester) async {
    Map<String, dynamic>? submitted;
    await openForm(tester, (body) async {
      submitted = Map<String, dynamic>.from(body);
      return {'entry': {'id': 10, 'entry_code': 'JE-000010'}};
    });

    await tester.enterText(find.byType(TextFormField).at(0), 'Test manual journal');
    await tester.ensureVisible(find.byType(DropdownButtonFormField<int>).at(0));
    await tester.tap(find.byType(DropdownButtonFormField<int>).at(0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('1100 · Cash').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byType(DropdownButtonFormField<int>).at(1));
    await tester.tap(find.byType(DropdownButtonFormField<int>).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('4100 · Revenue').last);
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byType(TextFormField).at(1));
    await tester.enterText(find.byType(TextFormField).at(1), '125.00');
    await tester.ensureVisible(find.byType(TextFormField).at(5));
    await tester.enterText(find.byType(TextFormField).at(5), '125.00');
    await tester.ensureVisible(find.text('Save and post entry'));
    await tester.tap(find.text('Save and post entry'));
    await tester.pumpAndSettle();

    expect(submitted?['action'], 'create_manual_entry');
    expect(submitted?['description'], 'Test manual journal');
    expect((submitted?['lines'] as List).length, 2);
    expect((submitted?['lines'] as List)[0]['account_id'], 1);
    expect((submitted?['lines'] as List)[0]['debit'], '125.00');
    expect((submitted?['lines'] as List)[1]['account_id'], 2);
    expect((submitted?['lines'] as List)[1]['credit'], '125.00');
    expect(find.text('Open manual entry'), findsOneWidget);
  });

  testWidgets('manual journal blocks unbalanced totals before calling the API', (tester) async {
    var submitted = false;
    await openForm(tester, (_) async {
      submitted = true;
      return {};
    });

    await tester.enterText(find.byType(TextFormField).at(0), 'Unbalanced entry');
    await tester.tap(find.byType(DropdownButtonFormField<int>).at(0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('1100 · Cash').last);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<int>).at(1));
    await tester.pumpAndSettle();
    await tester.tap(find.text('4100 · Revenue').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byType(TextFormField).at(1));
    await tester.enterText(find.byType(TextFormField).at(1), '100');
    await tester.ensureVisible(find.byType(TextFormField).at(5));
    await tester.enterText(find.byType(TextFormField).at(5), '90');
    await tester.ensureVisible(find.text('Save and post entry'));
    await tester.tap(find.text('Save and post entry'));
    await tester.pumpAndSettle();

    expect(submitted, isFalse);
    expect(find.text('Total debit must equal total credit.'), findsOneWidget);
  });
}
