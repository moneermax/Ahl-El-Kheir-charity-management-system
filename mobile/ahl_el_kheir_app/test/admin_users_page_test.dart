import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/admin_users_page.dart';

void main() {
  testWidgets('native user directory renders account data and status', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AdminUsersPage(
        english: true,
        currentUserId: 1,
        loadOptions: () async => {'roles': [], 'departments': [], 'managers': []},
        submitAction: (_) async => {'message': 'Saved'},
        loadPage: (query, page) async => {
          'items': [
            {
              'id': 8,
              'username': 'hr.user',
              'full_name': 'HR User',
              'email': 'hr@example.test',
              'phone': '555-0100',
              'is_active': true,
              'role_code': 'hr_staff',
              'role_name': 'موظف الموارد البشرية',
              'role_en': 'HR Staff',
              'dept_name': 'الموارد البشرية',
              'dept_en': 'Human Resources',
              'manager_name': 'HR Manager',
              'last_login_at': '2026-10-25 09:00:00',
            },
          ],
          'total': 1,
          'page': page,
          'pages': 1,
        },
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('User directory'), findsOneWidget);
    expect(find.text('HR User'), findsOneWidget);
    expect(find.text('@hr.user'), findsOneWidget);
    expect(find.text('HR Staff'), findsOneWidget);
    expect(find.text('Human Resources'), findsOneWidget);
    expect(find.text('Active'), findsOneWidget);
    expect(find.text('Disable'), findsOneWidget);
    expect(find.text('Reset password'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('native user directory sends submitted search and resets to first page', (tester) async {
    String? submittedQuery;
    int? submittedPage;
    await tester.pumpWidget(MaterialApp(
      home: AdminUsersPage(
        english: true,
        currentUserId: 1,
        loadOptions: () async => {'roles': [], 'departments': [], 'managers': []},
        submitAction: (_) async => {'message': 'Saved'},
        loadPage: (query, page) async {
          submittedQuery = query;
          submittedPage = page;
          return {'items': [], 'total': 0, 'page': page, 'pages': 1};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'finance');
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();

    expect(submittedQuery, 'finance');
    expect(submittedPage, 1);
    expect(tester.takeException(), isNull);
  });
  testWidgets('native user directory confirms and submits account activation toggle', (tester) async {
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(
      home: AdminUsersPage(
        english: true,
        currentUserId: 1,
        loadOptions: () async => {'roles': [], 'departments': [], 'managers': []},
        loadPage: (_, page) async => {
          'items': [
            {'id': 8, 'username': 'staff', 'full_name': 'Staff', 'is_active': true, 'role_code': 'staff', 'role_en': 'Staff', 'page': page},
          ],
          'total': 1,
          'page': page,
          'pages': 1,
        },
        submitAction: (payload) async {
          submitted = payload;
          return {'message': 'User status updated.'};
        },
      ),
    ));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Disable').first);
    await tester.pumpAndSettle();
    expect(find.text('Disable account?'), findsOneWidget);
    await tester.tap(find.text('Disable').last);
    await tester.pumpAndSettle();

    expect(submitted?['action'], 'toggle');
    expect(submitted?['user_id'], 8);
    expect(tester.takeException(), isNull);
  });

  testWidgets('native user directory hides self-service account actions', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: AdminUsersPage(
        english: true,
        currentUserId: 8,
        loadOptions: () async => {'roles': [], 'departments': [], 'managers': []},
        loadPage: (_, page) async => {
          'items': [
            {'id': 8, 'username': 'admin', 'full_name': 'Admin', 'is_active': true, 'role_code': 'admin', 'role_en': 'Administrator'},
          ],
          'total': 1,
          'page': page,
          'pages': 1,
        },
        submitAction: (_) async => {'message': 'Saved'},
      ),
    ));
    await tester.pumpAndSettle();

    expect(find.text('Disable'), findsNothing);
    expect(find.text('Reset password'), findsNothing);
    expect(tester.takeException(), isNull);
  });

}
