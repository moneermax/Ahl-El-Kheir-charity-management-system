import 'package:ahl_el_kheir_app/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('shows the Arabic notification inbox and notification content',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: NotificationPage(
            english: false,
            loadPage: (_) async => {
              'items': [
                {
                  'id': 42,
                  'title': 'عنوان الإشعار',
                  'body': 'تفاصيل الإشعار',
                  'created_at': '2026-10-24 10:00:00',
                  'is_read': false,
                },
              ],
              'total': 1,
              'unread': 1,
              'page': 1,
              'pages': 1,
            },
            markRead: (_) async {},
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('الإشعارات'), findsOneWidget);
    expect(find.text('عنوان الإشعار'), findsOneWidget);
    expect(find.text('تفاصيل الإشعار'), findsOneWidget);
    expect(find.text('الإجمالي: 1  •  غير المقروء: 1'), findsOneWidget);
  });

  testWidgets('marks an unread notification as read and reloads the inbox',
      (tester) async {
    var markReadCalls = 0;
    var loadCalls = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationPage(
          english: true,
          loadPage: (_) async {
            loadCalls++;
            return {
              'items': [
                {
                  'id': 42,
                  'title': 'Leave request update',
                  'body': 'Your request was reviewed.',
                  'is_read': false,
                },
              ],
              'total': 1,
              'unread': 1,
              'page': 1,
              'pages': 1,
            };
          },
          markRead: (id) async {
            expect(id, 42);
            markReadCalls++;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Leave request update'));
    await tester.pumpAndSettle();

    expect(markReadCalls, 1);
    expect(loadCalls, 2);
  });
}
