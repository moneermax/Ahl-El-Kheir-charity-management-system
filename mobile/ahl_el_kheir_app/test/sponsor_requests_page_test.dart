import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/sponsor_requests_page.dart';

void main() {
  testWidgets('renders Arabic sponsor request queue and status', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: SponsorRequestsPage(
          english: false,
          loadRequests: (_, __, ___) async => {
            'items': [
              {'id': 1, 'sponsor_name': 'أحمد محمد', 'phone': '0912345678', 'gender': 'male', 'source': 'فيسبوك', 'status': 'new', 'created_at': '2026-10-24', 'notes': ''}
            ],
            'total': 1, 'page': 1, 'pages': 1,
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('طلبات الرعاية'), findsOneWidget);
    expect(find.text('أحمد محمد'), findsOneWidget);
    expect(find.text('جديد'), findsOneWidget);
    expect(find.textContaining('فيسبوك'), findsOneWidget);
  });
}
