<?php

/*
|--------------------------------------------------------------------------
| modules/users/profile.php
|--------------------------------------------------------------------------
| Personal profile:
|   - Personal information
|   - Profile photo
|   - Photo upload
|   - Photo removal
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';

Session::start();


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!Session::isLoggedIn()) {

    header(
        'Location: ' .
        APP_URL .
        'index.php'
    );

    exit();
}


$pageTitle = 'ملفي الشخصي';
$active = 'profile';


/*
|--------------------------------------------------------------------------
| Ensure profile columns exist
|--------------------------------------------------------------------------
*/

try {

    dbExecute(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL"
    );

    dbExecute(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL"
    );

    dbExecute(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS birth_date DATE NULL"
    );

    dbExecute(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS gender VARCHAR(20) NULL"
    );

} catch (Throwable $e) {

    /*
     * Ignore errors on older database engines.
     */
}


/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$uid = Session::getUserId();

$me = dbFetchOne(
    "SELECT
        u.*,
        r.name_ar AS role_name,
        r.code AS role_code
     FROM users u
     JOIN roles r ON r.id = u.role_id
     WHERE u.id = ?",
    [$uid]
);


/*
|--------------------------------------------------------------------------
| Avatar storage directory
|--------------------------------------------------------------------------
*/

$avatarsDir =
    dirname(__DIR__, 2) .
    '/storage/avatars';


if (!is_dir($avatarsDir)) {

    @mkdir(
        $avatarsDir,
        0777,
        true
    );
}


/*
|--------------------------------------------------------------------------
| Errors
|--------------------------------------------------------------------------
*/

$errors = [];


/*
|--------------------------------------------------------------------------
| POST handling
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
     * CSRF protection
     */
    if (!verify_csrf()) {

        $errors[] =
            'انتهت صلاحية الجلسة.';


    /*
     * ==============================================================
     * UPLOAD PHOTO
     * ==============================================================
     */
    } elseif (isset($_POST['upload_photo'])) {


        /*
         * Check upload.
         */
        if (
            !isset($_FILES['avatar']) ||
            $_FILES['avatar']['error'] !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                'تعذر رفع الصورة، حاول مرة أخرى.';

        } else {


            /*
             * Detect actual MIME type.
             */
            $finfo = new finfo(
                FILEINFO_MIME_TYPE
            );

            $mime = (string)$finfo->file(
                $_FILES['avatar']['tmp_name']
            );


            /*
             * Allowed image types.
             */
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];


            /*
             * Validate MIME.
             */
            if (!isset($allowed[$mime])) {

                $errors[] =
                    'نوع الملف غير مدعوم (JPG / PNG / WebP فقط).';


            /*
             * Validate size.
             */
            } elseif (
                $_FILES['avatar']['size'] >
                2 * 1024 * 1024
            ) {

                $errors[] =
                    'حجم الصورة يجب ألا يتجاوز 2MB.';


            } else {


                /*
                 * Generate unique filename.
                 */
                $fname =
                    'user_' .
                    $uid .
                    '_' .
                    time() .
                    '.' .
                    $allowed[$mime];


                /*
                 * Physical destination.
                 */
                $destination =
                    $avatarsDir .
                    DIRECTORY_SEPARATOR .
                    $fname;


                /*
                 * Move uploaded file.
                 */
                if (
                    move_uploaded_file(
                        $_FILES['avatar']['tmp_name'],
                        $destination
                    )
                ) {


                    /*
                     * Optional debug log.
                     *
                     * This records the physical file only.
                     */
                    $debugLog =
                        dirname(__DIR__, 2) .
                        '/storage/avatar_debug.log';

                    file_put_contents(
                        $debugLog,
                        "Upload Success!\n"
                    );

                    file_put_contents(
                        $debugLog,
                        "Physical Path: " .
                        $destination .
                        "\n",
                        FILE_APPEND
                    );

                    file_put_contents(
                        $debugLog,
                        "File Exists: " .
                        (
                            is_file($destination)
                                ? 'YES'
                                : 'NO'
                        ) .
                        "\n",
                        FILE_APPEND
                    );


                    /*
                     * Delete previous avatar.
                     */
                    if (!empty($me['avatar_path'])) {

                        $oldFileName =
                            basename(
                                (string)$me['avatar_path']
                            );

                        $old =
                            $avatarsDir .
                            DIRECTORY_SEPARATOR .
                            $oldFileName;

                        if (is_file($old)) {
                            @unlink($old);
                        }
                    }


                    /*
                     * Store RELATIVE path in database.
                     *
                     * IMPORTANT:
                     *
                     * Database:
                     * storage/avatars/user_X.jpg
                     *
                     * NOT:
                     * http://localhost...
                     */
                    dbExecute(
                        "UPDATE users
                         SET avatar_path = ?
                         WHERE id = ?",
                        [
                            'storage/avatars/' . $fname,
                            $uid
                        ]
                    );


                    /*
                     * Success.
                     */
                    flash(
                        'success',
                        'تم تحديث صورتك الشخصية.'
                    );


                    /*
                     * Redirect.
                     */
                    header(
                        'Location: ' .
                        APP_URL .
                        'modules/users/profile.php'
                    );

                    exit();


                } else {

                    $errors[] =
                        'تعذر حفظ الصورة على الخادم.';
                }
            }
        }


    /*
     * ==============================================================
     * REMOVE PHOTO
     * ==============================================================
     */
    } elseif (isset($_POST['remove_photo'])) {


        if (!empty($me['avatar_path'])) {

            $oldFileName =
                basename(
                    (string)$me['avatar_path']
                );

            $old =
                $avatarsDir .
                DIRECTORY_SEPARATOR .
                $oldFileName;

            if (is_file($old)) {
                @unlink($old);
            }
        }


        /*
         * Remove database reference.
         */
        dbExecute(
            "UPDATE users
             SET avatar_path = NULL
             WHERE id = ?",
            [$uid]
        );


        flash(
            'success',
            'تمت إزالة الصورة.'
        );


        header(
            'Location: ' .
            APP_URL .
            'modules/users/profile.php'
        );

        exit();


    /*
     * ==============================================================
     * UPDATE PERSONAL INFORMATION
     * ==============================================================
     */
    } else {


        $fullName =
            trim(
                $_POST['full_name'] ?? ''
            );

        $email =
            trim(
                $_POST['email'] ?? ''
            );

        $phone =
            trim(
                $_POST['phone'] ?? ''
            );

        $address =
            trim(
                $_POST['address'] ?? ''
            );

        $birth =
            trim(
                $_POST['birth_date'] ?? ''
            );

        $gender =
            in_array(
                $_POST['gender'] ?? '',
                ['ذكر', 'أنثى'],
                true
            )
                ? $_POST['gender']
                : null;


        /*
         * Validation.
         */
        if ($fullName === '') {

            $errors[] =
                'الاسم الكامل مطلوب.';
        }


        if (
            $email !== '' &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $errors[] =
                'البريد الإلكتروني غير صالح.';
        }


        if (
            $email !== '' &&
            dbFetchOne(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 AND id <> ?",
                [
                    $email,
                    $uid
                ]
            )
        ) {

            $errors[] =
                'هذا البريد مستخدم في حساب آخر.';
        }


        if (
            $birth !== '' &&
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $birth
            )
        ) {

            $errors[] =
                'تاريخ الميلاد غير صالح.';
        }


        /*
         * Save personal information.
         */
        if (!$errors) {

            dbExecute(
                "UPDATE users
                 SET
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    address = ?,
                    birth_date = ?,
                    gender = ?
                 WHERE id = ?",
                [
                    $fullName,
                    $email !== ''
                        ? $email
                        : null,
                    $phone !== ''
                        ? $phone
                        : null,
                    $address !== ''
                        ? $address
                        : null,
                    $birth !== ''
                        ? $birth
                        : null,
                    $gender,
                    $uid
                ]
            );


            /*
             * Audit log.
             */
            try {

                dbExecute(
                    "INSERT INTO audit_log
                    (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        old_values,
                        new_values,
                        ip_address,
                        user_agent
                    )
                    VALUES
                    (
                        ?,
                        'UPDATE',
                        'users',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )",
                    [
                        $uid,
                        $uid,

                        json_encode(
                            [
                                'full_name' =>
                                    $me['full_name'],
                                'email' =>
                                    $me['email'],
                                'phone' =>
                                    $me['phone']
                            ],
                            JSON_UNESCAPED_UNICODE
                        ),

                        json_encode(
                            [
                                'full_name' =>
                                    $fullName,
                                'email' =>
                                    $email,
                                'phone' =>
                                    $phone,
                                'address' =>
                                    $address
                            ],
                            JSON_UNESCAPED_UNICODE
                        ),

                        $_SERVER['REMOTE_ADDR']
                            ?? '',

                        $_SERVER['HTTP_USER_AGENT']
                            ?? ''
                    ]
                );

            } catch (Throwable $e) {
                /*
                 * Do not prevent profile update if
                 * audit logging fails.
                 */
            }


            flash(
                'success',
                'تم حفظ بياناتك الشخصية بنجاح.'
            );


            header(
                'Location: ' .
                APP_URL .
                'modules/users/profile.php'
            );

            exit();
        }
    }
}


/*
|--------------------------------------------------------------------------
| BUILD AVATAR URL
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Physical path:
| D:/xampp/htdocs/AhlElKheir/storage/avatars/user_X.jpg
|
| Browser URL:
| http://localhost:8081/AhlElKheir/storage/avatars/user_X.jpg
|
| These are deliberately kept separate.
|--------------------------------------------------------------------------
*/

$avatarUrl = '';

$avatarPhysicalPath = '';

$avatarFileName = '';


if (!empty($me['avatar_path'])) {


    /*
     * Get ONLY the filename from the database value.
     */
    $avatarFileName =
        basename(
            (string)$me['avatar_path']
        );


    /*
     * Physical filesystem path.
     */
    $avatarPhysicalPath =
        $avatarsDir .
        DIRECTORY_SEPARATOR .
        $avatarFileName;


    /*
     * Verify the physical file exists.
     */
    if (is_file($avatarPhysicalPath)) {


        /*
         * Build the PUBLIC browser URL.
         *
         * APP_URL already contains:
         *
         * http://localhost:8081/AhlElKheir/
         */
        $avatarUrl =
            APP_URL .
            'storage/avatars/' .
            rawurlencode($avatarFileName);


        /*
         * Cache buster.
         *
         * IMPORTANT:
         * This is added ONLY ONCE.
         */
        $avatarModifiedTime =
            filemtime($avatarPhysicalPath);

        if ($avatarModifiedTime !== false) {

            $avatarUrl .=
                '?v=' .
                $avatarModifiedTime;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

include dirname(__DIR__, 2) . '/includes/header.php';

?>

<div class="welcome-section fade-in">

    <h2>ملفي الشخصي</h2>

    <p>
        عرض وتحديث بياناتك الأساسية وصورتك الشخصية
    </p>

</div>


<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>


<?php if ($errors): ?>

    <div class="alert alert-danger fade-in">

        <ul class="mb-0">

            <?php foreach ($errors as $er): ?>

                <li>
                    <?php echo e($er); ?>
                </li>

            <?php endforeach; ?>

        </ul>

    </div>

<?php endif; ?>


<div class="row g-4">


    <!-- =========================================================
         PROFILE CARD
         ========================================================= -->

    <div class="col-md-4">

        <div class="card text-center fade-in">

            <div class="card-body">


                <!-- =================================================
                     PROFILE AVATAR
                     THERE IS ONLY ONE IMAGE HERE.
                     ================================================= -->

                <div class="mb-3">

                    <?php if ($avatarUrl): ?>

                        <img
                            id="avatarPreview"
                            src="<?php echo e($avatarUrl); ?>"
                            alt="الصورة الشخصية"
                            class="rounded-circle border"
                            style="
                                width:120px;
                                height:120px;
                                object-fit:cover;
                                border-color:#1b4d8f!important;
                                background:#fff;
                            "
                        >

                    <?php else: ?>

                        <img
                            id="avatarPreview"
                            src=""
                            alt="الصورة الشخصية"
                            style="
                                display:none;
                                width:120px;
                                height:120px;
                                object-fit:cover;
                                border-radius:50%;
                                border:3px solid #1b4d8f;
                            "
                        >

                        <i
                            id="defaultAvatarIcon"
                            class="fas fa-user-circle"
                            style="
                                font-size:7rem;
                                color:#1b4d8f;
                            "
                        ></i>

                    <?php endif; ?>

                </div>


                <h5 class="mb-1">
                    <?php echo e($me['full_name']); ?>
                </h5>


                <div
                    class="text-muted small mb-2"
                    dir="ltr"
                >
                    @<?php echo e($me['username']); ?>
                </div>


                <span class="badge bg-primary mb-3">
                    <?php echo e($me['role_name']); ?>
                </span>


                <!-- =================================================
                     UPLOAD FORM
                     ================================================= -->

                <form
                    method="post"
                    enctype="multipart/form-data"
                    class="mb-2"
                >

                    <?php echo csrf_field(); ?>

                    <input
                        type="file"
                        name="avatar"
                        class="form-control form-control-sm mb-2"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >

                    <button
                        name="upload_photo"
                        value="1"
                        class="btn btn-primary btn-sm w-100"
                    >

                        <i class="fas fa-camera me-1"></i>

                        رفع صورة شخصية

                    </button>

                </form>


                <!-- =================================================
                     REMOVE PHOTO
                     ================================================= -->

                <?php if ($avatarUrl): ?>

                    <form method="post">

                        <?php echo csrf_field(); ?>

                        <button
                            name="remove_photo"
                            value="1"
                            class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('إزالة الصورة؟')"
                        >

                            <i class="fas fa-trash me-1"></i>

                            إزالة الصورة

                        </button>

                    </form>

                <?php endif; ?>


                <hr>


                <div class="text-start small">

                    <div class="mb-1">

                        <i class="fas fa-clock me-2 text-muted"></i>

                        آخر دخول:

                        <?php
                        echo e(
                            $me['last_login_at']
                            ?? '—'
                        );
                        ?>

                    </div>


                    <div class="mb-1">

                        <i class="fas fa-calendar-plus me-2 text-muted"></i>

                        تاريخ الإنشاء:

                        <?php
                        echo e(
                            $me['created_at']
                        );
                        ?>

                    </div>


                    <?php if (!empty($me['gender'])): ?>

                        <div class="mb-1">

                            <i class="fas fa-venus-mars me-2 text-muted"></i>

                            النوع:

                            <?php
                            echo e(
                                $me['gender']
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty($me['birth_date'])): ?>

                        <div class="mb-1">

                            <i class="fas fa-cake-candles me-2 text-muted"></i>

                            الميلاد:

                            <?php
                            echo e(
                                $me['birth_date']
                            );
                            ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         PERSONAL INFORMATION
         ========================================================= -->

    <div class="col-md-8">

        <div class="card fade-in">

            <div class="card-header">

                <i class="fas fa-id-card me-2"></i>

                البيانات الأساسية

            </div>


            <div class="card-body">

                <form method="post">

                    <?php echo csrf_field(); ?>


                    <div class="row g-3">


                        <div class="col-md-6">

                            <label class="form-label">

                                اسم المستخدم

                                <small class="text-muted">
                                    (ثابت)
                                </small>

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?php echo e($me['username']); ?>"
                                dir="ltr"
                                disabled
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">

                                الدور

                                <small class="text-muted">
                                    (ثابت)
                                </small>

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?php echo e($me['role_name']); ?>"
                                disabled
                            >

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                الاسم الكامل *
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                class="form-control"
                                required
                                value="<?php echo e($me['full_name']); ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                البريد الإلكتروني
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                dir="ltr"
                                value="<?php echo e($me['email'] ?? ''); ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                الهاتف
                            </label>

                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                dir="ltr"
                                value="<?php echo e($me['phone'] ?? ''); ?>"
                            >

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                العنوان
                            </label>

                            <input
                                type="text"
                                name="address"
                                class="form-control"
                                value="<?php echo e($me['address'] ?? ''); ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                تاريخ الميلاد
                            </label>

                            <input
                                type="date"
                                name="birth_date"
                                class="form-control"
                                value="<?php echo e($me['birth_date'] ?? ''); ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                النوع
                            </label>

                            <select
                                name="gender"
                                class="form-select"
                            >

                                <option value="">
                                    — غير محدد —
                                </option>

                                <option
                                    value="ذكر"
                                    <?php
                                    echo (
                                        ($me['gender'] ?? '') === 'ذكر'
                                    )
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    ذكر
                                </option>

                                <option
                                    value="أنثى"
                                    <?php
                                    echo (
                                        ($me['gender'] ?? '') === 'أنثى'
                                    )
                                        ? 'selected'
                                        : '';
                                    ?>
                                >
                                    أنثى
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="mt-4 d-flex gap-2">

                        <button
                            class="btn btn-primary"
                        >

                            <i class="fas fa-save me-1"></i>

                            حفظ التغييرات

                        </button>


                        <a
                            href="<?php echo APP_URL; ?>modules/users/change_password.php"
                            class="btn btn-outline-secondary"
                        >

                            <i class="fas fa-key me-1"></i>

                            تغيير كلمة المرور

                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const fileInput =
            document.querySelector(
                'input[type="file"][name="avatar"]'
            );

        const previewImg =
            document.querySelector(
                '#avatarPreview'
            );

        const defaultIcon =
            document.querySelector(
                '#defaultAvatarIcon'
            );


        if (fileInput && previewImg) {

            fileInput.addEventListener(
                'change',
                function (event) {

                    const file =
                        event.target.files[0];


                    if (!file) {
                        return;
                    }


                    const reader =
                        new FileReader();


                    reader.onload =
                        function (loadEvent) {

                            /*
                             * Show selected image immediately.
                             */
                            previewImg.src =
                                loadEvent.target.result;

                            previewImg.style.display =
                                'block';


                            /*
                             * Hide default icon.
                             */
                            if (defaultIcon) {

                                defaultIcon.style.display =
                                    'none';
                            }
                        };


                    reader.readAsDataURL(file);
                }
            );
        }

    }
);

</script>


<?php
include dirname(__DIR__, 2) . '/includes/footer.php';
?>