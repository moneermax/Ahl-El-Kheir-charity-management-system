<style>
.account-create-shell{padding:26px}
.account-create-intro{display:flex;align-items:center;gap:15px;padding:18px 20px;background:linear-gradient(135deg,#1b4d8f,#2c5aa0);color:#fff;border-radius:12px;margin-bottom:20px}
.account-create-icon{width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:1.35rem}
.account-create-intro h3{font-weight:700}
.account-create-intro p{opacity:.9}
.account-create-shell .form-label{font-weight:600}
.account-create-shell .form-control,.account-create-shell .form-select{border-radius:8px}
.section-title{font-weight:700;color:#1b4d8f;border-bottom:1px solid #e8edf5;padding-bottom:9px;margin-bottom:16px}
.account-create-result{background:#f5f8fc;border:1px solid #dce6f2;border-radius:10px;padding:15px 18px;color:#435064}
.account-create-result ul{padding-right:20px}
</style>
<?php
/*
 * Shared account creation form.
 * Both Admin and HR render this exact form and submit to their own
 * authorized controller, which calls hrCreateUserWithEmployee().
 */
?>
<div class="account-create-shell">
    <div class="account-create-intro">
        <div class="account-create-icon"><i class="fas fa-user-plus"></i></div>
        <div>
            <h3 class="mb-1">إنشاء حساب موظف جديد</h3>
            <p class="mb-0">إنشاء حساب الدخول وملف الموظف المرتبط به تلقائياً في خطوة واحدة.</p>
        </div>
    </div>

    <div class="alert alert-info border-0 mb-4">
        <i class="fas fa-circle-info me-1"></i>
        <strong>مهم:</strong> لا تحتاج إلى اختيار سجل موظف موجود. بعد إنشاء الحساب ينشئ النظام ملف الموظف ويربطه بالحساب تلقائياً.
        يمكن للموظف استكمال بياناته الشخصية لاحقاً من ملفه الشخصي.
    </div>

    <form method="post" autocomplete="off">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="create_user" value="1">

        <div class="section-title"><i class="fas fa-id-card me-2"></i>بيانات الحساب والهوية</div>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control form-control-lg" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control form-control-lg" dir="ltr" required pattern="[A-Za-z0-9_.]{3,30}">
            </div>
            <div class="col-md-3">
                <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control form-control-lg" dir="ltr" minlength="6" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control form-control-lg" dir="ltr">
            </div>
            <div class="col-md-4">
                <label class="form-label">رقم الهاتف</label>
                <input type="text" name="phone" class="form-control form-control-lg" dir="ltr">
            </div>
            <div class="col-md-4">
                <label class="form-label">الجنس</label>
                <select name="gender" class="form-select form-select-lg">
                    <option value="">— الجنس —</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
        </div>

        <div class="section-title"><i class="fas fa-sitemap me-2"></i>التعيين الإداري</div>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">الدور <span class="text-danger">*</span></label>
                <select name="role_code" class="form-select form-select-lg" required>
                    <option value="">— اختر الدور —</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo e($r['code']); ?>"><?php echo e($r[$nameCol]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">القسم</label>
                <select name="department_id" class="form-select form-select-lg">
                    <option value="">— اختر القسم —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>"><?php echo e($d[$nameCol]); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">المسؤول المباشر</label>
                <select name="manager_id" class="form-select form-select-lg">
                    <option value="">— غير معين —</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>"><?php echo e($m['full_name']); ?> (<?php echo e($m['username']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="account-create-result">
            <div><i class="fas fa-link me-2"></i><strong>ما الذي سيحدث تلقائياً؟</strong></div>
            <ul class="mb-0 mt-2">
                <li>إنشاء حساب المستخدم.</li>
                <li>إنشاء ملف موظف مرتبط بالحساب بواسطة <code>employees.user_id</code>.</li>
                <li>توليد كود الموظف وحالة التوظيف الأولية تلقائياً.</li>
                <li>يمكن استكمال البيانات الوظيفية والشخصية لاحقاً دون إنشاء حساب آخر.</li>
            </ul>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-user-plus me-2"></i>إنشاء الحساب والموظف
            </button>
        </div>
    </form>
</div>
