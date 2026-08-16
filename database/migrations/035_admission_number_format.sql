-- 035_admission_number_format.sql
--
-- Introduces a single, configurable admission-number format so manual student
-- creation and the admission workflow produce consistent numbers.
--
-- * Adds `admission_no_format` to school_settings (default `KA-{year}-{seq:04}`).
--   Supported tokens:
--     {year}    -> 4-digit year
--     {seq}     -> per-year sequence, unpadded
--     {seq:NN}  -> per-year sequence, zero-padded to NN digits (e.g. {seq:04})
-- * Adds `number_sequences`, a per-year counter used by fn_generate_admission_no
--   so sequence values are collision-safe under concurrent transactions (the
--   previous implementation used a racy MAX()+1 per call).
-- * Rewrites fn_generate_admission_no to read the configured format and produce
--   the number. It is used by sp_register_applicant_as_student (admission
--   pipeline) and the manual student-create path (StudentsAPI::create).

-- 1. Setting seed (only if absent so existing installs keep their value)
INSERT INTO school_settings (setting_key, setting_value, label)
SELECT 'admission_no_format', 'KA-{year}-{seq:04}', 'Admission Number Format'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key = 'admission_no_format');

-- 2. Per-year, collision-safe sequence table
CREATE TABLE IF NOT EXISTS number_sequences (
    context  VARCHAR(50)  NOT NULL,
    seq_year INT          NOT NULL,
    last_seq INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (context, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Backfill the counter from existing students using the default KA-YYYY-NNNN
--    shape (no-op when the students table is empty). Uses GREATEST so an
--    existing counter is never overwritten by a smaller value.
INSERT INTO number_sequences (context, seq_year, last_seq)
SELECT 'student_admission',
       CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(admission_no, '-', 2), '-', -1) AS UNSIGNED) AS seq_year,
       MAX(CAST(SUBSTRING_INDEX(admission_no, '-', -1) AS UNSIGNED)) AS last_seq
FROM students
WHERE admission_no REGEXP '^[A-Z0-9]+-[0-9]{4}-[0-9]+$'
GROUP BY seq_year
ON DUPLICATE KEY UPDATE last_seq = GREATEST(number_sequences.last_seq, VALUES(last_seq));

-- 4. Rewrite fn_generate_admission_no
DELIMITER $$

DROP FUNCTION IF EXISTS `fn_generate_admission_no`$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_generate_admission_no`(`p_year` INT) RETURNS VARCHAR(40) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
NOT DETERMINISTIC
MODIFIES SQL DATA
BEGIN
    DECLARE v_seq INT;
    DECLARE v_format VARCHAR(100);
    DECLARE v_seq_str VARCHAR(40);
    DECLARE v_pad_str VARCHAR(10);
    DECLARE v_pad INT;
    DECLARE v_result VARCHAR(40);

    SELECT setting_value INTO v_format
    FROM school_settings
    WHERE setting_key = 'admission_no_format'
    LIMIT 1;

    IF v_format IS NULL OR v_format = '' THEN
        SET v_format = 'KA-{year}-{seq:04}';
    END IF;

    -- Atomic per-year sequence: row-locked under InnoDB, so concurrent calls
    -- never hand out the same number. The sequence lives in the same transaction
    -- as the caller, so a rollback also rolls the counter back (no gaps).
    INSERT INTO number_sequences (context, seq_year, last_seq)
    VALUES ('student_admission', p_year, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE last_seq = LAST_INSERT_ID(last_seq + 1);
    SELECT LAST_INSERT_ID() INTO v_seq;

    -- Substitute tokens (explicit COLLATE keeps every operand on the settings
    -- column collation; otherwise CAST() yields general_ci and REPLACE fails
    -- with an illegal collation mix).
    SET v_result = REPLACE(v_format, '{year}', CONVERT(p_year, CHAR) COLLATE utf8mb4_unicode_ci);

    -- {seq:NN} (padded) or {seq} (unpadded)
    IF LOCATE('{seq:', v_result) > 0 THEN
        SET v_pad_str = SUBSTRING(
            SUBSTRING_INDEX(v_result, '{seq:', -1),
            1,
            LOCATE('}', SUBSTRING_INDEX(v_result, '{seq:', -1)) - 1
        );
        IF v_pad_str REGEXP '^[0-9]+$' AND CAST(v_pad_str AS UNSIGNED) > 0 THEN
            SET v_pad = CAST(v_pad_str AS UNSIGNED);
        ELSE
            SET v_pad = 4;
        END IF;
        SET v_seq_str = LPAD(v_seq, v_pad, '0');
        SET v_result = REGEXP_REPLACE(v_result, '\\{seq:[0-9]+\\}', v_seq_str);
    ELSE
        SET v_result = REPLACE(v_result, '{seq}', CONVERT(v_seq, CHAR) COLLATE utf8mb4_unicode_ci);
    END IF;

    RETURN v_result;
END$$

DELIMITER ;
