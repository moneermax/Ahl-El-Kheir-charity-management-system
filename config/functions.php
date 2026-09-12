<?php
declare(strict_types=1);
/**
 * Global Helpers & Routing — v2.4
 * Centralized dashboard routing for accounting roles.
 */

/* ---------- output & url ---------- */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string {
    return APP_URL . ltrim($path, '/');
}

function asset(string $path): string {
    return APP_URL . 'assets/' . ltrim($path, '/');
}

function redirect(string $url): void {
    if (!preg_match('#^https?://#i', $url)) {
        $url = APP_URL . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

/* ---------- session ---------- */
function is_logged_in(): bool {
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string {
    return $_SESSION['user_role'] ?? null;
}

function current_user_name(): ?string {
    return $_SESSION['user_name'] ?? null;
}

/* ---------- legacy notification request hardening ---------- */
/*
 * Older header code recognized ?mark_notif_read=1 as a state-changing GET.
 * Keep that legacy parameter inert unless a future explicit POST handler
 * consumes it. Current notification actions use dedicated POST+CSRF endpoints.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['mark_notif_read'])) {
    unset($_GET['mark_notif_read']);
}

/* ---------- CSRF ---------- */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
        $sent = $_POST['csrf_token'] ?? $_POST['csrf'] ?? '';
        return isset($_SESSION['csrf_token']) && $sent !== '' && hash_equals($_SESSION['csrf_token'], $sent);
    }
}

/* ---------- flash messages ---------- */
if (!function_exists('flash')) {
    function flash(string $type, string $message): void {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_all')) {
    function flash_all(): array {
        $m = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $m;
    }
}

/* ---------- auth guards ---------- */
if (!function_exists('require_login')) {
    function require_login(): void {
        if (!Session::isLoggedIn()) {
            header('Location: ' . APP_URL . 'index.php');
            exit();
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(string|array $roles): void {
        require_login();
        $userRole = Session::getUserRole();
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array($userRole, $roles, true)) {
            header('Location: ' . APP_URL . 'index.php');
            exit();
        }
    }
}

/* ---------- login router ---------- */
if (!function_exists('dashboard_for_role')) {
    function dashboard_for_role(?string $role): string {
        $role = trim((string) $role);
        return match ($role) {
            'admin', 'sudo', 'super_admin' => 'dashboard/admin_dashboard.php',
            'general_manager', 'gm' => 'dashboard/gm_dashboard.php',
            'vice_general_manager', 'vgm' => 'dashboard/vgm_dashboard.php',
            'financial_manager', 'fm', 'finance' => 'modules/accounting/fm_dashboard.php',
            'accountant', 'accountant_staff' => 'dashboard/accountant_staff_dashboard.php',
            'supervisor' => 'dashboard/supervisor_dashboard.php',
            'nanny' => 'dashboard/nanny_dashboard.php',
            'administration', 'staff' => 'dashboard/staff_dashboard.php',
            'social_media' => 'dashboard/staff_dashboard.php',
            'hr_manager', 'hr_staff' => 'dashboard/hr_dashboard.php',
            'projects_manager', 'project_supervisor' => 'dashboard/projects_dashboard.php',
            default => 'index.php',
        };
    }
}

/* ---------- arabic letter helpers ---------- */
function normalize_arabic_letter(string $ch): string {
    $map = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه'];
    return $map[$ch] ?? $ch;
}

function first_letter_of(string $name): array {
    $clean = preg_replace('/[\x{064B}-\x{0652}\x{0640}\x{200B}-\x{200D}\x{FEFF}]/u', '', trim($name));
    if ($clean === '') return ['', ''];
    $raw = mb_substr($clean, 0, 1, 'UTF-8');
    return [$raw, normalize_arabic_letter($raw)];
}

/* ---------- application-owned data integrity rules ---------- */
require_once __DIR__ . '/data_integrity.php';
ak_register_data_integrity_hooks();

/* ---------- centralized global-search authorization ---------- */
require_once __DIR__ . '/search_permissions.php';

if (function_exists('ak_search_enforce_request')) {
    ak_search_enforce_request();
}

if (function_exists('ak_search_register_ui_filter')) {
    ak_search_register_ui_filter();
}
