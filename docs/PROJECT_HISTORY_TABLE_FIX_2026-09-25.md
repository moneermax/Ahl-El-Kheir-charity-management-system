# Project History Table Fix — 2026-09-25

## Scope

Existing project view only. No new project was created and no database schema change was made.

## Change

The **سجل التغييرات** section on `modules/projects/view.php` now presents the existing project status-history records as a responsive Bootstrap table instead of scattered text blocks.

Columns:

- التاريخ والوقت
- المستخدم
- الحالة السابقة
- الحالة الجديدة
- السبب / الملاحظات

## Preservation

- The existing `project_status_history` query and records are unchanged.
- No tables, columns, statuses, relationships, migrations, triggers, views, stored procedures, functions, or scheduled events were added.
- Project approval, launch, operational, expense, document, accounting, and notification workflows are unchanged.
- The change is presentation-only and uses the existing history records already loaded by `view.php`.
