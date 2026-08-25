-- Migration 162: retain provenance for every imported CBC curriculum dimension.
-- The raw extraction is the source of truth; these columns make re-imports
-- auditable without changing the normalized curriculum relationships.

ALTER TABLE sub_strands
    ADD COLUMN source_document varchar(255) NULL AFTER source_subject,
    ADD COLUMN source_page varchar(20) NULL AFTER source_document;

ALTER TABLE learning_outcomes
    ADD COLUMN source_document varchar(255) NULL AFTER grade_level,
    ADD COLUMN source_page varchar(20) NULL AFTER source_document;

CREATE INDEX idx_lo_source ON learning_outcomes (source_document, source_page);
