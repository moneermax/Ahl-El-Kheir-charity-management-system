# وثيقة المتطلبات الفنية والنظام الإلكتروني لإدارة المشاريع والاعتماد المالي
*(موجهة للتطوير البرمجي واستخدام نماذج الذكاء الاصطناعي - Product Requirements Document)*

---

## 📌 Context & Objective (السياق والهدف)
تأسيس نظام إدارة إلكتروني لمنظمة خيريّة تعمل في **كفالة الأيتام والمشاريع الخيرية والتنموية**. 
الهدف الأساسي هو ربط **مدير المشاريع** (منشئ المشروع) بـ **المدير المالي** (المعتمِد للميزانية) عبر بيئة عمل ذكية، مرنة، وسريعة تُقلل التكرار والأخطاء.

---

## 🎯 الأولوية القصوى (Core Requirement): "النموذج المطور والديناميكي"

يركز هذا النظام على **تطبيق المقترح الاحترافي المرن** وتجنب النماذج التقليدية الصلبة، وذلك عبر المبادئ التالية:

1. **الاستمارات الديناميكية (Dynamic Forms):** تتغير حقول الاستمارة تلقائياً بناءً على "نوع المشروع" (كفالة أيتام، إغاثة، آبار/بناء، تمكين اقتصادي).
2. **قوالب الميزانيات المسبقة (Budget Templates):** تحميل بنود ميزانية شائعة تلقائياً مع أسعار استرشادية عند اختيار نوع المشروع لتسريع الإدخال بنسبة 70%.
3. **التعديل والتشارك المباشر (Inline Collaboration):** إمكانية وضع ملاحظات مالية على بنود محددة والتعديل عليها دون الحاجة لرفض الطلب وإعادة الدورة المستندية.
4. **مستويات الاعتماد المرنة (Threshold-Based Approval):** اعتماد سريع للمشاريع الصغيرة، وموافقات متعددة المستويات للمشاريع ذات الميزانيات الضخمة.
5. **الربط المحاسبي والإستراتيجي:** ربط الميزانية بالخطة التشغيلية السنوية ومراكز التكلفة المحاسبية (Cost Centers).

---

## 1️⃣ استمارة إنشاء المشروع (Project Creation Form - PM View)

### البيانات الأساسية
* **عنوان المشروع ورقم المرجع:** يُولد تلقائياً (`#PRJ-YYYY-XXXX`).
* **تصنيف المشروع:** (كفالة أيتام، مياه، إغاثة، تمكين اقتصادي، إلخ) — *تتغير باقي الشاشة بحسب الاختيار*.
* **الربط الإستراتيجي:** اختيار الهدف من الخطة السنوية التي يخدمها المشروع.
* **الموقع والمستفيدون:** تحديد المنطقة، عدد المستفيدين المباشرين، وفئة المستهدفين.
* **الجدول الزمني:** تاريخ البداية والنهاية والمدة المتوقعة.

### الميزانية الذكية (Smart Budgeting)
* **تحميل قالب ميزانية:** زر اختيار قالب مسبق (مثال: قالب سلال غذائية).
* **جدول البنود:** [اسم البند] | [الوحدة] | [الكمية] | [سعر الوحدة الاسترشادي] | [الإجمالي الفرعي - تلقائي].
* **التنبيه بالأسعار المرجعية:** ينبه النظام تلقائياً إذا تجاوز سعر الوحدة أسعار الشراء السابقة في المنظمة.
* **مصدر التمويل وجدولة الصرف:** (تبرع مخصص، الصندوق العام، منحة) + اختيار طريقة الصرف (دفعة واحدة / دفعات).
* **المرفقات:** رفع عروض الأسعار والدراسات الميدانية.

---

## 2️⃣ شاشات وإجراءات المدير المالي (Financial Approval Workflow)

### لوحة المراجعة والتحليل (Financial Dashboard & Audit Panel)
* **مؤشر سيولة الحساب (Real-Time Budget Indicator):** يوضح للمدير المالي الرصيد المتاح في صندوق/حساب هذا النشاط والمبلغ المتبقي بعد اعتماد المشروع.
* **الملاحظات المضمنة (Inline Comments):** إضافة ملاحظة على أي بند مالي محدد ليتناقش فيها مع مدير المشاريع بدلاً من رفض الطلب.

### قرارات التصديق (Decision Panel)
* **اعتماد سريع (Fast-Track Approval):** للمشاريع الصغيرة.
* **اعتماد مع تعديلات (Approve with Edits):** اعتماد بعد تعديل الميزانية بالتوافق.
* **طلب مراجعة (Request Revision):** إرجاع الطلب لملاحظات معينة.
* **تحديد مركز التكلفة (Cost Center / Account Code):** حقل إجباري للمدير المالي لربطه بالدفاتر المحاسبية.

---

## 3️⃣ مخطط قاعدة البيانات (Database Schema)

```sql
-- 1. جدول بيانات المشاريع
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_code VARCHAR(30) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    strategic_goal_id INT NULL,
    location VARCHAR(255) NOT NULL,
    project_manager_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_budget DECIMAL(12, 2) DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'USD',
    funding_source ENUM('restricted_grant', 'general_fund', 'zakat', 'individual_donor') NOT NULL,
    cost_center_code VARCHAR(50) NULL,
    status ENUM('draft', 'pending_financial_approval', 'revision_requested', 'approved', 'rejected', 'in_execution', 'completed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. جدول قوالب الميزانية المسبقة
CREATE TABLE budget_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    template_name VARCHAR(150) NOT NULL
);

CREATE TABLE budget_template_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    default_description VARCHAR(255) NOT NULL,
    default_unit VARCHAR(50),
    estimated_unit_price DECIMAL(10, 2)
);

-- 3. جدول بنود الميزانية المعتمدة للمشروع
CREATE TABLE project_budget_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    financial_notes TEXT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 4. جدول تتبع الاعتمادات والقرارات المالية
CREATE TABLE project_financial_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    financial_manager_id INT NOT NULL,
    action ENUM('approved', 'rejected', 'revision_requested') NOT NULL,
    notes TEXT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 5. جدول المصروفات الفعلية والتقارير
CREATE TABLE project_actual_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    budget_item_id INT NULL,
    expense_description VARCHAR(255) NOT NULL,
    amount_spent DECIMAL(12, 2) NOT NULL,
    receipt_number VARCHAR(100) NULL,
    expense_date DATE NOT NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (budget_item_id) REFERENCES project_budget_items(id) ON DELETE SET NULL
);
```

---

## 4️⃣ نموذج التقرير المالي اللاحق (Post-Execution Financial Closure)

تقرير قياس انحراف الميزانية بعد التنفيذ لتسوية الحسابات:

* **جدول مقارنة الأداء (Budget Variance Table):**
  * [البند المالي] | [المخطط] | [الفعلي] | [الانحراف Variance] | [نسبة الانحراف] | [الملاحظات والمستندات].
* **المؤشرات المالية:**
  * إجمالي المبلغ المعتمد مقابل المصروف الفعلي.
  * حساب الوفر الفعلي (توفير في الميزانية) أو العجز وتبريره.
  * **قرار التصفية المالية:** تسوية الفروقات وإرجاع المبالغ المتبقية لصندوق المنظمة وإغلاق الملف (`Reconciled & Closed`).

---

## 💡 تعليمات للموجه البرمجي / الذكاء الاصطناعي (Instruction for AI Agent)
> "بناءً على وثيقة المتطلبات أعلاه، يرجى كتابة الكود البرمجي (سواء Frontend أو Backend أو APIs) للتطبيق، مع التأكيد على برمجة **النماذج الديناميكية والقوالب المسبقة وقواعد البيانات** وفق أعلى معايير أداء الأنظمة المالية والجمعيات الخيرية."