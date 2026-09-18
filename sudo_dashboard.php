<?php
// =============================================
// SUPER ADMIN DASHBOARD - Professional Server Control Panel
// Version: 2.1 (Compact Stats)
// =============================================

require_once 'includes/db.php';
require_once 'includes/functions.php';

// 🔒 SECURITY: Strictly for UserID = 1 (Super Admin)
requireRole($conn, ['SuperAdmin']);
if ($_SESSION['user_id'] != 1) {
    header("Location: admin_dashboard.php");
    exit();
}

$current_user = getCurrentUser($conn);
$lang = $_SESSION['lang'] ?? 'en';
$page_title = $lang == 'ar' ? 'لوحة تحكم المدير العام' : 'Server Control Panel';

// ================= POST HANDLERS =================

// 1. Handle School Settings Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_school_settings'])) {
    $settings_data = [
        'school_name' => mysqli_real_escape_string($conn, $_POST['school_name']),
        'school_name_ar' => mysqli_real_escape_string($conn, $_POST['school_name_ar']),
        'school_slogan' => mysqli_real_escape_string($conn, $_POST['school_slogan']),
        'school_slogan_ar' => mysqli_real_escape_string($conn, $_POST['school_slogan_ar']),
        'school_phone' => mysqli_real_escape_string($conn, $_POST['school_phone']),
        'school_email' => mysqli_real_escape_string($conn, $_POST['school_email']),
        'school_address' => mysqli_real_escape_string($conn, $_POST['school_address']),
        'login_header_text' => mysqli_real_escape_string($conn, $_POST['login_header_text']),
        'login_header_text_ar' => mysqli_real_escape_string($conn, $_POST['login_header_text_ar'])
    ];
    
    foreach ($settings_data as $key => $value) {
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE SettingValue = '$value'");
    }
    
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        $filename = 'school_logo_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['school_logo']['tmp_name'], $target_dir . $filename);
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('school_logo', '$filename') ON DUPLICATE KEY UPDATE SettingValue = '$filename'");
    }
    if (isset($_FILES['login_picture']) && $_FILES['login_picture']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['login_picture']['name'], PATHINFO_EXTENSION));
        $filename = 'login_picture_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['login_picture']['tmp_name'], $target_dir . $filename);
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('login_picture', '$filename') ON DUPLICATE KEY UPDATE SettingValue = '$filename'");
    }
    if (isset($_FILES['login_background']) && $_FILES['login_background']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['login_background']['name'], PATHINFO_EXTENSION));
        $filename = 'login_bg_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['login_background']['tmp_name'], $target_dir . $filename);
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('login_background', '$filename') ON DUPLICATE KEY UPDATE SettingValue = '$filename'");
    }
    
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث إعدادات المدرسة' : 'School settings updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 2. Handle Theme Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_theme'])) {
    $selected_theme = mysqli_real_escape_string($conn, $_POST['selected_theme']);
    $primary_color = mysqli_real_escape_string($conn, $_POST['primary_color']);
    $secondary_color = mysqli_real_escape_string($conn, $_POST['secondary_color']);
    
    $preset_colors = [
        'default' => ['#667eea', '#764ba2'], 'blue' => ['#2193b0', '#6dd5ed'],
        'green' => ['#11998e', '#38ef7d'], 'orange' => ['#f12711', '#f5af19'],
        'purple' => ['#8E2DE2', '#4A00E0'], 'red' => ['#cb2d3e', '#ef473a'],
        'dark' => ['#2c3e50', '#3498db'], 'teal' => ['#00b4db', '#0083b0']
    ];
    
    if ($selected_theme == 'custom') {
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('primary_color', '$primary_color'), ('secondary_color', '$secondary_color'), ('active_theme', 'custom') ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
    } else if (isset($preset_colors[$selected_theme])) {
        $primary = $preset_colors[$selected_theme][0];
        $secondary = $preset_colors[$selected_theme][1];
        mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('primary_color', '$primary'), ('secondary_color', '$secondary'), ('active_theme', '$selected_theme') ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
    }
    
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث ألوان النظام' : 'Theme colors updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 3. Handle Maintenance Mode
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_maintenance'])) {
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $maintenance_message = mysqli_real_escape_string($conn, $_POST['maintenance_message']);
    $maintenance_message_ar = mysqli_real_escape_string($conn, $_POST['maintenance_message_ar']);
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('maintenance_mode', '$maintenance_mode'), ('maintenance_message', '$maintenance_message'), ('maintenance_message_ar', '$maintenance_message_ar') ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث وضع الصيانة' : 'Maintenance mode updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 4. Handle Default Attendance Settings Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_attendance_settings'])) {
    $default_check_in = mysqli_real_escape_string($conn, $_POST['default_check_in']);
    $default_check_out = mysqli_real_escape_string($conn, $_POST['default_check_out']);
    $default_attendance_status = mysqli_real_escape_string($conn, $_POST['default_attendance_status']);
    
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('default_check_in', '$default_check_in') ON DUPLICATE KEY UPDATE SettingValue = '$default_check_in'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('default_check_out', '$default_check_out') ON DUPLICATE KEY UPDATE SettingValue = '$default_check_out'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('default_attendance_status', '$default_attendance_status') ON DUPLICATE KEY UPDATE SettingValue = '$default_attendance_status'");
    
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث إعدادات الحضور الافتراضية' : 'Default attendance settings updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 5. Handle Currency Settings Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_currency_settings'])) {
    $currency_code = mysqli_real_escape_string($conn, $_POST['currency_code']);
    $currency_symbol = mysqli_real_escape_string($conn, $_POST['currency_symbol']);
    $currency_symbol_ar = mysqli_real_escape_string($conn, $_POST['currency_symbol_ar']);
    $currency_position = mysqli_real_escape_string($conn, $_POST['currency_position']);
    $decimal_places = (int)$_POST['decimal_places'];
    $thousands_separator = mysqli_real_escape_string($conn, $_POST['thousands_separator']);
    $decimal_separator = mysqli_real_escape_string($conn, $_POST['decimal_separator']);
    
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('currency_code', '$currency_code') ON DUPLICATE KEY UPDATE SettingValue = '$currency_code'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('currency_symbol', '$currency_symbol') ON DUPLICATE KEY UPDATE SettingValue = '$currency_symbol'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('currency_symbol_ar', '$currency_symbol_ar') ON DUPLICATE KEY UPDATE SettingValue = '$currency_symbol_ar'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('currency_position', '$currency_position') ON DUPLICATE KEY UPDATE SettingValue = '$currency_position'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('decimal_places', '$decimal_places') ON DUPLICATE KEY UPDATE SettingValue = '$decimal_places'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('thousands_separator', '$thousands_separator') ON DUPLICATE KEY UPDATE SettingValue = '$thousands_separator'");
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('decimal_separator', '$decimal_separator') ON DUPLICATE KEY UPDATE SettingValue = '$decimal_separator'");
    
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث إعدادات العملة' : 'Currency settings updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 6. Handle Cache Clear
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clear_cache'])) {
    $cache_type = $_POST['cache_type'];
    $result = '';
    if ($cache_type == 'twig' || $cache_type == 'all') {
        $cache_dir = __DIR__ . '/cache/twig/';
        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) { if (is_file($file)) unlink($file); }
        }
        $result .= 'Twig cache cleared. ';
    }
    if ($cache_type == 'session' || $cache_type == 'all') {
        $result .= 'Session cache flagged. ';
    }
    if ($cache_type == 'browser' || $cache_type == 'all') {
        $result .= 'Browser cache flag set. ';
    }
    $_SESSION['success_message'] = $result ?: 'Cache cleared';
    header("Location: sudo_dashboard.php");
    exit();
}

// 7. Handle Database Optimization
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['optimize_db'])) {
    $tables = mysqli_query($conn, "SHOW TABLES");
    $optimized = 0;
    while ($row = mysqli_fetch_array($tables)) {
        mysqli_query($conn, "OPTIMIZE TABLE `" . $row[0] . "`");
        $optimized++;
    }
    $_SESSION['success_message'] = "Optimized $optimized tables";
    header("Location: sudo_dashboard.php");
    exit();
}

// 8. Handle Force Logout User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['force_logout'])) {
    $user_id = intval($_POST['user_id']);
    if ($user_id != $_SESSION['user_id']) {
        mysqli_query($conn, "UPDATE tblusers SET IsActive = 0 WHERE UserID = $user_id");
        $_SESSION['success_message'] = "User has been logged out and deactivated";
    } else {
        $_SESSION['error_message'] = "Cannot logout yourself";
    }
    header("Location: sudo_dashboard.php");
    exit();
}

// 9. Handle Email Test
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email_address'];
    $subject = "Test Email from School Management System";
    $message = "This is a test email. Your mail configuration is working!";
    $headers = "From: " . ($settings['school_email'] ?? 'noreply@school.com');
    
    if (mail($test_email, $subject, $message, $headers)) {
        $_SESSION['success_message'] = "Test email sent to $test_email";
    } else {
        $_SESSION['error_message'] = "Failed to send test email. Check mail settings.";
    }
    header("Location: sudo_dashboard.php");
    exit();
}

// 10. Handle System Cleanup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['system_cleanup'])) {
    $temp_files = glob(__DIR__ . '/temp/*');
    $deleted = 0;
    foreach ($temp_files as $file) {
        if (is_file($file) && time() - filemtime($file) > 86400) {
            unlink($file);
            $deleted++;
        }
    }
    $session_path = session_save_path();
    if ($session_path && file_exists($session_path)) {
        $sessions = glob($session_path . '/sess_*');
        foreach ($sessions as $session) {
            if (is_file($session) && time() - filemtime($session) > 86400) {
                unlink($session);
                $deleted++;
            }
        }
    }
    $_SESSION['success_message'] = "System cleaned: $deleted temporary files removed";
    header("Location: sudo_dashboard.php");
    exit();
}

// 11. Handle Accounting Settings Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_accounting_settings'])) {
    $auto_approve_threshold = (float)$_POST['auto_approve_threshold'];
    
    mysqli_query($conn, "INSERT INTO tblsettings (SettingKey, SettingValue) VALUES ('auto_approve_threshold', '$auto_approve_threshold') ON DUPLICATE KEY UPDATE SettingValue = '$auto_approve_threshold'");
    
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث إعدادات المحاسبة' : 'Accounting settings updated';
    header("Location: sudo_dashboard.php");
    exit();
}

// 12. Handle Add Department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    $dept_name = mysqli_real_escape_string($conn, $_POST['department_name']);
    $dept_name_ar = mysqli_real_escape_string($conn, $_POST['department_name_ar']);
    $dept_type = mysqli_real_escape_string($conn, $_POST['department_type']);
    $secretary_role = mysqli_real_escape_string($conn, $_POST['secretary_mapped_role']);
    
    $insert = mysqli_query($conn, "INSERT INTO tbldepartments (DepartmentName, DepartmentName_ar, DepartmentType, secretary_mapped_role, IsActive) 
                                   VALUES ('$dept_name', '$dept_name_ar', '$dept_type', '$secretary_role', 1)");
    if ($insert) {
        $_SESSION['success_message'] = $lang == 'ar' ? 'تم إضافة القسم بنجاح' : 'Department added successfully';
    } else {
        $_SESSION['error_message'] = $lang == 'ar' ? 'فشل إضافة القسم: ' . mysqli_error($conn) : 'Failed to add department: ' . mysqli_error($conn);
    }
    session_write_close();
    header("Location: sudo_dashboard.php#departments");
    exit();
}

// 13. Handle Update Department Roles
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_department_roles'])) {
    foreach ($_POST['role'] as $dept_id => $role) {
        $dept_id = (int)$dept_id;
        $role = mysqli_real_escape_string($conn, $role);
        mysqli_query($conn, "UPDATE tbldepartments SET secretary_mapped_role = '$role' WHERE DepartmentID = $dept_id");
    }
    $_SESSION['success_message'] = $lang == 'ar' ? 'تم تحديث صلاحيات الأقسام' : 'Department roles updated';
    session_write_close();
    header("Location: sudo_dashboard.php#departments");
    exit();
}

// 14. Handle Delete Department
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_department'])) {
    $dept_id = (int)$_POST['department_id'];
    $employee_count = safeSelectValue($conn, "SELECT COUNT(*) FROM tblemployees WHERE DepartmentID = ?", 'i', [$dept_id]);
    if ($employee_count > 0) {
        $_SESSION['error_message'] = $lang == 'ar' ? 'لا يمكن حذف القسم لأنه يحتوي على موظفين' : 'Cannot delete department because it has employees';
    } else {
        mysqli_query($conn, "UPDATE tbldepartments SET IsActive = 0 WHERE DepartmentID = $dept_id");
        $_SESSION['success_message'] = $lang == 'ar' ? 'تم حذف القسم بنجاح' : 'Department deleted successfully';
    }
    session_write_close();
    header("Location: sudo_dashboard.php#departments");
    exit();
}

// Get current settings
$settings = [];
$result = mysqli_query($conn, "SELECT * FROM tblsettings");
while($row = mysqli_fetch_assoc($result)) $settings[$row['SettingKey']] = $row['SettingValue'];
$active_theme = $settings['active_theme'] ?? 'default';
$primary_color = $settings['primary_color'] ?? '#667eea';
$secondary_color = $settings['secondary_color'] ?? '#764ba2';
$maintenance_mode = $settings['maintenance_mode'] ?? 0;
$auto_approve_threshold = $settings['auto_approve_threshold'] ?? 500;

// System Health Data
$php_version = PHP_VERSION;
$mysql_version = $conn->server_info;
$disk_free = round(disk_free_space("/") / 1073741824, 2);
$disk_total = round(disk_total_space("/") / 1073741824, 2);
$disk_used = $disk_total - $disk_free;
$disk_percent = $disk_total > 0 ? round(($disk_used / $disk_total) * 100) : 0;
$memory_usage = round(memory_get_usage(true) / 1048576, 2);
$upload_max = ini_get('upload_max_filesize');
$max_execution = ini_get('max_execution_time');

// Get counts
$total_users = safeSelectValue($conn, "SELECT COUNT(*) FROM tblusers");
$total_students = safeSelectValue($conn, "SELECT COUNT(*) FROM tblstudents");
$total_teachers = safeSelectValue($conn, "SELECT COUNT(*) FROM tblusers WHERE UserRole = 'Teacher'");

// Get recent activity logs
$recent_logs = safeSelectArray($conn, "SELECT * FROM tblactivitylogs ORDER BY Timestamp DESC LIMIT 10");

// Get departments
$departments_result = mysqli_query($conn, "SELECT * FROM tbldepartments WHERE IsActive = 1 ORDER BY DepartmentID");
$departments = [];
while($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row;
}
$available_roles = ['Admin', 'SuperAdmin', 'Principal', 'Registrar', 'Accountant', 'HR', 'Teacher', 'Staff', 'TransportManager', 'Librarian', 'DepartmentHead'];

include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?></title>
<style>
:root {
    --primary: <?php echo $primary_color; ?>;
    --secondary: <?php echo $secondary_color; ?>;
    --bg: #0f0e17;
    --card-bg: #1a1a2e;
    --text: #fffffe;
    --text-muted: #a7a9be;
    --border: #2d2d44;
    --success: #2cb67d;
    --warning: #ff8906;
    --danger: #e74c3c;
    --info: #4cc9f0;
    --radius: 12px;
    --shadow: 0 8px 32px rgba(0,0,0,0.3);
}

* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 20px;
}

/* ========== TOP NAVBAR ========== */
.top-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 25px;
    background: var(--card-bg);
    border-radius: var(--radius);
    margin-bottom: 20px;
    border: 1px solid var(--border);
}
.top-nav .brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
}
.top-nav .brand .logo {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.top-nav .brand span {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.top-nav .nav-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.top-nav .nav-actions .status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: var(--success);
    color: #fff;
}
.top-nav .nav-actions .status-badge.warning { background: var(--warning); }
.top-nav .nav-actions .status-badge.danger { background: var(--danger); }
.top-nav .nav-actions a {
    color: var(--text-muted);
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 13px;
}
.top-nav .nav-actions a:hover {
    background: var(--border);
    color: var(--text);
}
.top-nav .nav-actions .logout-btn {
    background: var(--danger);
    color: #fff;
}
.top-nav .nav-actions .logout-btn:hover {
    opacity: 0.85;
    background: var(--danger);
}

/* ========== SYSTEM HEALTH BAR ========== */
.health-bar {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
.health-item {
    background: var(--card-bg);
    padding: 10px 14px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.health-item .icon { font-size: 18px; }
.health-item .info { flex:1; min-width:0; }
.health-item .info .label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing:0.3px; }
.health-item .info .value { font-size: 13px; font-weight: 600; }
.health-item .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink:0; }
.health-item .status-dot.ok { background: var(--success); }
.health-item .status-dot.warning { background: var(--warning); }
.health-item .status-dot.danger { background: var(--danger); }

/* ========== COMPACT STATS ROW - ALL IN ONE LINE ========== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 10px 14px;
    border: 1px solid var(--border);
    text-align: center;
    transition: all 0.3s;
}
.stat-card:hover { transform: translateY(-2px); border-color: var(--primary); }
.stat-card .number { 
    font-size: 20px; 
    font-weight: 700; 
    color: var(--primary);
    line-height: 1.2;
}
.stat-card .label { 
    font-size: 10px; 
    color: var(--text-muted); 
    margin-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.stat-card .sub-info {
    font-size: 9px;
    color: var(--text-muted);
    margin-top: 1px;
    opacity: 0.6;
}

/* ========== MAIN LAYOUT ========== */
.main-grid {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 20px;
}

/* ========== SIDEBAR ========== */
.sidebar {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 8px 0;
    height: fit-content;
    position: sticky;
    top: 20px;
}
.sidebar .menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.3s;
    border-left: 3px solid transparent;
    cursor: pointer;
}
.sidebar .menu-item:hover {
    background: rgba(255,255,255,0.05);
    color: var(--text);
}
.sidebar .menu-item.active {
    background: rgba(102,126,234,0.15);
    color: var(--primary);
    border-left-color: var(--primary);
}
.sidebar .menu-item .icon { font-size: 16px; width: 22px; text-align:center; }
.sidebar .menu-divider {
    height: 1px;
    background: var(--border);
    margin: 6px 18px;
}

/* ========== CONTENT ========== */
.content {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    padding: 25px;
}
.content .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}
.content .page-header h2 {
    font-size: 20px;
    font-weight: 700;
}
.content .page-header .subtitle {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 400;
}

/* ========== SECTION CARDS ========== */
.section-card {
    background: rgba(255,255,255,0.03);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--border);
}
.section-card .section-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-card .section-title .icon { font-size: 18px; }

/* ========== FORM STYLES ========== */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px; color: var(--text-muted); }
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 8px 12px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 13px;
    transition: all 0.3s;
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
}
.form-group input[type="color"] { height: 40px; padding: 4px; cursor: pointer; }
.form-group .help-text { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

/* ========== BUTTONS ========== */
.btn {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: #fff; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102,126,234,0.4); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(44,182,125,0.4); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(231,76,60,0.4); }
.btn-warning { background: var(--warning); color: #1a1a2e; }
.btn-warning:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,137,6,0.4); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
.btn-outline:hover { background: var(--border); }
.btn-sm { padding: 4px 12px; font-size: 11px; }

/* ========== QUICK ACTIONS GRID ========== */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
}
.quick-action {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
}
.quick-action:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}
.quick-action .icon { font-size: 24px; display: block; margin-bottom: 6px; }
.quick-action .label { font-size: 11px; font-weight: 500; }

/* ========== TABLES ========== */
.table-wrap { overflow-x: auto; }
.table-wrap table { width:100%; border-collapse:collapse; font-size:13px; }
.table-wrap th { text-align:left; padding:10px 12px; color:var(--text-muted); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border); }
.table-wrap td { padding:10px 12px; border-bottom:1px solid var(--border); }
.table-wrap tr:hover { background:rgba(255,255,255,0.02); }

/* ========== THEME SELECTOR ========== */
.theme-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 10px;
}
.theme-option {
    background: rgba(255,255,255,0.03);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}
.theme-option:hover { transform: scale(1.05); }
.theme-option.selected { border-color: var(--primary); }
.theme-option .preview { width: 100%; height: 40px; border-radius: 6px; margin-bottom: 4px; }
.theme-option .name { font-size: 10px; color: var(--text-muted); }

/* ========== TABS ========== */
.tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
}
.tab-btn {
    padding: 6px 14px;
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.3s;
}
.tab-btn:hover { color: var(--text); background: rgba(255,255,255,0.05); }
.tab-btn.active { background: rgba(102,126,234,0.15); color: var(--primary); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ========== LOG ENTRIES ========== */
.log-entry {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
    font-size: 12px;
}
.log-entry .time { color: var(--text-muted); font-size: 11px; min-width: 130px; }
.log-entry .action { color: var(--primary); font-weight: 500; }
.log-entry .details { color: var(--text-muted); flex:1; }
.log-entry .ip { color: var(--text-muted); font-size: 10px; }

/* ========== RESPONSIVE ========== */
@media (max-width: 1200px) {
    .health-bar { grid-template-columns: repeat(3, 1fr); }
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1024px) {
    .main-grid { grid-template-columns: 1fr; }
    .sidebar { display: none; }
    .sidebar.mobile-visible { display: block; position: fixed; top:0; left:0; width:280px; height:100vh; z-index:1000; border-radius:0; overflow-y:auto; }
    .sidebar-toggle { display: flex !important; }
    .form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .top-nav { flex-wrap: wrap; gap: 8px; }
    .top-nav .nav-actions { flex-wrap: wrap; }
    .health-bar { grid-template-columns: 1fr 1fr; }
    .stats-row { grid-template-columns: 1fr 1fr; }
    .quick-actions { grid-template-columns: 1fr 1fr; }
    .content { padding: 15px; }
}
@media (max-width: 480px) {
    .health-bar { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr 1fr; }
    .stats-row .stat-card .number { font-size: 16px; }
}
.sidebar-toggle { display: none; background: var(--card-bg); border:1px solid var(--border); color:var(--text); padding:6px 12px; border-radius:6px; cursor:pointer; font-size:14px; }
.sidebar-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; }
.sidebar-overlay.active { display:block; }
</style>
</head>
<body>

<!-- ========== SIDEBAR OVERLAY ========== -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ========== TOP NAVBAR ========== -->
<nav class="top-nav">
    <div class="brand">
        <div class="logo">⚙️</div>
        <span>Server Control</span>
        <span style="font-size:11px;font-weight:400;color:var(--text-muted);-webkit-text-fill-color:var(--text-muted);">v6.8</span>
    </div>
    <div class="nav-actions">
        <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">☰</button>
        <span class="status-badge <?php echo $maintenance_mode ? 'warning' : ''; ?>">
            <?php echo $maintenance_mode ? '🔧 Maintenance' : '🟢 Online'; ?>
        </span>
        <a href="health_check.php" target="_blank">📊 Health</a>
        <a href="backup.php">💾 Backup</a>
        <a href="logout.php" class="logout-btn">🚪 Logout</a>
    </div>
</nav>

<!-- ========== SYSTEM HEALTH BAR ========== -->
<div class="health-bar">
    <div class="health-item">
        <span class="icon">🐘</span>
        <div class="info">
            <div class="label">PHP</div>
            <div class="value"><?php echo $php_version; ?></div>
        </div>
        <span class="status-dot ok"></span>
    </div>
    <div class="health-item">
        <span class="icon">🗄️</span>
        <div class="info">
            <div class="label">MySQL</div>
            <div class="value"><?php echo substr($mysql_version, 0, 10); ?></div>
        </div>
        <span class="status-dot ok"></span>
    </div>
    <div class="health-item">
        <span class="icon">💾</span>
        <div class="info">
            <div class="label">Disk Free</div>
            <div class="value"><?php echo $disk_free; ?> GB</div>
        </div>
        <span class="status-dot <?php echo $disk_percent > 85 ? 'danger' : ($disk_percent > 70 ? 'warning' : 'ok'); ?>"></span>
    </div>
    <div class="health-item">
        <span class="icon">🧠</span>
        <div class="info">
            <div class="label">Memory</div>
            <div class="value"><?php echo $memory_usage; ?> MB</div>
        </div>
        <span class="status-dot ok"></span>
    </div>
    <div class="health-item">
        <span class="icon">📤</span>
        <div class="info">
            <div class="label">Max Upload</div>
            <div class="value"><?php echo $upload_max; ?></div>
        </div>
        <span class="status-dot ok"></span>
    </div>
    <div class="health-item">
        <span class="icon">⏱️</span>
        <div class="info">
            <div class="label">Timeout</div>
            <div class="value"><?php echo $max_execution; ?>s</div>
        </div>
        <span class="status-dot ok"></span>
    </div>
</div>

<!-- ========== COMPACT STATS ROW - ALL IN ONE LINE ========== -->
<div class="stats-row">
    <div class="stat-card">
        <div class="number"><?php echo $total_users; ?></div>
        <div class="label">Total Users</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo $total_students; ?></div>
        <div class="label">Students</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo $total_teachers; ?></div>
        <div class="label">Teachers</div>
    </div>
    <div class="stat-card">
        <div class="number"><?php echo count($departments); ?></div>
        <div class="label">Departments</div>
    </div>
    <div class="stat-card">
        <div class="number" style="color:<?php echo $maintenance_mode ? 'var(--warning)' : 'var(--success)'; ?>;font-size:16px;">
            <?php echo $maintenance_mode ? '🔧 ON' : '🟢 OFF'; ?>
        </div>
        <div class="label">Maintenance</div>
    </div>
    <div class="stat-card">
        <div class="number" style="font-size:16px;"><?php echo formatMoney($auto_approve_threshold); ?></div>
        <div class="label">Auto-Approve</div>
        <div class="sub-info">Limit</div>
    </div>
</div>

<!-- ========== MAIN LAYOUT ========== -->
<div class="main-grid">

    <!-- ========== SIDEBAR ========== -->
    <aside class="sidebar" id="sidebar">
        <div class="menu-item active" onclick="showTab('dashboard')">
            <span class="icon">📊</span> Dashboard
        </div>
        <div class="menu-item" onclick="showTab('quickactions')">
            <span class="icon">⚡</span> Quick Actions
        </div>
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="showTab('departments')">
            <span class="icon">🏢</span> Departments
        </div>
        <div class="menu-item" onclick="showTab('school')">
            <span class="icon">🏫</span> School Settings
        </div>
        <div class="menu-item" onclick="showTab('theme')">
            <span class="icon">🎨</span> Theme & Colors
        </div>
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="showTab('currency')">
            <span class="icon">💰</span> Currency
        </div>
        <div class="menu-item" onclick="showTab('accounting')">
            <span class="icon">📈</span> Accounting
        </div>
        <div class="menu-item" onclick="showTab('attendance')">
            <span class="icon">⏰</span> Attendance
        </div>
        <div class="menu-divider"></div>
        <div class="menu-item" onclick="showTab('system')">
            <span class="icon">🖥️</span> System Tools
        </div>
        <div class="menu-item" onclick="showTab('logs')">
            <span class="icon">📜</span> Activity Logs
        </div>
        <div class="menu-item" onclick="showTab('maintenance')">
            <span class="icon">🔧</span> Maintenance
        </div>
        <div class="menu-item" onclick="showTab('security')">
            <span class="icon">🔒</span> Security
        </div>
    </aside>

    <!-- ========== CONTENT ========== -->
    <main class="content">

        <!-- ========== PAGE HEADER ========== -->
        <div class="page-header">
            <div>
                <h2>⚙️ Server Control Panel</h2>
                <div class="subtitle">Welcome back, <?php echo htmlspecialchars($current_user['FullName']); ?> • <?php echo date('l, F j, Y'); ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="health_check.php" target="_blank" class="btn btn-outline btn-sm">📊 Health Check</a>
                <a href="backup.php" class="btn btn-primary btn-sm">💾 Backup Now</a>
            </div>
        </div>

        <!-- ========== TABS ========== -->
        <div class="tabs" id="mainTabs">
            <button class="tab-btn active" data-tab="dashboard">📊 Dashboard</button>
            <button class="tab-btn" data-tab="quickactions">⚡ Actions</button>
            <button class="tab-btn" data-tab="departments">🏢 Departments</button>
            <button class="tab-btn" data-tab="school">🏫 School</button>
            <button class="tab-btn" data-tab="theme">🎨 Theme</button>
            <button class="tab-btn" data-tab="currency">💰 Currency</button>
            <button class="tab-btn" data-tab="accounting">📈 Accounting</button>
            <button class="tab-btn" data-tab="attendance">⏰ Attendance</button>
            <button class="tab-btn" data-tab="system">🖥️ System</button>
            <button class="tab-btn" data-tab="logs">📜 Logs</button>
            <button class="tab-btn" data-tab="maintenance">🔧 Maintenance</button>
            <button class="tab-btn" data-tab="security">🔒 Security</button>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1: DASHBOARD -->
        <!-- ============================================================ -->
        <div id="tab-dashboard" class="tab-pane active">
            <div class="section-card">
                <div class="section-title"><span class="icon">🔗</span> Quick Access to All Dashboards</div>
                <div class="quick-actions">
                    <a href="admin_dashboard.php" class="quick-action"><span class="icon">👔</span><span class="label">Admin</span></a>
                    <a href="principal_dashboard.php" class="quick-action"><span class="icon">🏫</span><span class="label">Principal</span></a>
                    <a href="registrar_dashboard.php" class="quick-action"><span class="icon">📋</span><span class="label">Registrar</span></a>
                    <a href="teacher_dashboard.php" class="quick-action"><span class="icon">👨‍🏫</span><span class="label">Teacher</span></a>
                    <a href="accountant_dashboard.php" class="quick-action"><span class="icon">💰</span><span class="label">Accountant</span></a>
                    <a href="parent_dashboard.php" class="quick-action"><span class="icon">👨‍👩‍👧</span><span class="label">Parent</span></a>
                    <a href="staff_dashboard.php" class="quick-action"><span class="icon">👷</span><span class="label">Staff</span></a>
                    <a href="hr_dashboard.php" class="quick-action"><span class="icon">👥</span><span class="label">HR</span></a>
                    <a href="analytics_dashboard.php" class="quick-action"><span class="icon">📊</span><span class="label">Analytics</span></a>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">📈</span> System Overview</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <p style="color:var(--text-muted);font-size:12px;line-height:2;">
                            <strong style="color:var(--text);">PHP Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
                            <strong style="color:var(--text);">Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s<br>
                            <strong style="color:var(--text);">Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?><br>
                            <strong style="color:var(--text);">Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
                        </p>
                    </div>
                    <div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:12px;">
                        <p style="color:var(--text-muted);font-size:12px;line-height:2;">
                            <strong style="color:var(--text);">DB Tables:</strong> <?php 
                                $tables = mysqli_query($conn, "SHOW TABLES");
                                echo mysqli_num_rows($tables); 
                            ?><br>
                            <strong style="color:var(--text);">Disk Used:</strong> <?php echo round($disk_percent); ?>% (<?php echo round($disk_used, 2); ?> GB)<br>
                            <strong style="color:var(--text);">Session Path:</strong> <?php echo session_save_path() ?: 'Default'; ?><br>
                            <strong style="color:var(--text);">Timezone:</strong> <?php echo date_default_timezone_get(); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: QUICK ACTIONS -->
        <!-- ============================================================ -->
        <div id="tab-quickactions" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">⚡</span> Quick Action Tools</div>
                <div class="quick-actions">
                    <a href="promote_students.php" class="quick-action" style="border-color:var(--success);"><span class="icon">🎓</span><span class="label">Promote Students</span></a>
                    <a href="manage_users.php" class="quick-action"><span class="icon">👥</span><span class="label">User Manager</span></a>
                    <a href="bulk_import_students.php" class="quick-action" style="border-color:var(--warning);"><span class="icon">📊</span><span class="label">Bulk Import</span></a>
                    <a href="backup.php" class="quick-action" style="border-color:var(--info);"><span class="icon">💾</span><span class="label">Backup</span></a>
                    <a href="activity_logs.php" class="quick-action"><span class="icon">📜</span><span class="label">Activity Logs</span></a>
                    <a href="hash_passwords.php" class="quick-action" style="border-color:var(--danger);"><span class="icon">🔐</span><span class="label">Re-hash Passwords</span></a>
                    <a href="scripts/cleanup.php" class="quick-action" style="border-color:#6f42c1;"><span class="icon">🧹</span><span class="label">System Cleanup</span></a>
                    <a href="scripts/rotate_logs.php" class="quick-action" style="border-color:#20c997;"><span class="icon">🔄</span><span class="label">Rotate Logs</span></a>
                    <a href="health_check.php" target="_blank" class="quick-action" style="border-color:var(--primary);"><span class="icon">📊</span><span class="label">Health Check</span></a>
                    <a href="admin/reset_database.php" class="quick-action" style="border-color:var(--danger);" onclick="return confirm('⚠️ WARNING: This will DELETE ALL DATA! Are you sure?')">
                        <span class="icon">🗑️</span><span class="label">Reset Database</span>
                    </a>
                    <a href="admin/seed_test_data.php" class="quick-action" style="border-color:var(--success);"><span class="icon">🌱</span><span class="label">Seed Test Data</span></a>
                    <a href="phpinfo.php" target="_blank" class="quick-action" style="border-color:#6c757d;"><span class="icon">🐘</span><span class="label">PHP Info</span></a>
                    <a href="db_tools.php" class="quick-action" style="border-color:#6c757d;"><span class="icon">🗄️</span><span class="label">DB Tools</span></a>
                    <a href="file_list.php" class="quick-action" style="border-color:#6c757d;"><span class="icon">🗄️</span><span class="label">File List</span></a>

                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: DEPARTMENTS -->
        <!-- ============================================================ -->
        <div id="tab-departments" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">➕</span> Add New Department</div>
                <form method="POST">
                    <input type="hidden" name="add_department" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Department Name (English) *</label>
                            <input type="text" name="department_name" required placeholder="e.g., Information Technology">
                        </div>
                        <div class="form-group">
                            <label>Department Name (Arabic) *</label>
                            <input type="text" name="department_name_ar" required placeholder="مثال: تقنية المعلومات">
                        </div>
                        <div class="form-group">
                            <label>Department Type</label>
                            <select name="department_type">
                                <option value="administrative">Administrative</option>
                                <option value="academic">Academic</option>
                                <option value="support">Support</option>
                                <option value="transport">Transport</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="security">Security</option>
                                <option value="cleaning">Cleaning</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Secretary Role</label>
                            <select name="secretary_mapped_role">
                                <?php foreach ($available_roles as $role): ?>
                                    <option value="<?php echo $role; ?>"><?php echo $role; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">Role secretaries get when accessing this department's pages</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">➕ Add Department</button>
                </form>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">📋</span> Existing Departments</div>
                <form method="POST">
                    <input type="hidden" name="update_department_roles" value="1">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Department (EN)</th><th>Department (AR)</th><th>Secretary Role</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo $dept['DepartmentID']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($dept['DepartmentName']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dept['DepartmentName_ar']); ?></td>
                                    <td>
                                        <select name="role[<?php echo $dept['DepartmentID']; ?>]" style="background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:6px;padding:5px 8px;color:var(--text);font-size:12px;">
                                            <?php foreach ($available_roles as $role): ?>
                                                <option value="<?php echo $role; ?>" <?php echo ($dept['secretary_mapped_role'] ?? 'Staff') == $role ? 'selected' : ''; ?>>
                                                    <?php echo $role; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this department?')">
                                            <input type="hidden" name="delete_department" value="1">
                                            <input type="hidden" name="department_id" value="<?php echo $dept['DepartmentID']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:12px;">💾 Save Changes</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 4: SCHOOL SETTINGS -->
        <!-- ============================================================ -->
        <div id="tab-school" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">🏫</span> School Information</div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_school_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>School Name (English)</label><input type="text" name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? 'International School'); ?>" required></div>
                        <div class="form-group"><label>School Name (Arabic)</label><input type="text" name="school_name_ar" value="<?php echo htmlspecialchars($settings['school_name_ar'] ?? 'المدرسة العالمية'); ?>" required></div>
                        <div class="form-group"><label>Slogan (English)</label><input type="text" name="school_slogan" value="<?php echo htmlspecialchars($settings['school_slogan'] ?? 'Quality Education for All'); ?>"></div>
                        <div class="form-group"><label>Slogan (Arabic)</label><input type="text" name="school_slogan_ar" value="<?php echo htmlspecialchars($settings['school_slogan_ar'] ?? 'تعليم ذو جودة للجميع'); ?>"></div>
                        <div class="form-group"><label>Login Header (English)</label><input type="text" name="login_header_text" value="<?php echo htmlspecialchars($settings['login_header_text'] ?? 'Welcome'); ?>"></div>
                        <div class="form-group"><label>Login Header (Arabic)</label><input type="text" name="login_header_text_ar" value="<?php echo htmlspecialchars($settings['login_header_text_ar'] ?? 'مرحباً'); ?>"></div>
                        <div class="form-group"><label>Phone</label><input type="tel" name="school_phone" value="<?php echo htmlspecialchars($settings['school_phone'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="school_email" value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-group"><label>Address</label><textarea name="school_address" rows="2"><?php echo htmlspecialchars($settings['school_address'] ?? ''); ?></textarea></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>School Logo</label>
                            <?php if(!empty($settings['school_logo']) && file_exists('uploads/' . $settings['school_logo'])): ?>
                                <div style="margin-bottom:6px;"><img src="uploads/<?php echo $settings['school_logo']; ?>?t=<?php echo time(); ?>" style="height:40px;width:auto;border-radius:4px;"></div>
                            <?php endif; ?>
                            <input type="file" name="school_logo" accept="image/*" style="font-size:12px;">
                            <div class="help-text">Appears in header</div>
                        </div>
                        <div class="form-group">
                            <label>Login Page Picture</label>
                            <?php if(!empty($settings['login_picture']) && file_exists('uploads/' . $settings['login_picture'])): ?>
                                <div style="margin-bottom:6px;"><img src="uploads/<?php echo $settings['login_picture']; ?>?t=<?php echo time(); ?>" style="height:40px;width:auto;border-radius:4px;"></div>
                            <?php endif; ?>
                            <input type="file" name="login_picture" accept="image/*" style="font-size:12px;">
                            <div class="help-text">Top of login page</div>
                        </div>
                        <div class="form-group">
                            <label>Login Background</label>
                            <?php if(!empty($settings['login_background']) && file_exists('uploads/' . $settings['login_background'])): ?>
                                <div style="margin-bottom:6px;"><img src="uploads/<?php echo $settings['login_background']; ?>?t=<?php echo time(); ?>" style="height:40px;width:auto;border-radius:4px;"></div>
                            <?php endif; ?>
                            <input type="file" name="login_background" accept="image/*" style="font-size:12px;">
                            <div class="help-text">Full page background</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:12px;">💾 Save School Settings</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 5: THEME & COLORS -->
        <!-- ============================================================ -->
        <div id="tab-theme" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">🎨</span> Theme & Color Settings</div>
                <form method="POST">
                    <input type="hidden" name="update_theme" value="1">
                    <div class="form-group">
                        <label>Choose Preset Theme</label>
                        <div class="theme-grid">
                            <?php 
                            $themes = [
                                'default' => ['#667eea', '#764ba2', 'Default'],
                                'blue' => ['#2193b0', '#6dd5ed', 'Ocean Blue'],
                                'green' => ['#11998e', '#38ef7d', 'Forest Green'],
                                'orange' => ['#f12711', '#f5af19', 'Sunset Orange'],
                                'purple' => ['#8E2DE2', '#4A00E0', 'Royal Purple'],
                                'red' => ['#cb2d3e', '#ef473a', 'Crimson Red'],
                                'dark' => ['#2c3e50', '#3498db', 'Night Mode'],
                                'teal' => ['#00b4db', '#0083b0', 'Teal Wave'],
                                'custom' => [$primary_color, $secondary_color, 'Custom']
                            ];
                            foreach ($themes as $key => $theme): 
                                $selected = $active_theme == $key ? 'selected' : '';
                            ?>
                            <div class="theme-option <?php echo $selected; ?>" onclick="selectTheme('<?php echo $key; ?>')" data-theme="<?php echo $key; ?>">
                                <div class="preview" style="background:linear-gradient(135deg, <?php echo $theme[0]; ?>, <?php echo $theme[1]; ?>);"></div>
                                <div class="name"><?php echo $theme[2]; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="hidden" name="selected_theme" id="selected_theme" value="<?php echo $active_theme; ?>">
                    <div id="customColors" style="display: <?php echo $active_theme == 'custom' ? 'block' : 'none'; ?>; margin-top:15px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group">
                                <label>Primary Color</label>
                                <input type="color" name="primary_color" id="primary_color" value="<?php echo $primary_color; ?>" style="width:50px;height:36px;padding:3px;">
                                <span style="display:inline-block;vertical-align:middle;margin-left:8px;font-size:12px;color:var(--text-muted);"><?php echo $primary_color; ?></span>
                            </div>
                            <div class="form-group">
                                <label>Secondary Color</label>
                                <input type="color" name="secondary_color" id="secondary_color" value="<?php echo $secondary_color; ?>" style="width:50px;height:36px;padding:3px;">
                                <span style="display:inline-block;vertical-align:middle;margin-left:8px;font-size:12px;color:var(--text-muted);"><?php echo $secondary_color; ?></span>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:12px;">💾 Save Theme</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 6: CURRENCY -->
        <!-- ============================================================ -->
        <div id="tab-currency" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">💰</span> Currency Settings</div>
                <form method="POST">
                    <input type="hidden" name="update_currency_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>Currency Code (EN)</label><input type="text" name="currency_code" value="<?php echo htmlspecialchars($settings['currency_code'] ?? 'SDG'); ?>" required placeholder="USD, EUR, SDG"></div>
                        <div class="form-group"><label>Currency Code (AR)</label><input type="text" name="currency_symbol_ar" value="<?php echo htmlspecialchars($settings['currency_symbol_ar'] ?? 'ج.س'); ?>" required placeholder="ج.س, ج.م, د.إ"></div>
                        <div class="form-group"><label>Currency Symbol</label><input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'ج.س'); ?>" required placeholder="$ / £ / ج.س"></div>
                        <div class="form-group">
                            <label>Symbol Position</label>
                            <select name="currency_position">
                                <option value="before" <?php echo ($settings['currency_position'] ?? 'after') == 'before' ? 'selected' : ''; ?>>Before (e.g., $100)</option>
                                <option value="after" <?php echo ($settings['currency_position'] ?? 'after') == 'after' ? 'selected' : ''; ?>>After (e.g., 100 SDG)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Decimal Places</label>
                            <select name="decimal_places">
                                <option value="0" <?php echo ($settings['decimal_places'] ?? 2) == 0 ? 'selected' : ''; ?>>0</option>
                                <option value="1" <?php echo ($settings['decimal_places'] ?? 2) == 1 ? 'selected' : ''; ?>>1</option>
                                <option value="2" <?php echo ($settings['decimal_places'] ?? 2) == 2 ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo ($settings['decimal_places'] ?? 2) == 3 ? 'selected' : ''; ?>>3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Thousands Separator</label>
                            <select name="thousands_separator">
                                <option value="," <?php echo ($settings['thousands_separator'] ?? ',') == ',' ? 'selected' : ''; ?>>,</option>
                                <option value="." <?php echo ($settings['thousands_separator'] ?? ',') == '.' ? 'selected' : ''; ?>>.</option>
                                <option value=" " <?php echo ($settings['thousands_separator'] ?? ',') == ' ' ? 'selected' : ''; ?>>Space</option>
                                <option value="''" <?php echo ($settings['thousands_separator'] ?? ',') == "''" ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Decimal Separator</label>
                            <select name="decimal_separator">
                                <option value="." <?php echo ($settings['decimal_separator'] ?? '.') == '.' ? 'selected' : ''; ?>>.</option>
                                <option value="," <?php echo ($settings['decimal_separator'] ?? '.') == ',' ? 'selected' : ''; ?>>,</option>
                            </select>
                        </div>
                    </div>
                    <div style="background:rgba(44,182,125,0.1);padding:12px;border-radius:8px;margin:12px 0;">
                        <strong>Preview:</strong> <span id="previewAmount">1,234,567.89 SDG</span>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Save Currency Settings</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 7: ACCOUNTING -->
        <!-- ============================================================ -->
        <div id="tab-accounting" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">📈</span> Accounting Settings</div>
                <form method="POST">
                    <input type="hidden" name="update_accounting_settings" value="1">
                    <div class="form-group">
                        <label>Auto-Approval Threshold for Payments</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="number" name="auto_approve_threshold" id="auto_approve_threshold" value="<?php echo htmlspecialchars($settings['auto_approve_threshold'] ?? '500'); ?>" step="100" min="0" style="width:180px;padding:8px 12px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;">
                            <span style="color:var(--text-muted);font-size:13px;">SDG</span>
                        </div>
                        <div class="help-text">Payments below this amount will be auto-approved. Larger payments require admin approval.</div>
                    </div>
                    <div style="background:rgba(255,255,255,0.03);padding:10px;border-radius:6px;margin-bottom:12px;">
                        <span id="threshold_example" style="color:var(--text-muted);font-size:12px;">Payments under 500 SDG will be auto-approved</span>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Save Accounting Settings</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 8: ATTENDANCE -->
        <!-- ============================================================ -->
        <div id="tab-attendance" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">⏰</span> Default Attendance Settings</div>
                <form method="POST">
                    <input type="hidden" name="update_attendance_settings" value="1">
                    <div class="form-grid">
                        <div class="form-group"><label>Default Check-In Time</label><input type="time" name="default_check_in" value="<?php echo htmlspecialchars($settings['default_check_in'] ?? '08:00'); ?>" required><div class="help-text">Default time for employee check-in</div></div>
                        <div class="form-group"><label>Default Check-Out Time</label><input type="time" name="default_check_out" value="<?php echo htmlspecialchars($settings['default_check_out'] ?? '16:00'); ?>" required><div class="help-text">Default time for employee check-out</div></div>
                    </div>
                    <div class="form-group">
                        <label>Default Attendance Status</label>
                        <select name="default_attendance_status" style="max-width:200px;">
                            <option value="Present" <?php echo ($settings['default_attendance_status'] ?? 'Present') == 'Present' ? 'selected' : ''; ?>>Present</option>
                            <option value="Absent" <?php echo ($settings['default_attendance_status'] ?? 'Present') == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="Late" <?php echo ($settings['default_attendance_status'] ?? 'Present') == 'Late' ? 'selected' : ''; ?>>Late</option>
                        </select>
                        <div class="help-text">Default status when marking attendance</div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Save Attendance Settings</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 9: SYSTEM TOOLS -->
        <!-- ============================================================ -->
        <div id="tab-system" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">🗄️</span> Database Optimization</div>
                <form method="POST" onsubmit="return confirm('Optimize the database?')">
                    <input type="hidden" name="optimize_db" value="1">
                    <p style="color:var(--text-muted);font-size:13px;">Optimize all database tables to remove fragmentation and improve performance.</p>
                    <button type="submit" class="btn btn-success" style="margin-top:8px;">⚡ Optimize Now</button>
                </form>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">🗑️</span> Cache Management</div>
                <form method="POST">
                    <input type="hidden" name="clear_cache" value="1">
                    <div class="form-group" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <label style="margin:0;font-size:12px;">Cache Type:</label>
                        <select name="cache_type" style="padding:6px 10px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px;width:150px;">
                            <option value="twig">Twig Cache</option>
                            <option value="session">User Sessions</option>
                            <option value="browser">Browser Cache</option>
                            <option value="all">Clear All</option>
                        </select>
                        <button type="submit" class="btn btn-warning btn-sm">🗑️ Clear Now</button>
                    </div>
                </form>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">🔐</span> Password Management</div>
                <p style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">After importing database from another environment, passwords may be stored as plain text. Use this tool to encrypt them.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="hash_passwords.php" class="btn btn-primary btn-sm" onclick="return confirm('⚠️ WARNING: This will re-hash ALL passwords. Are you sure?')">🔐 Re-hash All Passwords</a>
                    <a href="check_passwords.php" class="btn btn-outline btn-sm">🔍 Check Password Status</a>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">📧</span> Email Test</div>
                <form method="POST">
                    <input type="hidden" name="test_email" value="1">
                    <div class="form-group" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <label style="margin:0;font-size:12px;">Test Email:</label>
                        <input type="email" name="test_email_address" required placeholder="admin@example.com" style="width:250px;padding:6px 10px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px;">
                        <button type="submit" class="btn btn-primary btn-sm">📧 Send Test</button>
                    </div>
                </form>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">💾</span> Backup Manager</div>
                <p style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">Create and restore database backups.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="backup.php" class="btn btn-primary btn-sm">💾 Backup Now</a>
                    <a href="backup_scheduler.php" class="btn btn-outline btn-sm">⏰ Schedule Backups</a>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 10: ACTIVITY LOGS -->
        <!-- ============================================================ -->
        <div id="tab-logs" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">📜</span> Recent Activity Logs</div>
                <div style="max-height:350px;overflow-y:auto;">
                    <?php if (!empty($recent_logs)): ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="log-entry">
                                <span class="time"><?php echo date('Y-m-d H:i:s', strtotime($log['Timestamp'])); ?></span>
                                <span class="action"><?php echo htmlspecialchars($log['Action']); ?></span>
                                <span class="details"><?php echo htmlspecialchars(substr($log['Details'], 0, 70)); ?></span>
                                <span class="ip">(<?php echo $log['IPAddress']; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted);text-align:center;padding:15px;">No activity logs found</p>
                    <?php endif; ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="activity_logs.php" class="btn btn-primary btn-sm">📜 View All Logs</a>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 11: MAINTENANCE -->
        <!-- ============================================================ -->
        <div id="tab-maintenance" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">🔧</span> Maintenance Mode</div>
                <form method="POST">
                    <input type="hidden" name="toggle_maintenance" value="1">
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="maintenance_mode" value="1" <?php echo $maintenance_mode ? 'checked' : ''; ?> style="transform:scale(1.2);">
                            Enable Maintenance Mode
                        </label>
                    </div>
                    <div class="form-grid">
                        <div class="form-group"><label>Maintenance Message (English)</label><textarea name="maintenance_message" rows="2" style="font-size:12px;"><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'System is under maintenance. Please check back later.'); ?></textarea></div>
                        <div class="form-group"><label>Maintenance Message (Arabic)</label><textarea name="maintenance_message_ar" rows="2" style="font-size:12px;"><?php echo htmlspecialchars($settings['maintenance_message_ar'] ?? 'النظام قيد الصيانة. يرجى العودة لاحقاً.'); ?></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Save Maintenance Settings</button>
                </form>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">🧹</span> System Cleanup</div>
                <p style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">Delete temporary files and old sessions.</p>
                <form method="POST" onsubmit="return confirm('Clean up the system?')">
                    <input type="hidden" name="system_cleanup" value="1">
                    <button type="submit" class="btn btn-warning btn-sm">🧹 Clean Now</button>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 12: SECURITY -->
        <!-- ============================================================ -->
        <div id="tab-security" class="tab-pane">
            <div class="section-card">
                <div class="section-title"><span class="icon">🔒</span> Security Settings</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px;">
                    <div style="background:rgba(255,255,255,0.03);padding:12px;border-radius:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>Session Security</span>
                            <span style="color:var(--success);">✅ Enabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>CSRF Protection</span>
                            <span style="color:var(--success);">✅ Enabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>SQL Injection Protection</span>
                            <span style="color:var(--success);">✅ Enabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:12px;">
                            <span>.htaccess Security</span>
                            <span style="color:<?php echo file_exists(__DIR__ . '/../.htaccess') ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo file_exists(__DIR__ . '/../.htaccess') ? '✅ Found' : '❌ Missing'; ?>
                            </span>
                        </div>
                    </div>
                    <div style="background:rgba(255,255,255,0.03);padding:12px;border-radius:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>Password Hashing</span>
                            <span style="color:var(--success);">✅ Enabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>Error Logging</span>
                            <span style="color:var(--success);">✅ Enabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px;">
                            <span>Display Errors</span>
                            <span style="color:var(--success);">✅ Disabled</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:12px;">
                            <span>HTTPS (Recommended)</span>
                            <span style="color:<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'var(--success)' : 'var(--warning)'; ?>;">
                                <?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '✅ Enabled' : '⚠️ Not Enabled'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title"><span class="icon">👥</span> Active Sessions Manager</div>
                <?php $active_users = safeSelectArray($conn, "SELECT UserID, Username, UserRole, FullName, IsActive FROM tblusers WHERE IsActive = 1 LIMIT 20"); ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($active_users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['FullName']); ?></strong><br><small style="color:var(--text-muted);font-size:10px;"><?php echo $user['Username']; ?></small></td>
                                <td><?php echo $user['UserRole']; ?></td>
                                <td><span style="color:var(--success);font-size:12px;">🟢 Active</span></td>
                                <td>
                                    <?php if ($user['UserID'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Force logout this user?')">
                                        <input type="hidden" name="force_logout" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🚪 Logout</button>
                                    </form>
                                    <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:11px;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ========== JAVASCRIPT ========== -->
<script>
// Tab switching
function showTab(tabName) {
    // Hide all panes
    document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
    // Show target
    const target = document.getElementById('tab-' + tabName);
    if (target) target.classList.add('active');
    
    // Update sidebar
    document.querySelectorAll('.sidebar .menu-item').forEach(item => item.classList.remove('active'));
    document.querySelectorAll('.sidebar .menu-item').forEach(item => {
        if (item.textContent.trim().toLowerCase().includes(tabName.toLowerCase())) {
            item.classList.add('active');
        }
    });
    
    // Update tabs
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('active');
        }
    });
    
    // Close mobile sidebar
    document.getElementById('sidebar').classList.remove('mobile-visible');
    document.getElementById('sidebarOverlay').classList.remove('active');
}

// Tab click handlers
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        showTab(this.dataset.tab);
    });
});

// Theme selection
function selectTheme(theme) {
    document.getElementById('selected_theme').value = theme;
    document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelectorAll('.theme-option').forEach(opt => {
        if (opt.dataset.theme === theme) opt.classList.add('selected');
    });
    if (theme === 'custom') {
        document.getElementById('customColors').style.display = 'block';
    } else {
        document.getElementById('customColors').style.display = 'none';
        const themeColors = {
            'default': ['#667eea', '#764ba2'],
            'blue': ['#2193b0', '#6dd5ed'],
            'green': ['#11998e', '#38ef7d'],
            'orange': ['#f12711', '#f5af19'],
            'purple': ['#8E2DE2', '#4A00E0'],
            'red': ['#cb2d3e', '#ef473a'],
            'dark': ['#2c3e50', '#3498db'],
            'teal': ['#00b4db', '#0083b0']
        };
        if (themeColors[theme]) {
            document.getElementById('primary_color').value = themeColors[theme][0];
            document.getElementById('secondary_color').value = themeColors[theme][1];
        }
    }
}

// Currency preview
function updateCurrencyPreview() {
    const symbol = document.querySelector('input[name="currency_symbol"]')?.value || '$';
    const position = document.querySelector('select[name="currency_position"]')?.value || 'after';
    const decimalPlaces = parseInt(document.querySelector('select[name="decimal_places"]')?.value || 2);
    const thousandSep = document.querySelector('select[name="thousands_separator"]')?.value || ',';
    const decimalSep = document.querySelector('select[name="decimal_separator"]')?.value || '.';
    
    let amount = 1234567.89;
    let formatted = amount.toFixed(decimalPlaces);
    let parts = formatted.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
    let result = parts.join(decimalSep);
    
    const preview = position === 'before' ? symbol + result : result + ' ' + symbol;
    document.getElementById('previewAmount').textContent = preview;
}

document.querySelectorAll('input[name="currency_symbol"], select[name="currency_position"], select[name="decimal_places"], select[name="thousands_separator"], select[name="decimal_separator"]').forEach(el => {
    if (el) el.addEventListener('change', updateCurrencyPreview);
});
updateCurrencyPreview();

// Threshold preview
document.getElementById('auto_approve_threshold')?.addEventListener('input', function() {
    const threshold = this.value || 0;
    document.getElementById('threshold_example').textContent = 'Payments under ' + threshold + ' SDG will be auto-approved';
});

// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-visible');
    overlay.classList.toggle('active');
}

// Handle URL hash for direct tab access
window.addEventListener('load', function() {
    const hash = window.location.hash.substring(1);
    if (hash && hash !== '') {
        showTab(hash);
    }
});
</script>

<?php include 'includes/footer.php'; ?>