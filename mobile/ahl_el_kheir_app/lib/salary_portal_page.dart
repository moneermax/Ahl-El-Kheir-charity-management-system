import 'package:flutter/material.dart';

class SalaryPortalPage extends StatelessWidget {
  const SalaryPortalPage({
    super.key,
    required this.english,
    required this.roleCode,
    required this.onOpen,
  });

  final bool english;
  final String roleCode;
  final ValueChanged<String> onOpen;

  String t(String ar, String en) => english ? en : ar;

  @override
  Widget build(BuildContext context) {
    final links = <_SalaryPortalLink>[];
    if (['financial_manager', 'admin', 'fm'].contains(roleCode)) {
      links.add(_SalaryPortalLink(
        title: t('إدارة سلف الرواتب', 'Salary advances'),
        description: t(
          'عرض مؤشرات السلف وسجل الطلبات المعالجة داخل التطبيق.',
          'View salary-advance status counts and processed-request history in the app.',
        ),
        path: 'modules/hr/salary_advance_dashboard.php',
        ready: true,
        icon: Icons.handshake_outlined,
      ));
      links.add(_SalaryPortalLink(
        title: t('إعفاء سلف الرواتب', 'Salary advance waivers'),
        description: t(
          'إعداد ومراجعة قرارات إعفاء السلف ضمن الصلاحيات المعتمدة.',
          'Prepare and review salary-advance waiver decisions within the existing permissions.',
        ),
        path: 'modules/hr/salary_advance_waiver_fm.php',
        ready: true,
        icon: Icons.volunteer_activism_outlined,
      ));
    }
    if (['hr_manager', 'hr_staff', 'financial_manager', 'admin'].contains(roleCode)) {
      links.add(_SalaryPortalLink(
        title: t('إدارة الرواتب', 'Payroll management'),
        description: t(
          'إنشاء ومراجعة مسيرات الرواتب ومتابعة مكونات الراتب وخصم السلف.',
          'Create and review payroll periods, salary components and advance deductions.',
        ),
        path: 'modules/hr/payroll.php',
        ready: true,
        icon: Icons.payments_outlined,
      ));
    }
    if (['financial_manager', 'admin'].contains(roleCode)) {
      links.add(_SalaryPortalLink(
        title: t('سياسة حوافز الموظفين', 'Employee incentive policy'),
        description: roleCode == 'admin'
            ? t(
                'الصفحة الحالية لا تسمح بدور المسؤول؛ لم يتم فتحها حفاظاً على صلاحيات النظام.',
                'The current policy page denies the administrator role, so this action is disabled to preserve its access guard.',
              )
            : t(
                'تفعيل سياسة الحوافز المعتمدة ومتابعة إصداراتها.',
                'Activate approved incentive policies and review their versions.',
              ),
        path: 'modules/hr/incentive_policy.php',
        ready: roleCode == 'financial_manager',
        icon: Icons.workspace_premium_outlined,
      ));
    }

    return Scaffold(
      appBar: AppBar(title: Text(t('بوابة الرواتب والحوافز', 'Payroll and incentives portal'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Text(t(
                'بوابة موحدة لخدمات الرواتب والسلف والحوافز المتاحة لدورك. جميع الصفحات المدعومة تفتح داخل التطبيق.',
                'A single hub for payroll, advances and incentive tools available to your role. Supported pages stay inside the app.',
              )),
            ),
          ),
          const SizedBox(height: 12),
          for (final link in links)
            Card(
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                leading: CircleAvatar(child: Icon(link.icon)),
                title: Text(link.title),
                subtitle: Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(link.description),
                      const SizedBox(height: 6),
                      Text(
                        link.ready
                            ? t('داخل التطبيق', 'IN APP')
                            : t('غير متاح داخل التطبيق بعد', 'NOT READY IN APP'),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: link.ready ? Theme.of(context).colorScheme.primary : Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ],
                  ),
                ),
                trailing: Icon(link.ready ? Icons.arrow_forward_ios_rounded : Icons.lock_outline_rounded, size: 18),
                onTap: link.ready ? () => onOpen(link.path) : null,
              ),
            ),
        ],
      ),
    );
  }
}

class _SalaryPortalLink {
  const _SalaryPortalLink({
    required this.title,
    required this.description,
    required this.path,
    required this.ready,
    required this.icon,
  });

  final String title;
  final String description;
  final String path;
  final bool ready;
  final IconData icon;
}
