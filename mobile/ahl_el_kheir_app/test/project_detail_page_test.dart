import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/project_detail_page.dart';
void main() {
 testWidgets('renders Arabic project detail sections', (tester) async {
  await tester.pumpWidget(MaterialApp(home: Directionality(textDirection: TextDirection.rtl, child: ProjectDetailPage(
   projectId: 24, english: false, submitProgress: (_) async => {'ok': true}, loadDetail: (_) async => {
    'project': {'id':24,'project_code':'PRJ-0024','name':'الإيواء والتأهيل السكني','description':'مشروع اختبار','current_status':'closed','approval_status':'approved','currency_code':'SDG','budget':10000.0,'funded':10000.0,'expenses':10000.0,'supervisor_name':'مشرف المشروع','closed_at':'2026-10-18 09:47:23','closure_summary':'اكتمل المشروع'},
    'team': [{'full_name':'أحمد حسين','section_code':'general','is_lead':true}],
    'milestones': [{'title':'التنفيذ','description':'مرحلة التنفيذ','planned_date':'2026-10-01','completion_percent':100.0}],
    'progress_updates': [{'update_date':'2026-10-17','completion_percent':75.0,'summary':'تقدم التنفيذ','achievements':'اكتملت المرحلة الأولى','issues':'تأخر التوريد','next_steps':'استكمال الأعمال','submitter_name':'مشرف المشروع'}],
    'history': [{'old_status':'under_review','new_status':'closed','reason':'اكتمل المشروع','created_at':'2026-10-18','actor_name':'مدير المشاريع'}],
   },
  ))));
  await tester.pumpAndSettle();
  expect(find.text('تفاصيل المشروع'), findsOneWidget);
  expect(find.text('الإيواء والتأهيل السكني'), findsOneWidget);
  expect(find.text('فريق المشروع'), findsOneWidget);
  expect(find.text('مراحل المشروع'), findsOneWidget);
  await tester.scrollUntilVisible(find.text('أحمد حسين'), 400, scrollable: find.byType(Scrollable));
  await tester.pumpAndSettle();
  expect(find.text('أحمد حسين'), findsOneWidget);
  await tester.scrollUntilVisible(find.text('تحديثات التقدم'), 400, scrollable: find.byType(Scrollable));
  await tester.pumpAndSettle();
  expect(find.text('تحديثات التقدم'), findsOneWidget);
  expect(find.text('تقدم التنفيذ'), findsOneWidget);
  expect(find.textContaining('تأخر التوريد'), findsOneWidget);
  await tester.scrollUntilVisible(find.text('آخر التغييرات'), 400, scrollable: find.byType(Scrollable));
  await tester.pumpAndSettle();
  expect(find.text('آخر التغييرات'), findsOneWidget);
 });
 testWidgets('submits a project progress update and refreshes the detail', (tester) async {
  var detailLoads = 0;
  Map<String, dynamic>? submitted;
  await tester.pumpWidget(MaterialApp(home: ProjectDetailPage(
   projectId: 31,
   english: false,
   submitProgress: (body) async { submitted = body; return {'ok': true}; },
   loadDetail: (_) async {
    detailLoads++;
    return {
     'project': {'id':31,'project_code':'PRJ-0031','name':'مشروع نشط','current_status':'active','approval_status':'approved','currency_code':'SDG','budget':1000,'funded':100,'expenses':0,'supervisor_name':'مشرف','can_add_progress':true},
     'team': [], 'milestones': [], 'progress_updates': [], 'history': [],
    };
   },
  )));
  await tester.pumpAndSettle();
  expect(find.text('إضافة تحديث تقدم'), findsOneWidget);
  await tester.tap(find.text('إضافة تحديث تقدم'));
  await tester.pumpAndSettle();
  expect(find.text('تحديث تقدم جديد'), findsOneWidget);
  await tester.enterText(find.byType(TextField).at(0), 'تقدم الأعمال');
  await tester.enterText(find.byType(TextField).at(2), '45');
  await tester.tap(find.text('حفظ'));
  await tester.pumpAndSettle();
  expect(submitted?['project_id'], 31);
  expect(submitted?['summary'], 'تقدم الأعمال');
  expect(submitted?['completion_percent'], 45.0);
  expect(detailLoads, 2);
 });

}
