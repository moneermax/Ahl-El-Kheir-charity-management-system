import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  testWidgets('shows the Arabic sign-in screen by default', (tester) async {
    await tester.pumpWidget(const AhlElKheirApp());
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(find.text('تسجيل الدخول'), findsOneWidget);
    expect(find.text('اسم المستخدم'), findsOneWidget);
  });
}
