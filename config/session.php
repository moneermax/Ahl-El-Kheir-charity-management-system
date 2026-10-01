<?php
// config/session.php
declare(strict_types=1);

if (!class_exists('Session', false)) {
    class Session {
        public static function start(): void {
            if (session_status() === PHP_SESSION_NONE) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => (defined('APP_ENV') && APP_ENV === 'production'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                session_name('AhlElKheirSession');
                session_start();

                // Rotate the session identifier on the first authenticated request.
                // This preserves the existing session data while preventing a pre-login
                // session identifier from becoming the long-lived authenticated ID.
                if (isset($_SESSION['user_id']) && !isset($_SESSION['_auth_session_rotated'])) {
                    if (session_regenerate_id(true)) {
                        $_SESSION['_auth_session_rotated'] = true;
                    }
                }
            }

            // Centralized record-level boundary for the small set of legacy
            // family/child routes that historically accepted direct IDs.
            if (function_exists('ak_enforce_family_request_scope')) {
                ak_enforce_family_request_scope();
            }

            // Newly-created/recovery-reset accounts must replace their temporary
            // password before accessing any normal application page. This is
            // enforced at the session boundary so direct URLs cannot bypass it.
            if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
                $passwordChangePage = $script === 'change_password.php';
                $logoutPage = $script === 'logout.php';

                if (!$passwordChangePage && !$logoutPage && function_exists('dbFetchOne')) {
                    $passwordState = dbFetchOne(
                        "SELECT password_change_required FROM users WHERE id = ? LIMIT 1",
                        [(int)$_SESSION['user_id']]
                    );

                    if ($passwordState && (int)$passwordState['password_change_required'] === 1) {
                        $target = (defined('APP_URL') ? APP_URL : '/') . 'modules/users/change_password.php?forced=1';
                        header('Location: ' . $target);
                        exit();
                    }
                }
            }
        }

        public static function isLoggedIn(): bool {
            return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        }

        public static function getUserRole(): string {
            $role = trim((string)($_SESSION['user_role'] ?? 'guest'));
            return match ($role) {
                'fm', 'finance' => 'financial_manager',
                default => $role,
            };
        }

        public static function getUserId(): int {
            return (int)($_SESSION['user_id'] ?? 0);
        }

        public static function getUserName(): string {
            return (string)($_SESSION['user_name'] ?? '');
        }

        public static function hasFlash(): bool {
            return !empty($_SESSION['flash']);
        }

        public static function getFlash(): array {
            $flashes = $_SESSION['flash'] ?? [];
            unset($_SESSION['flash']);
            return $flashes;
        }
    }
}

// Global helpers (kept for backward compatibility with existing UI/includes)
if (!function_exists('flash')) {
    function flash(string $type, string $message): void {
        $_SESSION['flash'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('get_flashes')) {
    function get_flashes(): array {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }
}