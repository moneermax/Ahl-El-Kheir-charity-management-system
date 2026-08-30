<?php
// Root: logout.php - Terminates the AhlElKheirSession session properly
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/session.php';
// CRITICAL: resume the SAME named session that login created (AhlElKheirSession).
// Without this, PHP would destroy an empty default session and leave you logged in.
Session::start();
$uid = Session::isLoggedIn() ? Session::getUserId() : null;
// Audit the logout (never block logout if audit fails)
if ($uid !== null) {
try {
dbExecute(
"INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent)
VALUES (?, 'LOGOUT', 'users', ?, ?, ?)",
[
$uid,
$uid,
$_SERVER['REMOTE_ADDR'] ?? '',
$_SERVER['HTTP_USER_AGENT'] ?? ''
]
);
} catch (Throwable $e) { /* fail silently */ }
}
// Wipe all session data
$_SESSION = [];
// Delete the session cookie so the browser forgets it
if (ini_get('session.use_cookies')) {
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// Destroy the session on the server
session_destroy();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>جاري تسجيل الخروج...</title>
<style>
body {
font-family: 'Cairo', sans-serif;
background: #f4f7fb;
display: flex;
align-items: center;
justify-content: center;
height: 100vh;
margin: 0;
}
.logout-container {
text-align: center;
padding: 40px;
background: #fff;
border-radius: 12px;
box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.spinner {
border: 4px solid #f3f3f3;
border-top: 4px solid #1b4d8f;
border-radius: 50%;
width: 50px;
height: 50px;
animation: spin 1s linear infinite;
margin: 0 auto 20px;
}
@keyframes spin {
0% { transform: rotate(0deg); }
100% { transform: rotate(360deg); }
}
h2 {
color: #1b4d8f;
margin-bottom: 10px;
}
p {
color: #6c757d;
}
</style>
</head>
<body>
<div class="logout-container">
<div class="spinner"></div>
<h2>جاري تسجيل الخروج...</h2>
<p>يرجى الانتظار لحظة</p>
</div>
<script>
// Clear the age alert modal shown flag so it appears on next login
sessionStorage.removeItem('ageAlertModalShown');

// Redirect to login page after a brief delay
setTimeout(function() {
window.location.href = '<?php echo APP_URL; ?>index.php';
}, 1000);
</script>
</body>
</html>