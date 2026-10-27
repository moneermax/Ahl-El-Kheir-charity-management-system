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
    expect(loadCalls, 1);
    expect(find.text('Notification details'), findsOneWidget);
    expect(find.text('Your request was reviewed.'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();

    expect(loadCalls, 2);
  });

  testWidgets('opens notification details even when marking it read fails',
      (tester) async {
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
                  'id': 43,
                  'title': 'Unread alert',
                  'body': 'Please review.',
                  'is_read': false,
                },
              ],
              'total': 1,
              'unread': 1,
              'page': 1,
              'pages': 1,
            };
          },
          markRead: (_) async => throw Exception('network failure'),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Unread alert'));
    await tester.pumpAndSettle();

    expect(loadCalls, 1);
    expect(find.text('Notification details'), findsOneWidget);
    expect(find.text('Please review.'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();

    expect(loadCalls, 2);
    expect(find.textContaining('network failure'), findsOneWidget);
  });

  testWidgets('does not send a mark-read request for an already-read item',
      (tester) async {
    var markReadCalls = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationPage(
          english: true,
          loadPage: (_) async => {
            'items': [
              {
                'id': 44,
                'title': 'Previously read alert',
                'body': 'Already seen.',
                'is_read': true,
              },
            ],
            'total': 1,
            'unread': 0,
            'page': 1,
            'pages': 1,
          },
          markRead: (_) async => markReadCalls++,
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Previously read alert'));
    await tester.pumpAndSettle();

    expect(markReadCalls, 0);
  });

  test('notification links resolve to an in-app module path only', () {
    expect(
      notificationNativeTargetPath(
        'http://localhost:8081/AhlElKheir/modules/hr/salary_advance_waiver_gm.php?decision_id=17',
      ),
      'modules/hr/salary_advance_waiver_gm.php',
    );
    expect(notificationNativeTargetPath('https://example.invalid/related'), isNull);
    expect(
      notificationNativeTargetPath('https://example.invalid/modules/notifications/index.php'),
      isNull,
    );
    expect(notificationNativeTargetPath('javascript:alert(1)'), isNull);
  });

  testWidgets('opens notification details and exposes its supported in-app destination',
      (tester) async {
    const relatedLink = 'http://localhost:8081/AhlElKheir/modules/hr/salary_advance_waiver_gm.php?decision_id=17';
    String? openedLink;
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationPage(
          english: false,
          loadPage: (_) async => {
            'items': [
              {
                'id': 77,
                'title': 'إشعار تجريبي',
                'body': 'محتوى الإشعار داخل التطبيق',
                'created_at': '2026-10-25 12:00:00',
                'is_read': true,
                'link': relatedLink,
              },
            ],
            'total': 1,
            'unread': 0,
            'page': 1,
            'pages': 1,
          },
          markRead: (_) async {},
          openLinkedPage: (link) async {
            openedLink = link;
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('إشعار تجريبي'));
    await tester.pumpAndSettle();

    expect(find.text('تفاصيل الإشعار'), findsOneWidget);
    expect(find.text('محتوى الإشعار داخل التطبيق'), findsOneWidget);
    expect(find.text('فتح الصفحة المرتبطة'), findsOneWidget);

    await tester.tap(find.text('فتح الصفحة المرتبطة'));
    await tester.pumpAndSettle();

    expect(openedLink, relatedLink);
  });


}
