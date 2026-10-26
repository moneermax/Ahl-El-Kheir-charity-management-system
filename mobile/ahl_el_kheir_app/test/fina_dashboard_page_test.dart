import 'package:ahl_el_kheir_app/fina_dashboard_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Fina dashboard renders independent collection metrics and review action', (tester) async {
    await tester.pumpWidget(MaterialApp(home: FinaDashboardPage(
      english: true,
      loadDashboard: () async => {
        'pending_count': 3,
        'approved_count': 5,
        'returned_count': 1,
        'approved_amount': 1250.5,
        'currency_code': 'SDG',
        'liability_account': {'code': '2300', 'configured': true, 'active': true},
        'can_view_review': true,
      },
      onOpenReview: () {},
    )));
    await tester.pumpAndSettle();
    expect(find.text('Fina Al-Khair dashboard'), findsOneWidget);
    expect(find.text('Pending review'), findsOneWidget);
    expect(find.text('3'), findsOneWidget);
    expect(find.text('Approved collections'), findsOneWidget);
    expect(find.text('Returned collections'), findsOneWidget);
    expect(find.text('Liability account 2300'), findsOneWidget);
    expect(find.text('Account is configured and active.'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Review collections'), 180, scrollable: find.byType(Scrollable).first);
    expect(find.text('Review collections'), findsOneWidget);
  });
}
