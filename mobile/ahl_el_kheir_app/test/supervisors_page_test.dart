import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/supervisors_page.dart';

void main() {
  testWidgets('renders Arabic supervisor details and filters archived records', (tester) async {
    final calls = <({String query, bool archived, int page})>[];
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: SupervisorsPage(
          english: false,
          canManage: false,
          submitStatusAction: (_, __) async => <String, dynamic>{},
          loadPage: (query, archived, page) async {
            calls.add((query: query, archived: archived, page: page));
            return {
              'items': [
                {
                  'id': 5,
                  'username': 'supervisor5',
                  'full_name': 'سارة محمد',
                  'phone': '0912345678',
                  'email': 'sara@example.test',
                  'supervisor_status': 'active',
                  'letters': 'أ(إ)، ب(ذ)',
                },
              ],
              'total': 1,
              'page': page,
              'pages': 1,
            };
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('المشرفون'), findsOneWidget);
    expect(find.text('سارة محمد'), findsOneWidget);
    expect(find.textContaining('أ(إ)، ب(ذ)'), findsOneWidget);
    expect(calls.first.archived, isFalse);

    await tester.tap(find.byType(SwitchListTile));
    await tester.pumpAndSettle();
    expect(calls.last.archived, isTrue);
    expect(find.text('إظهار من غادروا والمؤرشفين'), findsOneWidget);
  });
  testWidgets('VGM can confirm a supervisor status toggle and see refreshed status', (tester) async {
    var active = true;
    final actions = <List<dynamic>>[];
    await tester.pumpWidget(MaterialApp(
      home: SupervisorsPage(
        english: true,
        canManage: true,
        loadPage: (_, __, page) async => {
          'items': [
            {
              'id': 21,
              'username': 'supervisor.one',
              'full_name': 'Supervisor One',
              'phone': '',
              'email': '',
              'is_active': active,
              'supervisor_status': active ? 'active' : 'suspended',
              'letters': 'ب',
              'last_login_at': '',
            },
          ],
          'total': 1,
          'page': page,
          'pages': 1,
          'show_archived': false,
        },
        submitStatusAction: (id, action) async {
          actions.add([id, action]);
          active = !active;
          return {'supervisor_id': id, 'is_active': active};
        },
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.text('Active'), findsOneWidget);
    await tester.tap(find.byIcon(Icons.pause_circle_outline_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Suspend supervisor?'), findsOneWidget);
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();

    expect(actions, [[21, 'toggle_status']]);
    expect(find.text('Suspended'), findsOneWidget);
    expect(find.byIcon(Icons.play_circle_outline_rounded), findsOneWidget);
  });

  testWidgets('read-only supervisor list hides status controls', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: SupervisorsPage(
        english: true,
        canManage: false,
        loadPage: (_, __, page) async => {
          'items': [
            {
              'id': 22,
              'username': 'supervisor.two',
              'full_name': 'Supervisor Two',
              'phone': '',
              'email': '',
              'is_active': true,
              'supervisor_status': 'active',
              'letters': '',
              'last_login_at': '',
            },
          ],
          'total': 1,
          'page': page,
          'pages': 1,
          'show_archived': false,
        },
        submitStatusAction: (_, __) async => <String, dynamic>{},
      ),
    ));

    await tester.pumpAndSettle();
    expect(find.byIcon(Icons.pause_circle_outline_rounded), findsNothing);
    expect(find.byIcon(Icons.play_circle_outline_rounded), findsNothing);
  });

}
