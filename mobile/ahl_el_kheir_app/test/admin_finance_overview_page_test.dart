import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_finance_overview_page.dart';

void main() {
  testWidgets('renders read-only treasury, monthly flow and disbursement metrics', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AdminFinanceOverviewPage(
        english: true,
        loadOverview: () async => {
          'period': {'start': '2026-10-01', 'end': '2026-10-31'},
          'treasury': {'cash': 1234.5, 'bank': 2000, 'wallet': 75, 'total': 3309.5},
          'monthly_flow': {'income_total': 8000, 'outgoing_total': 2500},
          'admin_fees': {'admin_fees_total': 900, 'admin_fees_month': 100},
          'disbursements': {'total': 12, 'pending_approval': 2, 'approved': 3, 'transferred': 4, 'received': 3, 'pending_amount': 500, 'transferred_amount': 700, 'returned_amount': 50},
          'quick_stats': {'active_sponsorships': 10, 'active_families': 8, 'active_nannies': 4, 'received_this_month': 600},
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Finance overview'), findsOneWidget);
    expect(find.text('2026-10-01 — 2026-10-31'), findsOneWidget);
    expect(find.text('Treasury'), findsOneWidget);
    expect(find.text('1,234.50'), findsOneWidget);
    expect(find.text('3,309.50'), findsOneWidget);
    expect(find.text('Monthly flow'), findsOneWidget);
    expect(find.text('8,000.00'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Monthly disbursements'), 220, scrollable: find.byType(Scrollable).first);
    expect(find.text('Monthly disbursements'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Operational counts'), 220, scrollable: find.byType(Scrollable).first);
    expect(find.text('Operational counts'), findsOneWidget);
    await tester.scrollUntilVisible(find.textContaining('do not perform accounting actions'), 220, scrollable: find.byType(Scrollable).first);
    expect(find.textContaining('do not perform accounting actions'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
