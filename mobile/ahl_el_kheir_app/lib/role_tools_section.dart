import 'package:flutter/material.dart';

class RoleToolsSection extends StatelessWidget {
  const RoleToolsSection({
    super.key,
    required this.roleCode,
    required this.english,
    required this.onOpen,
  });

  final String roleCode;
  final bool english;
  final ValueChanged<String> onOpen;

  @override
  Widget build(BuildContext context) {
    final groups = _groups(roleCode, english);
    if (groups.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _SectionTitle(
          title: english ? 'Additional role tools' : 'أدوات إضافية حسب الدور',
          subtitle: english
              ? 'Additional modules from the existing system'
              : 'وحدات إضافية من النظام الحالي',
        ),
        const SizedBox(height: 12),
        for (final group in groups) ...[
          Padding(
            padding: const EdgeInsetsDirectional.only(start: 2, bottom: 8, top: 8),
            child: Text(
              group.title,
              style: TextStyle(color: Theme.of(context).colorScheme.primary, fontSize: 14, fontWeight: FontWeight.w800),
            ),
          ),
          for (final tool in group.tools)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Card(
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                    child: Icon(tool.icon, color: Theme.of(context).colorScheme.primary),
                  ),
                  title: Text(tool.title),
                  subtitle: Text(tool.subtitle),
                  trailing: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        tool.path == 'modules/families/suspensions.php'
                            ? Icons.phone_android_rounded
                            : Icons.phone_android_rounded,
                        size: 18,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        tool.path == 'modules/families/suspensions.php'
                            ? (english ? 'APP' : 'تطبيق')
                            : _isNativeTool(tool.path)
                                ? (english ? 'IN APP' : 'داخل التطبيق')
                                : (english ? 'NOT READY' : 'غير جاهز'),
                        style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                  onTap: () => onOpen(tool.path),
                ),
              ),
            ),
        ],
      ],
    );
  }

  bool _isNativeTool(String path) => const {
    'modules/families/suspensions.php',
    'modules/deputy_gm/groups.php',
    'modules/hr/attendance_policy.php',
    'modules/hr/payroll_integrity.php',
    'modules/hr/payroll.php',
    'modules/hr/payroll_policy.php',
    'modules/hr/incentive_verification.php',
    'modules/hr/employment_states.php',
    'modules/hr/gm_payroll_period_history.php',
    'modules/families/orphan_forms_index.php',
    'modules/sponsors/assign.php',
    'modules/accounting/fina_dashboard.php',
    'modules/transactions/fina_payment_create.php',
    'modules/accounting/fina_payment_review.php',
    'modules/accounting/fina_payment_history.php',
    'modules/accounting/my_nannies.php',
    'modules/accounting/fina_payment_report.php',
    'modules/reports/index.php',
    'modules/reports/orphaned_families.php',
    'modules/users/index.php',
    'modules/departments/index.php',
    'modules/users/profile.php',
    'modules/users/recovery.php',
    'modules/hr/employees.php',
    'modules/hr/attendance.php',
    'modules/hr/leaves.php',
    'modules/projects/index.php',
    'modules/sponsors/new_requests.php',
    'modules/sponsors/contacted_requests.php',
    'modules/accounting/disbursements.php',
    'modules/accounting/gm_reconciliation.php',
    'modules/administration/winback.php',
    'modules/hr/incentive_policy.php',
    'modules/hr/salary_advance_waiver_fm.php',
    'modules/hr/salary_advance_waiver_gm.php',
    'modules/accounting/journal.php',
    'modules/accounting/vouchers.php',
    'modules/reports/financial.php',
    'modules/reports/sponsorship.php',
    'modules/reports/operational.php',
    'modules/reports/hr.php',
  }.contains(path);

  List<_ToolGroup> _groups(String role, bool en) {
    _Tool t(String ar, String eng, String arSub, String enSub, String path, IconData icon) =>
        _Tool(en ? eng : ar, en ? enSub : arSub, path, icon);
    _ToolGroup g(String ar, String eng, List<_Tool> tools) =>
        _ToolGroup(en ? eng : ar, tools);

    switch (role) {
      case 'admin':
      case 'sudo':
      case 'super_admin':
        return [
          g('الإدارة والمتابعة', 'Administration and follow-up', [
            t('متابعات استرجاع الكفلاء', 'Sponsor win-back', 'متابعة حملات استرجاع الكفلاء', 'Review sponsor win-back campaigns', 'modules/administration/winback.php', Icons.replay_rounded),
          ]),
        ];
      case 'general_manager':
      case 'gm':
        return [
          g('التقارير التنفيذية', 'Executive reporting', [
            t('مركز التقارير', 'Report center', 'التقارير المتاحة للمدير العام', 'Reports available to the General Manager', 'modules/reports/index.php', Icons.assessment_outlined),
            t('تقرير الأسر غير المكفولة', 'Uncovered families report', 'متابعة الأسر التي تحتاج إلى كفالة', 'Review families that need sponsorship', 'modules/reports/orphaned_families.php', Icons.home_work_outlined),
            t('تقرير الكفالات', 'Sponsorship report', 'متابعة التغطية وحالة الكفالات', 'Review coverage and sponsorship status', 'modules/reports/sponsorship.php', Icons.volunteer_activism_outlined),
            t('التقرير التشغيلي', 'Operational report', 'مؤشرات العمل التشغيلي', 'Operational indicators', 'modules/reports/operational.php', Icons.query_stats_rounded),
            t('التقرير المالي', 'Financial report', 'مراجعة المؤشرات المالية التنفيذية', 'Review executive financial indicators', 'modules/reports/financial.php', Icons.account_balance_outlined),
            t('تقرير الموارد البشرية', 'HR report', 'مؤشرات وتقارير الموارد البشرية', 'Human resources indicators and reports', 'modules/reports/hr.php', Icons.badge_outlined),
            t('التحويلات الشهرية', 'Monthly disbursements', 'متابعة دفعات الكفالات الشهرية', 'Review monthly sponsorship disbursements', 'modules/accounting/disbursements.php', Icons.account_balance_wallet_outlined),
            t('مطابقة المدير العام', 'GM reconciliation', 'مراجعة المطابقة المالية المتاحة للمدير العام', 'Review reconciliation available to the General Manager', 'modules/accounting/gm_reconciliation.php', Icons.balance_outlined),
          ]),
          g('المتابعة والاعتماد', 'Follow-up and approvals', [
            t('متابعات استرجاع الكفلاء', 'Sponsor win-back', 'متابعة الحملات المفتوحة', 'Review open win-back campaigns', 'modules/administration/winback.php', Icons.replay_rounded),
            t('سياسة الحوافز', 'Incentive policy', 'مراجعة واعتماد سياسة الحوافز', 'Review and approve incentive policy', 'modules/hr/incentive_policy.php', Icons.workspace_premium_outlined),
            t('إعفاء السلف', 'Salary advance waivers', 'مراجعة قرارات إعفاء السلف واعتمادها', 'Review and approve salary-advance waiver decisions', 'modules/hr/salary_advance_waiver_gm.php', Icons.handshake_outlined),
            t('سجل فترات الرواتب', 'Payroll period history', 'سجل الصرف وكشوف الموظفين داخل التطبيق', 'Review payroll payment periods and employee reports in-app', 'modules/hr/gm_payroll_period_history.php', Icons.payments_outlined),
            t('سجل تعليقات الكفالات', 'Sponsorship suspension cases', 'عرض حالات تعليق الكفالات', 'View sponsorship suspension cases', 'modules/families/suspensions.php', Icons.pause_circle_outline_rounded),
          ]),
        ];
      case 'vice_general_manager':
      case 'vgm':
        return [
          g('الإشراف والتوزيع', 'Supervision and assignment', [
            t('إدارة مجموعات الأيتام', 'Orphan groups', 'إدارة المجموعات ودورة حياتها', 'Manage orphan groups and their lifecycle', 'modules/deputy_gm/groups.php', Icons.groups_outlined),
            t('متابعات استرجاع الكفلاء', 'Sponsor win-back', 'متابعة حملات استرجاع الكفلاء', 'Review sponsor win-back campaigns', 'modules/administration/winback.php', Icons.replay_rounded),
            t('مطابقة المدير العام', 'GM reconciliation', 'مراجعة المطابقة المالية المتاحة للإدارة', 'Review financial reconciliation available to management', 'modules/accounting/gm_reconciliation.php', Icons.balance_outlined),
            t('مركز التقارير', 'Report center', 'تقارير التشغيل المتاحة للدور', 'Operational reports available to this role', 'modules/reports/index.php', Icons.assessment_outlined),
            t('إدارة تعليق الكفالات', 'Manage sponsorship suspensions', 'تعليق أو رفع التعليق وفق الصلاحيات', 'Suspend or lift suspensions within your permissions', 'modules/families/suspensions.php', Icons.pause_circle_outline_rounded),
          ]),
        ];
      case 'financial_manager':
      case 'fm':
      case 'finance':
        return [
          g('العمليات المالية', 'Financial operations', [
            t('الرصيد الافتتاحي', 'Opening balance', 'إدارة الرصيد الافتتاحي وسياسة الرسوم الإدارية', 'Manage opening balance and administrative-fee policy', 'modules/accounting/opening_balance.php', Icons.account_balance_outlined),
            t('مطابقة المدير العام', 'GM reconciliation', 'مراجعة المطابقة المالية', 'Review financial reconciliation', 'modules/accounting/gm_reconciliation.php', Icons.balance_outlined),
            t('الحاضنات المعيّنات', 'Assigned nannies', 'متابعة الحاضنات المعيّنات والتحقق', 'Review assigned nannies and verification', 'modules/accounting/my_nannies.php', Icons.support_agent_outlined),
            if (role == 'financial_manager') t('التحويلات الشهرية', 'Monthly disbursements', 'متابعة التحويلات والصرف', 'Review monthly transfers and disbursements', 'modules/accounting/disbursements.php', Icons.account_balance_wallet_outlined),
            t('دليل الحسابات', 'Chart of accounts', 'الحسابات المحاسبية', 'Accounting accounts', 'modules/accounting/accounts.php', Icons.account_tree_outlined),
            t('دفتر اليومية', 'Journal', 'قيود اليومية المحاسبية', 'Accounting journal entries', 'modules/accounting/journal.php', Icons.menu_book_outlined),
            t('السندات', 'Vouchers', 'سندات القبض والصرف', 'Receipt and payment vouchers', 'modules/accounting/vouchers.php', Icons.receipt_long_outlined),
            t('مراجعة المعاملات المالية', 'Financial transaction review', 'مراجعة المعاملات الواردة', 'Review incoming financial transactions', 'modules/accounting/fm_transaction_review.php', Icons.fact_check_outlined),
            t('تقارير مالية', 'Financial reports', 'التقارير المالية', 'Financial reporting', 'modules/reports/financial.php', Icons.bar_chart_rounded),
            t('بوابة الرواتب والحوافز', 'Payroll and incentives portal', 'الرواتب والحوافز والعمليات المرتبطة', 'Payroll, incentives and related operations', 'modules/hr/salary_portal.php', Icons.badge_outlined),
            t('سياسة الحوافز', 'Incentive policy', 'تفعيل سياسة الحوافز المعتمدة', 'Activate approved incentive policies', 'modules/hr/incentive_policy.php', Icons.workspace_premium_outlined),
            t('إعفاء السلف', 'Salary advance waivers', 'تجهيز قرارات الإعفاء وتنفيذ القرارات المعتمدة', 'Prepare waiver decisions and execute GM-approved decisions', 'modules/hr/salary_advance_waiver_fm.php', Icons.handshake_outlined),
            if (role == 'financial_manager') t('إدارة الرواتب', 'Payroll management', 'إدارة فترات الرواتب وإثباتات التحويل', 'Manage payroll periods and transfer proofs', 'modules/hr/payroll.php', Icons.payments_outlined),
            if (role != 'finance') t('لوحة فينا الخير', 'Fina Al-Khair dashboard', 'مؤشرات التحصيلات المستقلة', 'Standalone collection indicators', 'modules/accounting/fina_dashboard.php', Icons.dashboard_rounded),
            if (role != 'finance') t('تسجيل تحصيل فينا', 'Record Fina collection', 'تسجيل تحصيل جديد وإرساله للمراجعة', 'Register a collection and submit it for review', 'modules/transactions/fina_payment_create.php', Icons.add_card_rounded),
            if (role != 'finance') t('مراجعة تحصيلات فينا', 'Review Fina collections', 'مراجعة التحصيلات المعلقة وسجل القرارات', 'Review pending collections and decision history', 'modules/accounting/fina_payment_review.php', Icons.fact_check_outlined),
          ]),
        ];
      case 'accountant':
      case 'accountant_staff':
        return [
          g('المحاسبة والتوزيعات', 'Accounting and disbursements', [
            if (role == 'accountant_staff') t('صرف مجموعات الكفالات', 'Group disbursements', 'إدارة عمليات الصرف ضمن الصلاحيات الحالية', 'Manage disbursements within existing permissions', 'modules/accounting/group_disbursements.php', Icons.groups_outlined),
            if (role == 'accountant_staff') t('التحويلات الشهرية', 'Monthly disbursements', 'متابعة التحويلات المسموح بها للدور', 'Review disbursements available to this role', 'modules/accounting/disbursements.php', Icons.account_balance_wallet_outlined),
            if (role == 'accountant_staff') t('السندات', 'Vouchers', 'عرض سندات القبض والصرف', 'View receipt and payment vouchers', 'modules/accounting/vouchers.php', Icons.receipt_long_outlined),
            t('الحاضنات المعيّنات', 'Assigned nannies', 'متابعة الحاضنات المعيّنات والتحقق', 'Review assigned nannies and verification', 'modules/accounting/my_nannies.php', Icons.support_agent_outlined),
            if (role == 'accountant_staff') t('استمارات الأيتام', 'Orphan forms', 'عرض استمارات الأيتام وفق الصلاحيات', 'View orphan forms within your permissions', 'modules/families/orphan_forms_index.php', Icons.assignment_outlined),

            t('مركز التقارير', 'Report center', 'التقارير المتاحة للمحاسبة', 'Reports available to accounting staff', 'modules/reports/index.php', Icons.assessment_outlined),
          ]),
        ];
      case 'supervisor':
        return [
          g('متابعة تحصيلات فينا الخير', 'Fina Al-Khair collections', [
            t('استمارات الأيتام', 'Orphan forms', 'عرض استمارات الأيتام ضمن نطاق الصلاحيات', 'View orphan forms within your role scope', 'modules/families/orphan_forms_index.php', Icons.assignment_outlined),
            t('مراجعة تحصيلات فينا', 'Review Fina collections', 'فتح صفحة مراجعة التحصيلات', 'Open the collection review page', 'modules/accounting/fina_payment_review.php', Icons.fact_check_outlined),
            t('سجل تحصيلات فينا', 'Fina collection history', 'متابعة سجل التحصيلات', 'Review collection history', 'modules/accounting/fina_payment_history.php', Icons.history_rounded),
            t('تقرير تحصيلات فينا', 'Fina collection report', 'تقارير التحصيلات الخاصة بالدور', 'Collection reports for this role', 'modules/accounting/fina_payment_report.php', Icons.summarize_outlined),
          ]),
        ];
      case 'nanny':
        return [
          g('الصرف والمتابعة', 'Disbursement follow-up', [
            t('التحويلات الشهرية', 'Monthly disbursements', 'متابعة دفعات المجموعات', 'Review group payments', 'modules/accounting/disbursements.php', Icons.account_balance_wallet_outlined),
            t('إعادة صرف المبالغ المرتجعة', 'Reissue returned funds', 'معالجة المبالغ المرتجعة وفق صلاحيات الحاضنة', 'Handle returned funds within nanny permissions', 'modules/accounting/returned_funds_nanny.php', Icons.currency_exchange_rounded),
            t('مركز التقارير', 'Report center', 'تقارير المتابعة', 'Follow-up reports', 'modules/reports/index.php', Icons.assessment_outlined),
            t('تعليق الكفالات', 'Sponsorship suspensions', 'إدارة التعليقات ضمن الأسر المسندة إليك', 'Manage suspensions within your assigned families', 'modules/families/suspensions.php', Icons.pause_circle_outline_rounded),
            t('استمارات الأيتام', 'Orphan forms', 'عرض استمارات الأيتام ضمن نطاق الصلاحيات', 'View orphan forms within your role scope', 'modules/families/orphan_forms_index.php', Icons.assignment_outlined),
          ]),
        ];
      case 'administration':
      case 'staff':
      case 'social_media':
        return [
          g('خدمة المستفيدين والمتابعة', 'Sponsor service and follow-up', [
            t('الطلبات الجديدة', 'New sponsor requests', 'مراجعة الطلبات الجديدة الواردة', 'Review newly received sponsor requests', 'modules/sponsors/new_requests.php', Icons.mark_email_unread_outlined),
            t('الطلبات التي تمت متابعتها', 'Contacted sponsor requests', 'متابعة الطلبات التي تم التواصل بشأنها', 'Review sponsor requests already contacted', 'modules/sponsors/contacted_requests.php', Icons.mark_email_read_outlined),
            if (role == 'administration') t('الأسر المتاحة للكفالة', 'Families available for sponsorship', 'متابعة الأسر غير المغطاة', 'Review families without active sponsorship', 'modules/administration/available_families.php', Icons.home_work_outlined),
            if (role == 'administration') t('متابعات استرجاع الكفلاء', 'Sponsor win-back', 'متابعة حملات استرجاع الكفلاء', 'Review sponsor win-back campaigns', 'modules/administration/winback.php', Icons.replay_rounded),
            t('استمارات الأيتام', 'Orphan forms', 'بحث وفتح الاستمارات ضمن نطاق الصلاحيات', 'Search and open forms within role and family scope', 'modules/families/orphan_forms_index.php', Icons.assignment_outlined),
            t('ملفي الشخصي', 'My profile', 'عرض وتحديث بيانات الحساب', 'View and update your account profile', 'modules/users/profile.php', Icons.account_circle_outlined),
          ]),
        ];
      case 'hr_manager':
      case 'hr_staff':
        return [
          g('إدارة الموارد البشرية', 'Human resources operations', [
            t('مركز التقارير', 'Report center', 'التقارير المتاحة للموارد البشرية', 'Reports available to Human Resources', 'modules/reports/index.php', Icons.assessment_outlined),
            t('إدارة الموظفين', 'Employee management', 'سجل الموظفين وإجراءات الموارد البشرية', 'Employee records and HR actions', 'modules/hr/employees.php', Icons.badge_outlined),
            t('الحضور والانصراف', 'Attendance management', 'إدارة سجلات الحضور', 'Manage attendance records', 'modules/hr/attendance.php', Icons.fact_check_outlined),
            t('إدارة الإجازات', 'Leave management', 'مراجعة طلبات الإجازة', 'Review leave requests', 'modules/hr/leaves.php', Icons.event_available_outlined),
            t('الرواتب', 'Payroll', 'إدارة فترات الرواتب', 'Manage payroll periods', 'modules/hr/payroll.php', Icons.payments_outlined),
            t('بناء العقود', 'Contract builder', 'إنشاء ومتابعة عقود الموظفين', 'Create and manage employee contracts', 'modules/hr/contract_builder.php', Icons.description_outlined),
            t('سياسة الرواتب', 'Payroll policy', 'إعدادات وسياسات الرواتب', 'Payroll settings and policies', 'modules/hr/payroll_policy.php', Icons.policy_outlined),
            if (role == 'hr_manager') ...[
              t('حالات التوظيف', 'Employment states', 'عرض الحالات الحالية وسجلها دون تعديل البيانات', 'Review current employment states and history (read-only)', 'modules/hr/employment_states.php', Icons.work_history_outlined),
              t('سياسة الحوافز', 'Incentive policy', 'إدارة سياسة الحوافز', 'Manage incentive policy', 'modules/hr/incentive_policy.php', Icons.workspace_premium_outlined),
              t('عكس الرواتب', 'Payroll reversal', 'إجراءات عكس الرواتب المصرح بها', 'Authorized payroll reversal operations', 'modules/hr/payroll_reversal.php', Icons.undo_rounded),
              t('التحقق من الحوافز', 'Incentive verification', 'مراجعة الحوافز', 'Review incentives', 'modules/hr/incentive_verification.php', Icons.verified_outlined),
              t('سياسة الحضور', 'Attendance policy', 'قواعد الحضور الفعالة', 'Effective attendance rules', 'modules/hr/attendance_policy.php', Icons.schedule_outlined),
              t('سلامة الرواتب', 'Payroll integrity', 'فحص تشخيصي للرواتب والقيود دون تعديل البيانات', 'Read-only checks for payroll and accounting consistency', 'modules/hr/payroll_integrity.php', Icons.shield_outlined),
              t('طلبات استعادة كلمة المرور', 'Password recovery requests', 'مراجعة طلبات استعادة الحسابات المعلقة', 'Review pending account recovery requests', 'modules/users/recovery.php', Icons.password_rounded),
            ],
          ]),
        ];
      case 'projects_manager':
      case 'project_supervisor':
        return [
          g('إدارة المشروعات', 'Project management', [
            if (role == 'projects_manager') t('إنشاء مشروع', 'Create project', 'فتح نموذج إنشاء مشروع جديد', 'Open the existing new-project form', 'modules/projects/form.php', Icons.create_new_folder_outlined),
            t('سجل المشروعات', 'Project register', 'عرض المشروعات والتقدم والمعالم داخل التطبيق', 'Review projects, progress and milestones inside the app', 'modules/projects/index.php', Icons.folder_open_outlined),
          ]),
        ];
      default:
        return [];
    }
  }
}

class _ToolGroup {
  const _ToolGroup(this.title, this.tools);
  final String title;
  final List<_Tool> tools;
}

class _Tool {
  const _Tool(this.title, this.subtitle, this.path, this.icon);
  final String title;
  final String subtitle;
  final String path;
  final IconData icon;
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.only(start: 2, end: 2, bottom: 2),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(
              color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w800,
            )),
            const SizedBox(height: 3),
            Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant, height: 1.45,
            )),
          ],
        ),
      );
}
