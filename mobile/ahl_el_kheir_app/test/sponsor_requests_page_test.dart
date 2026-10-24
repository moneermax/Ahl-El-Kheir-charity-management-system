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
              {'id': 1, 'sponsor_name': 'أحمد محمد', 'phone': '0912345678', 'gender': 'male', 'source': 'فيسبوك', 'brought_by_name': 'سارة علي', 'brought_by_user': 'Fallback User', 'status': 'new', 'created_at': '2026-10-24', 'notes': ''}
            ],
            'total': 1, 'page': 1, 'pages': 1,
          },
          submitAction: (_, __) async => {'message': 'تم تحديث الحالة.'},
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('طلبات الرعاية'), findsOneWidget);
    expect(find.text('أحمد محمد'), findsOneWidget);
    expect(find.text('جديد'), findsOneWidget);
    expect(find.textContaining('فيسبوك'), findsOneWidget);
    expect(find.textContaining('سارة علي'), findsOneWidget);
    expect(find.textContaining('Fallback User'), findsNothing);
  });

  testWidgets('falls back to account name when sponsor request attribution name is empty', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: SponsorRequestsPage(
          english: false,
          loadRequests: (_, __, ___) async => {
            'items': [
              {
                'id': 2,
                'sponsor_name': 'مريم أحمد',
                'brought_by_name': '  ',
                'brought_by_user': 'موظف التسجيل',
                'status': 'new',
              }
            ],
            'total': 1, 'page': 1, 'pages': 1,
          },
          submitAction: (_, __) async => {'message': 'تم تحديث الحالة.'},
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.textContaining('بواسطة: موظف التسجيل'), findsOneWidget);
  });

  testWidgets('confirms and submits the contacted transition then reloads the queue', (tester) async {
    var status = 'new';
    var actionCalls = 0;
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: SponsorRequestsPage(
          english: false,
          loadRequests: (_, __, ___) async => {
            'items': [
              {'id': 7, 'sponsor_name': 'سالم أحمد', 'status': status}
            ],
            'total': 1, 'page': 1, 'pages': 1,
          },
          submitAction: (action, id) async {
            expect(action, 'mark_contacted');
            expect(id, 7);
            actionCalls++;
            status = 'contacted';
            return {'message': 'تم تحديث الحالة.'};
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('تأكيد التواصل'), findsOneWidget);
    await tester.tap(find.text('تأكيد التواصل'));
    await tester.pumpAndSettle();
    expect(find.text('تأكيد تسجيل التواصل؟'), findsOneWidget);
    await tester.tap(find.text('تأكيد'));
    await tester.pumpAndSettle();
    expect(actionCalls, 1);
    expect(find.text('تم التواصل'), findsOneWidget);
    expect(find.text('تأكيد التواصل'), findsNothing);
  });
}
