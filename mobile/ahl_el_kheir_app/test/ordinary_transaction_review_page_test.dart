import 'dart:convert';
import 'dart:typed_data';

import 'package:ahl_el_kheir_app/ordinary_transaction_review_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, dynamic> sampleTransaction(String status) => {
  'id': 41,
  'transaction_code': 'TXN-041',
  'submitted_at': '2026-10-27 10:30:00',
  'transaction_date': '2026-10-27',
  'creator_name': 'Transaction creator',
  'sponsor_name': 'Sponsor A',
  'purpose_label': 'تبرع عام',
  'payment_period': '',
  'amount': 125.0,
  'currency_code': 'SDG',
  'has_receipt': true,
  'has_unified_receipt': false,
  'status': status,
  'reviewed_at': status == 'pending_fm_review' ? '' : '2026-10-27 11:00:00',
  'reviewer_name': status == 'pending_fm_review' ? '' : 'Financial Manager',
  'review_reason': status == 'returned' ? 'Attach a clearer receipt' : '',
};

void main() {
  testWidgets('return requires a reason and submits it after confirmation', (tester) async {
    var loadCount = 0;
    int? submittedId;
    String? submittedAction;
    String? submittedReason;
    await tester.pumpWidget(MaterialApp(
      home: OrdinaryTransactionReviewPage(
        english: true,
        loadQueue: () async {
          loadCount++;
          return loadCount == 1
              ? {'pending': [sampleTransaction('pending_fm_review')], 'history': []}
              : {'pending': [], 'history': [sampleTransaction('returned')]};
        },
        loadReceipt: (id, kind) async => {
          'bytes': Uint8List.fromList(base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')),
          'mime': 'image/png',
          'filename': 'receipt.png',
        },
        submitAction: (id, action, reason) async {
          submittedId = id;
          submittedAction = action;
          submittedReason = reason;
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Financial transaction review'), findsOneWidget);
    expect(find.text('TXN-041'), findsOneWidget);
    expect(find.text('Approve and post'), findsOneWidget);
    expect(find.text('Return for correction'), findsOneWidget);

    await tester.tap(find.text('View receipt'));
    await tester.pumpAndSettle();
    expect(find.text('Transaction receipt'), findsOneWidget);
    expect(find.byType(Image), findsOneWidget);
    await tester.pageBack();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Return for correction'));
    await tester.pumpAndSettle();
    expect(submittedAction, isNull);
    expect(find.text('Enter a reason before returning the transaction.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('ordinary-return-reason-41')), 'Attach a clearer receipt');
    await tester.tap(find.text('Return for correction'));
    await tester.pumpAndSettle();
    expect(find.text('Return transaction?'), findsOneWidget);
    await tester.tap(find.text('Return for correction').last);
    await tester.pumpAndSettle();

    expect(submittedId, 41);
    expect(submittedAction, 'return');
    expect(submittedReason, 'Attach a clearer receipt');
    await tester.tap(find.textContaining('Review history'));
    await tester.pumpAndSettle();
    expect(find.text('Returned'), findsOneWidget);
  });

  testWidgets('approval is confirmed and refreshes into review history', (tester) async {
    var loadCount = 0;
    int? submittedId;
    String? submittedAction;
    await tester.pumpWidget(MaterialApp(
      home: OrdinaryTransactionReviewPage(
        english: true,
        loadQueue: () async {
          loadCount++;
          return loadCount == 1
              ? {'pending': [sampleTransaction('pending_fm_review')], 'history': []}
              : {'pending': [], 'history': [sampleTransaction('posted')]};
        },
        loadReceipt: (id, kind) async => {
          'bytes': Uint8List.fromList(base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC')),
          'mime': 'image/png',
          'filename': 'receipt.png',
        },
        submitAction: (id, action, reason) async {
          submittedId = id;
          submittedAction = action;
        },
      ),
    ));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Approve and post'));
    await tester.pumpAndSettle();
    expect(find.text('Approve transaction?'), findsOneWidget);
    await tester.tap(find.text('Approve and post').last);
    await tester.pumpAndSettle();

    expect(submittedId, 41);
    expect(submittedAction, 'approve');
    expect(loadCount, 2);
    await tester.tap(find.textContaining('Review history'));
    await tester.pumpAndSettle();
    expect(find.text('Posted'), findsOneWidget);
  });
}
