-- Project closure reason used by the Projects Manager closure workflow.
-- The application writes the selected POST field `closure_reason` to
-- `project_lifecycle.close_reason` when the project is formally closed.
-- No triggers or views.

ALTER TABLE project_lifecycle
    ADD COLUMN IF NOT EXISTS close_reason VARCHAR(64) NULL;
