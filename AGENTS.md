# Repository Agent Instructions — Ahl El Kheir

This file is mandatory for every AI-assisted session working in this repository. Read it before inspecting or changing project files.

## Work ownership: minimize user effort

**The AI assistant owns the repository work. The user should not have to act as the assistant's hands.**

The assistant must do as much as possible directly with available repository, GitHub, file, and code tools:
- Read and search source, schema, documentation, tests, and Git history directly.
- Trace workflows and root causes before proposing changes.
- Implement the smallest justified source, migration, test, and documentation changes directly when write tools are available.
- Review its own diffs and run every available syntax/static/focused check.
- Report exact files, commits, commands, and evidence.

The user should only be asked to perform actions that truly require their local environment or organizational authority, such as:
- Inspecting/running the local XAMPP + MariaDB application.
- Applying a reviewed migration to the local database.
- Performing browser acceptance that the assistant cannot access.
- Supplying a business/accounting decision that cannot be established from repository evidence.

Do not ask the user to copy/paste whole files, whole documents, large SQL dumps, or hundreds of lines of terminal output when repository tools can retrieve the information. Do not delegate repository search, file reading, coding, tests, or documentation updates to the user when available tools can do that work.

When local evidence is essential, first finish all remote/repository inspection. Then request **one small, read-only, narrowly scoped command** with bounded output. Prefer filtered summaries, line ranges, counts, or targeted query results. Never request an unfiltered dump. Explain why the requested evidence cannot be obtained directly.

Be honest about tool boundaries. Never claim to have accessed the user's local filesystem, browser, or database unless a tool actually did so. If repository write access is unavailable, explain the limitation and provide the smallest practical patch or command rather than turning the user into the implementation operator.

## New-session startup

At the beginning of every new session:
1. Read this file.
2. Read current versions from `main` of:
   - `docs/CHATGPT_SESSION_INDEX.md`
   - `docs/AHL_EL_KHEIR_MASTER_STATUS.md`
   - `docs/AHL_EL_KHEIR_MASTER_AUDIT.md`
   - `docs/CHATGPT_MASTER_CONTINUATION_PROMPT.md`
   - the relevant module continuation/work-plan document.
3. Determine the actual open task from current repository evidence. Do not assume an old chat summary is current.
4. Inspect relevant source/schema/docs directly before asking the user for anything.
5. Ask for local Git/database/browser evidence only if it is genuinely needed for the next step. Keep the command and requested output minimal.
6. Continue from the next verified step; do not repeat completed work without concrete regression evidence.

## Non-negotiable project safeguards

- Existing repository only: `moneermax/Ahl-El-Kheir-charity-management-system`. Never rebuild or create a replacement.
- `main` only. Never create branches.
- Never use destructive Git commands (`reset --hard`, `clean`, `restore`, force-push, or equivalent).
- Preserve all staged, unstaged, untracked, backup, receipt, and other user data. Never overwrite or discard it.
- Never pull/fetch/synchronize a local checkout until its current branch, HEAD, and working-tree state are understood.
- Do not guess tables, columns, statuses, permissions, workflows, accounting rules, or fixes.
- `database/ahl_el_kheir.sql` is schema truth. Inspect the current dump and live schema when required.
- Database changes require explicit dated migrations. Never run `CREATE`/`ALTER` at application runtime.
- No triggers, views, stored procedures, database functions, or events.
- Preserve procedural PHP architecture and existing module behavior. Do not modify unrelated modules.
- Inspect the exact root cause before fixing failures. Prefer the smallest justified change.
- Syntax-check relevant PHP; run focused tests and real local runtime/browser checks when required.
- Never claim success without actual verification.
- Update canonical and relevant module documentation after meaningful milestones.
- Commit directly to `main` only when the change is ready and the repository state is safe. Record exact commit hashes and verification evidence.

## User interaction standard

Use concise explanations. Give one focused next action at a time. Do not make the user repeat facts already available in current repository documentation or prior context. If an assumption is unsafe, challenge it and explain the evidence needed. Accuracy and preservation of financial/user data outrank speed.
