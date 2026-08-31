<<<<<<< HEAD
<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'يجب تسجيل الدخول أولاً.');
        redirect('index.php');
    }
}

function require_role(array $roles): void
{
    require_login();

    $role = current_user_role();

    if (!in_array($role, $roles, true)) {
        flash('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        redirect(dashboard_for_role($role));
    }
=======
<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'يجب تسجيل الدخول أولاً.');
        redirect('index.php');
    }
}

function require_role(array $roles): void
{
    require_login();

    $role = current_user_role();

    if (!in_array($role, $roles, true)) {
        flash('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        redirect(dashboard_for_role($role));
    }
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
}