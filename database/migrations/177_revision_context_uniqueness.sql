ALTER TABLE scheme_workbooks
    DROP INDEX uk_scheme_workbook_context,
    ADD UNIQUE KEY uk_scheme_workbook_revision_context (academic_year_term_id, academic_year_class_stream_learning_area_id, teacher_id, revision_number);
