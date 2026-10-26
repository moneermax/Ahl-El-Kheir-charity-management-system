import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_dashboard_page.dart';

void main() {
  testWidgets('admin dashboard fits a narrow phone viewport and exposes native recovery route', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    String? openedTool;

    await tester.pumpWidget(MaterialApp(
      home: AdminDashboardPage(
        english: true,
        fullName: 'System Administrator',
        roleCode: 'admin',
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

    final layoutException = tester.takeException();
    if (layoutException != null) debugDumpRenderTree();
    expect(layoutException, isNull, reason: 'Dashboard must not overflow on a narrow phone.');
    expect(find.text('Administration dashboard'), findsNothing);

    await tester.scrollUntilVisible(
      find.text('Password recovery requests'),
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Password recovery requests'));
    expect(openedTool, 'recovery');
    expect(tester.takeException(), isNull);
  });

  testWidgets('Sudo sees native user administration but not tools whose server guards reject Sudo', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    String? openedTool;

    await tester.pumpWidget(MaterialApp(
      home: AdminDashboardPage(
        english: true,
        fullName: 'System Operator',
        roleCode: 'sudo',
        loadOverview: () async => {
          'stats': <String, dynamic>{},
          'recent_audit': <Map<String, dynamic>>[],
          'server': {'database_connected': true, 'php_version': '8.2'},
        },
        onOpenTool: (tool) => openedTool = tool,
        onOpenAgeAlerts: () {},
      ),
    ));
    await tester.pumpAndSettle();

    final scrollable = find.descendant(
      of: find.byKey(const Key('admin-dashboard-list')),
      matching: find.byType(Scrollable),
    ).first;
    Future<void> scrollTo(String label) async {
      await tester.scrollUntilVisible(
        find.text(label),
        180,
        scrollable: scrollable,
      );
    }

    void expectUnavailableTool(String label) {
      expect(find.text(label), findsNothing,
          reason: '$label must not be advertised to Sudo.');
    }

    await scrollTo('User accounts');
    expect(find.text('User accounts'), findsOneWidget);

    // Check each role-specific group only after its section is built in the
    // lazy list; trying to scroll to a deliberately absent tile throws.
    await scrollTo('SYSTEM ADMINISTRATION');
    expect(find.byKey(const Key('admin-tool-departments')), findsNothing,
        reason: 'Departments must not be advertised to Sudo.');
    expect(find.byKey(const Key('admin-tool-settings')), findsNothing,
        reason: 'System settings tile must not be advertised to Sudo.');
    expect(find.byKey(const Key('admin-tool-recovery')), findsNothing,
        reason: 'Password recovery must not be advertised to Sudo.');

    await scrollTo('CORE RECORDS');
    expect(find.byKey(const Key('admin-tool-reports')), findsNothing,
        reason: 'Reports tile must not be advertised to Sudo.');

    await scrollTo('PROTECTION & MAINTENANCE');
    expect(find.byKey(const Key('admin-tool-audit')), findsNothing,
        reason: 'Audit tile must not be advertised to Sudo.');

    await scrollTo('User accounts');
    await tester.tap(find.text('User accounts'));
    expect(openedTool, 'users');
    expect(tester.takeException(), isNull);
  });

}
