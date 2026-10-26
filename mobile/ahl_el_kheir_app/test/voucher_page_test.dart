import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/voucher_page.dart';

void main() {
  const sample = <String, dynamic>{
    'ok': true,
    'data': {
      'vouchers': [
        {
          'id': 9,
          'voucher_type': 'receipt',
          'voucher_no': 'RV-000009',
          'voucher_date': '2026-10-20',
          'party_name': 'Test sponsor',
          'amount': 1250.5,
          'cash_code': '1100',
          'cash_name': 'Cash',
          'other_code': '4100',
          'other_name': 'Donation revenue',
          'description': 'Monthly sponsorship',
          'reference_number': 'REF-9',
          'entry_code': 'JE-000009',
          'status': 'posted',
          'creator': 'Finance user',
        },
      ],
      'cash_accounts': [
        {'id': 1, 'code': '1100', 'name_ar': 'Cash', 'balance': 8200.25},
      ],
      'can_issue': true,
      'can_void': false,
      'limit': 300,
    },
  };

  testWidgets('shows voucher details and cash balances in Arabic', (tester) async {
    final requestedTypes = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: VoucherPage(
        english: false,
        loadVouchers: (type) async {
          requestedTypes.add(type);
          return sample;
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('سندات القبض والصرف'), findsOneWidget);
    expect(find.text('RV-000009'), findsOneWidget);
    expect(find.text('Test sponsor'), findsOneWidget);
    expect(find.text('1250.50'), findsOneWidget);
    expect(find.text('8200.25'), findsOneWidget);
    expect(find.textContaining('القراءة فقط'), findsOneWidget);
    expect(requestedTypes, ['']);
  });

  testWidgets('filters to payment vouchers and refreshes through the loader', (tester) async {
    final requestedTypes = <String>[];
    await tester.pumpWidget(MaterialApp(
      home: VoucherPage(
        english: true,
        loadVouchers: (type) async {
          requestedTypes.add(type);
          return {
            'ok': true,
            'data': {
              'vouchers': type == 'payment' ? [] : sample['data']['vouchers'],
              'cash_accounts': sample['data']['cash_accounts'],
              'can_issue': false,
              'can_void': false,
              'limit': 300,
            },
          };
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Payments'));
    await tester.pumpAndSettle();

    expect(requestedTypes, ['', 'payment']);
    expect(find.text('No matching vouchers.'), findsOneWidget);
  });

  testWidgets('shows a recoverable error and retries', (tester) async {
    var calls = 0;
    await tester.pumpWidget(MaterialApp(
      home: VoucherPage(
        english: true,
        loadVouchers: (_) async {
          calls++;
          if (calls == 1) throw Exception('Temporary API failure');
          return sample;
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Could not load vouchers'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(find.text('RV-000009'), findsOneWidget);
    expect(calls, 2);
  });
}
