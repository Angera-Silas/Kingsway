-- 042_relocate_functional_state_and_drop_log_tables.sql
--
-- Completes the log-to-file migration:
--
-- 1. Adds api_tokens.revoked_at so API-token revocation state no longer lives
--    in the audit_logs ledger (AuthSessionService reads at.revoked_at now).
-- 2. Creates dedicated tables for functional HR workflow state that was
--    persisted inside audit_logs JSON rows (entity='staff_lifecycle_action',
--    entity='staff_promotion', entity='staff_appointment_approval'):
--      - staff_lifecycle_actions
--      - staff_promotions
--      - staff_appointment_approvals
--    All three are structurally empty in the live DB (the audit_logs rows were
--    BaseAPI request/read entries, not HR state), so no data migration is needed.
-- 3. Drops every database-side object that wrote to the pure-log tables
--    (system_events / audit_logs writers) so the DDL in step 4 is clean:
--      - views: vw_pending_sms, v_payment_security_alerts, v_user_security,
--        vw_failed_attempts_by_ip
--      - pure-log triggers (system_events / audit_logs writers)
--      - rewritten mixed triggers (functional side preserved)
--      - dead stored procedures that only wrote to log tables
--      - rewritten live stored procedures (log writes removed, functional
--        behaviour preserved): sp_onboard_student_enrollment,
--        sp_register_applicant_as_student, sp_auto_rollover_fee_structures,
--        sp_schedule_maintenance
-- 4. Drops the 10 pure-log tables:
--      audit_logs, login_attempts, system_events, sms_communications,
--      term_transition_log, academic_year_rollover_log, payment_webhooks_log,
--      maintenance_logs, vehicle_fuel_logs, system_error_logs
--
-- All logging now goes through App\API\Includes\FileLogger to logs/<env>/<category>.log.

-- ---------------------------------------------------------------------------
-- 1. API token revocation state
-- ---------------------------------------------------------------------------
ALTER TABLE api_tokens
    ADD COLUMN revoked_at DATETIME NULL DEFAULT NULL AFTER is_active;

-- Backfill the new column for tokens revoked before this migration; their
-- revocation timestamps currently live in the audit_logs ledger (action
-- 'token_revoke'), which is dropped below.
UPDATE api_tokens at
JOIN (
    SELECT entity_id, MAX(created_at) AS revoked_at
    FROM audit_logs
    WHERE action = 'token_revoke'
      AND entity = 'api_token'
      AND status = 'success'
    GROUP BY entity_id
) r ON r.entity_id = at.id
SET at.revoked_at = r.revoked_at
WHERE at.is_active = 0
  AND at.revoked_at IS NULL;

-- ---------------------------------------------------------------------------
-- 2. Dedicated HR workflow tables
-- ---------------------------------------------------------------------------

-- Staff lifecycle actions (legacy staff_lifecycle_actions shape, previously
-- carried in audit_logs details JSON).
CREATE TABLE staff_lifecycle_actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    effective_date DATE NULL,
    reason TEXT NULL,
    from_position VARCHAR(255) NULL,
    to_position VARCHAR(255) NULL,
    from_department_id INT UNSIGNED NULL,
    to_department_id INT UNSIGNED NULL,
    from_salary DECIMAL(10,2) NULL,
    to_salary DECIMAL(10,2) NULL,
    from_contract_type VARCHAR(50) NULL,
    to_contract_type VARCHAR(50) NULL,
    from_supervisor_id INT UNSIGNED NULL,
    to_supervisor_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    review_comment TEXT NULL,
    applied_at DATETIME NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_staff_id (staff_id),
    KEY idx_status (status),
    KEY idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Internal promotions (legacy staff_promotions shape, previously in audit_logs
-- JSON). New-staff appointments keep using staff_appointments.
CREATE TABLE staff_promotions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id INT UNSIGNED NOT NULL,
    promotion_type VARCHAR(50) NOT NULL DEFAULT 'transfer',
    is_temporary TINYINT(1) NOT NULL DEFAULT 0,
    from_position VARCHAR(255) NULL,
    to_position VARCHAR(255) NULL,
    from_department_id INT UNSIGNED NULL,
    to_department_id INT UNSIGNED NULL,
    from_salary DECIMAL(10,2) NULL,
    to_salary DECIMAL(10,2) NULL,
    from_contract_type VARCHAR(50) NULL,
    to_contract_type VARCHAR(50) NULL,
    from_supervisor_id INT UNSIGNED NULL,
    to_supervisor_id INT UNSIGNED NULL,
    effective_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reason TEXT NULL,
    letter_url VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    submitted_by INT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_reason TEXT NULL,
    payroll_adjustment_id INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_staff_id (staff_id),
    KEY idx_status (status),
    KEY idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval/history trail for internal and new-staff appointments (legacy
-- staff_appointment_approvals shape, previously in audit_logs JSON).
CREATE TABLE staff_appointment_approvals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_type VARCHAR(20) NOT NULL,
    appointment_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    remarks TEXT NULL,
    previous_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    changes JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointment (appointment_type, appointment_id),
    KEY idx_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Drop database-side dependencies on the log tables
-- ---------------------------------------------------------------------------

-- Views
DROP VIEW IF EXISTS vw_pending_sms;
DROP VIEW IF EXISTS v_payment_security_alerts;
DROP VIEW IF EXISTS v_user_security;
DROP VIEW IF EXISTS vw_failed_attempts_by_ip;

-- Pure-log triggers: system_events writers
DROP TRIGGER IF EXISTS after_enrollment_insert;
DROP TRIGGER IF EXISTS after_enrollment_update;
DROP TRIGGER IF EXISTS after_enrollment_delete;
DROP TRIGGER IF EXISTS trg_check_allocation_expiry;
DROP TRIGGER IF EXISTS trg_check_and_create_arrears;
DROP TRIGGER IF EXISTS trg_create_portfolio_on_promotion;
DROP TRIGGER IF EXISTS trg_emit_attendance_event;
DROP TRIGGER IF EXISTS trg_emit_low_stock_event;
DROP TRIGGER IF EXISTS trg_emit_payment_event;
DROP TRIGGER IF EXISTS trg_emit_student_status_event;
DROP TRIGGER IF EXISTS trg_log_arrears_creation;
DROP TRIGGER IF EXISTS trg_log_competency_assessment;
DROP TRIGGER IF EXISTS trg_log_conduct_recording;
DROP TRIGGER IF EXISTS trg_log_csl_participation;
DROP TRIGGER IF EXISTS trg_log_discount_waiver;
DROP TRIGGER IF EXISTS trg_log_material_approval;
DROP TRIGGER IF EXISTS trg_log_payment_transaction;
DROP TRIGGER IF EXISTS trg_log_pci_awareness;
DROP TRIGGER IF EXISTS trg_log_requisition_status;
DROP TRIGGER IF EXISTS trg_log_scheme_changes;
DROP TRIGGER IF EXISTS trg_log_settlement_plan;
DROP TRIGGER IF EXISTS trg_log_sms_delivery;
DROP TRIGGER IF EXISTS trg_log_value_demonstration;
DROP TRIGGER IF EXISTS trg_notify_performance_review_complete;

-- Pure-log triggers: audit_logs writers
DROP TRIGGER IF EXISTS trg_audit_insert;
DROP TRIGGER IF EXISTS trg_audit_update;
DROP TRIGGER IF EXISTS trg_audit_delete;

-- Rewritten mixed triggers: functional side preserved, log writes removed.
-- trg_check_maintenance_overdue still force-marks overdue equipment.
DROP TRIGGER IF EXISTS trg_check_maintenance_overdue;
DELIMITER $$
CREATE TRIGGER trg_check_maintenance_overdue
BEFORE UPDATE ON equipment_maintenance
FOR EACH ROW
BEGIN
    IF NEW.status = 'pending' AND NEW.next_maintenance_date < CURDATE() THEN
        SET NEW.status = 'overdue';
    END IF;
END$$
DELIMITER ;

-- trg_auto_create_notification still fans announcements out to staff users.
DROP TRIGGER IF EXISTS trg_auto_create_notification;
DELIMITER $$
CREATE TRIGGER trg_auto_create_notification
AFTER INSERT ON announcements_bulletin
FOR EACH ROW
BEGIN
    IF NEW.target_audience = 'all' OR NEW.target_audience = 'staff' THEN
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            priority,
            read_status,
            created_at
        )
        SELECT u.id,
            NEW.title,
            SUBSTRING(NEW.content, 1, 200),
            'announcement',
            CASE
                WHEN NEW.priority = 'normal' THEN 'medium'
                WHEN NEW.priority = 'critical' THEN 'high'
                ELSE NEW.priority
            END,
            'unread',
            NOW()
        FROM users u
        WHERE u.status = 'active' AND u.id <> NEW.published_by;
    END IF;
END$$
DELIMITER ;

-- trg_log_email_delivery still stamps last_failed_email on external
-- institutions when a delivery fails; the system_events write is removed.
DROP TRIGGER IF EXISTS trg_log_email_delivery;
DELIMITER $$
CREATE TRIGGER trg_log_email_delivery
AFTER UPDATE ON external_emails
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        IF NEW.status = 'failed' THEN
            UPDATE external_institutions
            SET last_failed_email = NOW()
            WHERE id = NEW.institution_id;
        END IF;
    END IF;
END$$
DELIMITER ;

-- trg_update_obligation_on_payment still moves student fee obligations to
-- paid/partial once confirmed payments cover the term; system_events removed.
DROP TRIGGER IF EXISTS trg_update_obligation_on_payment;
DELIMITER $$
CREATE TRIGGER trg_update_obligation_on_payment
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    UPDATE student_fee_obligations sfo
    JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
    JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
    SET sfo.status = CASE
        WHEN (COALESCE((
                SELECT SUM(p.amount) FROM payments p
                WHERE p.student_id = NEW.student_id
                  AND p.status = 'confirmed'
                  AND p.payment_date >= ayt.opening_date
                  AND p.payment_date <= ayt.closing_date
            ), 0) + COALESCE(sfo.sponsored_waiver_amount, 0)) >= sfo.amount_due THEN 'paid'
        WHEN COALESCE((
                SELECT SUM(p.amount) FROM payments p
                WHERE p.student_id = NEW.student_id
                  AND p.status = 'confirmed'
                  AND p.payment_date >= ayt.opening_date
                  AND p.payment_date <= ayt.closing_date
            ), 0) > 0 THEN 'partial'
        ELSE sfo.status
    END
    WHERE sfo.student_academic_enrollment_id IN (
        SELECT id FROM student_academic_enrollments
        WHERE student_id = NEW.student_id AND enrollment_status = 'active'
    )
      AND NEW.payment_date >= ayt.opening_date
      AND NEW.payment_date <= ayt.closing_date
      AND sfo.status IN ('pending', 'partial');
END$$
DELIMITER ;

-- Dead stored procedures that only wrote to log tables (0 PHP / trigger /
-- event callers, or already re-pointed to FileLogger):
DROP PROCEDURE IF EXISTS sp_allocate_payment;
DROP PROCEDURE IF EXISTS sp_assign_role;
DROP PROCEDURE IF EXISTS sp_assign_staff_type_and_category;
DROP PROCEDURE IF EXISTS sp_broadcast_notification;
DROP PROCEDURE IF EXISTS sp_calculate_payroll_for_staff;
DROP PROCEDURE IF EXISTS sp_cleanup_failed_attempts;
DROP PROCEDURE IF EXISTS sp_complete_maintenance;
DROP PROCEDURE IF EXISTS sp_create_arrears_record;
DROP PROCEDURE IF EXISTS sp_create_user_session;
DROP PROCEDURE IF EXISTS sp_deny_permission;
DROP PROCEDURE IF EXISTS sp_end_user_session;
DROP PROCEDURE IF EXISTS sp_generate_api_token;
DROP PROCEDURE IF EXISTS sp_generate_performance_rating;
DROP PROCEDURE IF EXISTS sp_grant_permission;
DROP PROCEDURE IF EXISTS sp_issue_allocation;
DROP PROCEDURE IF EXISTS sp_process_monthly_payroll;
DROP PROCEDURE IF EXISTS sp_process_requisition;
DROP PROCEDURE IF EXISTS sp_record_food_consumption;
DROP PROCEDURE IF EXISTS sp_record_login_attempt;
DROP PROCEDURE IF EXISTS sp_return_allocation;
DROP PROCEDURE IF EXISTS sp_revoke_permission;
DROP PROCEDURE IF EXISTS sp_revoke_role;
DROP PROCEDURE IF EXISTS sp_schedule_discipline_hearing;
DROP PROCEDURE IF EXISTS sp_send_announcement;
DROP PROCEDURE IF EXISTS sp_send_external_email;
DROP PROCEDURE IF EXISTS sp_send_internal_message;
DROP PROCEDURE IF EXISTS sp_send_sms_to_parents;
DROP PROCEDURE IF EXISTS sp_unlock_user_account;
DROP PROCEDURE IF EXISTS sp_update_kpi_achievement;

-- Rewritten live stored procedures: functional behaviour preserved, the log
-- write is removed so the procedure no longer touches a dropped table.
-- sp_register_applicant_as_student: applicant -> person+student + parent link
-- + application status flip; system_events write removed.
DROP PROCEDURE IF EXISTS sp_register_applicant_as_student;
DELIMITER $$
CREATE PROCEDURE `sp_register_applicant_as_student`(IN `p_application_id` INT UNSIGNED, IN `p_operator_id` INT UNSIGNED, IN `p_student_type_id` INT UNSIGNED, OUT `p_student_id` INT UNSIGNED, OUT `p_admission_no` VARCHAR(20))
BEGIN
    DECLARE v_applicant_name VARCHAR(100);
    DECLARE v_first_name VARCHAR(50);
    DECLARE v_last_name VARCHAR(50);
    DECLARE v_dob DATE;
    DECLARE v_gender ENUM('male','female','other');
    DECLARE v_parent_id INT UNSIGNED;
    DECLARE v_person_id INT UNSIGNED;
    DECLARE v_application_no VARCHAR(20);
    DECLARE v_year INT;

    SELECT applicant_name, date_of_birth, gender, parent_id, application_no
      INTO v_applicant_name, v_dob, v_gender, v_parent_id, v_application_no
    FROM admission_applications
    WHERE id = p_application_id
      AND status <> 'cancelled'
    LIMIT 1;

    IF v_applicant_name IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Application not found or cancelled';
    END IF;

    IF EXISTS (SELECT 1 FROM students WHERE application_id = p_application_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Application already registered as a student';
    END IF;

    SET v_first_name = TRIM(SUBSTRING_INDEX(v_applicant_name, ' ', 1));
    SET v_last_name = TRIM(SUBSTRING_INDEX(v_applicant_name, ' ', -1));
    IF v_last_name = v_first_name THEN
        SET v_last_name = NULL;
    END IF;

    INSERT INTO persons (id, first_name, middle_name, last_name, dob, gender)
    SELECT COALESCE(MAX(id), 0) + 1, v_first_name, NULL, v_last_name, v_dob, v_gender
    FROM persons;
    SET v_person_id = (SELECT MAX(id) FROM persons);

    SET v_year = YEAR(CURDATE());
    SET p_admission_no = fn_generate_admission_no(v_year);

    INSERT INTO students (id, person_id, admission_no, status, student_type_id, application_id, admission_date, created_at, updated_at)
    SELECT COALESCE(MAX(id), 0) + 1, v_person_id, p_admission_no, 'active', p_student_type_id, p_application_id, CURDATE(), NOW(), NOW()
    FROM students;
    SET p_student_id = (SELECT MAX(id) FROM students);

    IF v_parent_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM student_parents WHERE student_id = p_student_id AND parent_id = v_parent_id) THEN
        INSERT INTO student_parents (student_id, parent_id, relationship, is_primary_contact, is_emergency_contact)
        VALUES (p_student_id, v_parent_id, 'parent', 1, 1);
    END IF;

    UPDATE admission_applications
       SET status = 'enrolled',
           enrolled_student_id = p_student_id,
           enrolled_at = COALESCE(enrolled_at, NOW()),
           updated_at = NOW()
     WHERE id = p_application_id;

    SELECT p_student_id AS student_id, p_admission_no AS admission_no;
END$$
DELIMITER ;

-- sp_onboard_student_enrollment: seeds learning areas, fee obligations, dormitory
-- for boarders; system_events write removed.
DROP PROCEDURE IF EXISTS sp_onboard_student_enrollment;
DELIMITER $$
CREATE PROCEDURE `sp_onboard_student_enrollment`(IN `p_enrollment_id` INT UNSIGNED, IN `p_operator_id` INT UNSIGNED, OUT `p_obligations_generated` INT)
BEGIN
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_academic_year_class_id INT UNSIGNED;
    DECLARE v_class_id INT UNSIGNED;
    DECLARE v_class_grade VARCHAR(20);
    DECLARE v_level_band VARCHAR(20);
    DECLARE v_student_type_code VARCHAR(20);
    DECLARE v_dormitory_id INT UNSIGNED;
    DECLARE v_count INT;

    SET p_obligations_generated = 0;

    SELECT sae.student_id, sae.academic_year_id, aycs.academic_year_class_id, ayc.class_id
      INTO v_student_id, v_academic_year_id, v_academic_year_class_id, v_class_id
    FROM student_academic_enrollments sae
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    WHERE sae.id = p_enrollment_id;

    IF v_student_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Enrollment not found';
    END IF;

    SELECT grade_level INTO v_class_grade FROM classes WHERE id = v_class_id;

    IF v_class_grade IS NOT NULL THEN
        SET v_level_band = CASE
            WHEN v_class_grade IN ('PP1', 'PP2') THEN 'pp'
            WHEN v_class_grade IN ('Grade 1', 'Grade 2', 'Grade 3') THEN 'lower_primary'
            WHEN v_class_grade IN ('Grade 4', 'Grade 5', 'Grade 6') THEN 'upper_primary'
            WHEN v_class_grade IN ('Grade 7', 'Grade 8', 'Grade 9') THEN 'junior_secondary'
            ELSE NULL
        END;

        SELECT COUNT(*) INTO v_count
        FROM academic_year_class_learning_areas
        WHERE academic_year_class_id = v_academic_year_class_id;

        IF v_count = 0 AND v_level_band IS NOT NULL THEN
            INSERT INTO academic_year_class_learning_areas (id, academic_year_class_id, learning_area_id, strand_id, sub_strand_id, status, planned_weeks, notes)
            SELECT
                COALESCE((SELECT MAX(id) FROM academic_year_class_learning_areas), 0) + ROW_NUMBER() OVER (ORDER BY la.id),
                v_academic_year_class_id,
                la.id,
                NULL,
                NULL,
                'planned',
                NULL,
                'auto-seeded on student onboarding'
            FROM learning_areas la
            WHERE la.level_band = v_level_band COLLATE utf8mb4_general_ci
              AND la.status = 'active';
        END IF;
    END IF;

    CALL sp_generate_student_fee_obligations(v_student_id, v_academic_year_id, NULL, p_obligations_generated);
    SELECT COUNT(*) INTO p_obligations_generated
    FROM student_fee_obligations
    WHERE student_academic_enrollment_id = p_enrollment_id;

    SELECT st.code INTO v_student_type_code
    FROM students s
    JOIN student_types st ON st.id = s.student_type_id
    WHERE s.id = v_student_id;

    IF v_student_type_code IN ('BOARD', 'WEEKLY') THEN
        SELECT d.id INTO v_dormitory_id
        FROM dormitories d
        LEFT JOIN dormitory_assignments da ON da.dormitory_id = d.id AND da.status = 'active'
        JOIN students s ON s.id = v_student_id
        JOIN persons prs ON prs.id = s.person_id
        WHERE d.status = 'active'
          AND (d.gender = prs.gender OR d.gender = 'mixed')
        GROUP BY d.id
        ORDER BY COUNT(da.id) ASC, d.id ASC
        LIMIT 1;

        IF v_dormitory_id IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM dormitory_assignments
                           WHERE student_academic_enrollment_id = p_enrollment_id AND status = 'active') THEN
            INSERT INTO dormitory_assignments (id, student_academic_enrollment_id, dormitory_id, academic_year_id, assigned_date, status, assigned_by, created_at)
            SELECT COALESCE(MAX(id), 0) + 1, p_enrollment_id, v_dormitory_id, v_academic_year_id, CURDATE(), 'active', p_operator_id, NOW()
            FROM dormitory_assignments;
        END IF;
    END IF;
END$$
DELIMITER ;

-- sp_auto_rollover_fee_structures: copies fee schedules to the target year;
-- the academic_year_rollover_log write is removed (rollover audit now happens
-- in PHP via FileLogger), p_log_id is left 0.
DROP PROCEDURE IF EXISTS sp_auto_rollover_fee_structures;
DELIMITER $$
CREATE PROCEDURE `sp_auto_rollover_fee_structures`(IN `p_from_year` INT, IN `p_to_year` INT, IN `p_apply_increase` TINYINT, OUT `p_copied` INT, OUT `p_log_id` INT)
BEGIN
    DECLARE v_copied INT DEFAULT 0;
    DECLARE v_from_year_id INT UNSIGNED;
    DECLARE v_to_year_id INT UNSIGNED;

    SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = p_from_year LIMIT 1;
    SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = p_to_year LIMIT 1;

    INSERT INTO academic_year_fee_schedules (
        id,
        academic_year_id,
        academic_year_term_id,
        academic_year_class_id,
        student_type_id,
        fee_catalog_id,
        amount,
        due_date,
        status,
        created_by,
        created_at,
        updated_at
    )
    SELECT
        COALESCE((SELECT MAX(id) FROM academic_year_fee_schedules), 0) + ROW_NUMBER() OVER (ORDER BY src.id),
        v_to_year_id,
        dst_ayt.id,
        dst_ayc.id,
        src.student_type_id,
        src.fee_catalog_id,
        CASE WHEN p_apply_increase = 1 THEN ROUND(src.amount * 1.1, 2) ELSE src.amount END,
        src.due_date,
        'draft',
        NULL,
        NOW(),
        NOW()
    FROM academic_year_fee_schedules src
    JOIN academic_year_classes src_ayc ON src_ayc.id = src.academic_year_class_id
    JOIN academic_year_classes dst_ayc ON dst_ayc.class_id = src_ayc.class_id
    LEFT JOIN academic_year_terms src_ayt ON src_ayt.id = src.academic_year_term_id
    LEFT JOIN academic_year_terms dst_ayt ON dst_ayt.academic_year_id = v_to_year_id
        AND dst_ayt.term_id = src_ayt.term_id
    WHERE src.academic_year_id = v_from_year_id
      AND dst_ayc.academic_year_id = v_to_year_id
      AND src.status = 'active'
      AND (src.academic_year_term_id IS NULL OR dst_ayt.id IS NOT NULL);

    SET v_copied = ROW_COUNT();
    SET p_copied = v_copied;
    SET p_log_id = 0;
END$$
DELIMITER ;

-- sp_schedule_maintenance: inserts the equipment_maintenance row (functional);
-- the system_events write is removed.
DROP PROCEDURE IF EXISTS sp_schedule_maintenance;
DELIMITER $$
CREATE PROCEDURE `sp_schedule_maintenance`(IN `p_equipment_id` INT UNSIGNED, IN `p_maintenance_type_id` INT UNSIGNED, IN `p_next_maintenance_date` DATE, IN `p_notes` TEXT)
BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_equipment_exists INT DEFAULT 0;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT COUNT(*) INTO v_equipment_exists
FROM item_serials
WHERE id = p_equipment_id;
IF v_equipment_exists = 0 THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Equipment not found';
END IF;
INSERT INTO equipment_maintenance (
    equipment_id,
    maintenance_type_id,
    next_maintenance_date,
    status,
    notes,
    created_at,
    updated_at
  )
VALUES (
    p_equipment_id,
    p_maintenance_type_id,
    p_next_maintenance_date,
    'pending',
    p_notes,
    NOW(),
    NOW()
  );
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- 4. Drop the pure-log tables
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS system_events;
DROP TABLE IF EXISTS sms_communications;
DROP TABLE IF EXISTS term_transition_log;
DROP TABLE IF EXISTS academic_year_rollover_log;
DROP TABLE IF EXISTS payment_webhooks_log;
DROP TABLE IF EXISTS maintenance_logs;
DROP TABLE IF EXISTS vehicle_fuel_logs;
DROP TABLE IF EXISTS system_error_logs;
