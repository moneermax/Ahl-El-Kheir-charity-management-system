-- Remove only the exact, unedited generic proposal drafts from title-only fallback rows.
-- Any HR-modified text or approved description is preserved. Role-specific drafts are stored separately.
UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'المدير العام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'قيادة الجمعية والإشراف على تحقيق أهدافها واعتماد القرارات الإدارية ضمن الصلاحيات المعتمدة.'
  AND responsibilities = '• متابعة الخطط والأولويات والأداء العام للجمعية.\n• الإشراف على تنفيذ القرارات الإدارية ضمن الصلاحيات المعتمدة.\n• دعم الحوكمة والمساءلة وحماية موارد الجمعية.\n• احترام فصل الصلاحيات المالية والتشغيلية وعدم تجاوز مسارات الاعتماد.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'نائب المدير العام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'تنسيق المتابعة الإدارية وتنفيذ المهام المفوضة من المدير العام ضمن حدود التفويض المعتمد.'
  AND responsibilities = '• متابعة تنفيذ الخطط والقرارات المفوضة.\n• تنسيق العمل بين الوحدات عند التكليف.\n• رفع المسائل الجوهرية إلى المدير العام.\n• ممارسة الصلاحيات المفوضة فقط وعدم افتراض صلاحيات اعتماد غير منصوص عليها.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'المدير المالي'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'الإشراف على سلامة العمليات المالية والمحاسبية والرقابة والتسويات والإقفال المالي وفق الصلاحيات المعتمدة.'
  AND responsibilities = '• مراجعة العمليات المالية والمحاسبية ضمن الصلاحيات.\n• الإشراف على الخزينة والتسويات والمطابقات والتقارير المالية.\n• متابعة الإقفال المالي للمشروعات والتحقق من المستندات والأرصدة.\n• ضمان التوثيق والفصل بين الطلب التشغيلي والمراجعة والاعتماد والترحيل.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'مدير الموارد البشرية'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'إدارة عمليات الموارد البشرية وسجلات الموظفين والتوظيف والعقود والحضور والإجازات والرواتب بالتنسيق مع الجهات المختصة.'
  AND responsibilities = '• صيانة سجلات الموظفين وبيانات التوظيف والعقود.\n• تنسيق التوظيف والحضور والإجازات والرواتب وإجراءات الموارد البشرية.\n• متابعة تطبيق السياسات والإجراءات ومتطلبات الامتثال ذات الصلة.\n• التنسيق مع المالية دون تجاوز صلاحياتها المحاسبية أو المالية.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'مدير النظام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'إدارة الجوانب التقنية وحسابات المستخدمين والصلاحيات والإعدادات وفق ضوابط أمن المعلومات والتفويض المعتمد.'
  AND responsibilities = '• إدارة حسابات المستخدمين والإعدادات والصلاحيات المصرح بها.\n• دعم استمرارية النظام وسلامة البيانات وسريتها.\n• توثيق التغييرات التقنية ومتابعة المشكلات.\n• عدم منح صلاحيات أو إجراء تغييرات خارج التفويض والسياسات المعتمدة.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'مشرف'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'تنفيذ ومتابعة الأعمال التشغيلية المرتبطة بنطاق الإشراف المسند وفق إجراءات الجمعية.'
  AND responsibilities = '• متابعة السجلات والطلبات التشغيلية ضمن نطاق المسؤولية المحدد.\n• توثيق المتابعات والنتائج والتواصل وفق الإجراءات.\n• تصعيد الحالات الاستثنائية إلى الجهة المختصة.\n• لا تشمل الوظيفة اعتماداً مالياً أو ترحيلاً محاسبياً ما لم يوجد تفويض مستقل وصريح.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'مشرف مشروع'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'متابعة تنفيذ المشروع المسند وتوثيق التقدم والأدلة التشغيلية ورفع المسائل التي تتطلب قراراً أو تدخلاً.'
  AND responsibilities = '• متابعة الأنشطة والمخرجات والجدول التشغيلي للمشروع المسند.\n• تحديث معلومات التقدم وإرفاق الأدلة التشغيلية المسموح بها.\n• توثيق المخاطر والعوائق ورفعها للمدير المختص.\n• لا تمنح الوظيفة سلطة الإقفال المالي أو الاعتماد المحاسبي.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'مندوب استرجاع الكفلاء'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'متابعة التواصل التشغيلي مع الكفلاء غير النشطين وتوثيق النتائج ورفع الحالات التي تحتاج إلى تدخل.'
  AND responsibilities = '• تنفيذ اتصالات المتابعة مع الكفلاء وفق الإجراءات المعتمدة.\n• تسجيل محاولات التواصل والنتائج بدقة.\n• إحالة الحالات التي تتطلب تغيير بيانات أو قراراً إلى الجهة المخولة.\n• عدم تقديم التزامات مالية أو وعود غير مصرح بها.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'محاسب'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'تنفيذ الأعمال المحاسبية المصرح بها وتوثيق المستندات والقيود والمطابقات وإعداد البيانات المطلوبة تحت الإشراف المالي.'
  AND responsibilities = '• إعداد وتسجيل المعاملات المحاسبية المصرح بها استناداً إلى مستندات مؤيدة.\n• دعم المطابقات والتسويات وإعداد التقارير المطلوبة.\n• حفظ الأدلة والسجلات وفق ضوابط التدقيق.\n• إحالة الاستثناءات والقرارات المالية إلى المدير المالي وعدم تجاوز حدود الصلاحية.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'موظف العلاقات العامة والإعلام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'دعم التواصل المؤسسي والعلاقات العامة وإعداد المواد الإعلامية المعتمدة مع حماية خصوصية المستفيدين والبيانات.'
  AND responsibilities = '• دعم إعداد ونشر الرسائل والمواد الإعلامية التي جرى اعتمادها.\n• تنسيق أنشطة العلاقات العامة والتواصل مع الجهات ذات الصلة وفق التكليف.\n• حماية البيانات الشخصية وصور المستفيدين وعدم النشر دون الإذن المطلوب.\n• الالتزام بسياسات الحماية والسرية والهوية المؤسسية.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'أخصائية شؤون الأمهات'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'مسودة مقترحة: دعم متابعة شؤون الأمهات وتنسيق الملفات والخدمات والإحالات ذات الصلة مع الحفاظ على السرية والحماية. يجب على HR تأكيد نطاق الوظيفة الفعلي.'
  AND responsibilities = '• مسودة تحتاج تأكيداً من HR: متابعة الملفات والتواصل والتنسيق والإحالات المتعلقة بشؤون الأمهات ضمن نطاق البرنامج.\n• الحفاظ على سرية المعلومات واحترام المستفيدات.\n• الالتزام بضوابط الحماية والتصعيد عند وجود مخاوف.\n• يجب تأكيد المسؤوليات الفعلية والمؤهلات مع المشرف قبل الاعتماد.';

UPDATE hr_job_description_templates
SET role_purpose = NULL, responsibilities = NULL
WHERE position_title = 'موظف'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND role_purpose = 'وصف عام مؤقت فقط: تنفيذ المهام التشغيلية التي يحددها القسم والمشرف المباشر كتابةً. يجب تخصيص المسؤوليات والصلاحيات لهذا الموظف قبل اعتماد الوصف.'
  AND responsibilities = '• يحدد المشرف المباشر المهام اليومية والنتائج المتوقعة كتابةً.\n• الالتزام بسياسات الجمعية والسرية والحماية والسلامة.\n• توثيق العمل ورفع المخاطر أو التعارضات إلى المشرف.\n• هذه صياغة عامة غير كافية للاعتماد النهائي؛ يجب تخصيص المهام والصلاحيات قبل اعتماد الوصف.';

-- Remove the exact generic qualifications placeholder only; retain any HR-edited qualifications.
UPDATE hr_job_description_templates
SET qualifications = NULL
WHERE system_role_code = ''
  AND description_status = 'needs_review'
  AND qualifications = 'مسودة مقترحة غير معتمدة: تُحدد المؤهلات العلمية والخبرة والمهارات المطلوبة بالتناسب مع المهام الفعلية وبمراجعة الموارد البشرية والمشرف المختص. لا تُعد هذه العبارة شرط توظيف رسمياً ولا تستبدل متطلبات قانونية أو مهنية واجبة.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'المدير العام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'يُحدد وفق الهيكل التنظيمي والحوكمة المعتمدة؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'نائب المدير العام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المدير العام (مقترح وفق التفويض؛ يحتاج تأكيداً من HR).';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'المدير المالي'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المدير العام / الجهة الإدارية المخولة وفق الهيكل المعتمد؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'مدير الموارد البشرية'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المدير العام / الجهة الإدارية المخولة وفق الهيكل المعتمد؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'مدير النظام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'يُحدد وفق الهيكل والتفويض التقني المعتمد؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'مشرف'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المسؤول التشغيلي المباشر وفق نطاق الإشراف؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'مشرف مشروع'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'مدير المشروع / مدير المشروعات وفق التكليف؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'مندوب استرجاع الكفلاء'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المسؤول الإداري عن متابعة الكفلاء؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'محاسب'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المدير المالي أو المسؤول المحاسبي المفوض؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'موظف العلاقات العامة والإعلام'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المسؤول الإداري المكلف بالعلاقات العامة والإعلام؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'أخصائية شؤون الأمهات'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'المشرف المباشر للبرنامج؛ يحتاج تأكيداً من HR.';

UPDATE hr_job_description_templates
SET reporting_to = NULL
WHERE position_title = 'موظف'
  AND system_role_code = ''
  AND description_status = 'needs_review'
  AND reporting_to = 'يحدد حسب القسم والتكليف الفعلي؛ يحتاج تأكيداً من HR.';

