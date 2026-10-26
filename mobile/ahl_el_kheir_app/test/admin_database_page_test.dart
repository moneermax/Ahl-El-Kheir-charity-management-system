import 'package:ahl_el_kheir_app/admin_database_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('database browser renders selected table schema and recent rows', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AdminDatabasePage(
        english: true,
        loadPage: (table) async => {
          'tables': [
            {'name': 'users', 'count': 2},
            {'name': 'roles', 'count': 4},
          ],
          'selected_table': table ?? 'users',
          'columns': [
            {'name': 'id', 'type': 'int', 'nullable': 'NO', 'key': 'PRI', 'extra': 'auto_increment'},
            {'name': 'username', 'type': 'varchar(100)', 'nullable': 'NO', 'key': 'UNI', 'extra': ''},
          ],
          'rows': [
            {'id': 2, 'username': 'admin'},
            {'id': 1, 'username': 'staff'},
          ],
          'row_limit': 50,
        },
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Database browser'), findsOneWidget);
    expect(find.text('Read-only browser'), findsOneWidget);
    expect(find.text('users (2)'), findsOneWidget);
    expect(find.text('Table schema: users'), findsOneWidget);
    expect(find.text('varchar(100) · Nullable: NO · Key: UNI'), findsOneWidget);
    expect(find.text('admin'), findsOneWidget);
    expect(find.text('staff'), findsOneWidget);
    expect(find.textContaining('No data changes or schema operations'), findsOneWidget);
  });
}
