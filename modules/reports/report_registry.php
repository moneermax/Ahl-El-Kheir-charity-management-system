<?php
// modules/reports/report_registry.php - Central report visibility/authorization registry.
// Report visibility is role-based and kept in one place so the reports dashboard
// and individual report guards use the same access matrix.

if (!function_exists('ak_report_catalog')) {
    function ak_report_catalog(): array
    {
        return [
            'overview' => [
                'label' => 'التقارير العامة',
                'description' => 'لوحة المؤشرات العامة والتقارير المتاحة لك',
                'icon' => 'fa-chart-pie',
                'url' => 'modules/reports/index.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'accountant', 'accountant_staff', 'financial_manager', 'nanny', 'hr_manager', 'hr_staff'],
            ],
            'financial' => [
                'label' => 'التقارير المالية',
                'description' => 'الإيرادات والمصروفات والتدفقات والخزينة والدفعات والإرجاعات والإبطالات',
                'icon' => 'fa-coins',
                'url' => 'modules/reports/financial.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'financial_manager'],
            ],
            'sponsorship' => [
                'label' => 'تقارير الكفالات والرعاة',
                'description' => 'تغطية الكفالات واستمرارية الرعاة وتوزيع وحالة الكفالات',
                'icon' => 'fa-hand-holding-heart',
                'url' => 'modules/reports/sponsorship.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor'],
            ],
            'operational' => [
                'label' => 'التقارير التشغيلية',
                'description' => 'تقارير المشرفين والحاضنات والتحقق والمتابعة التشغيلية',
                'icon' => 'fa-tasks',
                'url' => 'modules/reports/operational.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny', 'financial_manager'],
            ],
            'hr' => [
                'label' => 'تقارير الموارد البشرية',
                'description' => 'القوى العاملة والحضور والإجازات والرواتب',
                'icon' => 'fa-users-cog',
                'url' => 'modules/reports/hr.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'hr_manager', 'hr_staff'],
            ],
            'orphaned' => [
                'label' => 'تقرير الأسر غير المكفولة',
                'description' => 'الأسر والأطفال الذين لا توجد لهم كفالة نشطة',
                'icon' => 'fa-users-slash',
                'url' => 'modules/reports/orphaned_families.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'supervisor', 'nanny'],
            ],
            'reconciliation' => [
                'label' => 'تقرير المصالحة',
                'description' => 'رؤية إدارية لحركة الداخل والخارج والدفعات المفتوحة والإرجاعات والإبطالات',
                'icon' => 'fa-scale-balanced',
                'url' => 'modules/accounting/gm_reconciliation.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'financial_manager'],
            ],
            'sponsor_monthly' => [
                'label' => 'تقرير الكفيل الشهري',
                'description' => 'تقرير شهري عن الكفيل والكفالات والتحويلات المرتبطة بها',
                'icon' => 'fa-file-invoice-dollar',
                'url' => 'modules/transactions/sponsor-monthly-report.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'financial_manager', 'nanny'],
            ],
            'lost_contact' => [
                'label' => 'تقرير الأسر المتعذر التواصل معها',
                'description' => 'الأسر التي ما زالت دفعاتها في انتظار التأكيد بسبب تعذر التواصل',
                'icon' => 'fa-exclamation-triangle',
                'url' => 'modules/reports/lost_contact_report.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'accountant_staff', 'nanny'],
            ],
            'confirmed_disbursements' => [
                'label' => 'تقرير التحويلات المؤكدة',
                'description' => 'التحويلات التي تم تأكيد استلامها مع ملخص البنود والمبالغ',
                'icon' => 'fa-check-circle',
                'url' => 'modules/reports/confirmed_disbursements_report.php',
                'roles' => ['admin', 'general_manager', 'vice_general_manager', 'accountant', 'accountant_staff', 'nanny'],
            ],
            'my_financial' => [
                'label' => 'تقاريري المالية',
                'description' => 'المعاملات المالية التي أنشأها المستخدم نفسه',
                'icon' => 'fa-file-invoice',
                'url' => 'modules/reports/my_financial.php',
                'roles' => ['accountant_staff'],
            ],
        ];
    }
}

if (!function_exists('ak_report_can_access')) {
    function ak_report_can_access(string $reportKey, ?string $role = null): bool
    {
        $role = $role !== null ? trim($role) : (class_exists('Session') ? Session::getUserRole() : '');
        $catalog = ak_report_catalog();
        return isset($catalog[$reportKey]) && in_array($role, $catalog[$reportKey]['roles'], true);
    }
}

if (!function_exists('ak_report_require_access')) {
    function ak_report_require_access(string $reportKey, string $fallback = 'modules/reports/index.php'): void
    {
        if (!class_exists('Session') || !Session::isLoggedIn()) {
            header('Location: ' . APP_URL . 'index.php');
            exit();
        }

        if (!ak_report_can_access($reportKey, Session::getUserRole())) {
            $_SESSION['flash'][] = [
                'type' => 'error',
                'message' => t('reports.permission_denied'),
            ];
            header('Location: ' . APP_URL . $fallback);
            exit();
        }
    }
}

if (!function_exists('ak_report_allowed_catalog')) {
    function ak_report_allowed_catalog(?string $role = null): array
    {
        $role = $role !== null ? trim($role) : (class_exists('Session') ? Session::getUserRole() : '');
        $allowed = [];
        foreach (ak_report_catalog() as $key => $report) {
            if (in_array($role, $report['roles'], true)) {
                $allowed[$key] = $report;
            }
        }
        return $allowed;
    }
}
