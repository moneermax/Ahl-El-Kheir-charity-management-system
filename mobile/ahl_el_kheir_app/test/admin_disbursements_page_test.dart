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
    expect(find.textContaining('Other batch actions are not available on this screen yet'), findsOneWidget);
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


  testWidgets('administrator can choose a transfer receipt and post the batch', (tester) async {
    int? transferredBatchId;
    String? transferFilename;
    await tester.pumpWidget(MaterialApp(
      home: AdminDisbursementDetailPage(
        english: true,
        batchId: 12,
        roleCode: 'admin',
        loadDetail: (id, page) async => {
          'batch': {
            'id': id, 'month': '2026-10', 'status': 'pending_approval',
            'total_amount': 3000, 'items_total': 1, 'items_paid': 0,
            'items_pending': 1, 'items_returned': 0, 'paid_amount': 0,
            'pending_amount': 3000, 'returned_amount': 0,
          },
          'items': [],
          'items_pagination': {'page': page, 'pages': 1, 'total': 0, 'per_page': 100},
        },
        pickTransferReceipt: () async => PlatformFile(
          name: 'bank-transfer.png', size: 3, bytes: Uint8List.fromList([1, 2, 3]),
        ),
        submitTransfer: (id, receipt) async {
          transferredBatchId = id;
          transferFilename = receipt.name;
          return {'batch_id': id, 'status': 'transferred'};
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('disbursement-pick-transfer-receipt')), findsOneWidget);
    final submit = find.byKey(const Key('disbursement-submit-transfer'));
    expect(tester.widget<FilledButton>(submit).onPressed, isNull);
    await tester.tap(find.byKey(const Key('disbursement-pick-transfer-receipt')));
    await tester.pumpAndSettle();
    expect(find.text('bank-transfer.png'), findsOneWidget);
    expect(tester.widget<FilledButton>(submit).onPressed, isNotNull);
    await tester.tap(submit);
    await tester.pumpAndSettle();
    expect(transferredBatchId, 12);
    expect(transferFilename, 'bank-transfer.png');
    expect(find.text('Transfer completed and accounting journal posted.'), findsOneWidget);
  });

  testWidgets('administrator can select eligible groups and create pending monthly batches', (tester) async {
    String? createdMonth;
    List<int>? createdGroups;
    final now = DateTime.now();
    final month = '${now.year}-${now.month.toString().padLeft(2, '0')}';
    await tester.pumpWidget(MaterialApp(
      home: Builder(builder: (context) => Scaffold(
        body: Center(child: ElevatedButton(
          key: const Key('open-create-batches'),
          onPressed: () {
            Navigator.of(context).push<bool>(MaterialPageRoute<bool>(builder: (_) => DisbursementBatchCreatePage(
              english: true,
              loadEligibleGroups: (requestedMonth) async => {
                'eligible_month': requestedMonth,
                'eligible_groups': [
                  {'id': 5, 'group_name': 'Group A', 'nanny_name': 'Nanny One', 'orphan_count': 1, 'expected_amount': 3000},
                ],
              },
              createBatches: (requestedMonth, groupIds) async {
                createdMonth = requestedMonth;
                createdGroups = List<int>.from(groupIds);
                return {'created_count': 1, 'skipped_count': 0};
              },
            )));
          },
          child: const Text('Open'),
        )),
      )),
    ));
    await tester.tap(find.byKey(const Key('open-create-batches')));
    await tester.pumpAndSettle();
    expect(find.text('Eligible groups'), findsOneWidget);
    expect(find.text('Group A'), findsOneWidget);
    await tester.tap(find.byKey(const Key('disbursement-group-5')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('disbursement-create-submit')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 250));
    expect(find.text('Created 1 batch(es) and skipped 0 group(s).'), findsOneWidget);
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(createdMonth, month);
    expect(createdGroups, [5]);
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
