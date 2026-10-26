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
      'other_accounts': [
        {'id': 4, 'code': '4100', 'name_ar': 'Donation revenue', 'account_type': 'revenue'},
        {'id': 5, 'code': '5100', 'name_ar': 'Office expense', 'account_type': 'expense'},
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
    expect(find.textContaining('للقراءة فقط'), findsOneWidget);
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


  testWidgets('authorized user can issue a receipt from the native form', (tester) async {
    final submitted=<Map<String,dynamic>>[]; var loads=0;
    await tester.pumpWidget(MaterialApp(home:VoucherPage(english:true,
      issueVoucher:(input)async{submitted.add(input);return {'ok':true};},voidVoucher:(_,__)async=>{'ok':true},
      loadVouchers:(_)async{loads++;return {'ok':true,'data':{'vouchers':sample['data']['vouchers'],'cash_accounts':sample['data']['cash_accounts'],'other_accounts':sample['data']['other_accounts'],'can_issue':true,'can_void':false,'limit':300}};}
    )));
    await tester.pumpAndSettle(); await tester.tap(find.text('New voucher')); await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('voucher-amount')),'12.50');
    await tester.enterText(find.byKey(const Key('voucher-party')),'Test donor');
    await tester.enterText(find.byKey(const Key('voucher-description')),'Test receipt');
    await tester.tap(find.text('Issue and post')); await tester.pumpAndSettle();
    expect(submitted,hasLength(1)); expect(submitted.single['action'],'issue_voucher');
    expect(submitted.single['voucher_type'],'receipt'); expect(submitted.single['amount'],12.5);
    expect(submitted.single['cash_account_id'],1); expect(submitted.single['other_account_id'],4); expect(loads,2);
  });

  testWidgets('authorized reviewer can void a posted voucher with a reason', (tester) async {
    final voided=<List<Object>>[]; var loads=0;
    await tester.pumpWidget(MaterialApp(home:VoucherPage(english:true,issueVoucher:(_)async=>{'ok':true},
      voidVoucher:(id,reason)async{voided.add([id,reason]);return {'ok':true};},
      loadVouchers:(_)async{loads++;final v=Map<String,dynamic>.from(sample['data']['vouchers'][0] as Map);if(loads>1)v['status']='voided';
        return {'ok':true,'data':{'vouchers':[v],'cash_accounts':sample['data']['cash_accounts'],'other_accounts':sample['data']['other_accounts'],'can_issue':false,'can_void':true,'limit':300}};}
    )));
    await tester.pumpAndSettle(); await tester.tap(find.text('Void voucher')); await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('voucher-void-reason')),'Duplicate receipt');
    await tester.tap(find.text('Void voucher').last); await tester.pumpAndSettle();
    expect(voided,hasLength(1)); expect(voided.single[0],9); expect(voided.single[1],'Duplicate receipt'); expect(loads,2);
  });

}
