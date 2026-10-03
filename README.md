# Ahl El Kheir Charity Management System

This repository contains the existing Ahl El Kheir charity management system.

## Developer entry point

Read the canonical documentation in this order:

1. docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md
2. docs/AHL_EL_KHEIR_ARCHITECTURE_AND_DATA_MODEL.md
3. docs/AHL_EL_KHEIR_ROLES_AND_WORKFLOWS.md
4. docs/AHL_EL_KHEIR_EMPLOYEE_TASKS_AND_USER_GUIDE.md
5. docs/AHL_EL_KHEIR_OPERATIONS_SECURITY.md
6. docs/AHL_EL_KHEIR_DOCUMENTATION_INDEX.md

Then read the relevant domain evidence under docs/ and inspect the actual source/schema before making changes.

## Development baseline

Working directory: D:\xampp\htdocs\AhlElKheir
Local URL: http://localhost:8081/AhlElKheir/
Database: ahl_el_kheir
Branch: main

Technology: procedural PHP 8.2+, PDO, MariaDB/MySQL, Bootstrap 5.3 RTL, Vanilla JS, Font Awesome 6 and Cairo.

## Non-negotiable engineering rules

Preserve the existing system. Do not rebuild it.
Use the repository source/schema as truth.
Use migrations for structural DB changes; never runtime DDL.
Do not introduce triggers, views, stored procedures, functions or events.
Keep server-side authorization authoritative.
Do not use destructive Git commands or force-push.
Work on main.

## Current documentation status

The new canonical analysis and employee operating documentation was established on 2026-10-03. Historical audit/checkpoint documents remain in the repository as evidence and are governed by docs/AHL_EL_KHEIR_DOCUMENTATION_INDEX.md.
