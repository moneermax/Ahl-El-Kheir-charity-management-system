# Projects View Runtime Fixes — 2026-09-25

## Scope
Existing `PRJ-0010` post-launch Project Supervisor testing. No new project was created and no database schema change was made.

## Fixes

1. The shared bottom Back control is inserted immediately before `.app-footer`, while the top control remains at the top of the page. The helper remains idempotent and does not create duplicates.
2. Project success feedback is kept with the relevant Projects section instead of leaving the user at the page top. Document-upload success feedback is moved into the documents section; expense success feedback returns the user to the expenses section.
3. Project document links are normalized from their raw relative `href` to `APP_URL . 'modules/projects/serve_project_document.php?id=...'` on page load. This explicitly fixes the browser resolution problem that produced `/modules/projects/modules/projects/serve_project_document.php`.
4. The Project Supervisor's **الوثائق والتصاريح والشهادات** records are presented as a compact table with one record per row and the existing View/Edit/Delete/Verify controls together in the actions column. Receipt records remain excluded from this general documents section because receipts belong to **المصروفات**.

## Root cause of document View failure

The protected document endpoint itself exists at:

`modules/projects/serve_project_document.php`

The Project View rendered its link as the relative path:

`modules/projects/serve_project_document.php?id=...`

Because the current page is already under `/modules/projects/`, the browser resolved that relative URL as:

`/modules/projects/modules/projects/serve_project_document.php?id=...`

The current footer-side Projects page enhancement now detects every project-document View link by its actual `href`, extracts the document ID, and replaces it with an application-root URL. This is a client-side normalization of the existing link and does not alter the document endpoint or permissions.

## Preservation

- No project approval or launch workflow was changed.
- No accounting event was added or changed.
- No tables, columns, statuses, relationships, triggers, views, stored procedures, functions, or scheduled events were added.
- Existing protected-document and draft-expense authorization rules remain in force.

## Verification target

After pulling the latest commit, hard-refresh `modules/projects/view.php?id=10` as the assigned Project Supervisor and verify:

- exactly two Back controls, with the second immediately above the footer;
- saving an expense does not leave the page at the top;
- uploading/editing a supporting document does not leave the page at the top;
- clicking **عرض** opens `modules/projects/serve_project_document.php?id=2` (or the corresponding record ID) rather than a duplicated `/modules/projects/modules/projects/` path;
- supporting documents appear in a single table row per record with their controls beside the record.
