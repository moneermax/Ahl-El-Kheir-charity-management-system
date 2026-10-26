import 'package:ahl_el_kheir_app/admin_backup_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('admin backup page lists files and invokes native actions', (tester) async {
    var created = false;
    final deleted = <String>[];
    final downloaded = <String>[];
    await tester.pumpWidget(MaterialApp(home: AdminBackupPage(
      english: true,
      loadBackups: () async => {
        'database_name': 'ahl_el_kheir',
        'backups': created ? [] : [
          {'filename': 'backup_20261026_080000_a1b2c3.sql', 'size_kb': 123.4, 'modified_at': '2026-10-26 08:00:00'},
        ],
      },
      createBackup: () async { created = true; return {'backup': {'filename': 'backup_20261026_080001_d4e5f6.sql'}}; },
      deleteBackup: (filename) async { deleted.add(filename); return {'deleted': filename}; },
      downloadBackup: (filename) async { downloaded.add(filename); return true; },
    )));
    await tester.pumpAndSettle();
    expect(find.text('Database backup'), findsOneWidget);
    expect(find.text('Database: ahl_el_kheir'), findsOneWidget);
    expect(find.text('backup_20261026_080000_a1b2c3.sql'), findsOneWidget);
    await tester.tap(find.text('Download'));
    await tester.pumpAndSettle();
    expect(downloaded, ['backup_20261026_080000_a1b2c3.sql']);
    await tester.tap(find.text('Create backup now'));
    await tester.pumpAndSettle();
    expect(created, isTrue);
    expect(find.text('No backups available yet.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
