import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/employees_page.dart';

void main() {
  testWidgets('renders Arabic employee directory rows', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: EmployeesPage(
            english: false,
            openDetail: (_) {},
            loadPage: (query, page) async => {
              'items': [
                {
                  'id': 7,
                  'employee_code': 'EMP-0007',
                  'full_name': 'سارة علي',
                  'position': 'مسؤولة البرامج',
                  'department_name_ar': 'البرامج',
                  'department_name_en': 'Programs',
                  'employment_state_code': 'active',
                  'employment_state_name': 'نشط',
                  'employment_state_category': 'working',
                  'hire_date': '2025-05-01',
                },
              ],
              'total': 1,
              'page': 1,
              'pages': 1,
            },
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('الموظفون'), findsOneWidget);
    expect(find.text('سارة علي'), findsOneWidget);
    expect(find.textContaining('EMP-0007'), findsOneWidget);
    expect(find.text('على رأس العمل'), findsOneWidget);
  });
}
