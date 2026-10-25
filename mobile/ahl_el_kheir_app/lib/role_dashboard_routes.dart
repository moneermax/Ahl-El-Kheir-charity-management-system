/// Existing website dashboard routes copied from the role-aware sidebar.
/// This provides a bridge to the full established dashboard while the mobile
/// workspace continues to grow; authorization remains enforced by each page.
String? roleDashboardPath(String roleCode) => const <String, String>{
      'admin': 'dashboard/admin_dashboard.php',
      'sudo': 'dashboard/admin_dashboard.php',
      'super_admin': 'dashboard/admin_dashboard.php',
      'general_manager': 'dashboard/gm_dashboard.php',
      'gm': 'dashboard/gm_dashboard.php',
      'vice_general_manager': 'dashboard/vgm_dashboard.php',
      'vgm': 'dashboard/vgm_dashboard.php',
      'financial_manager': 'modules/accounting/fm_dashboard.php',
      'fm': 'modules/accounting/fm_dashboard.php',
      'finance': 'modules/accounting/fm_dashboard.php',
      'accountant': 'dashboard/accountant_staff_dashboard.php',
      'accountant_staff': 'dashboard/accountant_staff_dashboard.php',
      'supervisor': 'dashboard/supervisor_dashboard.php',
      'nanny': 'dashboard/nanny_dashboard.php',
      'administration': 'dashboard/staff_dashboard.php',
      'staff': 'dashboard/staff_dashboard.php',
      'social_media': 'dashboard/staff_dashboard.php',
      'hr_manager': 'dashboard/hr_dashboard.php',
      'hr_staff': 'dashboard/hr_dashboard.php',
      'projects_manager': 'dashboard/projects_dashboard.php',
      'project_supervisor': 'dashboard/projects_dashboard.php',
    }[roleCode];
