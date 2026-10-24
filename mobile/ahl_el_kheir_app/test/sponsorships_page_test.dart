import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsorships_page.dart';

void main() {
  testWidgets('renders Arabic sponsorship list and active summary', (tester) async {
    await tester.pumpWidget(MaterialApp(home: Directionality(
      textDirection: TextDirection.rtl,
      child: SponsorshipsPage(english: false, loadDetail: (_) async => {'sponsorship': {}, 'payments': []}, loadSponsorships: (_, __, ___, ____) async => {
        'items': [{
          'id': 1, 'sponsorship_code': 'SH-000001', 'monthly_amount': 2000.0,
          'status': 'active', 'start_date': '2026-01-01', 'sponsor_name': 'محمد أحمد',
          'sponsor_code': 'SP-000001', 'child_name': 'أحمد علي',
          'mother_name': 'فاطمة علي', 'family_code': 'FAM-0001'
        }],
        'total': 1, 'page': 1, 'pages': 1, 'active_count': 1, 'active_monthly_total': 2000.0
      }),
    )));
    await tester.pumpAndSettle();
    expect(find.text('الكفالات'), findsOneWidget);
    expect(find.text('SH-000001'), findsOneWidget);
    expect(find.textContaining('الإجمالي الشهري النشط: 2000.00 SDG'), findsOneWidget);
    expect(find.text('نشطة'), findsOneWidget);
  });
}