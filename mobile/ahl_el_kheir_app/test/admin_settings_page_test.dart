import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_settings_page.dart';

void main() {
  testWidgets('native settings loads values and submits edits without echoing SMTP password', (tester) async {
    var saved = <String, dynamic>{};
    var loadCount = 0;
    await tester.pumpWidget(MaterialApp(
      home: AdminSettingsPage(
        english: true,
        loadSettings: () async {
          loadCount++;
          return {
            'groups': {
              'organization': {
                'org_name_en': {'type': 'text', 'label_ar': 'اسم المنظمة بالإنجليزية'},
                'support_email': {'type': 'email', 'label_ar': 'بريد الدعم'},
              },
              'smtp': {
                'smtp_password': {'type': 'password', 'label_ar': 'كلمة مرور SMTP'},
                'smtp_encryption': {'type': 'select', 'label_ar': 'تشفير SMTP', 'options': ['tls', 'ssl', 'none']},
              },
            },
            'values': {
              'org_name_en': 'Ahl El Kheir',
              'support_email': 'support@example.org',
              'smtp_password': '',
              'smtp_password_configured': true,
              'smtp_encryption': 'tls',
            },
          };
        },
        saveSettings: (values) async {
          saved = Map<String, dynamic>.from(values);
          return {'saved': true};
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('System settings'), findsOneWidget);
    expect(find.text('Ahl El Kheir'), findsOneWidget);
    expect(find.textContaining('Configured'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.enterText(find.byType(TextFormField).first, 'Ahl El Kheir Foundation');
    await tester.ensureVisible(find.text('Save settings'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save settings'));
    await tester.pumpAndSettle();

    expect(saved['org_name_en'], 'Ahl El Kheir Foundation');
    expect(saved.containsKey('smtp_password'), isFalse);
    expect(loadCount, 2);
    expect(tester.takeException(), isNull);
  });
}
