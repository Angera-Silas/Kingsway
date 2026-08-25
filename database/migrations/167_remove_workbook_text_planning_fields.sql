-- New workbooks no longer store delivery resources, assessment, or coverage
-- as text. Those are lesson-plan atomic records.
ALTER TABLE scheme_workbook_items
    DROP COLUMN resources,
    DROP COLUMN assessment_approach,
    DROP COLUMN expected_coverage_notes;
