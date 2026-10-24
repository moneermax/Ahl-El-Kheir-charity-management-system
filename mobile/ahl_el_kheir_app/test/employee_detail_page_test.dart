import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/employee_detail_page.dart';

void main() {
  testWidgets('renders employee details in Arabic', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: EmployeeDetailPage(
          employeeId: 7,
          english: false,
          loadDetail: (id) async => {
            'employee': {
              'id': id,
              'employee_code': 'EMP-0007',
              'full_name': 'سارة علي',
              'position': 'مسؤولة البرامج',
              'department_name_ar': 'البرامج',
              'hire_date': '2025-05-01',
              'employment_state_category': 'working',
              'employment_state_name': 'على رأس العمل',
              'employment_state_name_en': 'Active',
              'phone': '01000000000',
            },
          },
        ),
      ),
    ));
    await tester.pumpAndSettle();
    expect(find.text('بيانات الموظف'), findsOneWidget);
    expect(find.text('سارة علي'), findsOneWidget);
    expect(find.text('EMP-0007'), findsOneWidget);
    expect(find.text('على رأس العمل'), findsOneWidget);
    expect(find.text('مسؤولة البرامج'), findsOneWidget);
    expect(find.text('01000000000'), findsOneWidget);
  });
}
