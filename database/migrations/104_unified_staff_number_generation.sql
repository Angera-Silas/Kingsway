-- Unified staff number generation.
-- Mirrors the admission-number pattern: configurable format in school_settings,
-- atomic sequence in number_sequences, collision-safe DB function.

-- ── 1. School-settings rows ────────────────────────────────────────────────
INSERT INTO school_settings (setting_key, setting_value, label)
SELECT 'staff_no_format', 'KPST#{seq}', 'Staff Number Format'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key = 'staff_no_format');

INSERT INTO school_settings (setting_key, setting_value, label)
SELECT 'staff_no_start_sequence', '1', 'First staff sequence number'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM school_settings WHERE setting_key = 'staff_no_start_sequence');

-- ── 2. Seed number_sequences from existing staff ───────────────────────────
-- Compute current max sequence from legacy KWPS### numbers so new numbers
-- continue without collision.
SET @max_seq = (
    SELECT COALESCE(MAX(CAST(SUBSTRING(staff_no, 5) AS UNSIGNED)), 0)
    FROM staff
    WHERE staff_no REGEXP '^KWPS[0-9]+$'
);
INSERT INTO number_sequences (context, seq_year, last_seq)
SELECT 'staff_number', 0, GREATEST(@max_seq, 0)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM number_sequences WHERE context = 'staff_number' AND seq_year = 0);

-- ── 3. DB function ────────────────────────────────────────────────────────
DROP FUNCTION IF EXISTS fn_generate_staff_no;
DELIMITER $$
CREATE FUNCTION fn_generate_staff_no() RETURNS VARCHAR(40)
NOT DETERMINISTIC MODIFIES SQL DATA
BEGIN
    DECLARE v_seq INT;
    DECLARE v_format VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_start INT DEFAULT 1;
    DECLARE v_seq_str VARCHAR(40);
    DECLARE v_pad_str VARCHAR(10);
    DECLARE v_pad INT DEFAULT 0;
    DECLARE v_result VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    -- Read format from school_settings
    SELECT setting_value INTO v_format FROM school_settings
    WHERE setting_key = 'staff_no_format' LIMIT 1;
    IF v_format IS NULL OR v_format = '' THEN SET v_format = 'KPST#{seq}'; END IF;

    -- Read starting sequence
    SELECT CAST(setting_value AS UNSIGNED) INTO v_start FROM school_settings
    WHERE setting_key = 'staff_no_start_sequence' LIMIT 1;
    IF v_start IS NULL OR v_start < 1 THEN SET v_start = 1; END IF;

    -- Staff numbers are always global (no {year} token).
    -- Atomically increment the sequence with row-level lock.
    SELECT last_seq INTO v_seq FROM number_sequences
    WHERE context = 'staff_number' AND seq_year = 0 FOR UPDATE;
    IF v_seq IS NULL THEN
        SET v_seq = v_start;
        INSERT INTO number_sequences (context, seq_year, last_seq)
        VALUES ('staff_number', 0, v_seq);
    ELSE
        SET v_seq = v_seq + 1;
        UPDATE number_sequences SET last_seq = v_seq
        WHERE context = 'staff_number' AND seq_year = 0;
    END IF;

    -- Token substitution
    SET v_result = v_format;
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
