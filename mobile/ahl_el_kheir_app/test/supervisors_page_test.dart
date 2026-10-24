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
}
