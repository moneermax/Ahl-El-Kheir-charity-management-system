import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_dashboard_page.dart';

void main() {
  testWidgets('admin dashboard exposes guarded password recovery route', (tester) async {
    String? openedTool;

    await tester.pumpWidget(MaterialApp(
      home: AdminDashboardPage(
        english: true,
        fullName: 'System Administrator',
        loadOverview: () async => {
          'stats': {
            'users': 1,
            'sponsors': 2,
            'families': 3,
            'sponsorships': 4,
            'pending_recovery': 1,
          },
          'recent_audit': <Map<String, dynamic>>[],
          'server': {'database_connected': true, 'php_version': '8.2'},
        },
        onOpenTool: (tool) => openedTool = tool,
        onOpenAgeAlerts: () {},
      ),
    ));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Administration dashboard'),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Administration dashboard'));
    expect(openedTool, 'web_dashboard');

    await tester.scrollUntilVisible(
      find.text('Password recovery requests'),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Password recovery requests'));
    expect(openedTool, 'recovery');
  });
}
