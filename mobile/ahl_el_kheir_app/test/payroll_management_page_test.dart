import 'dart:typed_data';
import 'package:ahl_el_kheir_app/payroll_management_page.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, dynamic> fixtureRow({
  required int id,
  required String status,
  String? proofState,
  bool hasProof = false,
}) => {
  'id': id, 'employee_id': id, 'employee_code': 'EMP-00' + id.toString(),
  'employee_name': 'Employee ' + id.toString(), 'month': 10, 'year': 2026,
  'basic_salary': 1000, 'allowances': 10, 'overtime': 0, 'deductions': 0,
  'attendance_deduction': 0, 'salary_advance_deduction': 0, 'incentive_amount': 0,
  'net_salary': 1010, 'status': status, 'accounting_status': status == 'paid' ? 'posted' : (status == 'approved' ? 'ready' : 'none'),
  'accounting_entry_id': status == 'paid' ? 99 : null, 'payment_account_id': 1,
  'payment_account_code': '1200', 'has_payment_proof': hasProof,
  'payment_proof_id': hasProof ? id + 100 : null, 'payment_proof_state': proofState,
  'payment_proof_filename': hasProof ? 'proof.pdf' : null,
};

Map<String, dynamic> pageData(List<Map<String, dynamic>> rows) => {
  'month': 10, 'year': 2026, 'period': '2026-10', 'transfer_schema_available': true,
  'summary': {'count': rows.length, 'draft': rows.where((r) => r['status'] == 'draft').length,
    'approved': rows.where((r) => r['status'] == 'approved').length, 'paid': rows.where((r) => r['status'] == 'paid').length,
    'net': rows.fold<num>(0, (v, r) => v + (r['net_salary'] as num)), 'incentives': 0},
  'rows': rows,
  'active_payment_accounts': [{'id': 1, 'code': '1200', 'name_ar': 'البنك', 'name_en': 'Bank'}],
  'default_payment_account': {'default_payment_account_id': 1},
  'active_payroll_policy': {'id': 1, 'version_no': 1, 'effective_from': '2026-01-01'},
};

void main() {
  testWidgets('payroll screen distinguishes draft approval from payment confirmation', (tester) async {
    final submitted = <Map<String, dynamic>>[];
    final rows = [fixtureRow(id: 1, status: 'draft'), fixtureRow(id: 2, status: 'approved', proofState: 'prepared', hasProof: true)];
    await tester.pumpWidget(MaterialApp(home: PayrollManagementPage(
      english: true,
      loadPage: (month, year) async => pageData(rows),
      submitAction: (body) async { submitted.add(Map<String, dynamic>.from(body)); return {}; },
      pickProof: () async => null,
      uploadProof: (id, month, year, proof, legacy) async => {},
    )));
    await tester.pumpAndSettle();
    expect(find.text('Payroll management'), findsOneWidget);
    expect(find.byKey(const Key('payroll-approve-1')), findsOneWidget);
    expect(find.byKey(const Key('payroll-confirm-transfer-1')), findsNothing);
    expect(find.byKey(const Key('payroll-confirm-transfer-2')), findsOneWidget);
    await tester.tap(find.byKey(const Key('payroll-confirm-transfer-2')));
    await tester.pumpAndSettle();
    expect(find.text('Confirm transfer execution'), findsOneWidget);
    expect(submitted, isEmpty);
    await tester.tap(find.widgetWithText(FilledButton, 'Confirm').last);
    await tester.pumpAndSettle();
    expect(submitted.single['action'], 'confirm_transfer');
    expect(submitted.single['payroll_id'], 2);
    expect(submitted.single['month'], 10);
    expect(submitted.single['year'], 2026);
  });

  testWidgets('proof upload remains a separate action and is not transfer confirmation', (tester) async {
    var uploadedId = 0;
    var uploadedLegacy = true;
    final rows = [fixtureRow(id: 3, status: 'approved')];
    await tester.pumpWidget(MaterialApp(home: PayrollManagementPage(
      english: true,
      loadPage: (month, year) async => pageData(rows),
      submitAction: (body) async => {'action': body['action']},
      pickProof: () async => PlatformFile(name: 'transfer.pdf', size: 8, bytes: Uint8List.fromList([37, 80, 68, 70, 45, 49, 46, 52])),
      uploadProof: (id, month, year, proof, legacy) async {
        uploadedId = id;
        uploadedLegacy = legacy;
        expect(proof.name, 'transfer.pdf');
        return {'proof_state': 'prepared'};
      },
    )));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('payroll-pick-proof-3')));
    await tester.pumpAndSettle();
    expect(find.text('transfer.pdf'), findsOneWidget);
    await tester.tap(find.byKey(const Key('payroll-upload-proof-3')));
    await tester.pumpAndSettle();
    expect(uploadedId, 3);
    expect(uploadedLegacy, isFalse);
    expect(find.byKey(const Key('payroll-confirm-transfer-3')), findsNothing);
    expect(find.text('Proof saved only; payment was not recorded.'), findsOneWidget);
  });
}
