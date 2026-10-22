SET NAMES utf8mb4;

-- Apply after the existing job-description seed/proposal migrations. Add the organization-wide
-- baseline to active templates without duplicating either item. Any changed description is
-- returned to needs_review because its qualifications now require internal confirmation.
UPDATE hr_job_description_templates
SET qualifications = CONCAT('• الحد الأدنى للمؤهل الأكاديمي: شهادة الثانوية العامة أو ما يعادلها.\n• معرفة أساسية باستخدام الحاسوب وبرامج المكتب.', CASE WHEN NULLIF(TRIM(qualifications), '') IS NULL THEN '' ELSE CONCAT('\n', TRIM(qualifications)) END), description_status = 'needs_review', updated_at = CURRENT_TIMESTAMP
WHERE is_active = 1
  AND description_status <> 'approved'
  AND COALESCE(qualifications, '') NOT LIKE '%الحد الأدنى للمؤهل الأكاديمي: شهادة الثانوية العامة أو ما يعادلها.%'
  AND COALESCE(qualifications, '') NOT LIKE '%معرفة أساسية باستخدام الحاسوب وبرامج المكتب.%';
