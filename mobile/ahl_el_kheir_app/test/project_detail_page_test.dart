import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/project_detail_page.dart';
void main() {
 testWidgets('renders Arabic project detail sections', (tester) async {
  await tester.pumpWidget(MaterialApp(home: Directionality(textDirection: TextDirection.rtl, child: ProjectDetailPage(
   projectId: 24, english: false, loadDetail: (_) async => {
    'project': {'id':24,'project_code':'PRJ-0024','name':'الإيواء والتأهيل السكني','description':'مشروع اختبار','current_status':'closed','approval_status':'approved','currency_code':'SDG','budget':10000.0,'funded':10000.0,'expenses':10000.0,'supervisor_name':'مشرف المشروع','closed_at':'2026-10-18 09:47:23','closure_summary':'اكتمل المشروع'},
    'team': [{'full_name':'أحمد حسين','section_code':'general','is_lead':true}],
    'milestones': [{'title':'التنفيذ','description':'مرحلة التنفيذ','planned_date':'2026-10-01','completion_percent':100.0}],
    'history': [{'old_status':'under_review','new_status':'closed','reason':'اكتمل المشروع','created_at':'2026-10-18','actor_name':'مدير المشاريع'}],
   },
  ))));
  await tester.pumpAndSettle();
  expect(find.text('تفاصيل المشروع'), findsOneWidget);
  expect(find.text('الإيواء والتأهيل السكني'), findsOneWidget);
  expect(find.text('فريق المشروع'), findsOneWidget);
  expect(find.text('مراحل المشروع'), findsOneWidget);
  await tester.scrollUntilVisible(find.text('آخر التغييرات'), 400, scrollable: find.byType(Scrollable));
  await tester.pumpAndSettle();
  expect(find.text('آخر التغييرات'), findsOneWidget);
  expect(find.text('أحمد حسين'), findsOneWidget);
 });
}
