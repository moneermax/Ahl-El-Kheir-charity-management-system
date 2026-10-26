import 'dart:convert';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/profile_page.dart';

void main() {
  final png = base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgAAIAAAUAAXpeqz8AAAAASUVORK5CYII=');
  Map<String, dynamic> sampleProfile({bool hasAvatar = false}) => {
    'id': 12, 'username': 'profile-test', 'full_name': 'Test User', 'email': 'test@example.test',
    'phone': '0123456789', 'address': 'Test address', 'birth_date': '1990-01-02',
    'gender': 'ذكر', 'role_code': 'admin', 'role_name': 'مدير النظام', 'has_avatar': hasAvatar,
  };

  testWidgets('loads and saves personal profile details in the native screen', (tester) async {
    var profile = sampleProfile();
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(MaterialApp(home: ProfilePage(
      english: true,
      loadProfile: () async => {'profile': Map<String, dynamic>.from(profile)},
      saveProfile: (values) async {
        submitted = Map<String, dynamic>.from(values);
        profile['full_name'] = values['full_name'];
        return {'profile': Map<String, dynamic>.from(profile), 'message': 'Profile saved.'};
      },
      pickAvatar: () async => null,
      uploadAvatar: (_) async => {},
      loadAvatar: () async => {'bytes': png, 'mime': 'image/png'},
      removeAvatar: () async => {},
    )));
    await tester.pumpAndSettle();
    expect(find.text('My profile'), findsOneWidget);
    await tester.enterText(find.byType(TextFormField).first, 'Updated Test User');
    await tester.ensureVisible(find.text('Save profile'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save profile'));
    await tester.pumpAndSettle();
    expect(submitted?['action'], 'update_profile');
    expect(submitted?['full_name'], 'Updated Test User');
    expect(find.text('Profile saved.'), findsOneWidget);
  });

  testWidgets('uploads and removes the personal photo without leaving the app', (tester) async {
    var profile = sampleProfile();
    var uploadCalled = false;
    var removeCalled = false;
    await tester.pumpWidget(MaterialApp(home: ProfilePage(
      english: true,
      loadProfile: () async => {'profile': Map<String, dynamic>.from(profile)},
      saveProfile: (_) async => {'profile': profile},
      pickAvatar: () async => PlatformFile(name: 'avatar.png', size: png.length, bytes: png),
      uploadAvatar: (_) async {
        uploadCalled = true;
        profile['has_avatar'] = true;
        return {'has_avatar': true, 'message': 'Profile photo updated.'};
      },
      loadAvatar: () async => {'bytes': png, 'mime': 'image/png'},
      removeAvatar: () async {
        removeCalled = true;
        profile['has_avatar'] = false;
        return {'has_avatar': false, 'message': 'Profile photo removed.'};
      },
    )));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Change photo'));
    await tester.pumpAndSettle();
    expect(uploadCalled, isTrue);
    expect(find.text('Remove photo'), findsOneWidget);
    await tester.tap(find.text('Remove photo'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Remove'));
    await tester.pumpAndSettle();
    expect(removeCalled, isTrue);
    expect(find.text('Remove photo'), findsNothing);
    expect(find.text('Profile photo removed.'), findsOneWidget);
  });
}
