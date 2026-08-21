-- Allow short global admission numbers such as KPS111, KPS112.
-- Formats containing {year} remain per-year sequences; formats without it
-- use one school-wide sequence so numbers do not reset every academic year.
INSERT INTO school_settings (setting_key, setting_value, label)
SELECT 'admission_no_start_sequence', '1', 'First admission sequence number'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key = 'admission_no_start_sequence');

DROP FUNCTION IF EXISTS fn_generate_admission_no;
DELIMITER $$
CREATE FUNCTION fn_generate_admission_no(p_year INT) RETURNS VARCHAR(40)
NOT DETERMINISTIC MODIFIES SQL DATA
BEGIN
    DECLARE v_seq INT;
    DECLARE v_format VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_scope_year INT;
    DECLARE v_start INT DEFAULT 1;
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_seq_str VARCHAR(40);
    DECLARE v_pad_str VARCHAR(10);
    DECLARE v_pad INT DEFAULT 0;
    DECLARE v_result VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    SELECT setting_value INTO v_format FROM school_settings
    WHERE setting_key = 'admission_no_format' LIMIT 1;
    IF v_format IS NULL OR v_format = '' THEN SET v_format = 'KPS{seq}'; END IF;

    SET v_scope_year = IF(LOCATE('{year}', v_format) > 0, p_year, 0);
    SELECT CAST(setting_value AS UNSIGNED) INTO v_start FROM school_settings
    WHERE setting_key = 'admission_no_start_sequence' LIMIT 1;
    IF v_start IS NULL OR v_start < 1 THEN SET v_start = 1; END IF;

    SELECT last_seq INTO v_seq FROM number_sequences
    WHERE context = 'student_admission' AND seq_year = v_scope_year FOR UPDATE;
    IF v_seq IS NULL THEN
        SET v_seq = v_start;
        INSERT INTO number_sequences (context, seq_year, last_seq)
        VALUES ('student_admission', v_scope_year, v_seq);
    ELSE
        SET v_seq = v_seq + 1;
        UPDATE number_sequences SET last_seq = v_seq
        WHERE context = 'student_admission' AND seq_year = v_scope_year;
    END IF;

    SET v_result = REPLACE(v_format, '{year}', CONVERT(p_year, CHAR) COLLATE utf8mb4_unicode_ci);
    IF LOCATE('{seq:', v_result) > 0 THEN
        SET v_pad_str = SUBSTRING(SUBSTRING_INDEX(v_result, '{seq:', -1), 1,
            LOCATE('}', SUBSTRING_INDEX(v_result, '{seq:', -1)) - 1);
        IF v_pad_str REGEXP '^[0-9]+$' THEN SET v_pad = CAST(v_pad_str AS UNSIGNED); END IF;
        IF v_pad < 1 THEN SET v_pad = 1; END IF;
        SET v_seq_str = LPAD(v_seq, v_pad, '0');
        SET v_result = REGEXP_REPLACE(v_result, '\\{seq:[0-9]+\\}', v_seq_str);
    ELSE
        SET v_result = REPLACE(v_result, '{seq}', CONVERT(v_seq, CHAR) COLLATE utf8mb4_unicode_ci);
    END IF;
    RETURN v_result;
END$$
DELIMITER ;
