import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:file_picker/file_picker.dart';
import 'package:ahl_el_kheir_app/admin_disbursements_page.dart';

void main() {
  testWidgets('loads filtered disbursement batches and opens read-only family details', (tester) async {
    Map<String, Object?>? requested;
    await tester.pumpWidget(MaterialApp(
      home: AdminDisbursementsPage(
        english: true,
        loadPage: ({required month, required status, required query, required page}) async {
          requested = {'month': month, 'status': status, 'query': query, 'page': page};
          return {
            'items': [
              {
                'id': 4,
                'month': '2026-10',
                'status': 'transferred',
                'total_amount': 1500,
                'nanny_name': 'Nanny One',
                'group_name': 'Group A',
                'items_total': 2,
                'items_paid': 1,
                'items_pending': 1,
                'items_returned': 0,
                'paid_amount': 750,
                'pending_amount': 750,
                'returned_amount': 0,
              },
            ],
            'pagination': {'page': 1, 'pages': 1, 'total': 1, 'per_page': 50},
          };
        },
        loadDetail: (id, page) async => {
          'batch': {
            'id': id,
            'month': '2026-10',
            'status': 'transferred',
            'total_amount': 1500,
            'nanny_name': 'Nanny One',
            'group_name': 'Group A',
            'items_total': 1,
            'items_paid': 1,
            'items_pending': 0,
            'items_returned': 0,
            'paid_amount': 1500,
            'pending_amount': 0,
            'returned_amount': 0,
          },
          'items': [
            {
              'id': 19,
              'family_id': 7,
              'family_code': 'FAM-007',
              'mother_name': 'Family Mother',
              'amount': 1500,
              'status': 'paid',
              'children_count': 2,
            },
          ],
          'items_pagination': {'page': page, 'pages': 1, 'total': 1, 'per_page': 100},
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Monthly disbursements'), findsOneWidget);
    expect(find.text('Results: 1'), findsOneWidget);
    expect(requested?['status'], 'all');

    final scrollable = find.byType(Scrollable).first;
    await tester.scrollUntilVisible(
      find.byKey(const Key('disbursement-batch-4')),
      180,
      scrollable: scrollable,
    );
    expect(find.byKey(const Key('disbursement-batch-4')), findsOneWidget);
    await tester.tap(find.byKey(const Key('disbursement-batch-4')));
    await tester.pumpAndSettle();

    expect(find.text('Batch details #4'), findsOneWidget);
    expect(find.text('Family Mother'), findsOneWidget);
    expect(find.text('FAM-007 · Children: 2 · Paid'), findsOneWidget);
    expect(find.textContaining('Read-only — batches cannot be changed'), findsOneWidget);
  });

  testWidgets('nanny can choose and submit a required receipt for a pending family item', (tester) async {
    int? submittedItemId;
    String? submittedFilename;
    await tester.pumpWidget(MaterialApp(
      home: AdminDisbursementDetailPage(
        english: true,
        batchId: 9,
        roleCode: 'nanny',
        loadDetail: (id, page) async => {
          'batch': {
            'id': id, 'month': '2026-10', 'status': 'transferred',
            'total_amount': 500, 'items_total': 1, 'items_paid': 0,
            'items_pending': 1, 'items_returned': 0, 'paid_amount': 0,
            'pending_amount': 500, 'returned_amount': 0,
          },
          'items': [
            {'id': 19, 'family_id': 7, 'family_code': 'FAM-007', 'mother_name': 'Family Mother',
             'amount': 500, 'status': 'pending', 'children_count': 1},
          ],
          'items_pagination': {'page': page, 'pages': 1, 'total': 1, 'per_page': 100},
        },
        pickReceipt: () async => PlatformFile(
          name: 'receipt.png', size: 3, bytes: Uint8List.fromList([1, 2, 3]),
        ),
        submitItemConfirmation: (id, receipt) async {
          submittedItemId = id;
          submittedFilename = receipt.name;
          return {'item_id': id, 'status': 'paid'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Choose receipt file'), findsOneWidget);
    final confirm = find.byKey(const Key('disbursement-confirm-item-19'));
    expect(confirm, findsOneWidget);
    expect(tester.widget<FilledButton>(confirm).onPressed, isNull);

    await tester.tap(find.byKey(const Key('disbursement-pick-receipt-19')));
    await tester.pumpAndSettle();
    expect(find.text('receipt.png'), findsOneWidget);
    expect(tester.widget<FilledButton>(confirm).onPressed, isNotNull);

    await tester.tap(confirm);
    await tester.pumpAndSettle();
    expect(submittedItemId, 19);
    expect(submittedFilename, 'receipt.png');
    expect(find.text('Family receipt confirmed successfully.'), findsOneWidget);
  });

  testWidgets('shows a safe empty state when no batches match filters', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AdminDisbursementsPage(
        english: false,
        loadPage: ({required month, required status, required query, required page}) async => {
          'items': <Object?>[],
          'pagination': {'page': 1, 'pages': 1, 'total': 0, 'per_page': 50},
        },
        loadDetail: (id, page) async => {},
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('لا توجد دفعات مطابقة للفلاتر.'), findsOneWidget);
    expect(find.text('إجمالي النتائج: 0'), findsOneWidget);
  });
}
