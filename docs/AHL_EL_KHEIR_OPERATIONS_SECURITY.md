# Ahl El Kheir — Operations, Security and Maintenance

Status: Canonical operational reference
Baseline: 2026-10-03

## 1. Development environment

- Windows
- XAMPP / Apache
- PHP 8.2+
- MariaDB/MySQL
- Database: ahl_el_kheir
- Local URL: http://localhost:8081/AhlElKheir/

Use config/environment.php and PRODUCTION_PREPARATION.md for deployment-specific settings.

## 2. Security rules

- All protected pages require authentication.
- Page authorization must be enforced server-side.
- Record-level scope must be enforced server-side.
- Workflow-state transitions must be validated server-side.
- State-changing requests use POST and CSRF.
- File-serving routes require authentication, authorization and safe path handling.
- Search must respect record scope.
- UI visibility is not a security control.

## 3. Financial safety

Never:
- edit historical posted journals to repair a test;
- invent balancing entries;
- alter treasury balances directly;
- represent a receivable/control event as an expense;
- bypass FM/accounting approval;
- settle Fina through Ahl operating treasury;
- close a project with an unreconciled controlled balance;
- repeat a return/disbursement/settlement after its completion without a real business event.

## 4. Database safety

Schema changes belong in database/migrations/.

Runtime requests must not create/alter schema or create triggers/views/stored procedures/functions/events.

## 5. Git safety

Work on main. Do not create routine branches.

Never use:
- git reset --hard
- git clean
- git restore to discard work
- force push

Before synchronization, inspect status and preserve intentional local work.

## 6. Engineering method

Inspect -> Understand -> Design -> Implement -> Static review -> Runtime test -> Documentation -> Commit.

For financial changes additionally define:
business event, debit/credit, reference, duplicate protection, evidence and reconciliation.

## 7. Testing

Tests must be tied to a real risk and actual schema/workflow.

Use controlled fixtures when necessary. Do not rerun closed acceptance fixtures unless there is regression evidence.

Runtime claims must be based on actual runtime evidence, not source inspection alone.

## 8. Production preparation

Before production:
- secure environment values;
- disable development error display;
- verify HTTPS;
- verify file/storage permissions;
- apply migrations in order;
- verify backups/restoration;
- verify accounting opening balances;
- verify users/roles;
- verify uploads/receipts;
- verify no runtime DDL remains.

## 9. Maintenance

Do not delete or rewrite an apparently unused table/migration/fixture without determining whether it is:
- current runtime dependency;
- historical evidence;
- migration dependency;
- compatibility code.

Fina settlement tables are intentionally retained for current dependencies/history.

## 10. Evidence standard

An incident record should capture:
user/role, URL/action, record ID, workflow state, audit event, journal/reference if financial, notification result and exact error.

## 11. Current verified boundaries

- Projects Phase 5 controlled reconciliation/closure: runtime verified and closed.
- HR Salary Advance Stages 1–6: completed at their documented acceptance boundary.
- Fina core settlement model: accepted.
- Accounting/notification audits: completed at documented evidence boundaries.
- Development/test data remains non-production data unless explicitly designated.
