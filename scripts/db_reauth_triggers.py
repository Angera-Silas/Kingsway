#!/usr/bin/env python3
"""Re-authored triggers for the 3NF/4NF deliverable.

The 23 legacy triggers below previously failed create-time validation because
they referenced retired tables/columns (class_streams, class_enrollments,
payment_transactions, academic_terms, staff_payroll, user.email, etc.). Each is
re-pointed at the equivalent 3NF/4NF structure:

  class_enrollments / class_year_assignments  -> student_academic_enrollments
                                                   (+ system_events logging; the
                                                    new schema derives counts)
  class_streams / students.stream_id          -> academic_year_class_streams +
                                                   student_academic_enrollments
  academic_terms                              -> academic_year_terms
  payment_transactions / financial_transactions -> payments
  payment_allocations_detailed                -> payments (implicit allocation)
  student_arrears / sp_create_arrears_record  -> student_fee_obligations +
                                                   system_events (derived)
  inventory_requisitions                      -> requisitions
  staff_performance_reviews                   -> performance_reviews
  staff_payroll                               -> payslips
  audit_trail                                 -> audit_logs
  users.email / users.role_id                 -> persons.email / user_roles

Target tables without AUTO_INCREMENT ids (streams, academic_year_class_streams,
student_fee_obligations) receive an explicit MAX(id)+1 / ROW_NUMBER() id.

Every statement is emitted verbatim by the Section-4 builder; the import
pipeline validates them at CREATE time against the scratch database.
"""

REAUTHORED_TRIGGERS = {}


def trig(name, sql):
    """Register a re-authored trigger definition."""
    REAUTHORED_TRIGGERS[name] = "CREATE TRIGGER `%s` %s" % (name, sql)


# ---------------------------------------------------------------------------
# Users: audit trail (audit_trail -> audit_logs; email/role_id -> person_id)
# ---------------------------------------------------------------------------

trig(
    "trg_audit_delete",
    """BEFORE DELETE ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_logs (
    action,
    entity,
    entity_id,
    user_id,
    details,
    status,
    created_at
  )
VALUES (
    'DELETE',
    'users',
    OLD.id,
    OLD.id,
    JSON_OBJECT(
      'username',
      OLD.username,
      'person_id',
      OLD.person_id,
      'status',
      OLD.status
    ),
    'success',
    NOW()
  );
END""",
)

trig(
    "trg_audit_insert",
    """AFTER INSERT ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_logs (
    action,
    entity,
    entity_id,
    user_id,
    details,
    status,
    created_at
  )
VALUES (
    'INSERT',
    'users',
    NEW.id,
    NEW.id,
    JSON_OBJECT(
      'username',
      NEW.username,
      'person_id',
      NEW.person_id,
      'status',
      NEW.status
    ),
    'success',
    NOW()
  );
END""",
)

trig(
    "trg_audit_update",
    """AFTER UPDATE ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_logs (
    action,
    entity,
    entity_id,
    user_id,
    details,
    status,
    created_at
  )
VALUES (
    'UPDATE',
    'users',
    NEW.id,
    NEW.id,
    JSON_OBJECT(
      'username',
      OLD.username,
      'person_id',
      OLD.person_id,
      'status',
      OLD.status
    ),
    JSON_OBJECT(
      'username',
      NEW.username,
      'person_id',
      NEW.person_id,
      'status',
      NEW.status
    ),
    NOW()
  );
END""",
)

trig(
    "trg_validate_email",
    """BEFORE INSERT ON `persons` FOR EACH ROW BEGIN
    IF NEW.email IS NOT NULL
       AND NEW.email <> ''
       AND NEW.email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid email format';
    END IF;
END""",
)

trig(
    "trg_prevent_user_delete",
    """BEFORE DELETE ON `users` FOR EACH ROW BEGIN
    DECLARE v_role_count INT;
    SELECT COUNT(*) INTO v_role_count
    FROM user_roles ur
    JOIN roles r ON r.id = ur.role_id
    WHERE ur.user_id = OLD.id AND r.name = 'admin';
    IF v_role_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete admin users';
    END IF;
END""",
)

# ---------------------------------------------------------------------------
# Classes / streams / enrollments
# ---------------------------------------------------------------------------

trig(
    "trg_auto_create_default_stream",
    """AFTER INSERT ON `academic_year_classes` FOR EACH ROW BEGIN
    DECLARE v_class_name VARCHAR(50);
    DECLARE v_default_stream_name VARCHAR(50);
    DECLARE v_stream_id INT UNSIGNED;
    DECLARE v_next_stream_id INT UNSIGNED;
    DECLARE v_next_aycs_id INT UNSIGNED;

    SELECT name INTO v_class_name FROM classes WHERE id = NEW.class_id;
    SET v_default_stream_name = CONCAT(COALESCE(v_class_name, CONCAT('Class ', NEW.class_id)), ' - A');

    IF NOT EXISTS (SELECT 1 FROM streams WHERE name = v_default_stream_name) THEN
        SET v_next_stream_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM streams);
        INSERT INTO streams (id, name, code, capacity)
        VALUES (v_next_stream_id, v_default_stream_name, CONCAT('AYC', NEW.id), 40);
    END IF;

    SET v_stream_id = (SELECT id FROM streams WHERE name = v_default_stream_name LIMIT 1);

    IF v_stream_id IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM academic_year_class_streams WHERE academic_year_class_id = NEW.id) THEN
        SET v_next_aycs_id = (SELECT COALESCE(MAX(id), 0) + 1 FROM academic_year_class_streams);
        INSERT INTO academic_year_class_streams (id, academic_year_class_id, stream_id, capacity, status)
        VALUES (v_next_aycs_id, NEW.id, v_stream_id, 40, 'active');
    END IF;
END""",
)

trig(
    "trg_validate_academic_term",
    """BEFORE INSERT ON `academic_year_terms` FOR EACH ROW BEGIN
    IF NEW.opening_date IS NOT NULL
       AND NEW.closing_date IS NOT NULL
       AND NEW.opening_date >= NEW.closing_date THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Term start date must be before end date';
    END IF;
END""",
)

trig(
    "trg_validate_class_capacity",
    """BEFORE INSERT ON `student_academic_enrollments` FOR EACH ROW BEGIN
    DECLARE v_capacity INT UNSIGNED;
    DECLARE v_current_count INT;
    SELECT aycs.capacity INTO v_capacity
    FROM academic_year_class_streams aycs
    WHERE aycs.id = NEW.academic_year_class_stream_id;
    SELECT COUNT(*) INTO v_current_count
    FROM student_academic_enrollments
    WHERE academic_year_class_stream_id = NEW.academic_year_class_stream_id
      AND enrollment_status = 'active';
    IF v_capacity IS NOT NULL AND v_current_count >= v_capacity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Class capacity exceeded';
    END IF;
END""",
)

trig(
    "trg_update_class_capacity",
    """AFTER INSERT ON `students` FOR EACH ROW BEGIN
    UPDATE academic_capacity_reservations
    SET reservation_status = 'converted',
        converted_to_enrollment_at = NOW()
    WHERE application_id = NEW.application_id
      AND reservation_status = 'provisional';
END""",
)

# Enrollment lifecycle: counts are derived in the 3NF schema, so these hooks
# emit observability events instead of maintaining counter columns.

trig(
    "after_enrollment_delete",
    """AFTER DELETE ON `student_academic_enrollments` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'enrollment_removed',
        JSON_OBJECT(
            'enrollment_id', OLD.id,
            'student_id', OLD.student_id,
            'academic_year_id', OLD.academic_year_id,
            'academic_year_class_stream_id', OLD.academic_year_class_stream_id
        ),
        NOW()
    );
END""",
)

trig(
    "after_enrollment_insert",
    """AFTER INSERT ON `student_academic_enrollments` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'enrollment_created',
        JSON_OBJECT(
            'enrollment_id', NEW.id,
            'student_id', NEW.student_id,
            'academic_year_id', NEW.academic_year_id,
            'academic_year_class_stream_id', NEW.academic_year_class_stream_id
        ),
        NOW()
    );
END""",
)

trig(
    "after_enrollment_update",
    """AFTER UPDATE ON `student_academic_enrollments` FOR EACH ROW BEGIN
    IF OLD.academic_year_class_stream_id <> NEW.academic_year_class_stream_id
       OR OLD.enrollment_status <> NEW.enrollment_status THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'enrollment_updated',
            JSON_OBJECT(
                'enrollment_id', NEW.id,
                'student_id', NEW.student_id,
                'old_academic_year_class_stream_id', OLD.academic_year_class_stream_id,
                'new_academic_year_class_stream_id', NEW.academic_year_class_stream_id,
                'old_status', OLD.enrollment_status,
                'new_status', NEW.enrollment_status
            ),
            NOW()
        );
    END IF;
END""",
)

# ---------------------------------------------------------------------------
# Attendance / payments / finance
# ---------------------------------------------------------------------------

trig(
    "trg_emit_attendance_event",
    """AFTER INSERT ON `student_attendance` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'attendance_marked',
        JSON_OBJECT(
            'student_academic_enrollment_id', NEW.student_academic_enrollment_id,
            'date', NEW.date,
            'status', NEW.status,
            'session_id', NEW.session_id
        ),
        NOW()
    );
END""",
)

trig(
    "trg_emit_payment_event",
    """AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'payment_received',
        JSON_OBJECT(
            'payment_id', NEW.id,
            'student_id', NEW.student_id,
            'parent_id', NEW.parent_id,
            'amount', NEW.amount,
            'method', NEW.method,
            'reference', NEW.reference,
            'receipt', NEW.receipt_no
        ),
        NOW()
    );
END""",
)

trig(
    "trg_log_payment_transaction",
    """AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'payment_status_changed',
            JSON_OBJECT(
                'payment_id', NEW.id,
                'student_id', NEW.student_id,
                'old_status', OLD.status,
                'new_status', NEW.status,
                'reference', NEW.reference
            ),
            NOW()
        );
    END IF;
END""",
)

trig(
    "trg_update_obligation_on_payment",
    """AFTER INSERT ON `payments` FOR EACH ROW BEGIN
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

    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'payment_allocated',
        JSON_OBJECT(
            'payment_id', NEW.id,
            'student_id', NEW.student_id,
            'amount', NEW.amount
        ),
        NOW()
    );
END""",
)

trig(
    "trg_create_student_obligations",
    """AFTER INSERT ON `student_academic_enrollments` FOR EACH ROW BEGIN
    DECLARE v_base_id INT UNSIGNED;
    SET v_base_id = (SELECT COALESCE(MAX(id), 0) FROM student_fee_obligations);

    INSERT INTO student_fee_obligations (
        id,
        student_academic_enrollment_id,
        academic_year_id,
        academic_year_term_id,
        academic_year_fee_schedule_id,
        amount_due,
        status,
        due_date,
        created_at
    )
    SELECT
        v_base_id + ROW_NUMBER() OVER (),
        NEW.id,
        fs.academic_year_id,
        fs.academic_year_term_id,
        fs.id,
        fs.amount,
        'pending',
        COALESCE(fs.due_date, ayt.closing_date),
        NOW()
    FROM academic_year_fee_schedules fs
    JOIN academic_year_terms ayt ON ayt.id = fs.academic_year_term_id
    JOIN academic_year_class_streams aycs ON aycs.id = NEW.academic_year_class_stream_id
    JOIN students st ON st.id = NEW.student_id
    WHERE fs.academic_year_class_id = aycs.academic_year_class_id
      AND fs.academic_year_id = NEW.academic_year_id
      AND fs.student_type_id = st.student_type_id
      AND fs.status = 'active'
      AND NOT EXISTS (
          SELECT 1 FROM student_fee_obligations sfo
          WHERE sfo.student_academic_enrollment_id = NEW.id
            AND sfo.academic_year_term_id = fs.academic_year_term_id
            AND sfo.academic_year_fee_schedule_id = fs.id
      );
END""",
)

# Arrears are derived from student_fee_obligations in the 3NF schema, so the
# "arrears record" triggers emit events instead of writing a retired table.

trig(
    "trg_check_and_create_arrears",
    """AFTER UPDATE ON `student_fee_obligations` FOR EACH ROW BEGIN
    IF NEW.status IN ('pending', 'partial')
       AND NEW.due_date IS NOT NULL
       AND NEW.due_date < CURDATE()
       AND (OLD.due_date IS NULL OR OLD.due_date >= CURDATE()) THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'arrears_detected',
            JSON_OBJECT(
                'student_id', (SELECT student_id FROM student_academic_enrollments WHERE id = NEW.student_academic_enrollment_id),
                'obligation_id', NEW.id,
                'amount', NEW.amount_due,
                'due_date', NEW.due_date,
                'status', NEW.status
            ),
            NOW()
        );
    END IF;
END""",
)

trig(
    "trg_log_arrears_creation",
    """AFTER INSERT ON `student_fee_obligations` FOR EACH ROW BEGIN
    IF NEW.status IN ('pending', 'partial')
       AND NEW.due_date IS NOT NULL
       AND NEW.due_date < CURDATE() THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'arrears_created',
            JSON_OBJECT(
                'student_id', (SELECT student_id FROM student_academic_enrollments WHERE id = NEW.student_academic_enrollment_id),
                'obligation_id', NEW.id,
                'amount', NEW.amount_due,
                'due_date', NEW.due_date,
                'status', NEW.status
            ),
            NOW()
        );
    END IF;
END""",
)

# ---------------------------------------------------------------------------
# Requisitions / schemes of work / performance reviews / payroll
# ---------------------------------------------------------------------------

trig(
    "trg_log_requisition_status",
    """AFTER UPDATE ON `requisitions` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO audit_logs (
            action,
            entity,
            entity_id,
            user_id,
            details,
            status,
            created_at
        )
        VALUES (
            'UPDATE',
            'requisitions',
            NEW.id,
            COALESCE(NEW.approved_by, NEW.requested_by),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status),
            'success',
            NOW()
        );

        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            CONCAT('requisition_', NEW.status),
            JSON_OBJECT(
                'requisition_id', NEW.id,
                'requisition_number', NEW.requisition_number
            ),
            NOW()
        );
    END IF;
END""",
)

trig(
    "trg_log_scheme_changes",
    """AFTER UPDATE ON `schemes_of_work` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at)
    VALUES (
        'UPDATE',
        'schemes_of_work',
        NEW.id,
        (SELECT u.id FROM users u JOIN staff s ON u.person_id = s.person_id WHERE s.id = NEW.approved_by LIMIT 1),
        JSON_OBJECT(
            'old_status', OLD.status,
            'new_status', NEW.status,
            'old_academic_year_calendar_week_id', OLD.academic_year_calendar_week_id,
            'new_academic_year_calendar_week_id', NEW.academic_year_calendar_week_id
        ),
        'success',
        NOW()
    );
END""",
)

trig(
    "trg_notify_performance_review_complete",
    """AFTER UPDATE ON `performance_reviews` FOR EACH ROW BEGIN
    IF NEW.status = 'acknowledged' AND OLD.status <> 'acknowledged' THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'performance_review_completed',
            JSON_OBJECT(
                'staff_id', NEW.staff_id,
                'review_id', NEW.id,
                'period', NEW.period,
                'rating', NEW.rating
            ),
            NOW()
        );
    END IF;
END""",
)

trig(
    "trg_validate_payroll_payment",
    """BEFORE UPDATE ON `payslips` FOR EACH ROW BEGIN
    IF NEW.payment_status = 'paid' AND OLD.payment_status != 'paid' THEN
        IF NEW.net_salary <= 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot process payment: Net salary must be positive';
        END IF;

        IF NEW.payment_date IS NULL THEN
            SET NEW.payment_date = CURDATE();
        END IF;
    END IF;
END""",
)


# ---------------------------------------------------------------------------
# NEW enrollment automation triggers (2026 deliverable round)
# ---------------------------------------------------------------------------
trig(
    "trg_enrollment_auto_onboard",
    """
AFTER INSERT ON `student_academic_enrollments` FOR EACH ROW BEGIN
    DECLARE v_obligations INT;
    CALL sp_onboard_student_enrollment(NEW.id, NULL, v_obligations);
END
""",
)
trig(
    "trg_student_application_backfill",
    """
AFTER INSERT ON `students` FOR EACH ROW BEGIN
    IF NEW.application_id IS NOT NULL THEN
        UPDATE admission_applications
           SET status = 'enrolled',
               enrolled_student_id = NEW.id,
               enrolled_at = COALESCE(enrolled_at, NOW()),
               updated_at = NOW()
         WHERE id = NEW.application_id
           AND status <> 'cancelled'
           AND (status <> 'enrolled' OR enrolled_student_id IS NULL);
    END IF;
END
""",
)
