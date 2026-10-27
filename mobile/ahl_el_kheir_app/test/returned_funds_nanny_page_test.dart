import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/returned_funds_nanny_page.dart';

void main() {
  testWidgets('authorized returned amount is redelivered only after receipt and confirmation', (tester) async {
    var loads = 0;
    final submitted = <int>[];
    await tester.pumpWidget(MaterialApp(
      home: ReturnedFundsNannyPage(
        english: true,
        loadItems: () async {
          loads++;
          return {
            'items': loads == 1
                ? [
                    {
                      'id': 12,
                      'item_id': 30,
                      'amount': 250,
                      'month': '2026-10',
                      'authorized_at': '2026-10-27 10:00:00',
                      'authorization_reason': 'Family recontacted',
                      'family_code': 'FAM-TEST',
                      'mother_name': 'Test family',
                      'group_name': 'Group A',
                    },
                  ]
                : [],
          };
        },
        pickReceipt: () async => PlatformFile(
          name: 'receipt.pdf',
          size: 3,
          bytes: Uint8List.fromList([1, 2, 3]),
        ),
        submitRedelivery: (id, receipt) async {
          submitted.add(id);
          return {'id': id, 'status': 'redelivered'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Test family'), findsOneWidget);
    expect(find.text('250.00 SDG'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('returned-funds-confirm-12')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('returned-funds-confirm-submit')), findsOneWidget);
    expect(submitted, isEmpty);

    await tester.tap(find.byKey(const ValueKey('returned-funds-confirm-submit')));
    await tester.pumpAndSettle();

    expect(submitted, [12]);
    expect(loads, 2);
    expect(find.text('There are no returned amounts authorized for redelivery.'), findsOneWidget);
  });

  testWidgets('cancelled receipt picker leaves the authorized record unchanged', (tester) async {
    var submitted = false;
    await tester.pumpWidget(MaterialApp(
      home: ReturnedFundsNannyPage(
        english: false,
        loadItems: () async => {
          'items': [
            {'id': 9, 'amount': 75, 'month': '2026-09', 'family_code': 'FAM-9', 'mother_name': 'أسرة تجريبية'},
          ],
        },
        pickReceipt: () async => null,
        submitRedelivery: (id, receipt) async {
          submitted = true;
          return {};
        },
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('returned-funds-confirm-9')));
    await tester.pumpAndSettle();
    expect(submitted, isFalse);
    expect(find.text('أسرة تجريبية'), findsOneWidget);
  });
}
