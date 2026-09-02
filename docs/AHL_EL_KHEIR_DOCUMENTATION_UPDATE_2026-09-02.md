# Ahl El Kheir Charity Management System
## Documentation Update — 2026-09-02

**Repository:** `moneermax/Ahl-El-Kheir-charity-management-system`  
**Authoritative branch:** `main`  
**Purpose:** Record implementation changes that occurred after the consolidated repository audit and keep the system documentation synchronized with verified repository behavior.

---

# 1. Repository Continuity Check

The repository was re-checked after the documentation/development branch work was merged into `main`.

Verified on 2026-09-02:

- `main` is the repository default branch.
- The repository remains accessible with read/write permissions.
- The previously developed messaging soft-delete implementation is present on `main`.
- The documentation files created during the repository-audit work are present on `main`.
- The separate `documentation/system-analysis-v1` branch still exists as a branch reference, but `main` now contains the relevant implementation and documentation work. It should be treated as historical/development context unless deliberately reused.

The current authoritative rule for future work is:

`Inspect main → make the smallest justified change → test → review → commit`

No assumption should be made that an older development branch is still the active source of truth.

---

# 2. Current Documentation Set

The repository currently contains the following major documentation artifacts:

- `docs/AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` — comprehensive system analysis, functional specification, technical architecture, and developer/AI handoff.
- `docs/AHL_EL_KHEIR_COMPLETE_REPOSITORY_AUDIT.md` — consolidated repository audit and cross-module implementation review.
- `docs/AHL_EL_KHEIR_REPOSITORY_AUDIT.md` — Phase 1 repository implementation audit and correction register.
- `docs/AHL_EL_KHEIR_DOCUMENTATION_UPDATE_2026-09-02.md` — this chronological update record.

The older audit documents intentionally retain their original audit context and baseline references. They should not be rewritten retroactively merely because later implementation work occurred. New verified changes should be recorded in chronological update records and, when appropriate, incorporated into the next major revision of the system analysis.

---

# 3. Messaging System — Single Message Deletion

## 3.1 Status

**VERIFIED / IMPLEMENTED**

The messaging subsystem now supports deletion of an individual message from a conversation.

This is distinct from the pre-existing attachment deletion feature.

## 3.2 Authorization rule

The confirmed organizational rule is:

- A normal user may delete only a message that they personally sent.
- The System Administrator may delete any message that the administrator is authorized to access.
- Server-side authorization is authoritative; the presence or absence of a Delete button is only a UI convenience.

The deletion endpoint explicitly checks message access and deletion permission before changing the record.

## 3.3 Soft-delete architecture

Messages are **not physically deleted**.

The `messages` table now contains:

- `deleted_at` — timestamp indicating that the message was deleted.
- `deleted_by` — user who performed the deletion.

This design is intentional because the messaging schema uses `parent_id` to maintain reply relationships. A physical deletion could activate database cascade behavior and unintentionally remove replies/read records associated with a conversation.

The migration is stored at:

`database/migrations/2026-09-01_add_message_soft_delete.sql`

The migration adds indexes for deletion fields and links `deleted_by` to `users.id` with `ON DELETE SET NULL`.

## 3.4 Conversation behavior

When a message is deleted:

- The original message row remains in the database.
- The original body is no longer presented to the user.
- The UI displays `تم حذف هذه الرسالة`.
- The message remains in its original position in the conversation.
- Existing replies remain intact.
- A deleted reply does not delete neighboring replies.
- The delete control is removed after successful deletion.

This preserves conversation structure while preventing the deleted message content from being displayed normally.

## 3.5 Attachments

Attachments belonging to a deleted message are removed from the messaging attachment records and their physical stored files are cleaned up.

This prevents a deleted message from retaining visible/downloadable attachments through the normal messaging interface.

## 3.6 UI implementation

The client-side implementation is provided by:

`assets/js/messaging_message_delete.js`

The script:

- discovers root messages and replies in the current thread;
- requests server-side deletion state;
- adds Delete controls only when the server says the user may delete;
- uses SweetAlert2 confirmation when available;
- falls back to the browser confirmation dialog if SweetAlert2 is unavailable;
- updates the message in place after successful deletion;
- clears displayed attachments;
- preserves the surrounding conversation.

The deletion endpoint is:

`modules/messages/delete_message.php`

It supports state lookup for the current thread and a CSRF-protected POST action for deletion.

## 3.7 Inbox and unread behavior

Deleted messages are prevented from leaking their original body through messaging previews.

Message previews use:

`تم حذف هذه الرسالة`

when the latest represented message has been deleted.

Deleted messages are excluded from unread-message counting so that a deleted message does not continue to appear as an unread actionable item.

## 3.8 Auditability

The deletion service records the deletion event through application logging and stores the deleting user and timestamp in the message record.

This provides a basic historical trace without destroying the underlying conversation record.

---

# 4. Messaging Security Requirements

The following controls are now part of the verified message-deletion implementation:

1. Authentication is required.
2. CSRF validation is required for deletion POST requests.
3. Message visibility is checked server-side.
4. Message deletion ownership/admin authorization is checked server-side.
5. A message cannot be successfully deleted twice.
6. Message IDs are validated as positive integers.
7. The client does not determine authorization; it only reflects the server's authorization result.
8. Deleted content is not returned as the normal message body in messaging previews.

---

# 5. Updated Messaging Lifecycle

The messaging model should now be documented conceptually as:

```text
Message Created
      │
      ├── Active Message
      │      │
      │      ├── Reply / Conversation Thread
      │      │
      │      └── Attachment(s)
      │
      └── Soft Deleted
             │
             ├── deleted_at recorded
             ├── deleted_by recorded
             ├── body hidden from normal display
             ├── message position preserved
             ├── replies preserved
             └── attachments cleaned up
```

The important architectural distinction is:

`Delete Message ≠ DELETE database row`

For this system, deletion is a controlled historical state change.

---

# 6. Documentation Maintenance Rule Going Forward

The main system-analysis document remains the long-lived architectural reference. The audit documents remain evidence records for the audits in which they were produced. Chronological update documents such as this one record verified changes after those audits.

When a future feature materially changes an existing documented workflow, the next documentation revision should update the relevant section of `AHL_EL_KHEIR_SYSTEM_ANALYSIS.md` and add a dated change record rather than silently changing historical audit conclusions.

Future documentation updates must continue to distinguish:

- **VERIFIED** — confirmed in current repository implementation.
- **IMPLEMENTED-PARTIAL** — implementation exists but broader verification remains.
- **BUSINESS RULE** — explicitly agreed organizational behavior.
- **INFERRED** — interpretation requiring confirmation.
- **LEGACY/UNVERIFIED** — historical or uncertain implementation.
- **OPEN DECISION** — organizational/architectural decision still required.

---

# 7. Current State After This Update

The repository and documentation are synchronized at the level verified during this review.

The messaging system now has two distinct deletion concepts:

- **Attachment deletion** — removes an individual attachment.
- **Message deletion** — soft-deletes the individual message while preserving conversation structure.

The latter is now a verified implemented feature and should no longer be described as a future/open messaging requirement.
