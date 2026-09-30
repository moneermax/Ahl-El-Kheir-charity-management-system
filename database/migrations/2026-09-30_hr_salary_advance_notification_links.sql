-- Ahl El Kheir Charity Management System
-- Normalize legacy salary-advance FM notification links.
--
-- The FM review page was consolidated into salary_advance_processing.php.
-- Existing notifications created before that consolidation may still point
-- to the removed salary_advance_fm_review.php endpoint.

SET NAMES utf8mb4;

UPDATE notifications
SET link = REPLACE(
    link,
    'modules/hr/salary_advance_fm_review.php',
    'modules/hr/salary_advance_processing.php'
)
WHERE link LIKE '%modules/hr/salary_advance_fm_review.php%';
