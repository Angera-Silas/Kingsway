#!/usr/bin/env python3
"""Re-authored procedures and functions for the 3NF/4NF deliverable.

The 85 objects below previously referenced retired tables/columns
(class_streams, class_enrollments, class_year_assignments, academic_terms,
payment_transactions, student_fee_balances, student_promotions,
fee_structures_detailed, curriculum_units, staff_payroll, user_login_attempts,
permission_audit_log, inventory_requisitions, school_calendar, route_stops,
discipline_incidents, staff_child_fee_config, cbc_assessments, ...). MySQL
does not resolve table/column references for stored programs at CREATE time, so
these bodies still created successfully but would fail at RUNTIME. Each is
re-pointed at the equivalent 3NF/4NF structure:

  class_streams / students.stream_id / s.class_id
      -> streams + academic_year_classes + academic_year_class_streams +
         student_academic_enrollments
  academic_terms                              -> terms + academic_year_terms
  academic_years.year_label                   -> academic_years.year_code
  student names (students.first_name/last_name)
      -> persons.first_name/middle_name/last_name via students.person_id
  class_enrollments / class_year_assignments  -> student_academic_enrollments
  payment_transactions / financial_transactions -> payments
  student_fee_balances / student_arrears      -> vw_student_fee_balances /
                                                  vw_student_arrears (derived)
  fee_structures_detailed                     -> academic_year_fee_schedules
  fee_structures                              -> fee_catalog +
                                                  academic_year_fee_schedules
  student_fee_obligations.amount_paid/balance -> derived balance views +
                                                  payments
  user_login_attempts / account_unlock_history / failed_auth_attempts
      -> login_attempts + users.failed_login_attempts / account_locked_until
  permission_audit_log / audit_trail          -> audit_logs
  staff_payroll                               -> payslips / payroll_runs /
                                                  staff_payroll_profiles
  role_form_permissions                       -> form_permissions +
                                                  role_permissions +
                                                  user_permissions
  student_promotions                          -> promotion_batches +
                                                  student_transitions +
                                                  class_promotion_queue
  student_registrations                       -> student_academic_enrollments
  transport_payments                          -> transport_monthly_bills /
                                                  transport_bill_payments
  staff_child_fee_config                      -> school_settings
  curriculum_units / cbc_assessments          -> learning_areas / strands /
                                                  assessments
  class_schedules                             -> timetable_entries
  school_calendar                             -> calendar_day_types +
                                                  academic_year_calendar_days
  route_stops                                 -> transport_stops
  student_discipline                          -> discipline_incidents
  student_transport                           -> student_transport_assignments
  staff_performance_reviews                   -> performance_reviews
  inventory_requisitions                      -> requisitions
  student_fee_carryover / student_payment_history_summary
      -> fee_credit_notes / derived fee rows

Term semantics: every `p_term_id` parameter is treated as a `terms.id`
(T1/T2/T3, matching legacy academic_terms.id); tables that store
`academic_year_term_id` are reached through `academic_year_terms`.

Target tables without AUTO_INCREMENT ids (students, users, payments,
academic_year_*, streams, student_academic_enrollments, student_transitions,
student_fee_obligations, ...) receive an explicit COALESCE(MAX(id),0)+1 id on
INSERT so the statements are fully deterministic and DML-capable.

Every statement is emitted verbatim by the Section-4 builder; the import
pipeline validates them at CREATE time against the scratch database.
"""

REAUTHORIZED_PROCEDURES = {}

REAUTHORIZED_FUNCTIONS = {}


def proc(name, sql):
    """Register a re-authored procedure definition (body must start with
    CREATE DEFINER=... PROCEDURE `name` ...)."""
    REAUTHORIZED_PROCEDURES[name] = "CREATE DEFINER=`root`@`localhost` %s" % sql


def fn(name, sql):
    """Register a re-authored function definition (body must start with
    CREATE DEFINER=... FUNCTION `name` ...)."""
    REAUTHORIZED_FUNCTIONS[name] = "CREATE DEFINER=`root`@`localhost` %s" % sql


# ---------------------------------------------------------------------------
# Functions
# ---------------------------------------------------------------------------

fn(
    "calculate_class_average",
    """FUNCTION `calculate_class_average` (`p_class_id` INT UNSIGNED, `p_subject_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE avg_marks DECIMAL(10, 2);
SELECT AVG(ar.marks_obtained) INTO avg_marks
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
  JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
WHERE ayc.class_id = p_class_id
  AND a.learning_area_id = p_subject_id
  AND ayt.term_id = p_term_id;
RETURN COALESCE(avg_marks, 0.00);
END""",
)

fn(
    "calculate_term_fees",
    """FUNCTION `calculate_term_fees` (`p_student_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(10,2) READS SQL DATA BEGIN
DECLARE total_fees DECIMAL(10, 2);
SELECT COALESCE(SUM(sfo.amount_due), 0) INTO total_fees
FROM student_fee_obligations sfo
WHERE sfo.student_academic_enrollment_id IN (
    SELECT sae.id
    FROM student_academic_enrollments sae
    WHERE sae.student_id = p_student_id
      AND sae.academic_year_id = (
          SELECT MAX(sae2.academic_year_id)
          FROM student_academic_enrollments sae2
          WHERE sae2.student_id = p_student_id
      )
  )
  AND sfo.academic_year_term_id IN (
      SELECT ayt.id
      FROM academic_year_terms ayt
      WHERE ayt.term_id = p_term_id
  );
RETURN total_fees;
END""",
)

fn(
    "fn_get_batch_approval_percentage",
    """FUNCTION `fn_get_batch_approval_percentage` (`p_batch_id` INT UNSIGNED) RETURNS DECIMAL(5,2) DETERMINISTIC READS SQL DATA BEGIN
DECLARE v_total INT;
DECLARE v_approved INT;
SELECT COALESCE(total_students_processed, 0) INTO v_total
FROM promotion_batches
WHERE id = p_batch_id;
IF v_total = 0 THEN RETURN 0.00;
END IF;
SELECT COALESCE(total_promoted, 0) INTO v_approved
FROM promotion_batches
WHERE id = p_batch_id;
RETURN ROUND((v_approved / v_total) * 100, 2);
END""",
)

fn(
    "fn_get_child_discount_rate",
    """FUNCTION `fn_get_child_discount_rate` (`p_staff_id` INT UNSIGNED, `p_student_id` INT UNSIGNED) RETURNS DECIMAL(5,2) DETERMINISTIC BEGIN
    DECLARE v_child_order INT DEFAULT 1;
    DECLARE v_discount DECIMAL(5,2);
    DECLARE v_first_discount DECIMAL(5,2);
    DECLARE v_second_discount DECIMAL(5,2);
    DECLARE v_third_discount DECIMAL(5,2);

    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_first_discount
    FROM school_settings WHERE setting_key = 'first_child_discount_percentage';

    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_second_discount
    FROM school_settings WHERE setting_key = 'second_child_discount_percentage';

    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_third_discount
    FROM school_settings WHERE setting_key = 'third_child_discount_percentage';

    SET v_first_discount = IFNULL(v_first_discount, 50.00);
    SET v_second_discount = IFNULL(v_second_discount, 40.00);
    SET v_third_discount = IFNULL(v_third_discount, 30.00);

    SELECT COUNT(*) + 1 INTO v_child_order
    FROM staff_children sc
    JOIN students st ON sc.student_id = st.id
    WHERE sc.staff_id = p_staff_id
    AND sc.student_id < p_student_id
    AND sc.fee_deduction_enabled = 1
    AND st.status = 'active';

    SET v_discount = CASE
        WHEN v_child_order = 1 THEN v_first_discount
        WHEN v_child_order = 2 THEN v_second_discount
        ELSE v_third_discount
    END;

    RETURN v_discount;
END""",
)

fn(
    "fn_get_parent_total_fee_balance",
    """FUNCTION `fn_get_parent_total_fee_balance` (`p_parent_id` INT UNSIGNED) RETURNS DECIMAL(12,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;

    SELECT COALESCE(SUM(f.balance), 0)
    INTO v_total
    FROM vw_student_fee_balances f
    JOIN student_parents sp ON f.student_id = sp.student_id
    WHERE sp.parent_id = p_parent_id;

    RETURN v_total;
END""",
)

fn(
    "fn_get_student_promotion_status",
    """FUNCTION `fn_get_student_promotion_status` (`p_student_id` INT UNSIGNED, `p_from_year` YEAR, `p_to_year` YEAR) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC READS SQL DATA BEGIN
DECLARE v_status VARCHAR(50);
SELECT CASE
    WHEN pb.id IS NULL THEN 'no_promotion'
    WHEN cpq.approval_status = 'approved' THEN 'approved'
    WHEN cpq.approval_status = 'rejected' THEN 'rejected'
    WHEN cpq.approval_status IN ('pending', 'reviewing') THEN 'pending_approval'
    WHEN cpq.approval_status = 'hold' THEN 'suspended'
    ELSE 'no_promotion'
  END INTO v_status
FROM promotion_batches pb
LEFT JOIN class_promotion_queue cpq ON cpq.batch_id = pb.id
WHERE pb.from_academic_year = p_from_year
  AND pb.to_academic_year = p_to_year
  AND cpq.batch_id IN (
    SELECT st2.batch_id
    FROM student_transitions st2
    WHERE st2.student_id = p_student_id
  )
LIMIT 1;
RETURN IFNULL(v_status, 'no_promotion');
END""",
)

fn(
    "fn_is_school_day",
    """FUNCTION `fn_is_school_day` (`check_date` DATE) RETURNS TINYINT(1) DETERMINISTIC BEGIN
    DECLARE is_school BOOLEAN DEFAULT TRUE;
    DECLARE day_type_val VARCHAR(50);

    SELECT cdt.code INTO day_type_val
    FROM academic_year_calendar_days acd
    JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id
    WHERE acd.date = check_date
    LIMIT 1;

    IF day_type_val IN ('public_holiday', 'school_holiday', 'weekend') THEN
        SET is_school = FALSE;
    ELSEIF day_type_val IS NULL AND DAYOFWEEK(check_date) IN (1, 7) THEN
        SET is_school = FALSE;
    END IF;

    RETURN is_school;
END""",
)

fn(
    "fn_student_fee_due",
    """FUNCTION `fn_student_fee_due` (`p_student_id` INT, `p_term_id` INT) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE v_due DECIMAL(10, 2);
SELECT COALESCE(SUM(f.balance), 0) INTO v_due
FROM vw_student_fee_balances f
WHERE f.student_id = p_student_id
  AND f.term_id = p_term_id;
RETURN v_due;
END""",
)

fn(
    "fn_student_outstanding_balance",
    """FUNCTION `fn_student_outstanding_balance` (`p_student_id` INT) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE v_balance DECIMAL(10, 2);
SELECT COALESCE(SUM(balance), 0) INTO v_balance
FROM vw_student_fee_balances
WHERE student_id = p_student_id;
RETURN v_balance;
END""",
)

# ---------------------------------------------------------------------------
# Streams
# ---------------------------------------------------------------------------

proc(
    "sp_add_custom_stream",
    """PROCEDURE `sp_add_custom_stream` (IN `p_class_id` INT UNSIGNED, IN `p_stream_name` VARCHAR(50), IN `p_capacity` INT, IN `p_teacher_id` INT UNSIGNED)   BEGIN
DECLARE v_academic_year_class_id INT UNSIGNED;
DECLARE v_class_name VARCHAR(50);
DECLARE v_new_stream_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Add stream failed';
END;

SELECT c.name INTO v_class_name
FROM classes c
WHERE c.id = p_class_id;
IF v_class_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class not found';
END IF;

SELECT ayc.id INTO v_academic_year_class_id
FROM academic_years ay
JOIN academic_year_classes ayc ON ayc.academic_year_id = ay.id
  AND ayc.class_id = p_class_id
WHERE ay.is_current = 1
ORDER BY ay.id DESC
LIMIT 1;

IF EXISTS (
  SELECT 1
  FROM academic_year_class_streams aycs
  JOIN streams s ON s.id = aycs.stream_id
  WHERE aycs.academic_year_class_id = v_academic_year_class_id
    AND s.name = p_stream_name
) THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Stream name already exists for this class';
END IF;

INSERT INTO streams (id, name, code, capacity)
SELECT COALESCE(MAX(id), 0) + 1,
  p_stream_name,
  UPPER(REPLACE(p_stream_name, ' ', '')),
  p_capacity
FROM streams;

SET v_new_stream_id = (SELECT MAX(id) FROM streams);

INSERT INTO academic_year_class_streams (
    id,
    academic_year_class_id,
    stream_id,
    room_id,
    class_teacher_id,
    capacity,
    status
  )
SELECT COALESCE(MAX(id), 0) + 1,
  v_academic_year_class_id,
  v_new_stream_id,
  NULL,
  p_teacher_id,
  p_capacity,
  'active'
FROM academic_year_class_streams;

UPDATE academic_year_class_streams aycs
JOIN streams s ON s.id = aycs.stream_id
SET aycs.status = 'completed'
WHERE aycs.academic_year_class_id = v_academic_year_class_id
  AND s.name = v_class_name
  AND aycs.status = 'active'
  AND p_stream_name != v_class_name;

END""",
)

proc(
    "sp_ensure_class_streams",
    """PROCEDURE `sp_ensure_class_streams` (IN `p_class_id` INT UNSIGNED)   BEGIN
DECLARE v_stream_count INT;
DECLARE v_class_name VARCHAR(50);
DECLARE v_class_capacity INT;
DECLARE v_teacher_id INT UNSIGNED;
DECLARE v_academic_year_class_id INT UNSIGNED;
DECLARE v_default_stream_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

SELECT c.name,
  c.grade_level,
  sl.id INTO v_class_name,
  v_class_capacity,
  v_teacher_id
FROM classes c
  LEFT JOIN school_levels sl ON c.level_id = sl.id
WHERE c.id = p_class_id;
IF v_class_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class not found';
END IF;

SELECT ayc.id INTO v_academic_year_class_id
FROM academic_years ay
JOIN academic_year_classes ayc ON ayc.academic_year_id = ay.id
  AND ayc.class_id = p_class_id
WHERE ay.is_current = 1
ORDER BY ay.id DESC
LIMIT 1;
IF v_academic_year_class_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class not set up for the current academic year';
END IF;

SELECT COUNT(*) INTO v_stream_count
FROM academic_year_class_streams
WHERE academic_year_class_id = v_academic_year_class_id
  AND status = 'active';

IF v_stream_count = 0 THEN
  IF NOT EXISTS (SELECT 1 FROM streams WHERE name = v_class_name) THEN
    INSERT INTO streams (id, name, code, capacity)
    SELECT COALESCE(MAX(id), 0) + 1, v_class_name, UPPER(REPLACE(v_class_name, ' ', '')), v_class_capacity
    FROM streams;
  END IF;

  SELECT id INTO v_default_stream_id FROM streams WHERE name = v_class_name LIMIT 1;

  INSERT INTO academic_year_class_streams (
      id, academic_year_class_id, stream_id, room_id, class_teacher_id, capacity, status
    )
  SELECT COALESCE(MAX(id), 0) + 1, v_academic_year_class_id, v_default_stream_id, NULL, v_teacher_id, v_class_capacity, 'active'
  FROM academic_year_class_streams;
END IF;
END""",
)

proc(
    "sp_remove_custom_stream",
    """PROCEDURE `sp_remove_custom_stream` (IN `p_stream_id` INT UNSIGNED)   BEGIN
DECLARE v_class_id INT UNSIGNED;
DECLARE v_stream_name VARCHAR(50);
DECLARE v_class_name VARCHAR(50);
DECLARE v_remaining_custom_streams INT;
DECLARE v_academic_year_class_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

SELECT s.name INTO v_stream_name
FROM streams s
WHERE s.id = p_stream_id;
IF v_stream_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Stream not found';
END IF;

SELECT ayc.class_id,
  c.name INTO v_class_id,
  v_class_name
FROM academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
WHERE aycs.stream_id = p_stream_id
LIMIT 1;
IF v_class_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Stream is not assigned to any class';
END IF;
IF v_stream_name = v_class_name THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Cannot delete default stream. Deactivate custom streams instead.';
END IF;

UPDATE academic_year_class_streams
SET status = 'completed'
WHERE stream_id = p_stream_id;

SELECT ayc.id INTO v_academic_year_class_id
FROM academic_years ay
JOIN academic_year_classes ayc ON ayc.academic_year_id = ay.id
  AND ayc.class_id = v_class_id
WHERE ay.is_current = 1
ORDER BY ay.id DESC
LIMIT 1;

SELECT COUNT(*) INTO v_remaining_custom_streams
FROM academic_year_class_streams aycs
JOIN streams s ON s.id = aycs.stream_id
WHERE aycs.academic_year_class_id = v_academic_year_class_id
  AND aycs.status = 'active'
  AND s.name != v_class_name;

IF v_remaining_custom_streams = 0 THEN
UPDATE academic_year_class_streams aycs
JOIN streams s ON s.id = aycs.stream_id
SET aycs.status = 'active'
WHERE aycs.academic_year_class_id = v_academic_year_class_id
  AND s.name = v_class_name;
END IF;
END""",
)

# ---------------------------------------------------------------------------
# Promotions
# ---------------------------------------------------------------------------

proc(
    "sp_approve_class_promotion",
    """PROCEDURE `sp_approve_class_promotion` (IN `p_batch_id` INT UNSIGNED, IN `p_class_id` INT UNSIGNED, IN `p_stream_id` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_from_year_id INT UNSIGNED;
DECLARE v_to_year_id INT UNSIGNED;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class approval failed';
END;

SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = (SELECT from_academic_year FROM promotion_batches WHERE id = p_batch_id) LIMIT 1;
SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = (SELECT to_academic_year FROM promotion_batches WHERE id = p_batch_id) LIMIT 1;

UPDATE class_promotion_queue
SET approval_status = 'approved',
  approved_count = (
    SELECT COUNT(*)
    FROM student_academic_enrollments sae
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    WHERE aycs.stream_id = p_stream_id
      AND sae.enrollment_status = 'active'
  ),
  pending_count = 0,
  notes = p_notes,
  reviewed_at = NOW()
WHERE batch_id = p_batch_id
  AND class_id = p_class_id
  AND stream_id = p_stream_id;

INSERT INTO student_transitions (
    id,
    student_id,
    from_student_academic_enrollment_id,
    to_student_academic_enrollment_id,
    academic_year_id,
    transition_type,
    reason,
    decided_by,
    decided_at,
    executed_at
  )
SELECT (SELECT COALESCE(MAX(id), 0) FROM student_transitions) + ROW_NUMBER() OVER (ORDER BY sae.student_id),
  sae.student_id,
  sae.id,
  NULL,
  v_to_year_id,
  'promotion',
  p_notes,
  p_approved_by,
  NOW(),
  NOW()
FROM student_academic_enrollments sae
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
WHERE sae.enrollment_status = 'active'
  AND aycs.stream_id = p_stream_id
  AND sae.academic_year_id = v_from_year_id;

INSERT INTO student_academic_enrollments (
    id,
    student_id,
    academic_year_id,
    academic_year_class_stream_id,
    enrolled_on,
    enrollment_status
  )
SELECT (SELECT COALESCE(MAX(id), 0) FROM student_academic_enrollments) + ROW_NUMBER() OVER (ORDER BY sae.student_id),
  sae.student_id,
  v_to_year_id,
  aycs_to.id,
  CURDATE(),
  'active'
FROM student_academic_enrollments sae
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
JOIN academic_year_class_streams aycs_to ON aycs_to.stream_id = p_stream_id
  AND aycs_to.academic_year_class_id = (
    SELECT ayc.id
    FROM academic_year_classes ayc
    WHERE ayc.class_id = p_class_id
      AND ayc.academic_year_id = v_to_year_id
    LIMIT 1
  )
WHERE sae.enrollment_status = 'active'
  AND aycs.stream_id = p_stream_id
  AND sae.academic_year_id = v_from_year_id
  AND NOT EXISTS (
    SELECT 1 FROM student_academic_enrollments x
    WHERE x.student_id = sae.student_id
      AND x.academic_year_id = v_to_year_id
  );

UPDATE student_academic_enrollments
SET enrollment_status = 'completed'
WHERE id IN (
  SELECT sae.id
  FROM student_academic_enrollments sae
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  WHERE aycs.stream_id = p_stream_id
    AND sae.academic_year_id = v_from_year_id
);
END""",
)

proc(
    "sp_approve_student_promotion",
    """PROCEDURE `sp_approve_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_batch_id INT UNSIGNED;
DECLARE v_class_id INT UNSIGNED;
DECLARE v_stream_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Student approval failed';
END;

SELECT batch_id,
  class_id,
  stream_id INTO v_batch_id,
  v_class_id,
  v_stream_id
FROM class_promotion_queue
WHERE id = p_promotion_id;

UPDATE class_promotion_queue
SET approval_status = 'approved',
  approved_count = approved_count + 1,
  pending_count = GREATEST(pending_count - 1, 0),
  notes = p_notes,
  reviewed_at = NOW()
WHERE id = p_promotion_id;
END""",
)

proc(
    "sp_complete_promotion_batch",
    """PROCEDURE `sp_complete_promotion_batch` (IN `p_batch_id` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Batch completion failed';
END;

UPDATE promotion_batches
SET status = 'completed',
  total_promoted = (
    SELECT COALESCE(SUM(approved_count), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  ),
  total_rejected = (
    SELECT COALESCE(SUM(rejected_count), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COALESCE(SUM(pending_count), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  ),
  completed_at = NOW()
WHERE id = p_batch_id;
END""",
)

proc(
    "sp_reject_class_promotion",
    """PROCEDURE `sp_reject_class_promotion` (IN `p_batch_id` INT UNSIGNED, IN `p_class_id` INT UNSIGNED, IN `p_stream_id` INT UNSIGNED, IN `p_rejection_reason` TEXT, IN `p_reviewed_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class rejection failed';
END;

UPDATE class_promotion_queue
SET approval_status = 'rejected',
  rejected_count = COALESCE(pending_count, total_in_class),
  pending_count = 0,
  notes = p_rejection_reason,
  reviewed_at = NOW()
WHERE batch_id = p_batch_id
  AND class_id = p_class_id
  AND stream_id = p_stream_id;
END""",
)

proc(
    "sp_reject_student_promotion",
    """PROCEDURE `sp_reject_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_rejection_reason` TEXT, IN `p_reviewed_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Student rejection failed';
END;

UPDATE class_promotion_queue
SET approval_status = 'rejected',
  rejected_count = rejected_count + 1,
  pending_count = GREATEST(pending_count - 1, 0),
  notes = p_rejection_reason,
  reviewed_at = NOW()
WHERE id = p_promotion_id;
END""",
)

proc(
    "sp_suspend_student_promotion",
    """PROCEDURE `sp_suspend_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_suspension_type` VARCHAR(50), IN `p_suspension_reason` TEXT, IN `p_expected_return` DATE, IN `p_suspended_by` INT UNSIGNED)   BEGIN
DECLARE v_student_id INT UNSIGNED;
DECLARE v_academic_year YEAR;
DECLARE v_batch_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Suspension failed';
END;

SELECT student_id INTO v_student_id
FROM student_transitions
WHERE id = p_promotion_id;

SELECT from_academic_year INTO v_academic_year
FROM promotion_batches pb
JOIN student_transitions st ON st.academic_year_id = (
    SELECT id FROM academic_years WHERE year_code = pb.to_academic_year LIMIT 1
  )
WHERE st.id = p_promotion_id
LIMIT 1;

UPDATE class_promotion_queue
SET approval_status = 'hold',
  notes = p_suspension_reason,
  reviewed_at = NOW()
WHERE id = p_promotion_id;

INSERT INTO student_suspensions (
    student_id,
    academic_year,
    suspension_type,
    reason,
    suspension_date,
    expected_return_date,
    suspended_by,
    status
  )
VALUES (
    v_student_id,
    v_academic_year,
    p_suspension_type,
    p_suspension_reason,
    NOW(),
    p_expected_return,
    p_suspended_by,
    'active'
  );
END""",
)

proc(
    "sp_transfer_student_promotion",
    """PROCEDURE `sp_transfer_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_transfer_school` VARCHAR(255), IN `p_transfer_reason` TEXT, IN `p_processed_by` INT UNSIGNED)   BEGIN
DECLARE v_student_id INT UNSIGNED;
DECLARE v_from_enrollment_id INT UNSIGNED;
DECLARE v_academic_year_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Transfer failed';
END;

SELECT student_id,
  from_student_academic_enrollment_id,
  academic_year_id INTO v_student_id,
  v_from_enrollment_id,
  v_academic_year_id
FROM student_transitions
WHERE id = p_promotion_id;

UPDATE student_transitions
SET transition_type = 'transfer',
  reason = CONCAT(p_transfer_reason, ' | Transfer to: ', p_transfer_school),
  decided_by = p_processed_by,
  decided_at = NOW(),
  executed_at = NOW()
WHERE id = p_promotion_id;

UPDATE student_academic_enrollments
SET enrollment_status = 'transferred'
WHERE id = v_from_enrollment_id;

UPDATE students
SET status = 'transferred'
WHERE id = v_student_id;
END""",
)

proc(
    "sp_promote_by_grade_bulk",
    """PROCEDURE `sp_promote_by_grade_bulk` (IN `p_batch_id` INT, IN `p_from_year` INT, IN `p_to_year` INT, IN `p_from_grade` VARCHAR(50), IN `p_to_grade` VARCHAR(50))   BEGIN
    DECLARE v_to_year_id INT;
    DECLARE v_from_year_id INT;
    DECLARE v_to_class_id INT;

    SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = p_from_year LIMIT 1;
    SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = p_to_year LIMIT 1;
    SELECT id INTO v_to_class_id FROM classes WHERE name = p_to_grade LIMIT 1;

    INSERT INTO student_transitions (
        id, student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
        academic_year_id, transition_type, reason, decided_by, decided_at, executed_at
    )
    SELECT (SELECT COALESCE(MAX(id), 0) FROM student_transitions) + ROW_NUMBER() OVER (ORDER BY ce.student_id),
        ce.student_id,
        ce.id,
        NULL,
        v_to_year_id,
        'promotion',
        CONCAT('Bulk grade promotion from ', p_from_grade, ' to ', p_to_grade),
        p_batch_id,
        NOW(),
        NOW()
    FROM student_academic_enrollments ce
    JOIN academic_year_class_streams aycs ON aycs.id = ce.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
        AND ayc.academic_year_id = v_from_year_id
    JOIN classes c ON c.id = ayc.class_id
    WHERE c.name = p_from_grade
      AND ce.enrollment_status IN ('active', 'pending')
    ON DUPLICATE KEY UPDATE
        executed_at = NOW();
END""",
)

proc(
    "sp_promote_bulk_students",
    """PROCEDURE `sp_promote_bulk_students` (IN `p_batch_id` INT UNSIGNED, IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_student_ids` JSON)   BEGIN
DECLARE v_batch_status VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE v_index INT DEFAULT 0;
DECLARE v_student_count INT;
DECLARE v_student_id INT;
DECLARE v_current_class_id INT;
DECLARE v_current_stream_id INT;
DECLARE v_next_level_id INT UNSIGNED;
DECLARE v_next_class_id INT;
DECLARE v_next_stream_id INT;
DECLARE v_from_year_id INT UNSIGNED;
DECLARE v_to_year_id INT UNSIGNED;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
UPDATE promotion_batches
SET status = 'cancelled'
WHERE id = p_batch_id;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Bulk student promotion failed';
END;

SELECT status INTO v_batch_status
FROM promotion_batches
WHERE id = p_batch_id;
IF v_batch_status IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion batch not found';
END IF;

SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = p_from_year LIMIT 1;
SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = p_to_year LIMIT 1;

UPDATE promotion_batches
SET status = 'in_progress'
WHERE id = p_batch_id;

SET v_student_count = JSON_LENGTH(p_student_ids);
WHILE v_index < v_student_count DO
SET v_student_id = JSON_UNQUOTE(
    JSON_EXTRACT(p_student_ids, CONCAT('$[', v_index, ']'))
  );

SELECT c.id,
  aycs.stream_id INTO v_current_class_id,
  v_current_stream_id
FROM student_academic_enrollments sae
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
WHERE sae.student_id = v_student_id
  AND sae.academic_year_id = v_from_year_id
  AND sae.enrollment_status IN ('active', 'pending')
LIMIT 1;

SELECT sl.id + 1 INTO v_next_level_id
FROM classes c
JOIN school_levels sl ON c.level_id = sl.id
WHERE c.id = v_current_class_id;

SELECT c_next.id INTO v_next_class_id
FROM classes c_next
WHERE c_next.level_id = v_next_level_id
LIMIT 1;

SELECT s_next.id INTO v_next_stream_id
FROM academic_year_classes ayc_to
JOIN academic_year_class_streams aycs_to ON aycs_to.academic_year_class_id = ayc_to.id
JOIN streams s_next ON s_next.id = aycs_to.stream_id
JOIN streams s_cur ON s_cur.id = v_current_stream_id AND s_cur.name = s_next.name
WHERE ayc_to.class_id = v_next_class_id
  AND ayc_to.academic_year_id = v_to_year_id
LIMIT 1;

INSERT INTO student_transitions (
    id,
    student_id,
    from_student_academic_enrollment_id,
    to_student_academic_enrollment_id,
    academic_year_id,
    transition_type,
    reason,
    decided_by,
    decided_at
  )
SELECT COALESCE(MAX(id), 0) + 1,
  v_student_id,
  (SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = v_student_id AND sae.academic_year_id = v_from_year_id LIMIT 1),
  NULL,
  v_to_year_id,
  'promotion',
  'Pending approval',
  NULL,
  NOW()
FROM student_transitions;

INSERT INTO class_promotion_queue (
    batch_id, class_id, stream_id, total_in_class, approval_status
  )
VALUES (
    p_batch_id, v_next_class_id, v_next_stream_id, 1, 'pending'
  )
ON DUPLICATE KEY UPDATE
  total_in_class = total_in_class + 1;

SET v_index = v_index + 1;
END WHILE;

UPDATE promotion_batches
SET total_students_processed = (
    SELECT COALESCE(SUM(total_in_class), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COALESCE(SUM(pending_count), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  )
WHERE id = p_batch_id;
END""",
)

proc(
    "sp_promote_single_class",
    """PROCEDURE `sp_promote_single_class` (IN `p_batch_id` INT UNSIGNED, IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_current_class_id` INT UNSIGNED, IN `p_current_stream_id` INT UNSIGNED)   BEGIN
DECLARE v_batch_status VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE v_next_class_id INT UNSIGNED;
DECLARE v_next_stream_id INT UNSIGNED;
DECLARE v_from_year_id INT UNSIGNED;
DECLARE v_to_year_id INT UNSIGNED;
DECLARE v_transition_count INT UNSIGNED DEFAULT 0;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
UPDATE promotion_batches
SET status = 'cancelled'
WHERE id = p_batch_id;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Single class promotion failed';
END;

SELECT status INTO v_batch_status
FROM promotion_batches
WHERE id = p_batch_id;
IF v_batch_status IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion batch not found';
END IF;

SELECT id INTO v_from_year_id FROM academic_years WHERE year_code = p_from_year LIMIT 1;
SELECT id INTO v_to_year_id FROM academic_years WHERE year_code = p_to_year LIMIT 1;

SELECT acp.target_class_id INTO v_next_class_id
FROM academic_class_progression acp
WHERE acp.source_class_id = p_current_class_id
  AND acp.active = 1
LIMIT 1;

SELECT aycs_to.stream_id INTO v_next_stream_id
FROM academic_year_classes ayc_to
JOIN academic_year_class_streams aycs_to ON aycs_to.academic_year_class_id = ayc_to.id
JOIN streams s_next ON s_next.id = aycs_to.stream_id
JOIN streams s_cur ON s_cur.id = p_current_stream_id AND s_cur.name = s_next.name
WHERE ayc_to.class_id = v_next_class_id
  AND ayc_to.academic_year_id = v_to_year_id
LIMIT 1;

IF v_next_class_id IS NULL OR v_next_stream_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Next class or stream not found for promotion';
END IF;

IF v_batch_status = 'pending' THEN
UPDATE promotion_batches
SET status = 'in_progress'
WHERE id = p_batch_id;
END IF;

INSERT INTO student_transitions (
    id,
    student_id,
    from_student_academic_enrollment_id,
    to_student_academic_enrollment_id,
    academic_year_id,
    transition_type,
    reason,
    decided_by,
    decided_at
  )
SELECT (SELECT COALESCE(MAX(id), 0) FROM student_transitions) + ROW_NUMBER() OVER (ORDER BY s.id),
  s.id,
  sae.id,
  NULL,
  v_to_year_id,
  'promotion',
  'Pending approval',
  NULL,
  NOW()
FROM students s
JOIN student_academic_enrollments sae ON sae.student_id = s.id
  AND sae.academic_year_id = v_from_year_id
  AND sae.enrollment_status IN ('active', 'pending')
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
WHERE aycs.stream_id = p_current_stream_id
  AND s.status = 'active'
  AND NOT EXISTS (
    SELECT 1
    FROM student_transitions st
    WHERE st.student_id = s.id
      AND st.academic_year_id = v_to_year_id
      AND st.transition_type = 'promotion'
  );

SELECT ROW_COUNT() INTO v_transition_count;

INSERT INTO class_promotion_queue (
    batch_id,
    class_id,
    stream_id,
    total_in_class,
    approval_status
  )
VALUES (
    p_batch_id,
    v_next_class_id,
    v_next_stream_id,
    v_transition_count,
    'pending'
  )
ON DUPLICATE KEY UPDATE
  total_in_class = total_in_class + v_transition_count;

UPDATE promotion_batches
SET total_students_processed = (
    SELECT COALESCE(SUM(total_in_class), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COALESCE(SUM(pending_count), 0)
    FROM class_promotion_queue
    WHERE batch_id = p_batch_id
  )
WHERE id = p_batch_id;
END""",
)

# ---------------------------------------------------------------------------
# Finance
# ---------------------------------------------------------------------------

proc(
    "sp_allocate_payment",
    """PROCEDURE `sp_allocate_payment` (IN `p_payment_id` INT, IN `p_student_id` INT, IN `p_allocation_amount` DECIMAL(10,2), IN `p_term_id` INT, IN `p_year_id` INT, IN `p_allocated_by` INT)   BEGIN
    INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
    VALUES (
        'ALLOCATE_PAYMENT',
        'payments',
        p_payment_id,
        p_allocated_by,
        JSON_OBJECT(
            'student_id', p_student_id,
            'allocated_amount', p_allocation_amount,
            'term_id', p_term_id,
            'academic_year_id', p_year_id
        ),
        'success',
        NOW()
    );
END""",
)

proc(
    "sp_apply_fee_discount",
    """PROCEDURE `sp_apply_fee_discount` (IN `p_student_id` INT, IN `p_discount_amount` DECIMAL(10,2), IN `p_reason` VARCHAR(255), IN `p_approved_by` INT, IN `p_discount_type` VARCHAR(50), IN `p_term_id` INT, IN `p_year_id` INT)   BEGIN
    DECLARE v_year YEAR;

    SELECT year_code INTO v_year FROM academic_years WHERE id = p_year_id LIMIT 1;

    INSERT INTO fee_discounts_waivers (
        student_id, student_fee_obligation_id, discount_type, discount_value, discount_percentage,
        reason, academic_year, term_id, approved_by, approved_date, status, valid_until, created_at
    )
    SELECT
        p_student_id,
        sfo.id,
        CASE
            WHEN p_discount_type IN ('percentage','fixed_amount','full_waiver','merit','need_based','sibling') THEN p_discount_type
            ELSE 'other'
        END,
        p_discount_amount,
        NULL,
        p_reason,
        v_year,
        p_term_id,
        p_approved_by,
        NOW(),
        'active',
        NULL,
        NOW()
    FROM student_fee_obligations sfo
    JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
    WHERE sae.student_id = p_student_id
      AND sfo.academic_year_id = p_year_id
      AND (p_term_id IS NULL OR sfo.academic_year_term_id IN (
          SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term_id
      ));
END""",
)

proc(
    "sp_calculate_staff_child_fees",
    """PROCEDURE `sp_calculate_staff_child_fees` (IN `p_staff_id` INT UNSIGNED, IN `p_payroll_month` INT, IN `p_payroll_year` INT, OUT `p_total_fees` DECIMAL(12,2), OUT `p_fee_details` JSON)   BEGIN
    DECLARE v_max_deduction_pct DECIMAL(5,2);
    DECLARE v_staff_salary DECIMAL(12,2);
    DECLARE v_max_deductible DECIMAL(12,2);
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    DECLARE v_details JSON DEFAULT JSON_ARRAY();

    SET SESSION group_concat_max_len = 65535;

    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_max_deduction_pct
    FROM school_settings WHERE setting_key = 'max_monthly_deduction_percentage';
    SET v_max_deduction_pct = IFNULL(v_max_deduction_pct, 30.00);

    SELECT salary INTO v_staff_salary FROM staff WHERE id = p_staff_id;
    SET v_max_deductible = (v_staff_salary * v_max_deduction_pct / 100);

    SELECT CONCAT(
        '[',
        COALESCE(
            GROUP_CONCAT(
                JSON_OBJECT(
                    'student_id', sc.student_id,
                    'student_name', CONCAT(pe.first_name, ' ', pe.last_name),
                    'class_name', c.name,
                    'discount_percentage', fn_get_child_discount_rate(p_staff_id, sc.student_id),
                    'estimated_deduction', ROUND(
                        COALESCE(f.amount_due, 0) * fn_get_child_discount_rate(p_staff_id, sc.student_id) / 100,
                        2
                    ),
                    'fee_deduction_enabled', sc.fee_deduction_enabled,
                    'custom_deduction_percentage', sc.fee_deduction_percentage
                )
                ORDER BY sc.created_at
            ),
            ''
        ),
        ']'
    ) INTO v_details
    FROM staff_children sc
    JOIN students st ON sc.student_id = st.id
    JOIN persons pe ON pe.id = st.person_id
    LEFT JOIN vw_student_fee_balances f ON f.student_id = sc.student_id
    LEFT JOIN student_academic_enrollments sae
        ON sae.student_id = st.id AND sae.enrollment_status = 'active'
    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    LEFT JOIN classes c ON c.id = ayc.class_id
    WHERE sc.staff_id = p_staff_id
    AND sc.fee_deduction_enabled = 1
    AND st.status = 'active';

    SELECT COALESCE(SUM(
        ROUND(COALESCE(f.amount_due, 0) * fn_get_child_discount_rate(p_staff_id, sc.student_id) / 100, 2)
    ), 0) INTO v_total
    FROM staff_children sc
    JOIN students st ON sc.student_id = st.id
    LEFT JOIN vw_student_fee_balances f ON f.student_id = sc.student_id
    WHERE sc.staff_id = p_staff_id
    AND sc.fee_deduction_enabled = 1
    AND st.status = 'active';

    SET p_total_fees = LEAST(v_total, v_max_deductible);
    SET p_fee_details = IFNULL(v_details, JSON_ARRAY());
END""",
)

proc(
    "sp_calculate_student_fees",
    """PROCEDURE `sp_calculate_student_fees` (IN `p_student_id` INT, IN `p_year` INT, IN `p_term_id` INT)   BEGIN
    SELECT
        ayfs.id AS fee_structure_id,
        fc.name AS fee_type,
        ayfs.amount,
        ayfs.due_date
    FROM academic_year_fee_schedules ayfs
    JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
    JOIN student_academic_enrollments sae ON sae.student_id = p_student_id
        AND sae.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
        AND sae.enrollment_status IN ('active', 'pending')
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    WHERE ayfs.academic_year_class_id = aycs.academic_year_class_id
      AND ayfs.student_type_id = (SELECT student_type_id FROM students WHERE id = p_student_id)
      AND ayfs.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
      AND (p_term_id IS NULL OR ayfs.academic_year_term_id IN (
          SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term_id
      ))
      AND ayfs.status = 'active';
END""",
)

proc(
    "sp_carryover_fee_balance",
    """PROCEDURE `sp_carryover_fee_balance` (IN `p_student_id` INT, IN `p_from_term` INT, IN `p_to_term` INT)   BEGIN
    DECLARE v_balance DECIMAL(10,2) DEFAULT 0.00;

    SELECT COALESCE(SUM(balance), 0) INTO v_balance
    FROM vw_student_fee_balances
    WHERE student_id = p_student_id
      AND term_id = p_from_term;

    IF v_balance > 0 THEN
        INSERT INTO fee_credit_notes (
            credit_number, student_id, academic_year, term_id, source_transaction_id,
            credit_amount, credit_reason, status, applied_amount, remaining_amount,
            applied_to_year, applied_to_term_id, applied_at, expiry_date, notes, created_by
        )
        SELECT
            CONCAT('CR-', p_student_id, '-', p_from_term, '-', p_to_term, '-', UNIX_TIMESTAMP()),
            p_student_id,
            ay.year_code,
            p_from_term,
            NULL,
            v_balance,
            'Carried over balance',
            'active',
            0,
            v_balance,
            ay.year_code,
            p_to_term,
            NULL,
            NULL,
            NULL,
            NULL
        FROM vw_student_fee_balances f
        JOIN academic_years ay ON ay.id = f.academic_year_id
        WHERE f.student_id = p_student_id
          AND f.term_id = p_from_term
        LIMIT 1;
    END IF;
END""",
)

proc(
    "sp_check_finance_clearance",
    """PROCEDURE `sp_check_finance_clearance` (IN `p_student_id` INT, OUT `p_is_cleared` TINYINT, OUT `p_outstanding` DECIMAL(10,2), OUT `p_description` VARCHAR(255))   BEGIN
    DECLARE v_balance DECIMAL(10,2) DEFAULT 0.00;

    SELECT COALESCE(SUM(balance), 0) INTO v_balance
    FROM vw_student_fee_balances
    WHERE student_id = p_student_id;

    IF v_balance <= 0 THEN
        SET p_is_cleared = 1;
        SET p_outstanding = 0.00;
        SET p_description = 'Finance clearance approved - no outstanding balance';
    ELSE
        SET p_is_cleared = 0;
        SET p_outstanding = v_balance;
        SET p_description = CONCAT('Outstanding balance: KES ', FORMAT(v_balance, 2));
    END IF;
END""",
)

proc(
    "sp_complete_student_enrollment",
    """PROCEDURE `sp_complete_student_enrollment` (IN `p_student_id` INT, IN `p_class_id` INT, IN `p_stream_id` INT, IN `p_year_id` INT, OUT `p_enrollment_id` INT, OUT `p_fees_amount` DECIMAL(10,2))   BEGIN
    DECLARE v_academic_year_class_id INT UNSIGNED;
    DECLARE v_aycs_id INT UNSIGNED;
    DECLARE v_student_type_id INT UNSIGNED;

    SELECT ayc.id INTO v_academic_year_class_id
    FROM academic_year_classes ayc
    WHERE ayc.academic_year_id = p_year_id
      AND ayc.class_id = p_class_id
    LIMIT 1;

    SELECT aycs.id INTO v_aycs_id
    FROM academic_year_class_streams aycs
    WHERE aycs.academic_year_class_id = v_academic_year_class_id
      AND aycs.stream_id = p_stream_id
    LIMIT 1;

    IF v_aycs_id IS NULL THEN
        INSERT INTO academic_year_class_streams (
            id, academic_year_class_id, stream_id, room_id, class_teacher_id, capacity, status
        )
        SELECT COALESCE(MAX(id), 0) + 1, v_academic_year_class_id, p_stream_id, NULL, NULL,
            (SELECT capacity FROM streams WHERE id = p_stream_id), 'active'
        FROM academic_year_class_streams;
        SET v_aycs_id = (SELECT MAX(id) FROM academic_year_class_streams);
    END IF;

    INSERT INTO student_academic_enrollments (
        id, student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status
    )
    SELECT COALESCE(MAX(id), 0) + 1, p_student_id, p_year_id, v_aycs_id, CURDATE(), 'active'
    FROM student_academic_enrollments
    ON DUPLICATE KEY UPDATE
        enrollment_status = 'active';

    SET p_enrollment_id = (SELECT MAX(id) FROM student_academic_enrollments WHERE student_id = p_student_id);

    SELECT student_type_id INTO v_student_type_id FROM students WHERE id = p_student_id;

    SELECT COALESCE(SUM(ayfs.amount), 0) INTO p_fees_amount
    FROM academic_year_fee_schedules ayfs
    WHERE ayfs.academic_year_class_id = v_academic_year_class_id
      AND ayfs.student_type_id = v_student_type_id
      AND ayfs.status = 'active';
END""",
)

proc(
    "sp_create_arrears_record",
    """PROCEDURE `sp_create_arrears_record` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
DECLARE v_total_arrears DECIMAL(10, 2);

SELECT COALESCE(SUM(balance), 0) INTO v_total_arrears
FROM vw_student_arrears
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_term_id;
IF v_total_arrears > 0 THEN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'arrears_snapshot',
    JSON_OBJECT(
      'student_id',
      p_student_id,
      'academic_year',
      p_academic_year,
      'term_id',
      p_term_id,
      'total_arrears',
      v_total_arrears
    ),
    NOW()
  );
END IF;
END""",
)

proc(
    "sp_create_arrears_settlement_plan",
    """PROCEDURE `sp_create_arrears_settlement_plan` (IN `p_student_id` INT UNSIGNED, IN `p_arrears_id` INT UNSIGNED, IN `p_installments` INT, IN `p_first_payment_date` DATE, IN `p_created_by` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED)   BEGIN
DECLARE v_total_amount DECIMAL(10, 2);
DECLARE v_installment_amount DECIMAL(10, 2);
DECLARE v_final_payment_date DATE;

SELECT COALESCE(SUM(balance), 0) INTO v_total_amount
FROM vw_student_arrears
WHERE student_id = p_student_id;

SET v_installment_amount = ROUND(v_total_amount / p_installments, 2);
SET v_final_payment_date = DATE_ADD(
    p_first_payment_date,
    INTERVAL (p_installments - 1) MONTH
  );

INSERT INTO arrears_settlement_plans (
    student_id,
    arrears_id,
    total_amount,
    installments,
    installment_amount,
    first_payment_date,
    final_payment_date,
    created_by,
    approved_by,
    approved_date,
    status
  )
VALUES (
    p_student_id,
    p_arrears_id,
    v_total_amount,
    p_installments,
    v_installment_amount,
    p_first_payment_date,
    v_final_payment_date,
    p_created_by,
    p_approved_by,
    NOW(),
    'active'
  );
END""",
)

proc(
    "sp_generate_student_fee_obligations",
    """PROCEDURE `sp_generate_student_fee_obligations` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED, OUT `p_obligations_created` INT)   BEGIN
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_academic_year_class_id INT UNSIGNED;
    DECLARE v_student_type_id INT UNSIGNED;
    DECLARE v_enrollment_id INT UNSIGNED;

    SET p_obligations_created = 0;

    IF p_academic_year_id IS NULL THEN
        SELECT id INTO v_academic_year_id
        FROM academic_years
        WHERE is_current = 1
        LIMIT 1;
    ELSE
        SET v_academic_year_id = p_academic_year_id;
    END IF;

    SELECT s.student_type_id INTO v_student_type_id
    FROM students s
    WHERE s.id = p_student_id;

    SELECT sae.id, aycs.academic_year_class_id
    INTO v_enrollment_id, v_academic_year_class_id
    FROM student_academic_enrollments sae
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    WHERE sae.student_id = p_student_id
      AND sae.academic_year_id = v_academic_year_id
      AND sae.enrollment_status IN ('active', 'pending')
    LIMIT 1;

    IF v_enrollment_id IS NULL THEN
        SET p_obligations_created = 0;
    ELSE
        INSERT INTO student_fee_obligations (
            id,
            student_academic_enrollment_id,
            academic_year_id,
            academic_year_term_id,
            academic_year_fee_schedule_id,
            amount_due,
            status,
            due_date,
            is_sponsored,
            sponsored_waiver_amount,
            created_at,
            updated_at
        )
        SELECT
            COALESCE((SELECT MAX(id) FROM student_fee_obligations), 0) + ROW_NUMBER() OVER (ORDER BY ayfs.id),
            v_enrollment_id,
            v_academic_year_id,
            ayfs.academic_year_term_id,
            ayfs.id,
            ayfs.amount,
            'pending',
            COALESCE(ayfs.due_date, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
            0,
            0,
            NOW(),
            NOW()
        FROM academic_year_fee_schedules ayfs
        WHERE ayfs.academic_year_class_id = v_academic_year_class_id
          AND ayfs.student_type_id = v_student_type_id
          AND ayfs.status = 'active'
          AND (p_term_id IS NULL OR ayfs.academic_year_term_id IN (
              SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term_id
          ))
          AND NOT EXISTS (
              SELECT 1 FROM student_fee_obligations sfo
              WHERE sfo.student_academic_enrollment_id = v_enrollment_id
                AND sfo.academic_year_fee_schedule_id = ayfs.id
          );

        SET p_obligations_created = ROW_COUNT();
    END IF;
END""",
)

proc(
    "sp_get_class_fee_schedule",
    """PROCEDURE `sp_get_class_fee_schedule` (IN `p_class_id` INT, IN `p_year` INT)   BEGIN
    SELECT
        fc.name AS fee_type,
        ayfs.amount,
        ayfs.due_date,
        st.name AS student_type
    FROM academic_year_fee_schedules ayfs
    JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
    JOIN student_types st ON st.id = ayfs.student_type_id
    JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
        AND ayc.class_id = p_class_id
    JOIN academic_years ay ON ay.id = ayfs.academic_year_id
        AND ay.year_code = p_year
    WHERE ayfs.status = 'active'
    ORDER BY fc.name, st.name;
END""",
)

proc(
    "sp_get_fee_breakdown_for_review",
    """PROCEDURE `sp_get_fee_breakdown_for_review` (IN `p_year` INT, IN `p_term` INT)   BEGIN
    SELECT
        l.name AS level_name,
        fc.name AS fee_type,
        st.name AS student_type,
        ayfs.amount,
        ayfs.status,
        ayfs.due_date
    FROM academic_year_fee_schedules ayfs
    JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
    JOIN student_types st ON st.id = ayfs.student_type_id
    JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
    JOIN classes c ON c.id = ayc.class_id
    JOIN school_levels l ON l.id = c.level_id
    WHERE ayfs.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
      AND ayfs.academic_year_term_id IN (SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term)
    ORDER BY l.name, fc.name, st.name;
END""",
)

proc(
    "sp_get_fee_collection_rate",
    """PROCEDURE `sp_get_fee_collection_rate` (IN `p_year` INT, IN `p_term` INT)   BEGIN
    SELECT
        SUM(amount_paid) AS total_collected,
        COUNT(DISTINCT student_id) AS students_with_fees,
        COUNT(DISTINCT CASE WHEN balance <= 0 THEN student_id END) AS students_paid,
        ROUND(
            100.0 * SUM(amount_paid)
            / NULLIF(SUM(amount_due), 0),
            2
        ) AS collection_rate_percent
    FROM vw_student_fee_balances
    WHERE academic_year = p_year
      AND term_id = p_term;
END""",
)

proc(
    "sp_get_outstanding_fees_report",
    """PROCEDURE `sp_get_outstanding_fees_report` (IN `p_year` INT, IN `p_term` INT)   BEGIN
    SELECT
        s.id AS student_id,
        s.admission_no,
        CONCAT(p.first_name, ' ', p.last_name) AS student_name,
        f.balance AS outstanding_amount,
        c.name AS class_name
    FROM vw_student_fee_balances f
    JOIN students s ON s.id = f.student_id
    JOIN persons p ON p.id = s.person_id
    JOIN student_academic_enrollments sae ON sae.id = f.student_academic_enrollment_id
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    JOIN classes c ON c.id = ayc.class_id
    WHERE f.balance > 0
      AND f.academic_year = p_year
      AND f.term_id = p_term
    ORDER BY f.balance DESC;
END""",
)

proc(
    "sp_process_student_payment",
    """PROCEDURE `sp_process_student_payment` (IN `p_student_id` INT, IN `p_parent_id` INT, IN `p_amount` DECIMAL(10,2), IN `p_payment_method` VARCHAR(50), IN `p_reference_no` VARCHAR(100), IN `p_receipt_no` VARCHAR(50), IN `p_received_by` INT, IN `p_payment_date` DATETIME, IN `p_notes` TEXT)   BEGIN
    DECLARE v_transaction_id INT;

    INSERT INTO payments (
        id, student_id, receipt_no, amount, payment_date, method,
        reference, parent_id, received_by, status, notes, created_at, updated_at
    )
    SELECT COALESCE(MAX(id), 0) + 1, p_student_id, p_receipt_no, p_amount,
        COALESCE(p_payment_date, NOW()), p_payment_method,
        p_reference_no, p_parent_id, p_received_by, 'confirmed', p_notes, NOW(), NOW()
    FROM payments;

    SET v_transaction_id = (SELECT MAX(id) FROM payments);

    SELECT v_transaction_id AS transaction_id, p_amount AS amount_applied,
           'confirmed' AS status;
END""",
)

proc(
    "sp_reallocate_all_payments",
    """PROCEDURE `sp_reallocate_all_payments` ()   BEGIN
    UPDATE student_fee_obligations sfo
    LEFT JOIN (
        SELECT sfo2.id AS obligation_id,
            COALESCE(SUM(pay.amount), 0) AS total_paid
        FROM student_fee_obligations sfo2
        LEFT JOIN payments pay ON pay.student_id = (
            SELECT sae.student_id
            FROM student_academic_enrollments sae
            WHERE sae.id = sfo2.student_academic_enrollment_id
            LIMIT 1
        ) AND pay.status IN ('confirmed', 'completed', 'success')
        GROUP BY sfo2.id
    ) x ON x.obligation_id = sfo.id
    SET sfo.status = CASE
        WHEN COALESCE(x.total_paid, 0) >= sfo.amount_due THEN 'paid'
        WHEN COALESCE(x.total_paid, 0) > 0 THEN 'partial'
        ELSE 'pending'
    END;
END""",
)

proc(
    "sp_record_cash_payment",
    """PROCEDURE `sp_record_cash_payment` (IN `p_student_id` INT, IN `p_amount` DECIMAL(10,2), IN `p_payment_method` VARCHAR(50), IN `p_payment_date` DATETIME)   BEGIN
    INSERT INTO payments (
        id, student_id, receipt_no, amount, payment_date, method,
        reference, parent_id, received_by, status, notes, created_at, updated_at
    )
    SELECT COALESCE(MAX(id), 0) + 1, p_student_id, NULL, p_amount,
        COALESCE(p_payment_date, NOW()), p_payment_method,
        NULL, NULL, NULL, 'confirmed', NULL, NOW(), NOW()
    FROM payments;
END""",
)

proc(
    "sp_refresh_student_payment_summary",
    """PROCEDURE `sp_refresh_student_payment_summary` (IN `p_student_id` INT, IN `p_year` INT, IN `p_term_id` INT)   BEGIN
    UPDATE student_fee_obligations sfo
    LEFT JOIN (
        SELECT sfo2.id AS obligation_id,
            COALESCE(SUM(pay.amount), 0) AS total_paid
        FROM student_fee_obligations sfo2
        LEFT JOIN payments pay ON pay.student_id = (
            SELECT sae.student_id
            FROM student_academic_enrollments sae
            WHERE sae.id = sfo2.student_academic_enrollment_id
            LIMIT 1
        ) AND pay.status IN ('confirmed', 'completed', 'success')
        GROUP BY sfo2.id
    ) x ON x.obligation_id = sfo.id
    SET sfo.status = CASE
        WHEN COALESCE(x.total_paid, 0) >= sfo.amount_due THEN 'paid'
        WHEN COALESCE(x.total_paid, 0) > 0 THEN 'partial'
        ELSE 'pending'
    END
    WHERE sfo.student_academic_enrollment_id IN (
        SELECT sae.id
        FROM student_academic_enrollments sae
        WHERE sae.student_id = p_student_id
          AND sae.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_year LIMIT 1)
    )
      AND (p_term_id IS NULL OR sfo.academic_year_term_id IN (
          SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.term_id = p_term_id
      ));
END""",
)

proc(
    "sp_send_fee_reminder",
    """PROCEDURE `sp_send_fee_reminder` (IN `p_student_id` INT)   BEGIN
    DECLARE v_balance DECIMAL(10,2) DEFAULT 0.00;

    SELECT COALESCE(SUM(balance), 0) INTO v_balance
    FROM vw_student_fee_balances
    WHERE student_id = p_student_id;

    IF v_balance > 0 THEN
        INSERT INTO fee_reminders (
            student_id, parent_id, academic_year, term_id,
            reminder_type, outstanding_amount, sent_date, delivery_method, status
        )
        SELECT
            p_student_id,
            (SELECT MAX(sp.parent_id) FROM student_parents sp WHERE sp.student_id = p_student_id),
            f.academic_year,
            f.term_id,
            'arrears',
            v_balance,
            CURDATE(),
            'manual',
            'sent'
        FROM vw_student_fee_balances f
        WHERE f.student_id = p_student_id
        LIMIT 1;
    END IF;
END""",
)

proc(
    "sp_send_fee_reminders",
    """PROCEDURE `sp_send_fee_reminders` ()   BEGIN
    INSERT INTO fee_reminders (
        student_id, parent_id, academic_year, term_id,
        reminder_type, outstanding_amount, sent_date, delivery_method, status
    )
    SELECT
        f.student_id,
        (SELECT MAX(sp.parent_id) FROM student_parents sp WHERE sp.student_id = f.student_id),
        f.academic_year,
        f.term_id,
        'arrears',
        f.balance,
        CURDATE(),
        'sms',
        'queued'
    FROM vw_student_arrears f;
END""",
)

proc(
    "sp_process_student_sponsorship",
    """PROCEDURE `sp_process_student_sponsorship` (`p_student_id` INT UNSIGNED, `p_is_sponsored` BOOLEAN, `p_sponsor_type` VARCHAR(20), `p_waiver_percentage` DECIMAL(5,2), `p_sponsor_name` VARCHAR(100), OUT `p_result_message` VARCHAR(500))  MODIFIES SQL DATA BEGIN
DECLARE p_student_count INT DEFAULT 0;
DECLARE p_existing_waiver_id INT DEFAULT 0;

SELECT COUNT(*) INTO p_student_count
FROM students
WHERE id = p_student_id;
IF p_student_count = 0 THEN
SET p_result_message = CONCAT('ERROR: Student ID ', p_student_id, ' not found');
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = p_result_message;
END IF;

IF p_waiver_percentage < 0
OR p_waiver_percentage > 100 THEN
SET p_result_message = 'ERROR: Waiver percentage must be between 0 and 100';
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = p_result_message;
END IF;

IF p_is_sponsored = TRUE THEN
SELECT id INTO p_existing_waiver_id
FROM fee_discounts_waivers
WHERE student_id = p_student_id
  AND discount_type IN ('need_based', 'other')
  AND status = 'active'
ORDER BY id DESC
LIMIT 1;

IF p_existing_waiver_id IS NOT NULL THEN
UPDATE fee_discounts_waivers
SET discount_type = CASE WHEN p_sponsor_type IN ('government','bursary','NGO') THEN 'need_based' ELSE 'other' END,
  discount_percentage = p_waiver_percentage,
  reason = CONCAT('Sponsorship by ', p_sponsor_name, ' - ', p_sponsor_type),
  status = 'active',
  updated_at = NOW()
WHERE id = p_existing_waiver_id;
ELSE
INSERT INTO fee_discounts_waivers (
    student_id,
    discount_type,
    discount_percentage,
    reason,
    approved_by,
    approved_date,
    status,
    created_at
  )
VALUES (
    p_student_id,
    CASE WHEN p_sponsor_type IN ('government','bursary','NGO') THEN 'need_based' ELSE 'other' END,
    p_waiver_percentage,
    CONCAT('Sponsorship by ', p_sponsor_name, ' - ', p_sponsor_type),
    1,
    NOW(),
    'active',
    NOW()
  );
END IF;

UPDATE student_fee_obligations
SET is_sponsored = TRUE,
  sponsored_waiver_amount = amount_due * (p_waiver_percentage / 100),
  updated_at = NOW()
WHERE student_academic_enrollment_id IN (
    SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = p_student_id
  )
  AND status != 'paid';

SET p_result_message = CONCAT('SUCCESS: Sponsorship activated for student ', p_student_id, ' with ', p_waiver_percentage, '% waiver');
ELSE
UPDATE fee_discounts_waivers
SET status = 'cancelled',
  updated_at = NOW()
WHERE student_id = p_student_id
  AND discount_type IN ('need_based', 'other')
  AND status = 'active';

UPDATE student_fee_obligations
SET is_sponsored = FALSE,
  sponsored_waiver_amount = 0,
  updated_at = NOW()
WHERE student_academic_enrollment_id IN (
    SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = p_student_id
  );

SET p_result_message = CONCAT('SUCCESS: Sponsorship deactivated for student ', p_student_id);
END IF;
END""",
)

proc(
    "sp_validate_payment_request",
    """PROCEDURE `sp_validate_payment_request` (IN `p_admission_number` VARCHAR(50), IN `p_amount` DECIMAL(10,2), IN `p_transaction_ref` VARCHAR(100))   BEGIN
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_student_name VARCHAR(200);
    DECLARE v_student_status VARCHAR(50);
    DECLARE v_current_balance DECIMAL(10, 2);
    DECLARE v_duplicate_count INT;

    SELECT
        s.id,
        CONCAT(p.first_name, ' ', p.last_name),
        s.status,
        (SELECT COALESCE(SUM(f.balance), 0) FROM vw_student_fee_balances f WHERE f.student_id = s.id)
    INTO
        v_student_id,
        v_student_name,
        v_student_status,
        v_current_balance
    FROM students s
    JOIN persons p ON p.id = s.person_id
    WHERE s.admission_no = p_admission_number
    LIMIT 1;

    IF p_transaction_ref IS NOT NULL THEN
        SELECT COUNT(*) INTO v_duplicate_count
        FROM mpesa_transactions
        WHERE mpesa_code = p_transaction_ref;
    ELSE
        SET v_duplicate_count = 0;
    END IF;

    SELECT
        CASE
            WHEN v_student_id IS NULL THEN 'INVALID_ADMISSION'
            WHEN v_student_status NOT IN ('active', 'enrolled') THEN 'INACTIVE_STUDENT'
            WHEN v_duplicate_count > 0 THEN 'DUPLICATE_TRANSACTION'
            WHEN p_amount <= 0 THEN 'INVALID_AMOUNT'
            ELSE 'VALID'
        END AS validation_result,
        v_student_id AS student_id,
        p_admission_number AS admission_number,
        v_student_name AS student_name,
        v_student_status AS student_status,
        v_current_balance AS current_balance,
        p_amount AS payment_amount,
        v_duplicate_count AS duplicate_count;
END""",
)

# ---------------------------------------------------------------------------
# Academics
# ---------------------------------------------------------------------------

proc(
    "sp_assess_learner_competency",
    """PROCEDURE `sp_assess_learner_competency` (IN `p_student_id` INT UNSIGNED, IN `p_competency_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_performance_level_id` INT UNSIGNED, IN `p_evidence` TEXT, IN `p_teacher_notes` TEXT, IN `p_assessed_by` INT UNSIGNED, IN `p_assessed_date` DATE)   BEGIN
INSERT INTO learner_competencies (
    student_id,
    competency_id,
    academic_year,
    term_id,
    performance_level_id,
    evidence,
    teacher_notes,
    assessed_by,
    assessed_date
  )
VALUES (
    p_student_id,
    p_competency_id,
    p_academic_year,
    p_term_id,
    p_performance_level_id,
    p_evidence,
    p_teacher_notes,
    p_assessed_by,
    p_assessed_date
  ) ON DUPLICATE KEY
UPDATE performance_level_id = p_performance_level_id,
  evidence = p_evidence,
  teacher_notes = p_teacher_notes,
  assessed_by = p_assessed_by,
  assessed_date = p_assessed_date,
  updated_at = NOW();
END""",
)

proc(
    "sp_calculate_annual_scores",
    """PROCEDURE `sp_calculate_annual_scores` (IN `p_academic_year` YEAR(4), IN `p_term1_weight` DECIMAL(3,2), IN `p_term2_weight` DECIMAL(3,2), IN `p_term3_weight` DECIMAL(3,2))   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_student_id INT UNSIGNED;
DECLARE v_grade_level_id INT UNSIGNED;
DECLARE v_term1_score DECIMAL(5, 2);
DECLARE v_term1_grade VARCHAR(4);
DECLARE v_term2_score DECIMAL(5, 2);
DECLARE v_term2_grade VARCHAR(4);
DECLARE v_term3_score DECIMAL(5, 2);
DECLARE v_term3_grade VARCHAR(4);
DECLARE v_annual_score DECIMAL(5, 2);
DECLARE v_annual_percentage DECIMAL(5, 2);
DECLARE v_annual_grade VARCHAR(4);
DECLARE v_annual_points DECIMAL(5, 1);
DECLARE v_annual_rank INT;
DECLARE v_grade_total INT;
DECLARE v_grade_percentile DECIMAL(5, 2);
DECLARE v_avg_formative DECIMAL(5, 2);
DECLARE v_avg_summative DECIMAL(5, 2);
DECLARE v_pathway_classification VARCHAR(20);
DECLARE v_insights_summary JSON;
DECLARE student_cur CURSOR FOR
SELECT DISTINCT s.id,
  sae.academic_year_class_stream_id
FROM students s
JOIN student_academic_enrollments sae ON sae.student_id = s.id
  AND sae.enrollment_status IN ('active', 'pending')
WHERE s.status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error calculating annual scores';
END;
START TRANSACTION;
OPEN student_cur;
annual_loop: LOOP FETCH student_cur INTO v_student_id,
v_grade_level_id;
IF done THEN LEAVE annual_loop;
END IF;

SELECT COALESCE(tc.avg_overall_percentage, 0),
  COALESCE(tc.avg_overall_grade, 'BE2') INTO v_term1_score,
  v_term1_grade
FROM term_consolidations tc
  JOIN academic_year_terms ayt ON ayt.id = tc.academic_year_id * 0 + ayt.id
  JOIN academic_year_terms ayt2 ON ayt2.term_id = tc.term_id AND ayt2.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_academic_year LIMIT 1)
WHERE tc.student_id = v_student_id
  AND tc.term_id = 1
  AND tc.academic_year = p_academic_year
LIMIT 1;

SELECT COALESCE(tc.avg_overall_percentage, 0),
  COALESCE(tc.avg_overall_grade, 'BE2') INTO v_term2_score,
  v_term2_grade
FROM term_consolidations tc
WHERE tc.student_id = v_student_id
  AND tc.term_id = 2
  AND tc.academic_year = p_academic_year
LIMIT 1;

SELECT COALESCE(tc.avg_overall_percentage, 0),
  COALESCE(tc.avg_overall_grade, 'BE2') INTO v_term3_score,
  v_term3_grade
FROM term_consolidations tc
WHERE tc.student_id = v_student_id
  AND tc.term_id = 3
  AND tc.academic_year = p_academic_year
LIMIT 1;

SET v_annual_percentage = ROUND(
    (v_term1_score * p_term1_weight) + (v_term2_score * p_term2_weight) + (v_term3_score * p_term3_weight),
    2
  );

SET v_annual_score = v_annual_percentage;

SET v_annual_grade = calculate_grade(v_annual_percentage);
SET v_annual_points = calculate_points(v_annual_percentage);

SELECT COUNT(*) + 1,
  COUNT(DISTINCT s.id) INTO v_annual_rank,
  v_grade_total
FROM annual_scores ans
  JOIN students s ON ans.student_id = s.id
  JOIN student_academic_enrollments sae ON sae.student_id = s.id
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
WHERE ayc.class_id = (
    SELECT ayc2.class_id
    FROM student_academic_enrollments sae2
    JOIN academic_year_class_streams aycs2 ON aycs2.id = sae2.academic_year_class_stream_id
    JOIN academic_year_classes ayc2 ON ayc2.id = aycs2.academic_year_class_id
    WHERE sae2.student_id = v_student_id
      AND sae2.enrollment_status IN ('active', 'pending')
    LIMIT 1
  )
  AND ans.academic_year = p_academic_year
  AND ans.annual_percentage > v_annual_percentage;

IF v_grade_total > 0 THEN
SET v_grade_percentile = ROUND(
    (
      (v_grade_total - v_annual_rank + 1) / v_grade_total
    ) * 100,
    2
  );
ELSE
SET v_grade_percentile = 0;
SET v_annual_rank = 1;
SET v_grade_total = 1;
END IF;

SELECT ROUND(AVG(formative_percentage), 2),
  ROUND(AVG(summative_percentage), 2) INTO v_avg_formative,
  v_avg_summative
FROM term_subject_scores tss
WHERE tss.student_id = v_student_id
  AND tss.term_id IN (
    SELECT ayt.term_id
    FROM academic_year_terms ayt
    WHERE ayt.academic_year_id = (SELECT id FROM academic_years WHERE year_code = p_academic_year LIMIT 1)
  );

IF v_avg_formative >= 75
AND v_avg_summative >= 75 THEN
SET v_pathway_classification = 'excelling';
ELSEIF v_avg_formative < 40
OR v_avg_summative < 40 THEN
SET v_pathway_classification = 'support_needed';
ELSE
SET v_pathway_classification = 'on_track';
END IF;

SET v_insights_summary = JSON_OBJECT(
    'pathway',
    v_pathway_classification,
    'annual_performance',
    v_annual_grade,
    'annual_percentage',
    v_annual_percentage,
    'class_rank',
    CONCAT(v_annual_rank, ' out of ', v_grade_total),
    'percentile',
    v_grade_percentile,
    'formative_engagement',
    v_avg_formative,
    'summative_mastery',
    v_avg_summative,
    'recommendation',
    CASE
      WHEN v_pathway_classification = 'excelling' THEN 'Student is excelling! Consider advanced/enrichment pathways. Leverage strengths across all competencies.'
      WHEN v_pathway_classification = 'support_needed' THEN 'Student needs additional support. Work with teachers to identify specific competency gaps and develop targeted interventions.'
      ELSE 'Student is on track. Continue current learning pathways while monitoring growth areas.'
    END,
    'next_steps',
    JSON_ARRAY(
      'Review strong and weak subject areas with the student',
      'Set specific competency development goals for next term',
      'Consider enrichment or remedial activities based on pathway',
      'Engage parents in supporting student learning journey'
    )
  );

INSERT INTO annual_scores (
    student_id,
    academic_year,
    term1_weight,
    term1_score,
    term1_grade,
    term2_weight,
    term2_score,
    term2_grade,
    term3_weight,
    term3_score,
    term3_grade,
    annual_score,
    annual_percentage,
    annual_grade,
    annual_points,
    annual_rank,
    grade_total_students,
    grade_percentile,
    avg_formative_percentage,
    avg_summative_percentage,
    pathway_classification,
    insights_summary,
    calculated_at
  )
VALUES (
    v_student_id,
    p_academic_year,
    p_term1_weight,
    v_term1_score,
    v_term1_grade,
    p_term2_weight,
    v_term2_score,
    v_term2_grade,
    p_term3_weight,
    v_term3_score,
    v_term3_grade,
    v_annual_score,
    v_annual_percentage,
    v_annual_grade,
    v_annual_points,
    v_annual_rank,
    v_grade_total,
    v_grade_percentile,
    v_avg_formative,
    v_avg_summative,
    v_pathway_classification,
    v_insights_summary,
    NOW()
  ) ON DUPLICATE KEY
UPDATE term1_weight = p_term1_weight,
  term1_score = v_term1_score,
  term1_grade = v_term1_grade,
  term2_weight = p_term2_weight,
  term2_score = v_term2_score,
  term2_grade = v_term2_grade,
  term3_weight = p_term3_weight,
  term3_score = v_term3_score,
  term3_grade = v_term3_grade,
  annual_score = v_annual_score,
  annual_percentage = v_annual_percentage,
  annual_grade = v_annual_grade,
  annual_points = v_annual_points,
  annual_rank = v_annual_rank,
  grade_total_students = v_grade_total,
  grade_percentile = v_grade_percentile,
  avg_formative_percentage = v_avg_formative,
  avg_summative_percentage = v_avg_summative,
  pathway_classification = v_pathway_classification,
  insights_summary = v_insights_summary,
  calculated_at = NOW(),
  updated_at = NOW();
END LOOP;
CLOSE student_cur;
COMMIT;
END""",
)

proc(
    "sp_calculate_term_subject_score",
    """PROCEDURE `sp_calculate_term_subject_score` (IN `p_student_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED)   BEGIN
DECLARE v_formative_total DECIMAL(8, 2);
DECLARE v_formative_max DECIMAL(8, 2);
DECLARE v_formative_count INT;
DECLARE v_formative_pct DECIMAL(5, 2);
DECLARE v_formative_grade VARCHAR(4);
DECLARE v_summative_total DECIMAL(8, 2);
DECLARE v_summative_max DECIMAL(8, 2);
DECLARE v_summative_count INT;
DECLARE v_summative_pct DECIMAL(5, 2);
DECLARE v_summative_grade VARCHAR(4);
DECLARE v_overall_score DECIMAL(8, 2);
DECLARE v_overall_pct DECIMAL(5, 2);
DECLARE v_overall_grade VARCHAR(4);
DECLARE v_overall_points DECIMAL(3, 1);
DECLARE v_total_count INT;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error calculating term subject score';
END;
START TRANSACTION;

SELECT COALESCE(SUM(ar.marks_obtained), 0),
  COALESCE(SUM(a.max_marks), 0),
  COUNT(ar.id) INTO v_formative_total,
  v_formative_max,
  v_formative_count
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  LEFT JOIN assessment_types ast ON a.assessment_type_id = ast.id
  JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
WHERE ar.student_academic_enrollment_id IN (
    SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = p_student_id
  )
  AND ayt.term_id = p_term_id
  AND a.learning_area_id = p_subject_id
  AND (
    ast.is_formative = 1
    OR a.assessment_type_id IS NULL
  );

IF v_formative_max > 0 THEN
SET v_formative_pct = ROUND((v_formative_total / v_formative_max) * 100, 2);
ELSE
SET v_formative_pct = 0;
END IF;

SET v_formative_grade = calculate_grade(v_formative_pct);

SELECT COALESCE(SUM(ar.marks_obtained), 0),
  COALESCE(SUM(a.max_marks), 0),
  COUNT(ar.id) INTO v_summative_total,
  v_summative_max,
  v_summative_count
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  LEFT JOIN assessment_types ast ON a.assessment_type_id = ast.id
  JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
WHERE ar.student_academic_enrollment_id IN (
    SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = p_student_id
  )
  AND ayt.term_id = p_term_id
  AND a.learning_area_id = p_subject_id
  AND ast.is_summative = 1;

IF v_summative_max > 0 THEN
SET v_summative_pct = ROUND((v_summative_total / v_summative_max) * 100, 2);
ELSE
SET v_summative_pct = 0;
END IF;

SET v_summative_grade = calculate_grade(v_summative_pct);

SET v_total_count = v_formative_count + v_summative_count;
IF v_total_count > 0 THEN
SET v_overall_pct = ROUND(
    (v_formative_pct * 0.4) + (v_summative_pct * 0.6),
    2
  );
SET v_overall_score = ROUND(
    (v_formative_total * 0.4) + (v_summative_total * 0.6),
    2
  );
ELSE
SET v_overall_pct = 0;
SET v_overall_score = 0;
END IF;

SET v_overall_grade = calculate_grade(v_overall_pct);
SET v_overall_points = calculate_points(v_overall_pct);

INSERT INTO term_subject_scores (
    student_id,
    term_id,
    subject_id,
    formative_total,
    formative_max,
    formative_percentage,
    formative_grade,
    formative_count,
    summative_total,
    summative_max,
    summative_percentage,
    summative_grade,
    summative_count,
    overall_score,
    overall_percentage,
    overall_grade,
    overall_points,
    assessment_count,
    calculated_at
  )
VALUES (
    p_student_id,
    p_term_id,
    p_subject_id,
    v_formative_total,
    v_formative_max,
    v_formative_pct,
    v_formative_grade,
    v_formative_count,
    v_summative_total,
    v_summative_max,
    v_summative_pct,
    v_summative_grade,
    v_summative_count,
    v_overall_score,
    v_overall_pct,
    v_overall_grade,
    v_overall_points,
    v_total_count,
    NOW()
  ) ON DUPLICATE KEY
UPDATE formative_total = v_formative_total,
  formative_max = v_formative_max,
  formative_percentage = v_formative_pct,
  formative_grade = v_formative_grade,
  formative_count = v_formative_count,
  summative_total = v_summative_total,
  summative_max = v_summative_max,
  summative_percentage = v_summative_pct,
  summative_grade = v_summative_grade,
  summative_count = v_summative_count,
  overall_score = v_overall_score,
  overall_percentage = v_overall_pct,
  overall_grade = v_overall_grade,
  overall_points = v_overall_points,
  assessment_count = v_total_count,
  calculated_at = NOW(),
  updated_at = NOW();
COMMIT;
END""",
)

proc(
    "sp_compare_to_benchmark",
    """PROCEDURE `sp_compare_to_benchmark` (IN `p_class_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED)   BEGIN
DECLARE v_academic_year YEAR(4);
DECLARE v_grade_level_id INT UNSIGNED;
DECLARE v_class_avg DECIMAL(5, 2);
DECLARE v_benchmark_target DECIMAL(5, 2);
DECLARE v_variance DECIMAL(5, 2);

SELECT ay.year_code,
  c.level_id INTO v_academic_year,
  v_grade_level_id
FROM academic_year_terms ayt
JOIN academic_years ay ON ay.id = ayt.academic_year_id
JOIN classes c ON c.id = p_class_id
WHERE ayt.term_id = p_term_id
LIMIT 1;

SELECT AVG(tss.overall_percentage) INTO v_class_avg
FROM term_subject_scores tss
JOIN students s ON tss.student_id = s.id
JOIN student_academic_enrollments sae ON sae.student_id = s.id
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
WHERE ayc.class_id = p_class_id
  AND tss.subject_id = p_subject_id
  AND tss.term_id = p_term_id;

SELECT target_percentage INTO v_benchmark_target
FROM assessment_benchmarks
WHERE academic_year = v_academic_year
  AND grade_level_id = v_grade_level_id
  AND subject_id = p_subject_id
  AND benchmark_type = 'grade'
LIMIT 1;
IF v_benchmark_target IS NOT NULL THEN
SET v_variance = ROUND(v_class_avg - v_benchmark_target, 2);
END IF;
SELECT p_class_id AS class_id,
  p_subject_id AS subject_id,
  v_class_avg AS class_average,
  v_benchmark_target AS benchmark_target,
  v_variance AS variance,
  IF(v_variance >= 0, 'Exceeds', 'Below') AS performance_status;
END""",
)

proc(
    "sp_consolidate_term_scores",
    """PROCEDURE `sp_consolidate_term_scores` (IN `p_term_id` INT UNSIGNED, IN `p_academic_year` YEAR(4))   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_student_id INT UNSIGNED;
DECLARE v_class_id INT UNSIGNED;
DECLARE v_total_subjects INT;
DECLARE v_avg_percentage DECIMAL(5, 2);
DECLARE v_avg_grade VARCHAR(4);
DECLARE v_performance_json JSON;
DECLARE v_class_position INT;
DECLARE v_class_total INT;
DECLARE v_percentile DECIMAL(5, 2);
DECLARE v_points_total DECIMAL(5, 1);
DECLARE student_cur CURSOR FOR
SELECT DISTINCT s.id,
  ayc.class_id
FROM students s
JOIN student_academic_enrollments sae ON sae.student_id = s.id
  AND sae.enrollment_status IN ('active', 'pending')
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
WHERE s.status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error consolidating term scores';
END;
START TRANSACTION;
OPEN student_cur;
consolidate_loop:LOOP FETCH student_cur INTO v_student_id,
v_class_id;
IF done THEN LEAVE consolidate_loop;
END IF;

SELECT COUNT(DISTINCT subject_id) INTO v_total_subjects
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;
IF v_total_subjects > 0 THEN
SELECT ROUND(AVG(overall_percentage), 2),
  (
    SELECT DISTINCT overall_grade
    FROM term_subject_scores
    WHERE student_id = v_student_id
      AND term_id = p_term_id
    ORDER BY overall_percentage DESC
    LIMIT 1
  ) INTO v_avg_percentage,
  v_avg_grade
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;

SELECT JSON_OBJECTAGG(
    (
      SELECT name
      FROM learning_areas
      WHERE id = tss.subject_id
    ),
    tss.overall_grade
  ) INTO v_performance_json
FROM term_subject_scores tss
WHERE tss.student_id = v_student_id
  AND tss.term_id = p_term_id;

SELECT COALESCE(SUM(overall_points), 0) INTO v_points_total
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;

SELECT COUNT(*) + 1,
  (
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN student_academic_enrollments sae ON sae.student_id = s.id
      AND sae.enrollment_status IN ('active', 'pending')
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    WHERE ayc.class_id = v_class_id
      AND s.status = 'active'
  ) INTO v_class_position,
  v_class_total
FROM term_consolidations tc
  JOIN students s ON tc.student_id = s.id
  JOIN student_academic_enrollments sae ON sae.student_id = s.id
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
WHERE tc.term_id = p_term_id
  AND ayc.class_id = v_class_id
  AND tc.avg_overall_percentage > v_avg_percentage;

IF v_class_total > 0 THEN
SET v_percentile = ROUND(
    (
      (v_class_total - v_class_position + 1) / v_class_total
    ) * 100,
    2
  );
ELSE
SET v_percentile = 0;
END IF;

INSERT INTO term_consolidations (
    student_id,
    term_id,
    academic_year,
    total_subjects,
    total_assessed_subjects,
    avg_overall_percentage,
    avg_overall_grade,
    performance_summary,
    class_position,
    class_total,
    percentile,
    points_total,
    consolidated_at
  )
VALUES (
    v_student_id,
    p_term_id,
    p_academic_year,
    v_total_subjects,
    v_total_subjects,
    v_avg_percentage,
    v_avg_grade,
    v_performance_json,
    v_class_position,
    v_class_total,
    v_percentile,
    v_points_total,
    NOW()
  ) ON DUPLICATE KEY
UPDATE total_subjects = v_total_subjects,
  total_assessed_subjects = v_total_subjects,
  avg_overall_percentage = v_avg_percentage,
  avg_overall_grade = v_avg_grade,
  performance_summary = v_performance_json,
  class_position = v_class_position,
  class_total = v_class_total,
  percentile = v_percentile,
  points_total = v_points_total,
  consolidated_at = NOW(),
  updated_at = NOW();
END IF;
END LOOP;
CLOSE student_cur;
COMMIT;
END""",
)

proc(
    "sp_create_exam_schedule",
    """PROCEDURE `sp_create_exam_schedule` (IN `p_term_id` INT UNSIGNED, IN `p_exam_type` VARCHAR(50), IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_created_by` INT UNSIGNED)   BEGIN
    DECLARE v_current_date DATE;
    DECLARE v_class_stream_id INT UNSIGNED;
    DECLARE v_learning_area_id INT UNSIGNED;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_academic_year_term_id INT UNSIGNED;

    DECLARE subject_cursor CURSOR FOR
        SELECT DISTINCT aycs.id AS class_stream_id, la.id AS learning_area_id
        FROM academic_year_class_streams aycs
        JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
        CROSS JOIN learning_areas la
        WHERE la.status = 'active'
          AND ayc.academic_year_id = v_academic_year_id
          AND aycs.status = 'active'
        ORDER BY aycs.id, la.id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    SELECT ayt.id INTO v_academic_year_term_id
    FROM academic_year_terms ayt
    JOIN academic_years ay ON ay.id = ayt.academic_year_id
    WHERE ayt.term_id = p_term_id
      AND ay.is_current = 1
    LIMIT 1;

    SELECT ayt.academic_year_id INTO v_academic_year_id
    FROM academic_year_terms ayt
    WHERE ayt.id = v_academic_year_term_id
    LIMIT 1;

    START TRANSACTION;

    SET v_current_date = p_start_date;

    OPEN subject_cursor;

    schedule_loop: LOOP
        FETCH subject_cursor INTO v_class_stream_id, v_learning_area_id;

        IF done THEN
            LEAVE schedule_loop;
        END IF;

        INSERT INTO exam_schedules (
            academic_year_class_stream_id,
            academic_year_term_id,
            learning_area_id,
            exam_name,
            exam_type,
            exam_date,
            start_time,
            end_time,
            duration_minutes,
            created_by,
            status
        ) VALUES (
            v_class_stream_id,
            v_academic_year_term_id,
            v_learning_area_id,
            CONCAT(p_exam_type, ' - ', (SELECT name FROM learning_areas WHERE id = v_learning_area_id)),
            p_exam_type,
            v_current_date,
            '08:00:00',
            '11:00:00',
            180,
            p_created_by,
            'scheduled'
        );

        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
        IF v_current_date > p_end_date THEN
            SET v_current_date = p_start_date;
        END IF;

    END LOOP schedule_loop;

    CLOSE subject_cursor;

    COMMIT;

    SELECT 'Exam schedule created successfully' AS status, ROW_COUNT() AS entries_created;
END""",
)

proc(
    "sp_detect_schedule_conflicts",
    """PROCEDURE `sp_detect_schedule_conflicts` (IN `p_class_id` INT, IN `p_term_id` INT)   BEGIN
    SELECT
        cs1.id AS schedule_1_id,
        cs2.id AS schedule_2_id,
        cs1.day_of_week,
        ts1.start_time,
        ts1.end_time,
        'time_overlap' AS conflict_type
    FROM timetable_entries cs1
    JOIN timetable_entries cs2 ON cs1.id < cs2.id
        AND cs1.academic_year_class_stream_id = cs2.academic_year_class_stream_id
        AND cs1.day_of_week = cs2.day_of_week
        AND cs1.time_slot_id = cs2.time_slot_id
    JOIN time_slots ts1 ON ts1.id = cs1.time_slot_id
    JOIN time_slots ts2 ON ts2.id = cs2.time_slot_id
        AND ts1.start_time < ts2.end_time
        AND ts1.end_time > ts2.start_time
    JOIN academic_year_class_streams aycs ON aycs.id = cs1.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    JOIN academic_year_terms ayt ON ayt.id = cs1.academic_year_term_id
    WHERE ayc.class_id = p_class_id
      AND ayt.term_id = p_term_id;
END""",
)

proc(
    "sp_generate_school_year_report",
    """PROCEDURE `sp_generate_school_year_report` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR)   BEGIN
DECLARE v_attendance_percentage DECIMAL(5, 2);
DECLARE v_total_classes INT;
DECLARE v_classes_present INT;

SELECT ROUND(
    SUM(
      CASE
        WHEN sa.status = 'present' THEN 1
        ELSE 0
      END
    ) * 100 / COUNT(*),
    2
  ),
  COUNT(*),
  SUM(
    CASE
      WHEN sa.status = 'present' THEN 1
      ELSE 0
    END
  ) INTO v_attendance_percentage,
  v_total_classes,
  v_classes_present
FROM student_attendance sa
JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
WHERE sae.student_id = p_student_id
  AND YEAR(sa.date) = p_academic_year;

SELECT 'School Year Report' as report_type,
  s.admission_no,
  CONCAT(p.first_name, ' ', p.last_name) as student_name,
  p.dob,
  p.gender,
  c.name as current_class,
  p_academic_year as academic_year,
  v_attendance_percentage as attendance_percentage,
  v_total_classes as total_school_days,
  v_classes_present as days_present,
  (
    SELECT COUNT(*)
    FROM learner_competencies
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as competencies_assessed,
  (
    SELECT COUNT(*)
    FROM learner_values_acquisition
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as values_demonstrated,
  (
    SELECT COALESCE(SUM(hours_contributed), 0)
    FROM learner_csl_participation
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as csl_hours,
  (
    SELECT conduct_rating
    FROM conduct_tracking
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
    LIMIT 1
  ) as conduct_rating
FROM students s
  JOIN persons p ON p.id = s.person_id
  JOIN student_academic_enrollments sae ON sae.student_id = s.id
    AND sae.enrollment_status IN ('active', 'pending')
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
  JOIN classes c ON c.id = ayc.class_id
WHERE s.id = p_student_id;
END""",
)

proc(
    "sp_generate_student_report",
    """PROCEDURE `sp_generate_student_report` (IN `p_student_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED)   BEGIN
  SELECT
    s.admission_no,
    p.first_name,
    p.last_name,
    sl.name AS grade_level,
    c.name AS class_name,
    t.name AS academic_term,
    COUNT(DISTINCT a.id) AS total_assessments,
    AVG(ar.marks_obtained) AS average_score,
    COUNT(DISTINCT sa.id) AS total_school_days,
    COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.id END) AS days_present,
    tr.name AS transport_route,
    ts.name AS pickup_point,
    calculate_term_fees(p_student_id, p_term_id) AS term_fees,
    COALESCE(SUM(pay.amount), 0) AS fees_paid,
    calculate_term_fees(p_student_id, p_term_id) - COALESCE(SUM(pay.amount), 0) AS balance
  FROM students s
  JOIN persons p ON p.id = s.person_id
  JOIN student_academic_enrollments sae ON sae.student_id = s.id
    AND sae.enrollment_status IN ('active', 'pending')
  JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
  JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
  JOIN classes c ON c.id = ayc.class_id
  JOIN school_levels sl ON sl.id = c.level_id
  JOIN academic_year_terms ayt ON ayt.term_id = p_term_id
  JOIN terms t ON t.id = p_term_id
  LEFT JOIN assessments a ON a.academic_year_class_stream_id = aycs.id
    AND a.academic_year_term_id = ayt.id
  LEFT JOIN assessment_results ar ON ar.assessment_id = a.id
    AND ar.student_academic_enrollment_id = sae.id
  LEFT JOIN student_attendance sa ON sa.student_academic_enrollment_id = sae.id
    AND sa.date BETWEEN ayt.opening_date AND ayt.closing_date
  LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id
  LEFT JOIN transport_routes tr ON sta.route_id = tr.id
  LEFT JOIN transport_stops ts ON sta.stop_id = ts.id
  LEFT JOIN payments pay ON pay.student_id = s.id
    AND pay.status IN ('confirmed', 'completed', 'success')
    AND pay.payment_date >= ayt.opening_date
    AND pay.payment_date <= ayt.closing_date
  WHERE s.id = p_student_id
  GROUP BY s.id;
END""",
)

proc(
    "sp_get_assessment_trends",
    """PROCEDURE `sp_get_assessment_trends` (IN `p_student_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED)   BEGIN
SELECT ay.year_code,
  ayt.term_id,
  t.name AS term_name,
  la.name AS subject_name,
  tss.overall_percentage,
  tss.overall_grade,
  tss.formative_percentage,
  tss.summative_percentage,
  tss.assessment_count
FROM term_subject_scores tss
  JOIN academic_year_terms ayt ON ayt.term_id = tss.term_id
  JOIN academic_years ay ON ay.id = ayt.academic_year_id
  JOIN terms t ON t.id = tss.term_id
  JOIN learning_areas la ON tss.subject_id = la.id
WHERE tss.student_id = p_student_id
  AND (
    p_subject_id = 0
    OR tss.subject_id = p_subject_id
  )
ORDER BY ay.year_code DESC,
  ayt.term_id DESC;
END""",
)

proc(
    "sp_get_dormitory_students",
    """PROCEDURE `sp_get_dormitory_students` (IN `p_dormitory_id` INT, IN `p_date` DATE, IN `p_session_id` INT)   BEGIN
    SELECT
        s.id,
        s.admission_no,
        CONCAT(p.first_name, ' ', p.last_name) as student_name,
        c.name as class_name,
        da.bed_number,
        ba.status as current_status,
        ba.check_time,
        ba.notes,
        sp.id as permission_id,
        spt.name as permission_type,
        sp.end_date as permission_until
    FROM dormitory_assignments da
    JOIN students s ON da.student_id = s.id
    JOIN persons p ON p.id = s.person_id
    JOIN student_academic_enrollments sae ON sae.student_id = s.id
      AND sae.enrollment_status IN ('active', 'pending')
    JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    JOIN classes c ON c.id = ayc.class_id
    LEFT JOIN boarding_attendance ba ON s.id = ba.student_id
        AND ba.date = p_date
        AND ba.session_id = p_session_id
        AND ba.dormitory_id = p_dormitory_id
    LEFT JOIN student_permissions sp ON s.id = sp.student_id
        AND p_date BETWEEN sp.start_date AND sp.end_date
        AND sp.status = 'approved'
    LEFT JOIN student_permission_types spt ON sp.permission_type_id = spt.id
    WHERE da.dormitory_id = p_dormitory_id
        AND da.status = 'active'
        AND s.status = 'active'
    ORDER BY p.last_name, p.first_name;
END""",
)

proc(
    "sp_record_conduct",
    """PROCEDURE `sp_record_conduct` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_conduct_rating` VARCHAR(50), IN `p_conduct_comments` TEXT, IN `p_behavior_incidents` JSON, IN `p_teacher_notes` TEXT, IN `p_recorded_by` INT UNSIGNED, IN `p_recorded_date` DATE)   BEGIN
INSERT INTO conduct_tracking (
    student_id,
    academic_year,
    term_id,
    conduct_rating,
    conduct_comments,
    behavior_incidents,
    teacher_notes,
    recorded_by,
    recorded_date
  )
VALUES (
    p_student_id,
    p_academic_year,
    p_term_id,
    p_conduct_rating,
    p_conduct_comments,
    p_behavior_incidents,
    p_teacher_notes,
    p_recorded_by,
    p_recorded_date
  ) ON DUPLICATE KEY
UPDATE conduct_rating = p_conduct_rating,
  conduct_comments = p_conduct_comments,
  behavior_incidents = p_behavior_incidents,
  teacher_notes = p_teacher_notes,
  updated_at = NOW();
END""",
)

proc(
    "sp_record_csl_participation",
    """PROCEDURE `sp_record_csl_participation` (IN `p_student_id` INT UNSIGNED, IN `p_csl_activity_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_hours_contributed` INT, IN `p_role` VARCHAR(100), IN `p_reflection` TEXT, IN `p_teacher_feedback` TEXT)   BEGIN
INSERT INTO learner_csl_participation (
    student_id,
    csl_activity_id,
    academic_year,
    hours_contributed,
    role,
    reflection,
    teacher_feedback,
    participation_status
  )
VALUES (
    p_student_id,
    p_csl_activity_id,
    p_academic_year,
    p_hours_contributed,
    p_role,
    p_reflection,
    p_teacher_feedback,
    'participated'
  ) ON DUPLICATE KEY
UPDATE hours_contributed = p_hours_contributed,
  role = p_role,
  reflection = p_reflection,
  teacher_feedback = p_teacher_feedback,
  updated_at = NOW();
END""",
)

proc(
    "sp_record_discipline_case",
    """PROCEDURE `sp_record_discipline_case` (IN `p_student_id` INT UNSIGNED, IN `p_incident_type` VARCHAR(100), IN `p_description` TEXT, IN `p_severity` ENUM('minor','moderate','serious','critical'), IN `p_reported_by` INT UNSIGNED, OUT `p_case_id` INT UNSIGNED)   BEGIN
    DECLARE v_student_name VARCHAR(255);
    DECLARE v_enrollment_id INT UNSIGNED;
    DECLARE v_ayterm_id INT UNSIGNED;
    DECLARE v_parent_phone VARCHAR(20);

    START TRANSACTION;

    SELECT CONCAT(p.first_name, ' ', p.last_name),
      (SELECT pa.phone FROM persons pa WHERE pa.id = (SELECT sp.parent_id FROM student_parents sp WHERE sp.student_id = s.id LIMIT 1) LIMIT 1)
    INTO v_student_name, v_parent_phone
    FROM students s
    JOIN persons p ON p.id = s.person_id
    WHERE s.id = p_student_id;

    SELECT sae.id INTO v_enrollment_id
    FROM student_academic_enrollments sae
    WHERE sae.student_id = p_student_id
      AND sae.enrollment_status IN ('active', 'pending')
    LIMIT 1;

    SELECT ayt.id INTO v_ayterm_id
    FROM academic_year_terms ayt
    JOIN academic_years ay ON ay.id = ayt.academic_year_id
    WHERE ay.is_current = 1
      AND ayt.status = 'current'
    LIMIT 1;

    INSERT INTO discipline_incidents (
        student_academic_enrollment_id,
        academic_year_term_id,
        type,
        severity,
        description,
        incident_date,
        action_taken,
        status
    ) VALUES (
        v_enrollment_id,
        v_ayterm_id,
        p_incident_type,
        p_severity,
        p_description,
        NOW(),
        NULL,
        'pending'
    );

    SET p_case_id = LAST_INSERT_ID();

    CALL sp_record_conduct(
        p_student_id,
        YEAR(NOW()),
        (SELECT ayt2.term_id FROM academic_year_terms ayt2 WHERE ayt2.status = 'current' ORDER BY ayt2.id DESC LIMIT 1),
        'needs_improvement',
        CONCAT('Discipline incident: ', p_incident_type),
        p_reported_by,
        CURDATE()
    );

    IF p_severity IN ('serious', 'critical') THEN
        CALL sp_send_sms_to_parents(
            JSON_ARRAY(p_student_id),
            CONCAT('URGENT: Discipline incident involving ', v_student_name, '. Please contact school.'),
            p_reported_by
        );
    END IF;

    COMMIT;
END""",
)

proc(
    "sp_record_pci_awareness",
    """PROCEDURE `sp_record_pci_awareness` (IN `p_student_id` INT UNSIGNED, IN `p_pci_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_awareness_level` VARCHAR(50), IN `p_evidence` TEXT, IN `p_learning_activity` VARCHAR(255), IN `p_assessed_by` INT UNSIGNED, IN `p_assessed_date` DATE)   BEGIN
INSERT INTO learner_pci_awareness (
    student_id,
    pci_id,
    academic_year,
    term_id,
    awareness_level,
    evidence,
    learning_activity,
    assessed_by,
    assessed_date
  )
VALUES (
    p_student_id,
    p_pci_id,
    p_academic_year,
    p_term_id,
    p_awareness_level,
    p_evidence,
    p_learning_activity,
    p_assessed_by,
    p_assessed_date
  ) ON DUPLICATE KEY
UPDATE awareness_level = p_awareness_level,
  evidence = p_evidence,
  learning_activity = p_learning_activity,
  assessed_by = p_assessed_by,
  assessed_date = p_assessed_date;
END""",
)

proc(
    "sp_track_student_behavior",
    """PROCEDURE `sp_track_student_behavior` (IN `p_student_id` INT UNSIGNED, IN `p_observation_date` DATE, IN `p_behavior_rating` ENUM('excellent','good','satisfactory','needs_improvement','poor'), IN `p_notes` TEXT, IN `p_recorded_by` INT UNSIGNED)   BEGIN
    DECLARE v_term_id INT UNSIGNED;
    DECLARE v_academic_year YEAR;

    SELECT ayt.term_id, ay.year_code INTO v_term_id, v_academic_year
    FROM academic_year_terms ayt
    JOIN academic_years ay ON ay.id = ayt.academic_year_id
    WHERE ayt.status = 'current'
    LIMIT 1;

    INSERT INTO conduct_tracking (
        student_id,
        academic_year,
        term_id,
        conduct_rating,
        conduct_comments,
        teacher_notes,
        recorded_by,
        recorded_date
    ) VALUES (
        p_student_id,
        v_academic_year,
        v_term_id,
        p_behavior_rating,
        p_notes,
        p_notes,
        p_recorded_by,
        p_observation_date
    )
    ON DUPLICATE KEY UPDATE
        conduct_rating = p_behavior_rating,
        conduct_comments = CONCAT(conduct_comments, '\n', p_observation_date, ': ', p_notes),
        teacher_notes = CONCAT(teacher_notes, '\n', p_observation_date, ': ', p_notes),
        updated_at = NOW();
END""",
)

proc(
    "sp_implement_discipline_action",
    """PROCEDURE `sp_implement_discipline_action` (IN `p_case_id` INT UNSIGNED, IN `p_action_type` ENUM('warning','detention','suspension','expulsion','counseling'), IN `p_action_details` JSON, IN `p_implemented_by` INT UNSIGNED)   BEGIN
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_enrollment_id INT UNSIGNED;

    START TRANSACTION;

    SELECT sae.student_id, di.student_academic_enrollment_id
    INTO v_student_id, v_enrollment_id
    FROM discipline_incidents di
    JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
    WHERE di.id = p_case_id;

    CASE p_action_type
        WHEN 'suspension' THEN
            INSERT INTO student_suspensions (
                student_id, academic_year, suspension_type, reason,
                suspension_date, expected_return_date, suspended_by, status
            ) VALUES (
                v_student_id,
                YEAR(CURDATE()),
                'disciplinary',
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.reason')),
                NOW(),
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.end_date')),
                p_implemented_by,
                'active'
            );

        WHEN 'expulsion' THEN
            UPDATE students
            SET status = 'transferred'
            WHERE id = v_student_id;
            UPDATE student_academic_enrollments
            SET enrollment_status = 'withdrawn'
            WHERE id = v_enrollment_id;

        WHEN 'counseling' THEN
            INSERT INTO student_counseling_cases (
                student_id, case_code, title, case_type, referral_source,
                priority, status, description, assigned_to, opened_by, opened_at
            ) VALUES (
                v_student_id,
                CONCAT('CC-', p_case_id, '-', UNIX_TIMESTAMP()),
                'Disciplinary counseling',
                'disciplinary',
                'discipline',
                'medium',
                'active',
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.notes')),
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.counselor_id')),
                p_implemented_by,
                NOW()
            );
    END CASE;

    UPDATE discipline_incidents
    SET action_taken = p_action_type,
        description = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.notes')), description),
        status = CASE
            WHEN p_action_type = 'expulsion' THEN 'resolved'
            WHEN p_action_type = 'suspension' THEN 'escalated'
            ELSE 'resolved'
        END
    WHERE id = p_case_id;

    CALL sp_send_sms_to_parents(
        JSON_ARRAY(v_student_id),
        CONCAT('Discipline action: ', p_action_type, '. Details sent via email.'),
        p_implemented_by
    );

    COMMIT;
END""",
)

proc(
    "sp_schedule_discipline_hearing",
    """PROCEDURE `sp_schedule_discipline_hearing` (IN `p_case_id` INT UNSIGNED, IN `p_hearing_date` DATETIME, IN `p_panel_members` JSON, IN `p_scheduled_by` INT UNSIGNED)   BEGIN
    DECLARE v_student_id INT UNSIGNED;

    START TRANSACTION;

    SELECT sae.student_id INTO v_student_id
    FROM discipline_incidents di
    JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
    WHERE di.id = p_case_id;

    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'discipline_hearing_scheduled',
        JSON_OBJECT(
            'case_id', p_case_id,
            'student_id', v_student_id,
            'hearing_date', p_hearing_date,
            'panel_members', p_panel_members,
            'scheduled_by', p_scheduled_by
        ),
        NOW()
    );

    UPDATE discipline_incidents
    SET status = 'escalated'
    WHERE id = p_case_id;

    CALL sp_send_internal_message(
        p_panel_members,
        'Discipline Hearing Scheduled',
        CONCAT('You are scheduled for a discipline hearing on ', p_hearing_date),
        p_scheduled_by
    );

    COMMIT;
END""",
)

# ---------------------------------------------------------------------------
# Auth
# ---------------------------------------------------------------------------

proc(
    "sp_assign_role",
    """PROCEDURE `sp_assign_role` (IN `p_user_id` INT UNSIGNED, IN `p_role_name` VARCHAR(50), IN `p_assigned_by` INT UNSIGNED, IN `p_reason` VARCHAR(255), OUT `p_success` TINYINT) MODIFIES SQL DATA BEGIN
    DECLARE role_id INT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    SELECT id INTO role_id FROM roles WHERE name = p_role_name LIMIT 1;

    IF role_id IS NULL THEN
        SET p_success = 0;
    ELSE
        INSERT IGNORE INTO user_roles (user_id, role_id)
        VALUES (p_user_id, role_id);

        INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
        VALUES ('assign_role', 'user_roles', p_user_id, p_assigned_by, JSON_OBJECT('role_id', role_id, 'reason', p_reason), 'success', NOW());

        SET p_success = 1;
    END IF;
END""",
)

proc(
    "sp_check_form_permission",
    """PROCEDURE `sp_check_form_permission` (IN `p_user_id` INT UNSIGNED, IN `p_form_code` VARCHAR(50), IN `p_action` VARCHAR(50), OUT `p_has_permission` INT)   BEGIN
DECLARE v_allowed_actions LONGTEXT;
SELECT fp.actions INTO v_allowed_actions
FROM form_permissions fp
WHERE fp.form_code = p_form_code
LIMIT 1;
IF EXISTS (
    SELECT 1
    FROM v_user_permissions_effective v
    WHERE v.user_id = p_user_id
      AND v.permission_code = p_form_code
) THEN
IF v_allowed_actions IS NULL OR JSON_CONTAINS(v_allowed_actions, JSON_QUOTE(p_action)) THEN
SET p_has_permission = 1;
ELSE
SET p_has_permission = 0;
END IF;
ELSE
SET p_has_permission = 0;
END IF;
END""",
)

proc(
    "sp_cleanup_failed_attempts",
    """PROCEDURE `sp_cleanup_failed_attempts` ()   BEGIN
    DELETE FROM login_attempts
    WHERE status = 'failed'
      AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

    SELECT ROW_COUNT() as deleted_records;
END""",
)

proc(
    "sp_deny_permission",
    """PROCEDURE `sp_deny_permission` (IN `p_user_id` INT UNSIGNED, IN `p_permission_code` VARCHAR(255), IN `p_reason` VARCHAR(255), IN `p_changed_by` INT UNSIGNED, OUT `p_success` TINYINT)  MODIFIES SQL DATA BEGIN
    DECLARE perm_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    SELECT id INTO perm_id FROM permissions WHERE code = p_permission_code LIMIT 1;

    IF perm_id IS NULL THEN
        SET p_success = 0;
    ELSE
        INSERT INTO user_permissions (user_id, permission_id, permission_type, reason, granted_by)
        VALUES (p_user_id, perm_id, 'deny', p_reason, p_changed_by)
        ON DUPLICATE KEY UPDATE
            permission_type = 'deny',
            reason = p_reason,
            updated_at = NOW();

        INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
        VALUES ('deny_permission', 'user_permissions', p_user_id, p_changed_by, JSON_OBJECT('permission_id', perm_id, 'reason', p_reason), 'success', NOW());

        SET p_success = 1;
    END IF;
END""",
)

proc(
    "sp_grant_permission",
    """PROCEDURE `sp_grant_permission` (IN `p_user_id` INT UNSIGNED, IN `p_permission_code` VARCHAR(255), IN `p_reason` VARCHAR(255), IN `p_granted_by` INT UNSIGNED, IN `p_expires_at` TIMESTAMP, OUT `p_success` TINYINT)  MODIFIES SQL DATA BEGIN
    DECLARE perm_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    SELECT id INTO perm_id FROM permissions WHERE code = p_permission_code LIMIT 1;

    IF perm_id IS NULL THEN
        SET p_success = 0;
    ELSE
        INSERT INTO user_permissions (user_id, permission_id, permission_type, reason, granted_by, expires_at)
        VALUES (p_user_id, perm_id, 'grant', p_reason, p_granted_by, p_expires_at)
        ON DUPLICATE KEY UPDATE
            permission_type = 'grant',
            reason = p_reason,
            granted_by = p_granted_by,
            expires_at = p_expires_at,
            updated_at = NOW();

        INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
        VALUES ('grant_permission', 'user_permissions', p_user_id, p_granted_by, JSON_OBJECT('permission_id', perm_id, 'reason', p_reason), 'success', NOW());

        SET p_success = 1;
    END IF;
END""",
)

proc(
    "sp_record_login_attempt",
    """PROCEDURE `sp_record_login_attempt` (IN `p_username` VARCHAR(50), IN `p_ip_address` VARCHAR(45), IN `p_attempt_status` VARCHAR(20), IN `p_failure_reason` VARCHAR(255))   BEGIN
DECLARE v_failed_count INT;
DECLARE v_user_id INT UNSIGNED;
INSERT INTO login_attempts (
    username,
    user_id,
    ip_address,
    status,
    failure_reason
  )
SELECT
    p_username,
    (SELECT id FROM users WHERE username = p_username LIMIT 1),
    p_ip_address,
    p_attempt_status,
    p_failure_reason
FROM (SELECT 1) x;
IF p_attempt_status = 'failed' THEN
SELECT COUNT(*) INTO v_failed_count
FROM login_attempts
WHERE username = p_username
  AND ip_address = p_ip_address
  AND status = 'failed'
  AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
IF v_failed_count >= 5 THEN
SELECT id INTO v_user_id
FROM users
WHERE username = p_username
LIMIT 1;
IF v_user_id IS NOT NULL THEN
UPDATE users
SET status = 'suspended',
    failed_login_attempts = v_failed_count,
    account_locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
WHERE id = v_user_id;
INSERT INTO login_attempts (
    username,
    user_id,
    ip_address,
    status,
    failure_reason
  )
VALUES (p_username, v_user_id, p_ip_address, 'failed', 'account locked after repeated failures');
END IF;
END IF;
END IF;
END""",
)

proc(
    "sp_revoke_permission",
    """PROCEDURE `sp_revoke_permission` (IN `p_user_id` INT UNSIGNED, IN `p_permission_code` VARCHAR(255), IN `p_reason` VARCHAR(255), IN `p_changed_by` INT UNSIGNED, OUT `p_success` TINYINT)  MODIFIES SQL DATA BEGIN
    DECLARE perm_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    SELECT id INTO perm_id FROM permissions WHERE code = p_permission_code LIMIT 1;

    IF perm_id IS NULL THEN
        SET p_success = 0;
    ELSE
        DELETE FROM user_permissions
        WHERE user_id = p_user_id AND permission_id = perm_id;

        INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
        VALUES ('revoke_permission', 'user_permissions', p_user_id, p_changed_by, JSON_OBJECT('permission_id', perm_id, 'reason', p_reason), 'success', NOW());

        SET p_success = 1;
    END IF;
END""",
)

proc(
    "sp_revoke_role",
    """PROCEDURE `sp_revoke_role` (IN `p_user_id` INT UNSIGNED, IN `p_role_name` VARCHAR(50), IN `p_reason` VARCHAR(255), IN `p_changed_by` INT UNSIGNED, OUT `p_success` TINYINT)  MODIFIES SQL DATA BEGIN
    DECLARE v_role_id INT UNSIGNED;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    SELECT id INTO v_role_id FROM roles WHERE name = p_role_name LIMIT 1;

    IF v_role_id IS NULL THEN
        SET p_success = 0;
    ELSE
        DELETE FROM user_roles WHERE user_id = p_user_id AND role_id = v_role_id;

        INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
        VALUES ('revoke_role', 'user_roles', p_user_id, p_changed_by, JSON_OBJECT('role_id', v_role_id, 'reason', p_reason), 'success', NOW());

        SET p_success = 1;
    END IF;
END""",
)

proc(
    "sp_unlock_user_account",
    """PROCEDURE `sp_unlock_user_account` (IN `p_user_id` INT UNSIGNED, IN `p_unlocked_by` INT UNSIGNED, IN `p_unlock_reason` TEXT)   BEGIN
UPDATE users
SET status = 'active',
    failed_login_attempts = 0,
    account_locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
WHERE id = p_user_id;
INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
VALUES (
    'unlock_account',
    'users',
    p_user_id,
    p_unlocked_by,
    p_unlock_reason,
    'success',
    NOW()
);
INSERT INTO system_events (event_type, event_data)
VALUES (
    'account_unlocked',
    JSON_OBJECT(
      'user_id',
      p_user_id,
      'unlocked_by',
      p_unlocked_by
    )
  );
END""",
)

# ---------------------------------------------------------------------------
# Misc
# ---------------------------------------------------------------------------

proc(
    "sp_assign_student_transport",
    """PROCEDURE `sp_assign_student_transport` (IN `p_student_id` INT, IN `p_route_id` INT, IN `p_stop_id` INT, IN `p_month` INT, IN `p_year` INT)   BEGIN
    INSERT INTO student_transport_assignments (
        id, student_id, route_id, stop_id, month, year, expected_amount, status, assignment_date, assigned_by
    )
    SELECT COALESCE(MAX(id), 0) + 1,
        p_student_id,
        p_route_id,
        p_stop_id,
        p_month,
        p_year,
        (SELECT COALESCE(fee, 0) FROM transport_routes WHERE id = p_route_id),
        'active',
        CURDATE(),
        NULL
    FROM student_transport_assignments
    WHERE NOT EXISTS (
        SELECT 1
        FROM student_transport_assignments sta
        WHERE sta.student_id = p_student_id
          AND sta.month = p_month
          AND sta.year = p_year
          AND sta.status IN ('active', 'suspended')
    );

    SELECT
        p_student_id AS student_id,
        p_route_id AS route_id,
        p_stop_id AS stop_id,
        p_month AS month,
        p_year AS year,
        'assigned' AS status;
END""",
)

proc(
    "sp_auto_rollover_fee_structures",
    """PROCEDURE `sp_auto_rollover_fee_structures` (IN `p_from_year` INT, IN `p_to_year` INT, IN `p_apply_increase` TINYINT, OUT `p_copied` INT, OUT `p_log_id` INT)   BEGIN
    DECLARE v_copied INT DEFAULT 0;
    DECLARE v_from_year_id INT UNSIGNED;
    DECLARE v_to_year_id INT UNSIGNED;
    DECLARE v_rollover_id VARCHAR(20);

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
    SET v_rollover_id = CONCAT('FS', p_from_year, '-', p_to_year);

    INSERT INTO academic_year_rollover_log (
        rollover_id, from_year_id, to_year_id, step, status, details, performed_at
    )
    VALUES (
        v_rollover_id, v_from_year_id, v_to_year_id, 'create_new_year', 'completed',
        JSON_OBJECT('records_copied', v_copied, 'apply_increase', p_apply_increase),
        NOW()
    );

    SET p_log_id = LAST_INSERT_ID();
END""",
)

proc(
    "sp_bulk_mark_staff_attendance",
    """PROCEDURE `sp_bulk_mark_staff_attendance` (IN `p_department_id` INT, IN `p_date` DATE, IN `p_status` VARCHAR(20), IN `p_marked_by` INT)   BEGIN
    INSERT INTO staff_attendance (id, staff_id, date, status, marked_by, created_at)
    SELECT
        (SELECT COALESCE(MAX(id), 0) FROM staff_attendance) + ROW_NUMBER() OVER (ORDER BY s.id),
        s.id,
        p_date,
        p_status,
        p_marked_by,
        NOW()
    FROM staff s
    JOIN staff_department_assignments sda ON sda.staff_id = s.id
    WHERE sda.department_id = p_department_id
      AND s.status = 'active'
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        marked_by = VALUES(marked_by),
        updated_at = NOW();
END""",
)

proc(
    "sp_check_class_space_availability",
    """PROCEDURE `sp_check_class_space_availability` (IN `p_application_id` INT, IN `p_user_id` INT)   BEGIN
    DECLARE v_grade_applying_for VARCHAR(50);
    DECLARE v_academic_year YEAR;
    DECLARE v_grade_name VARCHAR(50);
    DECLARE v_target_class_id INT;
    DECLARE v_academic_year_class_id INT;
    DECLARE v_class_capacity INT DEFAULT 0;
    DECLARE v_current_student_count INT DEFAULT 0;
    DECLARE v_available_spaces INT DEFAULT 0;
    DECLARE v_space_available BOOLEAN DEFAULT FALSE;
    DECLARE v_space_message TEXT DEFAULT 'No class found for the applied grade and academic year';
    DECLARE v_requires_assessment BOOLEAN DEFAULT FALSE;

    SELECT grade_applying_for, academic_year
    INTO v_grade_applying_for, v_academic_year
    FROM admission_applications
    WHERE id = p_application_id;

    SET v_grade_name = TRIM(REPLACE(REPLACE(v_grade_applying_for, 'Grade', 'Grade '), '  ', ' '));

    SELECT c.id, ayc.id
    INTO v_target_class_id, v_academic_year_class_id
    FROM classes c
    JOIN academic_year_classes ayc ON ayc.class_id = c.id
    JOIN academic_years ay ON ay.id = ayc.academic_year_id
    WHERE c.name COLLATE utf8mb4_general_ci = v_grade_name COLLATE utf8mb4_general_ci
      AND ay.year_code = v_academic_year
    LIMIT 1;

    SET v_requires_assessment = (v_target_class_id IS NOT NULL AND v_target_class_id >= 8);

    IF v_academic_year_class_id IS NOT NULL THEN
        SELECT COALESCE(SUM(aycs.capacity), 0)
        INTO v_class_capacity
        FROM academic_year_class_streams aycs
        WHERE aycs.academic_year_class_id = v_academic_year_class_id;

        SELECT COUNT(*)
        INTO v_current_student_count
        FROM student_academic_enrollments sae
        JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
        WHERE aycs.academic_year_class_id = v_academic_year_class_id
          AND sae.enrollment_status IN ('active', 'pending');

        SET v_available_spaces = GREATEST(v_class_capacity - v_current_student_count, 0);
        SET v_space_available = v_available_spaces > 0;

        IF v_space_available THEN
            SET v_space_message = CONCAT('Class space available: ', v_available_spaces, ' slots out of ', v_class_capacity, ' total capacity.');
        ELSE
            SET v_space_message = CONCAT('No space available. Class is at capacity (', v_current_student_count, '/', v_class_capacity, ').');
        END IF;
    END IF;

    SELECT
        v_space_available AS space_available,
        v_available_spaces AS available_spaces,
        v_space_message AS space_message,
        v_target_class_id AS class_id,
        v_class_capacity AS capacity,
        v_current_student_count AS current_count,
        v_academic_year AS academic_year_id,
        v_requires_assessment AS requires_assessment;
END""",
)

proc(
    "sp_check_student_transport_status",
    """PROCEDURE `sp_check_student_transport_status` (IN `p_student_id` INT, IN `p_month` INT, IN `p_year` INT)   BEGIN
    SELECT
        sta.id,
        sta.student_id,
        sta.status,
        sta.expected_amount AS amount,
        COALESCE(tbp.payment_method, 'none') AS payment_method,
        tbp.payment_date AS paid_at,
        tr.name AS route_name
    FROM student_transport_assignments sta
    LEFT JOIN transport_routes tr ON tr.id = sta.route_id
    LEFT JOIN transport_monthly_bills tmb ON tmb.student_id = sta.student_id
        AND MONTH(tmb.billing_month) = p_month
        AND YEAR(tmb.billing_month) = p_year
    LEFT JOIN transport_bill_payments tbp ON tbp.bill_id = tmb.id
    WHERE sta.student_id = p_student_id
      AND sta.month = p_month
      AND sta.year = p_year
    ORDER BY sta.created_at DESC
    LIMIT 1;
END""",
)

proc(
    "sp_enroll_student_in_class",
    """PROCEDURE `sp_enroll_student_in_class` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year_id` INT UNSIGNED, IN `p_class_id` INT UNSIGNED, IN `p_stream_id` INT UNSIGNED, IN `p_enrollment_date` DATE, OUT `p_enrollment_id` INT UNSIGNED)   BEGIN
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_existing_enrollment_id INT UNSIGNED;
    DECLARE v_enrollment_date DATE;
    DECLARE v_academic_year_class_stream_id INT UNSIGNED;

    IF p_academic_year_id IS NULL THEN
        SELECT id INTO v_academic_year_id
        FROM academic_years
        WHERE is_current = 1
        LIMIT 1;
    ELSE
        SET v_academic_year_id = p_academic_year_id;
    END IF;

    SET v_enrollment_date = COALESCE(p_enrollment_date, CURDATE());

    SELECT aycs.id INTO v_academic_year_class_stream_id
    FROM academic_year_classes ayc
    JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
    WHERE ayc.academic_year_id = v_academic_year_id
      AND ayc.class_id = p_class_id
      AND aycs.stream_id = p_stream_id
    LIMIT 1;

    SELECT id INTO v_existing_enrollment_id
    FROM student_academic_enrollments
    WHERE student_id = p_student_id
      AND academic_year_id = v_academic_year_id
      AND enrollment_status IN ('active', 'pending')
    LIMIT 1;

    IF v_existing_enrollment_id IS NOT NULL THEN
        UPDATE student_academic_enrollments
        SET academic_year_class_stream_id = v_academic_year_class_stream_id,
            enrolled_on = v_enrollment_date
        WHERE id = v_existing_enrollment_id;

        SET p_enrollment_id = v_existing_enrollment_id;
    ELSE
        INSERT INTO student_academic_enrollments (
            id, student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status
        )
        SELECT COALESCE(MAX(id), 0) + 1,
            p_student_id,
            v_academic_year_id,
            v_academic_year_class_stream_id,
            v_enrollment_date,
            'active'
        FROM student_academic_enrollments;

        SET p_enrollment_id = (SELECT MAX(id) FROM student_academic_enrollments WHERE student_id = p_student_id);
    END IF;
END""",
)

proc(
    "sp_get_payments_by_admission",
    """PROCEDURE `sp_get_payments_by_admission` (IN `p_admission_number` VARCHAR(50), IN `p_limit` INT)   BEGIN
    SELECT
        payment_source,
        source_id,
        reference_code,
        student_id,
        admission_number,
        student_name,
        amount,
        transaction_date,
        contact,
        status,
        created_at
    FROM vw_payment_tracking
    WHERE admission_number = p_admission_number
    ORDER BY transaction_date DESC
    LIMIT p_limit;
END""",
)

proc(
    "sp_initialize_transfer_clearances",
    """PROCEDURE `sp_initialize_transfer_clearances` (IN `p_transfer_id` INT)   BEGIN

    UPDATE students s
    JOIN student_transitions st ON st.student_id = s.id
    SET s.status = 'transferred'
    WHERE st.id = p_transfer_id
      AND st.transition_type = 'transfer';

    UPDATE student_academic_enrollments sae
    JOIN student_transitions st ON st.student_id = sae.student_id
    SET sae.enrollment_status = 'transferred'
    WHERE st.id = p_transfer_id
      AND st.transition_type = 'transfer';
END""",
)

proc(
    "sp_link_parent_to_student",
    """PROCEDURE `sp_link_parent_to_student` (IN `p_parent_id` INT, IN `p_student_id` INT, IN `p_relationship` VARCHAR(50), IN `p_is_primary_contact` TINYINT, IN `p_is_emergency_contact` TINYINT, IN `p_financial_responsibility` DECIMAL(5,2), OUT `p_success` TINYINT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        SET p_success = 0;
    END;

    INSERT INTO student_parents (
        student_id, parent_id, relationship,
        is_primary_contact, is_emergency_contact
    ) VALUES (
        p_student_id, p_parent_id, p_relationship,
        p_is_primary_contact, p_is_emergency_contact
    ) ON DUPLICATE KEY UPDATE
        relationship = p_relationship,
        is_primary_contact = p_is_primary_contact,
        is_emergency_contact = p_is_emergency_contact;

    SET p_success = 1;
END""",
)

proc(
    "sp_migrate_admission_applications_to_new_workflow",
    """PROCEDURE `sp_migrate_admission_applications_to_new_workflow` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_app_id INT;
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_app_status VARCHAR(50);
    DECLARE v_doc_count INT;
    DECLARE v_verified_count INT;
    DECLARE v_rejected_count INT;
    DECLARE v_has_student_id INT;
    DECLARE v_enrolled_student_id INT;
    DECLARE v_workflow_data_json LONGTEXT;

    DECLARE app_cursor CURSOR FOR
        SELECT
            aa.id,
            COALESCE(wi.current_stage, 'application') as current_stage,
            aa.status,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count,
            aa.enrolled_student_id,
            aa.workflow_data_json
        FROM admission_applications aa
        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
        WHERE aa.status NOT IN ('enrolled', 'cancelled');

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    CREATE TEMPORARY TABLE IF NOT EXISTS migration_results (
        application_id INT,
        old_stage VARCHAR(50),
        new_stage VARCHAR(50),
        migration_notes TEXT
    );

    OPEN app_cursor;

    read_loop: LOOP
        FETCH app_cursor INTO v_app_id, v_current_stage, v_app_status, v_doc_count, v_verified_count, v_rejected_count, v_enrolled_student_id, v_workflow_data_json;

        IF done THEN
            LEAVE read_loop;
        END IF;

        SET v_workflow_data_json = COALESCE(v_workflow_data_json, '{}');

        CASE v_current_stage
            WHEN 'application' THEN
                IF v_doc_count = 0 THEN
                    UPDATE workflow_instances
                    SET current_stage = 'application_review', stage_code = 'application_review'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'application_review', 'No documents uploaded - moved to application_review');
                ELSEIF v_verified_count = 0 THEN
                    UPDATE workflow_instances
                    SET current_stage = 'documents_verification', stage_code = 'documents_verification'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                    UPDATE admission_applications
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_uploaded', 'true', '$.documents_uploaded_at', NOW())
                    WHERE id = v_app_id;

                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_verification', 'Documents uploaded but not verified');
                ELSE
                    UPDATE workflow_instances
                    SET current_stage = 'class_space_check', stage_code = 'class_space_check'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                    UPDATE admission_applications
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_uploaded', 'true', '$.documents_verified', 'true', '$.documents_verified_at', NOW())
                    WHERE id = v_app_id;

                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'class_space_check', 'Documents verified - moved to class_space_check');
                END IF;

            WHEN 'document_verification' THEN
                IF v_rejected_count > 0 THEN
                    UPDATE workflow_instances
                    SET current_stage = 'documents_upload', stage_code = 'documents_upload'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                    UPDATE admission_applications
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_rejected', 'true')
                    WHERE id = v_app_id;

                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_upload', 'Rejected documents - moved back to documents_upload');
                ELSEIF v_verified_count < v_doc_count THEN
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_verification', 'Partial verification - kept at documents_verification');
                ELSE
                    UPDATE workflow_instances
                    SET current_stage = 'class_space_check', stage_code = 'class_space_check'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                    UPDATE admission_applications
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_verified', 'true', '$.documents_verified_at', NOW())
                    WHERE id = v_app_id;

                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'class_space_check', 'All documents verified - moved to class_space_check');
                END IF;

            WHEN 'interview_scheduling' THEN
                UPDATE workflow_instances
                SET stage_code = 'interview_scheduling'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'interview_scheduling', 'Stage preserved');

            WHEN 'interview_assessment' THEN
                UPDATE workflow_instances
                SET current_stage = 'interview_results', stage_code = 'interview_results'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'interview_results', 'Renamed from interview_assessment to interview_results');

            WHEN 'placement_offer' THEN
                UPDATE workflow_instances
                SET current_stage = 'admission_decision', stage_code = 'admission_decision'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'admission_decision', 'Moved from placement_offer to admission_decision');

            WHEN 'fee_payment' THEN
                UPDATE workflow_instances
                SET current_stage = 'fees_payment', stage_code = 'fees_payment'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'fees_payment', 'Renamed from fee_payment to fees_payment');

            WHEN 'enrollment' THEN
                UPDATE workflow_instances
                SET current_stage = 'final_approval', stage_code = 'final_approval'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'final_approval', 'Moved from enrollment to final_approval');

            WHEN 'director_confirmation' THEN
                UPDATE workflow_instances
                SET current_stage = 'final_approval', stage_code = 'final_approval'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'final_approval', 'Director confirmation renamed to final_approval');

            ELSE
                UPDATE workflow_instances
                SET current_stage = 'application_review', stage_code = 'application_review'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;

                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'application_review', CONCAT('Unknown stage mapped to application_review'));
        END CASE;

    END LOOP;

    CLOSE app_cursor;

    SELECT * FROM migration_results ORDER BY application_id;

    DROP TEMPORARY TABLE IF EXISTS migration_results;
END""",
)

proc(
    "sp_process_requisition",
    """PROCEDURE `sp_process_requisition` (IN `p_requisition_id` INT UNSIGNED, IN `p_action` VARCHAR(50), IN `p_approved_by` INT UNSIGNED, IN `p_rejection_reason` TEXT)   BEGIN
    DECLARE v_error_msg VARCHAR(255);
    DECLARE v_current_status VARCHAR(50);
    DECLARE v_requisition_number VARCHAR(50);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END;

    SELECT status, requisition_number
    INTO v_current_status, v_requisition_number
    FROM requisitions
    WHERE id = p_requisition_id;

    IF v_requisition_number IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Requisition not found';
    END IF;

    CASE p_action
        WHEN 'submit' THEN
            UPDATE requisitions
            SET status = 'pending',
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status = 'pending';

        WHEN 'approve' THEN
            IF p_approved_by IS NULL THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Approver ID required';
            END IF;

            UPDATE requisitions
            SET status = 'approved',
                approved_by = p_approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status = 'pending';

        WHEN 'reject' THEN
            IF p_rejection_reason IS NULL THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Rejection reason required';
            END IF;

            UPDATE requisitions
            SET status = 'rejected',
                rejection_reason = p_rejection_reason,
                approved_by = p_approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status = 'pending';

        WHEN 'cancel' THEN
            UPDATE requisitions
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status != 'fulfilled';

        ELSE
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid action';
    END CASE;

    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        CONCAT('requisition_', p_action),
        JSON_OBJECT(
            'requisition_id', p_requisition_id,
            'action', p_action
        ),
        NOW()
    );
END""",
)

proc(
    "sp_process_staff_performance_review",
    """PROCEDURE `sp_process_staff_performance_review` (IN `p_staff_id` INT, OUT `p_score` DECIMAL(5,2), OUT `p_grade` VARCHAR(10))   BEGIN
    DECLARE v_avg_score DECIMAL(5,2);
    SELECT
        COALESCE(AVG(kpi.score), 0)
    INTO v_avg_score
    FROM performance_reviews pr
    JOIN performance_review_kpis kpi ON kpi.review_id = pr.id
    WHERE pr.staff_id = p_staff_id
      AND pr.status = 'acknowledged'
      AND YEAR(pr.review_date) = YEAR(CURDATE());

    SET p_score = v_avg_score;
    SET p_grade = CASE
        WHEN v_avg_score >= 90 THEN 'A'
        WHEN v_avg_score >= 75 THEN 'B'
        WHEN v_avg_score >= 60 THEN 'C'
        WHEN v_avg_score >= 50 THEN 'D'
        ELSE 'F'
    END;
END""",
)

proc(
    "sp_transition_to_new_term",
    """PROCEDURE `sp_transition_to_new_term` (IN `p_current_term` INT, IN `p_new_term` INT)   BEGIN

    UPDATE academic_year_terms SET status = 'completed' WHERE term_id = p_current_term AND status = 'current';

    UPDATE academic_year_terms SET status = 'current' WHERE term_id = p_new_term AND status = 'upcoming';
END""",
)

proc(
    "sp_validate_term_holiday_conflicts",
    """PROCEDURE `sp_validate_term_holiday_conflicts` (IN `p_start_date` DATE, IN `p_end_date` DATE)   BEGIN
    SELECT
        COALESCE(acd.title, cdt.name) AS event_name,
        acd.date AS start_date,
        acd.date AS end_date,
        cdt.code AS conflict_type
    FROM academic_year_calendar_days acd
    JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id
    WHERE acd.date BETWEEN p_start_date AND p_end_date
      AND cdt.code IN ('holiday', 'public_holiday', 'school_holiday', 'exam_day')
    ORDER BY acd.date;
END""",
)

# __END__


# ---------------------------------------------------------------------------
# NEW admissions/enrollment automation (2026 deliverable round)
# ---------------------------------------------------------------------------
fn(
    "fn_generate_admission_no",
    """
FUNCTION `fn_generate_admission_no` (p_year INT) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
DECLARE v_seq INT;
SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(admission_no, '-', -1) AS UNSIGNED)), 0) + 1 INTO v_seq
FROM students
WHERE admission_no LIKE CONCAT('KA-', p_year, '-%');
RETURN CONCAT('KA-', p_year, '-', LPAD(v_seq, 4, '0'));
END
""",
)
proc(
    "sp_register_applicant_as_student",
    """
PROCEDURE `sp_register_applicant_as_student` (
    IN p_application_id INT UNSIGNED,
    IN p_operator_id INT UNSIGNED,
    IN p_student_type_id INT UNSIGNED,
    OUT p_student_id INT UNSIGNED,
    OUT p_admission_no VARCHAR(20)
)
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

    -- Load the application (interview passed / placement offered → enroll)
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

    -- Split the applicant name into first/last
    SET v_first_name = TRIM(SUBSTRING_INDEX(v_applicant_name, ' ', 1));
    SET v_last_name = TRIM(SUBSTRING_INDEX(v_applicant_name, ' ', -1));
    IF v_last_name = v_first_name THEN
        SET v_last_name = NULL;
    END IF;

    -- Create the shared person record (manual id)
    INSERT INTO persons (id, first_name, middle_name, last_name, dob, gender)
    SELECT COALESCE(MAX(id), 0) + 1, v_first_name, NULL, v_last_name, v_dob, v_gender
    FROM persons;
    SET v_person_id = (SELECT MAX(id) FROM persons);

    -- Generate the admission number
    SET v_year = YEAR(CURDATE());
    SET p_admission_no = fn_generate_admission_no(v_year);

    -- Create the student record
    INSERT INTO students (id, person_id, admission_no, status, student_type_id, application_id, admission_date, created_at, updated_at)
    SELECT COALESCE(MAX(id), 0) + 1, v_person_id, p_admission_no, 'active', p_student_type_id, p_application_id, CURDATE(), NOW(), NOW()
    FROM students;
    SET p_student_id = (SELECT MAX(id) FROM students);

    -- Link the parent/guardian from the application form
    IF v_parent_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM student_parents WHERE student_id = p_student_id AND parent_id = v_parent_id) THEN
        INSERT INTO student_parents (student_id, parent_id, relationship, is_primary_contact, is_emergency_contact)
        VALUES (p_student_id, v_parent_id, 'parent', 1, 1);
    END IF;

    -- Mark the application as enrolled
    UPDATE admission_applications
       SET status = 'enrolled',
           enrolled_student_id = p_student_id,
           enrolled_at = COALESCE(enrolled_at, NOW()),
           updated_at = NOW()
     WHERE id = p_application_id;

    INSERT INTO system_events (event_type, event_data)
    VALUES (
        'applicant_registered_as_student',
        JSON_OBJECT(
            'application_id', p_application_id,
            'application_no', v_application_no,
            'student_id', p_student_id,
            'admission_no', p_admission_no,
            'person_id', v_person_id,
            'operator_id', p_operator_id
        )
    );

    SELECT p_student_id AS student_id, p_admission_no AS admission_no;
END
""",
)
proc(
    "sp_onboard_student_enrollment",
    """
PROCEDURE `sp_onboard_student_enrollment` (
    IN p_enrollment_id INT UNSIGNED,
    IN p_operator_id INT UNSIGNED,
    OUT p_obligations_generated INT
)
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

    -- 1) Seed class learning areas from the curriculum master when the class has none
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

    -- 2) Generate the fee account/billing from the year/term fee schedules (idempotent)
    CALL sp_generate_student_fee_obligations(v_student_id, v_academic_year_id, NULL, p_obligations_generated);
    SELECT COUNT(*) INTO p_obligations_generated
    FROM student_fee_obligations
    WHERE student_academic_enrollment_id = p_enrollment_id;

    -- 3) Auto-assign a dormitory for boarders with no active assignment
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

    -- 4) Log the onboarding event
    INSERT INTO system_events (event_type, event_data)
    VALUES (
        'student_enrollment_onboarded',
        JSON_OBJECT(
            'enrollment_id', p_enrollment_id,
            'student_id', v_student_id,
            'academic_year_id', v_academic_year_id,
            'obligations_generated', p_obligations_generated,
            'dormitory_id', v_dormitory_id,
            'operator_id', p_operator_id
        )
    );
END
""",
)
proc(
    "sp_place_application_into_class",
    """
PROCEDURE `sp_place_application_into_class` (
    IN p_application_id INT UNSIGNED,
    IN p_academic_year_class_stream_id INT UNSIGNED,
    IN p_enrollment_date DATE,
    IN p_operator_id INT UNSIGNED,
    IN p_student_type_id INT UNSIGNED,
    OUT p_student_id INT UNSIGNED,
    OUT p_admission_no VARCHAR(20),
    OUT p_enrollment_id INT UNSIGNED,
    OUT p_obligations_generated INT
)
BEGIN
    DECLARE v_academic_year_id INT UNSIGNED;
    DECLARE v_enrollment_date DATE;

    -- 1) Register the applicant as a student if not already registered
    SELECT MAX(id) INTO p_student_id FROM students WHERE application_id = p_application_id;

    IF p_student_id IS NULL THEN
        CALL sp_register_applicant_as_student(p_application_id, p_operator_id, p_student_type_id, p_student_id, p_admission_no);
    ELSE
        SELECT admission_no INTO p_admission_no FROM students WHERE id = p_student_id;
        IF p_student_type_id IS NOT NULL THEN
            UPDATE students SET student_type_id = p_student_type_id WHERE id = p_student_id;
        END IF;
    END IF;

    -- 2) Validate the target class-stream context
    SELECT ayc.academic_year_id INTO v_academic_year_id
    FROM academic_year_class_streams aycs
    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
    WHERE aycs.id = p_academic_year_class_stream_id;

    IF v_academic_year_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class-stream context not found';
    END IF;

    SET v_enrollment_date = COALESCE(p_enrollment_date, CURDATE());

    -- 3) Create or update the enrollment
    SELECT MAX(sae.id) INTO p_enrollment_id
    FROM student_academic_enrollments sae
    WHERE sae.student_id = p_student_id AND sae.academic_year_id = v_academic_year_id;

    IF p_enrollment_id IS NULL THEN
        -- enrollment INSERT auto-fires trg_enrollment_auto_onboard (learning areas,
        -- dormitory, events) and trg_create_student_obligations (billing)
        INSERT INTO student_academic_enrollments (id, student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status)
        SELECT COALESCE(MAX(id), 0) + 1, p_student_id, v_academic_year_id, p_academic_year_class_stream_id, v_enrollment_date, 'active'
        FROM student_academic_enrollments;
        SET p_enrollment_id = (SELECT MAX(id) FROM student_academic_enrollments);
        SET p_obligations_generated = (SELECT COUNT(*) FROM student_fee_obligations
                                       WHERE student_academic_enrollment_id = p_enrollment_id);
    ELSE
        UPDATE student_academic_enrollments
           SET academic_year_class_stream_id = p_academic_year_class_stream_id,
               enrollment_status = 'active',
               enrolled_on = COALESCE(enrolled_on, v_enrollment_date)
         WHERE id = p_enrollment_id;
        CALL sp_onboard_student_enrollment(p_enrollment_id, p_operator_id, p_obligations_generated);
    END IF;

    SELECT p_student_id AS student_id, p_admission_no AS admission_no,
           p_enrollment_id AS enrollment_id, p_obligations_generated AS obligations_generated;
END
""",
)
