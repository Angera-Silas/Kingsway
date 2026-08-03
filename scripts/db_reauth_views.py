#!/usr/bin/env python3
"""Re-authored database objects for the 3NF/4NF deliverable.

This module contains:

  NEW_VIEWS           - brand-new derived views that the target architecture
                        (docs/09_NORMALIZED_TARGET_ARCHITECTURE.md) calls for:
                        fee balances, arrears, totals and class positions.
  REAUTHORED_VIEWS    - the 73 legacy views that previously failed create-time
                        validation, re-authored against the new 3NF/4NF schema
                        (persons for names, academic_year_* + streams +
                        student_academic_enrollments instead of class_streams,
                        terms + academic_year_terms instead of academic_terms,
                        payments instead of payment_transactions, etc.).

Every statement is emitted by the Section-4 builder verbatim; the import
pipeline validates them at CREATE time against the scratch database.
"""

NEW_VIEWS = {}

REAUTHORED_VIEWS = {}


def vw(name, body):
    """Register a re-authored view definition."""
    REAUTHORED_VIEWS[name] = "CREATE OR REPLACE VIEW `%s` AS %s" % (name, body)


def nv(name, body):
    """Register a brand-new derived view definition."""
    NEW_VIEWS[name] = "CREATE OR REPLACE VIEW `%s` AS %s" % (name, body)


# ---------------------------------------------------------------------------
# NEW derived views (target architecture)
# ---------------------------------------------------------------------------

nv(
    "vw_student_fee_balances",
    """
SELECT
    sae.id                                        AS student_academic_enrollment_id,
    sae.student_id,
    sae.academic_year_id,
    ayt.id                                        AS academic_year_term_id,
    ayt.term_id                                   AS term_id,
    t.code                                        AS term_code,
    ay.year_code                                  AS academic_year,
    sum(coalesce(sfo.amount_due, 0))              AS amount_due,
    (sum(coalesce(fdw.discount_value, 0)) +
     sum(coalesce(sfo.sponsored_waiver_amount, 0))) AS amount_waived,
    sum(coalesce(pay.amount, 0))                  AS amount_paid,
    greatest(
        sum(coalesce(sfo.amount_due, 0))
        - sum(coalesce(fdw.discount_value, 0))
        - sum(coalesce(sfo.sponsored_waiver_amount, 0))
        - sum(coalesce(pay.amount, 0)), 0)        AS balance,
    CASE
        WHEN sum(coalesce(sfo.amount_due, 0)) <= 0 THEN 'no_due'
        WHEN sum(coalesce(sfo.amount_due, 0))
             - sum(coalesce(pay.amount, 0))
             - sum(coalesce(fdw.discount_value, 0))
             - sum(coalesce(sfo.sponsored_waiver_amount, 0)) <= 0 THEN 'paid'
        WHEN sum(coalesce(pay.amount, 0)) > 0 THEN 'partial'
        ELSE 'pending'
    END                                           AS payment_status,
    max(sfo.due_date)                             AS latest_due_date,
    greatest(
        to_days(curdate()) - to_days(coalesce(max(sfo.due_date), curdate())),
        0)                                        AS days_overdue
FROM student_academic_enrollments sae
JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = sae.academic_year_id
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN student_fee_obligations sfo
    ON sfo.student_academic_enrollment_id = sae.id
LEFT JOIN fee_discounts_waivers fdw
    ON fdw.student_fee_obligation_id = sfo.id AND fdw.status = 'approved'
LEFT JOIN payments pay
    ON pay.student_id = sae.student_id
   AND pay.status IN ('confirmed', 'completed', 'success')
   AND pay.payment_date >= ayt.opening_date
   AND pay.payment_date <= ayt.closing_date
WHERE sae.enrollment_status = 'active'
GROUP BY sae.id, ayt.id
""",
)

nv(
    "vw_student_arrears",
    """
SELECT
    f.*,
    CASE
        WHEN f.balance > 0 AND f.days_overdue > 0 THEN 'overdue'
        WHEN f.balance > 0 THEN 'current'
        ELSE 'cleared'
    END AS arrears_status
FROM vw_student_fee_balances f
WHERE f.balance > 0
""",
)

nv(
    "vw_fee_totals_by_year",
    """
SELECT
    academic_year_id,
    academic_year,
    count(DISTINCT student_id)                     AS total_students,
    sum(amount_due)                                AS total_fees_due,
    sum(amount_paid)                               AS total_fees_paid,
    sum(amount_waived)                             AS total_fees_waived,
    sum(balance)                                   AS total_outstanding,
    round(sum(amount_paid) / nullif(sum(amount_due), 0) * 100, 2)
                                                   AS collection_rate_percent,
    count(DISTINCT CASE WHEN balance <= 0 THEN student_id END) AS students_paid_full,
    count(DISTINCT CASE WHEN payment_status = 'partial' THEN student_id END) AS students_partial,
    count(DISTINCT CASE WHEN balance > 0 THEN student_id END) AS students_in_arrears
FROM vw_student_fee_balances
GROUP BY academic_year_id, academic_year
""",
)

nv(
    "vw_fee_totals_by_class",
    """
SELECT
    f.academic_year_id,
    f.academic_year,
    sl.id                    AS level_id,
    sl.name                  AS level_name,
    sl.code                  AS level_code,
    c.id                     AS class_id,
    c.name                   AS class_name,
    st.name                  AS stream_name,
    count(DISTINCT f.student_id)                    AS total_students,
    sum(f.amount_due)                               AS total_fees_due,
    sum(f.amount_paid)                              AS total_fees_paid,
    sum(f.amount_waived)                            AS total_fees_waived,
    sum(f.balance)                                  AS total_outstanding,
    round(sum(f.amount_paid) / nullif(sum(f.amount_due), 0) * 100, 2)
                                                    AS collection_rate_percent
FROM vw_student_fee_balances f
JOIN student_academic_enrollments sae
    ON sae.id = f.student_academic_enrollment_id
LEFT JOIN academic_year_class_streams aycs
    ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN streams st ON st.id = aycs.stream_id
GROUP BY f.academic_year_id, sl.id, c.id, st.id
""",
)

nv(
    "vw_class_positions",
    """
SELECT
    tc.student_id,
    ay.id                     AS academic_year_id,
    ay.year_code              AS academic_year,
    ayt.id                    AS academic_year_term_id,
    ayt.term_id               AS term_id,
    t.code                    AS term_code,
    c.id                      AS class_id,
    c.name                    AS class_name,
    st.name                   AS stream_name,
    tc.avg_overall_percentage AS avg_overall_percentage,
    tc.avg_overall_grade      AS avg_overall_grade,
    tc.points_total           AS points_total,
    tc.class_position,
    tc.class_total,
    tc.percentile
FROM term_consolidations tc
LEFT JOIN academic_year_terms ayt ON ayt.term_id = tc.term_id AND ayt.academic_year_id = (SELECT MAX(ay2.id) FROM academic_years ay2 WHERE ay2.year_code = tc.academic_year)
LEFT JOIN terms t ON t.id = tc.term_id
LEFT JOIN academic_years ay ON ay.year_code = tc.academic_year
LEFT JOIN student_academic_enrollments sae
    ON sae.student_id = tc.student_id AND sae.academic_year_id = ay.id
LEFT JOIN academic_year_class_streams aycs
    ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams st ON st.id = aycs.stream_id
""",
)

# ---------------------------------------------------------------------------
# 1. Security, identity and RBAC
# ---------------------------------------------------------------------------

vw(
    "class_assignments",
    """
SELECT
    t.academic_year_class_learning_area_id AS class_id,
    t.staff_id                             AS user_id,
    t.role                                 AS role,
    aycs.stream_id                         AS stream,
    coalesce(sn.name, '')                  AS section,
    coalesce(c.name, '')                   AS form,
    coalesce(aycla.learning_area_id, NULL) AS subject_id
FROM academic_year_class_learning_area_teachers t
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = t.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN academic_year_class_streams aycs
    ON aycs.academic_year_class_id = ayc.id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN classes c ON c.id = ayc.class_id
""",
)

vw(
    "v_active_users",
    """
SELECT
    u.id,
    u.username,
    p.email,
    p.first_name,
    p.last_name,
    u.status,
    r.id                    AS role_id,
    r.name                  AS role_name,
    u.last_login,
    u.created_at,
    count(DISTINCT ur.role_id)      AS total_roles,
    count(DISTINCT up.permission_id) AS total_permissions
FROM users u
JOIN persons p ON p.id = u.person_id
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN user_permissions up ON up.user_id = u.id
LEFT JOIN roles r ON r.id = (SELECT MIN(x.role_id) FROM user_roles x WHERE x.user_id = u.id)
WHERE u.status = 'active'
GROUP BY u.id
""",
)

vw(
    "v_payment_security_alerts",
    """
SELECT
    w.id,
    w.status AS event_type,
    w.source AS webhook_source,
    json_unquote(json_extract(w.webhook_data, '$.transaction_ref')) AS transaction_ref,
    NULL AS student_id,
    CAST(json_unquote(json_extract(w.webhook_data, '$.amount')) AS DECIMAL(14,2)) AS amount,
    w.ip_address,
    w.validation_error AS details,
    w.created_at,
    CASE
        WHEN w.signature_verified = 0 THEN 'HIGH'
        WHEN w.requires_review = 1 THEN 'CRITICAL'
        WHEN w.validation_error IS NOT NULL AND w.validation_error <> '' THEN 'MEDIUM'
        ELSE 'LOW'
    END AS severity,
    CASE
        WHEN timestampdiff(HOUR, w.created_at, current_timestamp()) < 1 THEN 'Recent'
        WHEN timestampdiff(DAY, w.created_at, current_timestamp()) < 1 THEN 'Today'
        ELSE 'Older'
    END AS time_window
FROM payment_webhooks_log w
ORDER BY w.created_at DESC
""",
)

vw(
    "v_user_security",
    """
SELECT
    u.id,
    u.username,
    p.email,
    u.status,
    u.failed_login_attempts,
    u.account_locked_until,
    u.last_login,
    u.password_changed_at AS last_password_change,
    to_days(current_timestamp()) - to_days(u.password_changed_at) AS password_age_days,
    count(DISTINCT la.id) AS total_login_attempts_24h
FROM users u
JOIN persons p ON p.id = u.person_id
LEFT JOIN login_attempts la
    ON u.id = la.user_id AND la.created_at > current_timestamp() - interval 24 hour
GROUP BY u.id
""",
)

vw(
    "vw_failed_attempts_by_ip",
    """
SELECT
    ip_address,
    count(0) AS attempt_count,
    max(created_at) AS last_attempt,
    group_concat(DISTINCT failure_reason SEPARATOR ',') AS failure_reasons
FROM login_attempts
WHERE status = 'failed'
  AND created_at >= current_timestamp() - interval 1 hour
GROUP BY ip_address
HAVING attempt_count >= 3
ORDER BY count(0) DESC, max(created_at) DESC
""",
)

# ---------------------------------------------------------------------------
# 2. Inventory and requisitions
# ---------------------------------------------------------------------------

vw(
    "vw_active_allocations",
    """
SELECT
    a.id,
    a.allocation_number,
    i.name AS item_name,
    i.code AS item_code,
    ic.name AS category,
    a.allocated_quantity,
    a.returned_quantity,
    a.allocated_quantity - a.returned_quantity AS outstanding_quantity,
    d.name AS department,
    a.allocated_to_event,
    c.name AS class_name,
    a.status,
    a.allocation_date,
    a.expected_return_date,
    p.first_name AS allocated_by_first,
    p.last_name AS allocated_by_last,
    a.created_at
FROM inventory_allocations a
LEFT JOIN inventory_items i ON a.item_id = i.id
LEFT JOIN inventory_categories ic ON i.category_id = ic.id
LEFT JOIN inventory_departments d ON a.allocated_to_department_id = d.id
LEFT JOIN classes c ON a.allocated_to_class_id = c.id
LEFT JOIN staff s ON a.allocated_by = s.id
LEFT JOIN persons p ON p.id = s.person_id
WHERE a.status IN ('allocated', 'issued', 'partially_returned')
""",
)

vw(
    "vw_pending_requisitions",
    """
SELECT
    r.id,
    r.requisition_number,
    d.name AS department,
    r.status,
    r.priority,
    r.requisition_date,
    r.required_date,
    count(ri.id) AS item_count,
    sum(ri.requested_quantity) AS total_quantity_requested,
    p1.first_name AS created_by_first,
    p1.last_name AS created_by_last,
    p2.first_name AS approved_by_first,
    p2.last_name AS approved_by_last,
    r.created_at
FROM requisitions r
LEFT JOIN inventory_departments d ON r.department_id = d.id
LEFT JOIN requisition_items ri ON r.id = ri.requisition_id
LEFT JOIN staff s1 ON r.requested_by = s1.id
LEFT JOIN persons p1 ON p1.id = s1.person_id
LEFT JOIN staff s2 ON r.approved_by = s2.id
LEFT JOIN persons p2 ON p2.id = s2.person_id
WHERE r.status IN ('draft', 'submitted', 'pending_approval', 'approved')
GROUP BY r.id
""",
)

vw(
    "vw_requisition_fulfillment",
    """
SELECT
    r.id,
    r.requisition_number,
    d.name AS department,
    ri.id AS item_id,
    i.name AS item_name,
    ri.requested_quantity,
    ri.unit,
    ri.approved_quantity,
    ri.fulfilled_quantity,
    ri.requested_quantity - coalesce(ri.fulfilled_quantity, 0) AS pending_quantity,
    ri.unit_cost,
    ri.requested_quantity * ri.unit_cost AS total_cost,
    r.status,
    r.priority,
    r.required_date,
    to_days(r.required_date) - to_days(curdate()) AS days_remaining,
    r.created_at
FROM requisitions r
LEFT JOIN inventory_departments d ON r.department_id = d.id
LEFT JOIN requisition_items ri ON r.id = ri.requisition_id
LEFT JOIN inventory_items i ON ri.item_id = i.id
""",
)

# ---------------------------------------------------------------------------
# 3. Finance (fee collection, credit notes, sponsored students)
# ---------------------------------------------------------------------------

vw(
    "vw_available_fee_credits",
    """
SELECT
    fcn.id,
    fcn.credit_number,
    fcn.student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no,
    fcn.academic_year,
    fcn.credit_amount,
    fcn.applied_amount,
    fcn.remaining_amount,
    fcn.credit_reason,
    fcn.expiry_date,
    fcn.status
FROM fee_credit_notes fcn
JOIN students s ON s.id = fcn.student_id
JOIN persons p ON p.id = s.person_id
WHERE fcn.status IN ('available', 'partially_applied')
  AND (fcn.expiry_date IS NULL OR fcn.expiry_date > curdate())
""",
)

vw(
    "vw_arrears_summary",
    """
SELECT
    sl.name AS level,
    sl.code AS level_code,
    count(DISTINCT sa.student_id) AS students_in_arrears,
    sum(sa.balance) AS total_arrears_amount,
    round(avg(sa.balance), 2) AS average_arrears,
    count(DISTINCT CASE WHEN sa.arrears_status = 'overdue' THEN sa.student_id END) AS overdue_students,
    count(DISTINCT CASE WHEN sa.days_overdue > 30 THEN sa.student_id END) AS overdue_more_than_30_days,
    count(DISTINCT CASE WHEN sa.days_overdue > 60 THEN sa.student_id END) AS overdue_more_than_60_days,
    count(DISTINCT CASE WHEN asp.status = 'active' THEN asp.id END) AS settlement_plans_active,
    sum(CASE WHEN asp.status = 'active' THEN asp.total_amount ELSE 0 END) AS amount_on_settlement_plans
FROM vw_student_arrears sa
JOIN students s ON sa.student_id = s.id
JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.academic_year_id = sa.academic_year_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN arrears_settlement_plans asp ON sa.student_id = asp.student_id
WHERE sa.academic_year = year(curdate())
GROUP BY sl.id
ORDER BY sl.name ASC
""",
)

vw(
    "vw_collection_rate_by_class",
    """
SELECT
    sl.name AS level_name,
    sl.code AS level_code,
    t.name AS academic_term,
    count(DISTINCT f.student_id) AS total_students,
    sum(f.amount_due) AS total_fees_due,
    sum(f.amount_paid) AS total_fees_paid,
    sum(f.amount_waived) AS total_fees_waived,
    round(sum(f.amount_paid) / nullif(sum(f.amount_due), 0) * 100, 2) AS collection_rate_percent,
    count(DISTINCT CASE WHEN f.payment_status = 'paid' THEN f.student_id END) AS students_paid_in_full,
    count(DISTINCT CASE WHEN f.payment_status = 'partial' THEN f.student_id END) AS students_partial_payment,
    count(DISTINCT CASE WHEN f.payment_status = 'pending' THEN f.student_id END) AS students_no_payment,
    round(avg(f.amount_paid), 2) AS average_payment_per_student
FROM vw_student_fee_balances f
JOIN student_academic_enrollments sae ON sae.id = f.student_academic_enrollment_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN terms t ON t.id = f.term_id
GROUP BY sl.id, t.id
ORDER BY sl.name ASC, t.id ASC
""",
)

vw(
    "vw_fee_carryover_summary",
    """
SELECT
    fcn.student_id,
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    concat(c.name, ' - ', sn.name) AS class_name,
    fcn.academic_year,
    fcn.term_id,
    CASE WHEN fcn.term_id IS NULL THEN 'Academic Year' ELSE concat('Term ', fcn.term_id) END AS period_type,
    fcn.applied_amount AS previous_balance,
    fcn.remaining_amount AS surplus_amount,
    fcn.status AS action_taken,
    fcn.created_at,
    fcn.notes
FROM fee_credit_notes fcn
JOIN students s ON s.id = fcn.student_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_academic_enrollments sae
    ON sae.student_id = s.id AND sae.academic_year_id = (SELECT MAX(x.id) FROM academic_years x WHERE x.year_code = fcn.academic_year)
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
ORDER BY fcn.academic_year DESC, fcn.created_at DESC
""",
)

vw(
    "vw_fee_collection_by_year",
    """
SELECT
    academic_year,
    count(DISTINCT student_id) AS total_students,
    sum(amount_due) AS total_fees_due,
    sum(amount_paid) AS total_collected,
    sum(balance) AS total_outstanding,
    round(sum(amount_paid) / nullif(sum(amount_due), 0) * 100, 2) AS collection_rate_percent,
    sum(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS students_paid_full,
    sum(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) AS students_partial,
    sum(CASE WHEN balance > 0 THEN 1 ELSE 0 END) AS students_arrears,
    sum(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS students_pending
FROM vw_student_fee_balances
GROUP BY academic_year
ORDER BY academic_year DESC
""",
)

vw(
    "vw_fee_schedule_by_class",
    """
SELECT
    sl.name AS level_name,
    sl.code AS level_code,
    t.name AS academic_term,
    st.name AS student_type,
    st.code AS student_type_code,
    ft.name AS fee_name,
    ft.category AS fee_category,
    ayfs.amount AS amount_due,
    ayfs.due_date AS due_date,
    count(DISTINCT s.id) AS number_of_students
FROM academic_year_fee_schedules ayfs
JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
LEFT JOIN terms t ON t.id = ayt.term_id
JOIN student_types st ON st.id = ayfs.student_type_id
LEFT JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
LEFT JOIN fee_types ft ON ft.id = fc.fee_type_id
LEFT JOIN student_academic_enrollments sae
    ON sae.academic_year_class_stream_id IN (SELECT x.id FROM academic_year_class_streams x WHERE x.academic_year_class_id = ayc.id)
   AND sae.student_id IN (SELECT s2.id FROM students s2 WHERE s2.student_type_id = st.id)
LEFT JOIN students s ON s.id = sae.student_id
WHERE ayfs.academic_year_id = (SELECT y.id FROM academic_years y WHERE y.year_code = year(curdate()) AND y.is_current = 1)
GROUP BY sl.id, t.id, st.id, ft.id, ayfs.id
ORDER BY sl.name ASC, t.id ASC, st.name ASC, ft.name ASC
""",
)

vw(
    "vw_fee_structure_annual_summary",
    """
SELECT
    ay.year_code AS academic_year,
    sl.name AS level_name,
    sl.id AS level_id,
    ft.name AS fee_type,
    ft.id AS fee_type_id,
    ft.category AS fee_category,
    sum(CASE WHEN ayt.term_id = 1 THEN ayfs.amount ELSE 0 END) AS term1_amount,
    sum(CASE WHEN ayt.term_id = 2 THEN ayfs.amount ELSE 0 END) AS term2_amount,
    sum(CASE WHEN ayt.term_id = 3 THEN ayfs.amount ELSE 0 END) AS term3_amount,
    sum(ayfs.amount) AS annual_total,
    ayfs.status AS status,
    0 AS is_auto_rollover,
    ayfs.approved_by AS reviewed_by,
    NULL AS reviewer_name,
    ayfs.approved_by AS approved_by,
    NULL AS approver_name,
    ayfs.approved_at AS approved_at,
    NULL AS activated_at,
    NULL AS copied_from_id,
    NULL AS copied_from_year,
    count(DISTINCT ayfs.id) AS structure_count
FROM academic_year_fee_schedules ayfs
JOIN academic_years ay ON ay.id = ayfs.academic_year_id
LEFT JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
LEFT JOIN fee_types ft ON ft.id = fc.fee_type_id
GROUP BY ay.id, ayfs.academic_year_class_id, fc.fee_type_id, ayfs.status
""",
)

vw(
    "vw_fee_transition_audit",
    """
SELECT
    fcn.student_id AS student_id,
    s.admission_no AS admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    fcn.academic_year AS from_academic_year,
    fcn.applied_to_year AS to_academic_year,
    CASE
        WHEN fcn.term_id IS NULL OR fcn.applied_to_term_id IS NULL THEN 'Year'
        ELSE concat('Term ', fcn.term_id, ' to Term ', fcn.applied_to_term_id)
    END AS transition_type,
    CASE WHEN fcn.credit_reason = 'refund' THEN 'refund' ELSE 'carried' END AS balance_action,
    fcn.applied_amount AS amount_transferred,
    fcn.credit_amount AS previous_balance,
    fcn.remaining_amount AS new_balance,
    fcn.applied_at AS created_at,
    fcn.notes AS notes
FROM fee_credit_notes fcn
JOIN students s ON fcn.student_id = s.id
JOIN persons p ON p.id = s.person_id
ORDER BY fcn.applied_at DESC
""",
)

vw(
    "vw_fee_type_collection",
    """
SELECT
    ft.name AS fee_type,
    ft.code AS fee_code,
    ft.category AS fee_category,
    ft.is_mandatory AS is_mandatory,
    sum(f.amount_due) AS total_due,
    sum(f.amount_paid) AS total_collected,
    sum(f.balance) AS total_outstanding,
    count(DISTINCT f.student_id) AS students_affected,
    round(sum(f.amount_paid) / nullif(sum(f.amount_due), 0) * 100, 2) AS collection_rate_percent,
    count(DISTINCT CASE WHEN f.payment_status = 'paid' THEN f.student_id END) AS students_paid,
    count(DISTINCT CASE WHEN f.payment_status = 'partial' THEN f.student_id END) AS students_partial,
    count(DISTINCT CASE WHEN f.payment_status = 'pending' THEN f.student_id END) AS students_pending
FROM vw_student_fee_balances f
LEFT JOIN student_fee_obligations sfo ON sfo.student_academic_enrollment_id = f.student_academic_enrollment_id
LEFT JOIN academic_year_fee_schedules ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
LEFT JOIN fee_catalog fc ON fc.id = ayfs.fee_catalog_id
LEFT JOIN fee_types ft ON ft.id = fc.fee_type_id
WHERE f.academic_year = year(curdate())
GROUP BY ft.id
ORDER BY sum(f.amount_due) DESC
""",
)

vw(
    "vw_outstanding_by_class",
    """
SELECT
    sl.name AS level_name,
    sl.code AS level_code,
    t.name AS academic_term,
    count(DISTINCT f.student_id) AS students_with_arrears,
    sum(f.balance) AS total_arrears,
    round(avg(f.balance), 2) AS average_arrears_per_student,
    min(f.balance) AS minimum_arrears,
    max(f.balance) AS maximum_arrears,
    count(DISTINCT CASE WHEN f.days_overdue > 30 THEN f.student_id END) AS students_overdue_30_days,
    count(DISTINCT CASE WHEN f.days_overdue > 60 THEN f.student_id END) AS students_overdue_60_days
FROM vw_student_fee_balances f
JOIN student_academic_enrollments sae ON sae.id = f.student_academic_enrollment_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN terms t ON t.id = f.term_id
WHERE f.balance > 0
GROUP BY sl.id, t.id
ORDER BY sl.name ASC, t.id ASC
""",
)

vw(
    "vw_pending_fee_structure_reviews",
    """
SELECT
    ay.year_code AS academic_year,
    sl.name AS level_name,
    count(DISTINCT ayfs.id) AS pending_structures,
    min(ayfs.created_at) AS oldest_pending_date,
    to_days(curdate()) - to_days(min(ayfs.created_at)) AS days_pending,
    ay.start_date,
    to_days(ay.start_date) - to_days(curdate()) AS days_until_start,
    CASE
        WHEN to_days(ay.start_date) - to_days(curdate()) <= 7 THEN 'URGENT'
        WHEN to_days(ay.start_date) - to_days(curdate()) <= 30 THEN 'HIGH'
        ELSE 'NORMAL'
    END AS priority
FROM academic_year_fee_schedules ayfs
JOIN academic_years ay ON ay.id = ayfs.academic_year_id
JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN school_levels sl ON sl.id = c.level_id
WHERE ayfs.status IN ('draft', 'pending_review')
GROUP BY ay.id, sl.id, ay.start_date
ORDER BY to_days(ay.start_date) - to_days(curdate()) ASC
""",
)

vw(
    "vw_sponsored_students_status",
    """
SELECT
    s.id,
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    st.name AS student_type,
    concat(c.name, ' - ', sn.name) AS class_name,
    1 AS is_sponsored,
    NULL AS sponsor_name,
    'obligation' AS sponsor_type,
    max(coalesce(sfo.sponsored_waiver_amount, 0)) AS sponsor_waiver_percentage,
    coalesce(sum(f.amount_due), 0) AS total_fees_due,
    coalesce(sum(f.amount_paid), 0) AS total_paid,
    coalesce(sum(f.balance), 0) AS current_balance,
    coalesce(sum(f.amount_waived), 0) AS total_waived
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_types st ON s.student_type_id = st.id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN student_fee_obligations sfo ON sfo.student_academic_enrollment_id = sae.id AND sfo.is_sponsored = 1
LEFT JOIN vw_student_fee_balances f ON f.student_academic_enrollment_id = sae.id AND f.academic_year_term_id = sfo.academic_year_term_id
WHERE sfo.is_sponsored = 1
GROUP BY s.id, st.id, c.id
ORDER BY s.admission_no ASC
""",
)

vw(
    "vw_student_fee_clearance",
    """
SELECT
    s.id AS student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no,
    coalesce(sum(f.balance), 0) AS total_outstanding,
    coalesce(sum(f.amount_paid), 0) AS total_paid,
    coalesce(sum(f.amount_due), 0) AS total_billed,
    coalesce(sum(CASE WHEN f.balance > 0 THEN 1 ELSE 0 END), 0) AS pending_obligations,
    CASE WHEN coalesce(sum(f.balance), 0) <= 0 THEN 'cleared' ELSE 'outstanding' END AS finance_clearance_status
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN vw_student_fee_balances f
    ON f.student_academic_enrollment_id = sae.id
   AND f.academic_year = year(curdate())
GROUP BY s.id
""",
)

vw(
    "vw_student_finance_history",
    """
SELECT
    pay.student_id,
    ay.year_code AS academic_year,
    t.name AS term_name,
    ayt.term_id AS term_number,
    sum(pay.amount) AS total_paid,
    count(pay.id) AS payment_count,
    pay.method AS payment_method,
    max(pay.payment_date) AS last_payment_date
FROM payments pay
JOIN students s ON s.id = pay.student_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = pay.student_id
LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = ay.id
   AND pay.payment_date >= ayt.opening_date AND pay.payment_date <= ayt.closing_date
LEFT JOIN terms t ON t.id = ayt.term_id
WHERE pay.status IN ('confirmed', 'completed', 'success')
GROUP BY pay.student_id, ay.id, ayt.term_id, pay.method
""",
)

vw(
    "vw_student_payment_history_multi_year",
    """
SELECT
    s.id AS student_id,
    p.first_name,
    p.last_name,
    s.admission_no,
    ay.year_code AS academic_year,
    t.name AS term_name,
    ayt.term_id AS term_number,
    count(pay.id) AS payment_count,
    sum(pay.amount) AS total_paid,
    min(pay.payment_date) AS first_payment_date,
    max(pay.payment_date) AS last_payment_date,
    sum(CASE WHEN pay.method = 'cash' THEN pay.amount ELSE 0 END) AS cash_total,
    sum(CASE WHEN pay.method = 'mpesa' THEN pay.amount ELSE 0 END) AS mpesa_total,
    sum(CASE WHEN pay.method = 'bank_transfer' THEN pay.amount ELSE 0 END) AS bank_total,
    f.amount_due,
    f.balance,
    f.payment_status AS fee_status
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN payments pay ON s.id = pay.student_id AND pay.status IN ('confirmed', 'completed', 'success')
LEFT JOIN student_academic_enrollments sae
    ON sae.student_id = s.id AND sae.academic_year_id = (SELECT MAX(x.id) FROM academic_years x WHERE x.id = sae.academic_year_id)
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = sae.academic_year_id
   AND pay.payment_date >= ayt.opening_date AND pay.payment_date <= ayt.closing_date
LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN vw_student_fee_balances f
    ON f.student_academic_enrollment_id = sae.id AND f.academic_year_term_id = ayt.id
GROUP BY s.id, ay.id, ayt.term_id, f.amount_due, f.balance, f.payment_status
ORDER BY s.admission_no ASC, ay.year_code DESC, ayt.term_id ASC
""",
)

vw(
    "vw_student_payment_status",
    """
SELECT
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    sl.name AS level,
    st.name AS student_type,
    t.name AS academic_term,
    sum(f.amount_due) AS total_fees_due,
    sum(f.amount_paid) AS total_fees_paid,
    sum(f.amount_waived) AS total_fees_waived,
    sum(f.balance) AS balance_outstanding,
    round(sum(f.amount_paid) / nullif(sum(f.amount_due), 0) * 100, 2) AS payment_percentage,
    CASE
        WHEN sum(f.balance) = 0 THEN 'PAID'
        WHEN sum(f.amount_paid) > 0 THEN 'PARTIAL'
        ELSE 'PENDING'
    END AS payment_status,
    max(pay.payment_date) AS last_payment_date,
    count(DISTINCT pay.id) AS number_of_payments,
    count(DISTINCT CASE WHEN dw.discount_type = 'full_waiver' THEN dw.id END) AS waivers_applied,
    CASE
        WHEN max(f.arrears_status) = 'overdue' THEN concat('OVERDUE (', max(f.days_overdue), ' days)')
        WHEN sum(f.balance) > 0 THEN 'ARREARS'
        ELSE 'OK'
    END AS arrears_status
FROM students s
JOIN persons p ON p.id = s.person_id
JOIN student_types st ON s.student_type_id = st.id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN vw_student_arrears f
    ON f.student_academic_enrollment_id = sae.id
   AND f.academic_year = year(curdate())
LEFT JOIN terms t ON t.id = f.term_id
LEFT JOIN payments pay ON s.id = pay.student_id AND pay.status IN ('confirmed', 'completed', 'success')
LEFT JOIN fee_discounts_waivers dw ON s.id = dw.student_id
WHERE s.status = 'active'
GROUP BY s.id, sl.id, t.id
ORDER BY s.admission_no ASC
""",
)

vw(
    "vw_student_payment_status_enhanced",
    """
SELECT
    s.id,
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    st.name AS student_type,
    concat(c.name, ' - ', sn.name) AS class_name,
    sl.name AS level_name,
    f.academic_year_id AS academic_year,
    coalesce(f.term_id, 1) AS term_number,
    coalesce(sum(f.amount_due), 0) AS total_due,
    coalesce(sum(f.amount_paid), 0) AS total_paid,
    coalesce(sum(f.amount_waived), 0) AS total_waived,
    coalesce(sum(f.balance), 0) AS current_balance,
    coalesce(sum(CASE WHEN f.academic_year_id = (SELECT MAX(y.id) FROM academic_years y) THEN f.balance ELSE 0 END), 0) AS year_balance,
    coalesce(sum(CASE WHEN f.academic_year_term_id = (SELECT MAX(x.id) FROM academic_year_terms x WHERE x.status = 'current') THEN f.balance ELSE 0 END), 0) AS term_balance,
    0 AS previous_year_balance,
    0 AS previous_term_balance,
    f.payment_status,
    coalesce(max(sfo.is_sponsored), 0) AS is_sponsored,
    coalesce(max(sfo.sponsored_waiver_amount), 0) AS sponsor_waiver_percentage
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_types st ON s.student_type_id = st.id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN school_levels sl ON sl.id = c.level_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN vw_student_fee_balances f ON f.student_academic_enrollment_id = sae.id
LEFT JOIN student_fee_obligations sfo ON sfo.student_academic_enrollment_id = sae.id
GROUP BY s.id, f.academic_year_id, f.term_id, f.payment_status
ORDER BY s.admission_no ASC, f.academic_year_id DESC, f.term_id DESC
""",
)

# ---------------------------------------------------------------------------
# 4. Attendance (student + staff + boarding)
# ---------------------------------------------------------------------------

vw(
    "vw_attendance_by_context",
    """
SELECT
    sa.id,
    sae.student_id,
    sa.date,
    sa.status,
    sa.register_type,
    sa.absence_reason,
    sa.check_in_time,
    sa.check_out_time,
    sa.notes,
    sa.marked_by,
    sae.academic_year_id,
    ayt.term_id AS term_id,
    ayc.class_id AS class_id,
    sa.session_id,
    ay.year_code AS academic_year_code,
    ay.year_name,
    t.code AS term_number,
    t.name AS term_name,
    c.name AS class_name,
    sn.name AS stream_name,
    ass.code AS session_code,
    ass.name AS session_name,
    ass.type AS session_type,
    ass.applies_to,
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    st.name AS student_type,
    st.code AS student_type_code
FROM student_attendance sa
JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
JOIN students s ON s.id = sae.student_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = sae.academic_year_id AND ayt.status = 'current'
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
LEFT JOIN student_types st ON st.id = s.student_type_id
""",
)

vw(
    "vw_boarding_roll_call",
    """
SELECT
    d.id AS dormitory_id,
    d.name AS dormitory_name,
    d.code AS dormitory_code,
    concat(hp.first_name, ' ', hp.last_name) AS house_parent,
    ba.date,
    ass.name AS session_name,
    ass.code AS session_code,
    count(DISTINCT s.id) AS total_students,
    sum(CASE WHEN ba.status = 'present' THEN 1 ELSE 0 END) AS present_count,
    sum(CASE WHEN ba.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
    sum(CASE WHEN ba.status = 'permission' THEN 1 ELSE 0 END) AS permission_count,
    sum(CASE WHEN ba.status = 'sick_bay' THEN 1 ELSE 0 END) AS sick_bay_count,
    round(sum(CASE WHEN ba.status = 'present' THEN 1 ELSE 0 END) * 100.0 / count(DISTINCT s.id), 1) AS attendance_percentage
FROM dormitories d
LEFT JOIN staff hps ON d.house_parent_id = hps.id
LEFT JOIN persons hp ON hp.id = hps.person_id
LEFT JOIN dormitory_assignments da ON d.id = da.dormitory_id AND da.status = 'active'
LEFT JOIN student_academic_enrollments sae ON sae.id = da.student_academic_enrollment_id
LEFT JOIN students s ON sae.student_id = s.id AND s.status = 'active'
LEFT JOIN boarding_attendance ba ON s.id = ba.student_id AND d.id = ba.dormitory_id
LEFT JOIN attendance_sessions ass ON ba.session_id = ass.id
WHERE d.status = 'active'
GROUP BY d.id, d.name, d.code, hp.first_name, hp.last_name, ba.date, ass.name, ass.code
""",
)

vw(
    "vw_boarding_roll_call_today",
    """
SELECT
    s.id AS student_id,
    s.admission_no,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    sn.name AS stream_name,
    c.name AS class_name,
    d.name AS dormitory_name,
    ds.bed_number,
    st.name AS student_type,
    ca.status AS class_status,
    ba.status AS boarding_status,
    ba.session_id AS boarding_session_id
FROM student_academic_enrollments sae
JOIN students s ON s.id = sae.student_id
JOIN persons p ON p.id = s.person_id
JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
JOIN streams sn ON sn.id = aycs.stream_id
JOIN student_types st ON st.id = s.student_type_id
LEFT JOIN dormitory_assignments ds
    ON ds.student_academic_enrollment_id = sae.id AND ds.status = 'active'
LEFT JOIN dormitories d ON d.id = ds.dormitory_id
LEFT JOIN student_attendance ca
    ON ca.student_academic_enrollment_id = sae.id AND ca.date = curdate() AND ca.register_type = 'class'
LEFT JOIN boarding_attendance ba ON ba.student_id = s.id AND ba.date = curdate()
WHERE s.status = 'active' AND st.code IN ('BOARD', 'WEEKLY')
""",
)

vw(
    "vw_expected_attendance_today",
    """
SELECT
    s.id AS student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no,
    st.code AS student_type_code,
    st.name AS student_type,
    ass.id AS session_id,
    ass.code AS session_code,
    ass.name AS session_name,
    ass.type AS session_type,
    ass.start_time,
    ass.end_time,
    CASE
        WHEN sp.id IS NOT NULL AND curdate() BETWEEN sp.start_date AND sp.end_date THEN 'permission'
        ELSE 'expected'
    END AS attendance_expectation,
    sp.id AS active_permission_id
FROM students s
JOIN persons p ON p.id = s.person_id
JOIN student_types st ON s.student_type_id = st.id
JOIN attendance_sessions ass
LEFT JOIN student_permissions sp
    ON s.id = sp.student_id
   AND curdate() BETWEEN sp.start_date AND sp.end_date
   AND sp.status = 'approved'
WHERE s.status = 'active'
  AND ass.status = 'active'
  AND (ass.applies_to = 'all'
       OR (ass.applies_to = 'boarders_only' AND st.code IN ('BOARD', 'WEEKLY'))
       OR (ass.applies_to = 'day_only' AND st.code = 'DAY'))
  AND json_contains(ass.applicable_days, concat('\"', dayname(curdate()), '\"'))
""",
)

vw(
    "vw_school_day_context",
    """
SELECT
    scd.date,
    cdt.code AS day_type,
    coalesce(scd.title, cdt.name) AS event_name,
    cdt.affects_day_students,
    cdt.affects_boarders,
    cdt.requires_attendance,
    ayt.id AS academic_year_id,
    ayt.term_id,
    dayname(scd.date) AS day_name,
    dayofweek(scd.date) AS day_number,
    CASE WHEN cdt.code IN ('school_day', 'half_day', 'exam_day', 'special_event') THEN 1 ELSE 0 END AS is_class_day,
    CASE WHEN cdt.code <> 'school_holiday' THEN 1 ELSE 0 END AS is_boarding_day
FROM academic_year_calendar_days scd
LEFT JOIN academic_year_calendar ac ON ac.id = scd.academic_year_calendar_id
LEFT JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
LEFT JOIN calendar_day_types cdt ON cdt.id = scd.calendar_day_type_id
""",
)

vw(
    "vw_student_attendance_summary",
    """
SELECT
    s.id AS student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no,
    c.name AS class_name,
    st.name AS student_type,
    d.name AS dormitory,
    ass.name AS session_name,
    ass.type AS session_type,
    sa.date,
    sa.status,
    sa.absence_reason,
    CASE
        WHEN sa.permission_id IS NOT NULL THEN 'Excused'
        WHEN sa.status = 'absent' THEN 'Unexcused'
        ELSE sa.status
    END AS attendance_category
FROM students s
JOIN persons p ON p.id = s.person_id
JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN student_types st ON s.student_type_id = st.id
LEFT JOIN dormitory_assignments da
    ON da.student_academic_enrollment_id = sae.id AND da.status = 'active'
LEFT JOIN dormitories d ON da.dormitory_id = d.id
LEFT JOIN student_attendance sa ON sa.student_academic_enrollment_id = sae.id
LEFT JOIN attendance_sessions ass ON sa.session_id = ass.id
WHERE s.status = 'active'
""",
)

vw(
    "vw_student_term_attendance_summary",
    """
SELECT
    sae.student_id AS student_id,
    sae.academic_year_id,
    ayt.term_id AS term_id,
    ayc.class_id AS class_id,
    sa.register_type,
    ay.year_code,
    t.code AS term_number,
    t.name AS term_name,
    c.name AS class_name,
    count(CASE WHEN sa.register_type = 'class' THEN sa.id END) AS class_days_marked,
    count(CASE WHEN sa.register_type = 'class' AND sa.status = 'present' THEN 1 END) AS class_days_present,
    count(CASE WHEN sa.register_type = 'class' AND sa.status = 'absent' THEN 1 END) AS class_days_absent,
    count(CASE WHEN sa.register_type = 'class' AND sa.status = 'late' THEN 1 END) AS class_days_late,
    count(CASE WHEN sa.register_type = 'boarding' THEN sa.id END) AS boarding_nights_marked,
    count(CASE WHEN sa.register_type = 'boarding' AND sa.status = 'present' THEN 1 END) AS boarding_nights_present,
    count(CASE WHEN sa.register_type = 'boarding' AND sa.status = 'absent' THEN 1 END) AS boarding_nights_absent,
    round(count(CASE WHEN sa.register_type = 'class' AND sa.status = 'present' THEN 1 END) * 100.0 / nullif(count(CASE WHEN sa.register_type = 'class' THEN 1 END), 0), 1) AS class_attendance_pct,
    round(count(CASE WHEN sa.register_type = 'boarding' AND sa.status = 'present' THEN 1 END) * 100.0 / nullif(count(CASE WHEN sa.register_type = 'boarding' THEN 1 END), 0), 1) AS boarding_attendance_pct
FROM student_attendance sa
JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = sae.academic_year_id AND ayt.status = 'current'
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
GROUP BY sae.id, ayt.id, ayc.class_id, sa.register_type
""",
)

vw(
    "vw_term_expected_days",
    """
SELECT
    ayt.id AS term_id,
    ayt.academic_year_id,
    count(0) AS expected_class_days
FROM academic_year_calendar_days scd
JOIN academic_year_calendar ac ON ac.id = scd.academic_year_calendar_id
JOIN academic_year_terms ayt ON ayt.id = ac.academic_year_term_id
JOIN calendar_day_types cdt ON cdt.id = scd.calendar_day_type_id
WHERE cdt.code IN ('school_day', 'half_day', 'exam_day')
  AND cdt.affects_day_students = 1
GROUP BY ayt.id, ayt.academic_year_id
""",
)

# ---------------------------------------------------------------------------
# 5. Academic: rosters, enrollments, timetables, schemes, exams
# ---------------------------------------------------------------------------

vw(
    "vw_class_rosters",
    """
SELECT
    aycs.id AS assignment_id,
    ay.year_code,
    c.name AS class_name,
    sn.name AS stream_name,
    concat(c.name, ' - ', sn.name) AS class_stream,
    aycs.class_teacher_id AS teacher_id,
    concat(p.first_name, ' ', p.last_name) AS teacher_name,
    NULL AS room_number,
    aycs.capacity AS capacity,
    (SELECT count(0) FROM student_academic_enrollments e2
      WHERE e2.academic_year_class_stream_id = aycs.id AND e2.enrollment_status = 'active')
        AS current_enrollment,
    aycs.capacity - (SELECT count(0) FROM student_academic_enrollments e2
      WHERE e2.academic_year_class_stream_id = aycs.id AND e2.enrollment_status = 'active')
        AS available_slots,
    round((SELECT count(0) FROM student_academic_enrollments e2
      WHERE e2.academic_year_class_stream_id = aycs.id AND e2.enrollment_status = 'active')
      / aycs.capacity * 100, 2) AS occupancy_rate
FROM academic_year_class_streams aycs
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN academic_years ay ON ay.id = ayc.academic_year_id
JOIN classes c ON c.id = ayc.class_id
JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN staff st ON aycs.class_teacher_id = st.id
LEFT JOIN persons p ON p.id = st.person_id
WHERE ay.is_current = 1 AND aycs.status = 'active'
""",
)

vw(
    "vw_class_timetable_coverage",
    """
SELECT
    c.id AS class_id,
    c.name AS class_name,
    NULL AS stream_name,
    ayt.id AS term_id,
    ayt.academic_year_id,
    count(te.id) AS slots_filled,
    (SELECT count(0) * 5 FROM time_slots ts WHERE ts.slot_type = 'lesson' AND ts.is_active = 1) AS total_lesson_slots,
    round(count(te.id) * 100.0 / nullif(
        (SELECT count(0) * 5 FROM time_slots ts2 WHERE ts2.slot_type = 'lesson' AND ts2.is_active = 1), 0), 1) AS coverage_pct
FROM classes c
LEFT JOIN academic_year_classes ayc ON ayc.class_id = c.id
LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = ayc.academic_year_id AND ayt.status = 'current'
LEFT JOIN timetable_entries te
    ON te.academic_year_class_stream_id = aycs.id AND te.status = 'active'
GROUP BY c.id, ayt.id, ayt.academic_year_id
""",
)

vw(
    "vw_current_enrollments",
    """
SELECT
    sae.id AS enrollment_id,
    sae.student_id,
    s.admission_no,
    concat(p.first_name, ' ', ifnull(p.middle_name, ''), ' ', p.last_name) AS student_name,
    p.gender,
    s.status AS student_status,
    ay.id AS academic_year_id,
    ay.year_code,
    ay.is_current,
    c.id AS class_id,
    c.name AS class_name,
    sn.id AS stream_id,
    sn.name AS stream_name,
    concat(c.name, ' - ', sn.name) AS class_stream,
    aycs.class_teacher_id AS teacher_id,
    tcp.first_name AS teacher_first_name,
    tcp.last_name AS teacher_last_name,
    NULL AS room_number,
    sae.enrollment_status,
    NULL AS year_average,
    NULL AS overall_grade,
    NULL AS class_rank,
    NULL AS attendance_percentage,
    tr.transition_type AS promotion_status,
    sae.enrolled_on AS enrollment_date
FROM student_academic_enrollments sae
JOIN students s ON s.id = sae.student_id
JOIN persons p ON p.id = s.person_id
JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN staff tc ON aycs.class_teacher_id = tc.id
LEFT JOIN persons tcp ON tcp.id = tc.person_id
LEFT JOIN student_transitions tr
    ON tr.student_id = sae.student_id AND tr.from_student_academic_enrollment_id = sae.id
WHERE sae.enrollment_status = 'active'
""",
)

vw(
    "vw_current_staff_assignments",
    """
SELECT
    t.id,
    t.staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no,
    sc.category_name AS staff_category,
    ayc.class_id AS class_id,
    c.name AS class_name,
    sn.name AS stream_name,
    t.role,
    la.name AS subject_name,
    ay.year_name AS academic_year,
    ay.status AS year_status,
    NULL AS start_date,
    NULL AS end_date,
    t.role AS status
FROM academic_year_class_learning_area_teachers t
JOIN staff s ON t.staff_id = s.id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = t.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN learning_areas la ON aycla.learning_area_id = la.id
LEFT JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
WHERE ay.status = 'active'
""",
)

vw(
    "vw_staff_assignments_detailed",
    """
SELECT
    t.id,
    t.staff_id,
    aycla.academic_year_class_id AS class_stream_id,
    ayc.class_id AS class_id,
    aycs.stream_id AS stream_id,
    ayt.academic_year_id,
    t.role,
    aycla.learning_area_id AS subject_id,
    NULL AS start_date,
    NULL AS end_date,
    t.role AS status,
    NULL AS notes,
    NULL AS created_at,
    NULL AS created_by,
    NULL AS updated_at,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    sn.name AS stream_name,
    c.name AS class_name,
    la.name AS subject_name,
    ay.year_name AS academic_year,
    count(0) OVER (PARTITION BY t.staff_id, ayt.academic_year_id) AS total_assignments
FROM academic_year_class_learning_area_teachers t
JOIN staff s ON t.staff_id = s.id
JOIN persons p ON p.id = s.person_id
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = t.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN learning_areas la ON aycla.learning_area_id = la.id
LEFT JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
""",
)

vw(
    "vw_staff_service_history",
    """
SELECT
    t.staff_id,
    ay.year_code AS academic_year,
    c.name AS class_name,
    sn.name AS stream_name,
    t.role,
    la.name AS subject_name,
    t.role AS status,
    NULL AS start_date,
    NULL AS end_date
FROM academic_year_class_learning_area_teachers t
JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
JOIN academic_years ay ON ay.id = ayt.academic_year_id
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = t.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN learning_areas la ON aycla.learning_area_id = la.id
ORDER BY t.staff_id ASC, ay.start_date ASC
""",
)

vw(
    "vw_staff_workload",
    """
SELECT
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    sc.category_name AS category_name,
    ay.year_name AS academic_year,
    count(DISTINCT ayc.class_id) AS classes_assigned,
    count(CASE WHEN t.role = 'class_teacher' THEN 1 END) AS class_teacher_count,
    count(CASE WHEN t.role = 'subject_teacher' THEN 1 END) AS subject_teacher_count,
    group_concat(DISTINCT c.name ORDER BY c.name ASC SEPARATOR ',') AS classes
FROM staff s
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
LEFT JOIN academic_year_class_learning_area_teachers t ON s.id = t.staff_id
LEFT JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id AND ay.status = 'active'
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = t.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
WHERE s.status = 'active'
GROUP BY s.id, s.staff_no, p.first_name, p.last_name, sc.category_name, ay.year_name
""",
)

vw(
    "vw_student_academic_history",
    """
SELECT
    sae.student_id,
    ay.year_code AS academic_year,
    ay.year_name,
    c.name AS class_name,
    sn.name AS stream_name,
    (SELECT avg_overall_percentage FROM term_consolidations tc1
      WHERE tc1.student_id = sae.student_id AND tc1.academic_year = ay.year_code AND tc1.term_id = 1)
        AS term1_average,
    (SELECT avg_overall_percentage FROM term_consolidations tc2
      WHERE tc2.student_id = sae.student_id AND tc2.academic_year = ay.year_code AND tc2.term_id = 2)
        AS term2_average,
    (SELECT avg_overall_percentage FROM term_consolidations tc3
      WHERE tc3.student_id = sae.student_id AND tc3.academic_year = ay.year_code AND tc3.term_id = 3)
        AS term3_average,
    tc.avg_overall_percentage AS year_average,
    tc.avg_overall_grade AS overall_grade,
    tc.class_position AS class_rank,
    NULL AS attendance_percentage,
    NULL AS days_present,
    NULL AS days_absent,
    tr.transition_type AS promotion_status,
    ayc2.class_id AS promoted_to_class_id,
    pc.name AS promoted_to_class
FROM student_academic_enrollments sae
JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
LEFT JOIN term_consolidations tc
    ON tc.student_id = sae.student_id AND tc.academic_year = ay.year_code
LEFT JOIN student_transitions tr
    ON tr.student_id = sae.student_id AND tr.academic_year_id = ay.id
LEFT JOIN student_academic_enrollments e2 ON e2.id = tr.to_student_academic_enrollment_id
LEFT JOIN academic_year_class_streams aycs2 ON aycs2.id = e2.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc2 ON ayc2.id = aycs2.academic_year_class_id
LEFT JOIN classes pc ON pc.id = ayc2.class_id
ORDER BY sae.student_id ASC, ay.start_date ASC
""",
)

vw(
    "vw_teacher_weekly_load",
    """
SELECT
    s.id AS teacher_id,
    concat(p.first_name, ' ', p.last_name) AS teacher_name,
    s.position AS designation,
    ayt.id AS term_id,
    ayt.academic_year_id,
    count(0) AS total_periods,
    count(DISTINCT ayc.class_id) AS classes_taught,
    count(DISTINCT te.learning_area_id) AS subjects_taught,
    sum(CASE WHEN te.day_of_week = 'Monday' THEN 1 ELSE 0 END) AS mon_periods,
    sum(CASE WHEN te.day_of_week = 'Tuesday' THEN 1 ELSE 0 END) AS tue_periods,
    sum(CASE WHEN te.day_of_week = 'Wednesday' THEN 1 ELSE 0 END) AS wed_periods,
    sum(CASE WHEN te.day_of_week = 'Thursday' THEN 1 ELSE 0 END) AS thu_periods,
    sum(CASE WHEN te.day_of_week = 'Friday' THEN 1 ELSE 0 END) AS fri_periods
FROM staff s
JOIN persons p ON p.id = s.person_id
JOIN timetable_entries te ON te.teacher_id = s.id AND te.status = 'active'
LEFT JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN academic_year_terms ayt ON ayt.id = te.academic_year_term_id
GROUP BY s.id, ayt.id, ayt.academic_year_id
""",
)

vw(
    "vw_upcoming_class_schedules",
    """
SELECT
    te.id,
    ayc.class_id AS class_id,
    c.name AS class_name,
    te.day_of_week,
    ts.start_time,
    ts.end_time,
    ts.period_number,
    te.learning_area_id AS subject_id,
    coalesce(la.name, '') AS subject_name,
    te.teacher_id,
    concat(p.first_name, ' ', p.last_name) AS teacher_name,
    NULL AS room_id,
    NULL AS room_name,
    ayt.academic_year_id,
    ayt.id AS term_id,
    te.status
FROM timetable_entries te
JOIN academic_year_class_streams aycs ON aycs.id = te.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
LEFT JOIN academic_year_terms ayt ON ayt.id = te.academic_year_term_id
LEFT JOIN time_slots ts ON ts.id = te.time_slot_id
LEFT JOIN learning_areas la ON la.id = te.learning_area_id
LEFT JOIN staff s ON te.teacher_id = s.id
LEFT JOIN persons p ON p.id = s.person_id
WHERE te.status = 'active'
""",
)

vw(
    "vw_upcoming_exam_schedules",
    """
SELECT
    es.id,
    ayt.id AS term_id,
    ayt.academic_year_id,
    ayc.class_id AS class_id,
    c.name AS class_name,
    es.learning_area_id AS subject_id,
    coalesce(la.name, '') AS subject_name,
    es.exam_name,
    es.exam_type,
    es.exam_date,
    es.start_time,
    es.end_time,
    es.duration_minutes,
    es.room_id,
    r.name AS room_name,
    es.venue,
    es.invigilator_id,
    concat(ip.first_name, ' ', ip.last_name) AS invigilator_name,
    es.supervisor_id,
    concat(sp.first_name, ' ', sp.last_name) AS supervisor_name,
    es.notes,
    es.status
FROM exam_schedules es
JOIN academic_year_class_streams aycs ON aycs.id = es.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN classes c ON c.id = ayc.class_id
LEFT JOIN academic_year_terms ayt ON ayt.id = es.academic_year_term_id
LEFT JOIN learning_areas la ON la.id = es.learning_area_id
LEFT JOIN rooms r ON es.room_id = r.id
LEFT JOIN staff inv ON es.invigilator_id = inv.id
LEFT JOIN persons ip ON ip.id = inv.person_id
LEFT JOIN staff sup ON es.supervisor_id = sup.id
LEFT JOIN persons sp ON sp.id = sup.person_id
WHERE es.status IN ('scheduled', 'upcoming', 'in_progress')
""",
)

vw(
    "vw_scheme_completion",
    """
SELECT
    sw.teacher_id AS teacher_id,
    concat(p.first_name, ' ', p.last_name) AS teacher_name,
    ayt.id AS term_id,
    la.name AS subject_name,
    count(sw.id) AS total_schemes,
    sum(CASE WHEN sw.status = 'approved' THEN 1 ELSE 0 END) AS approved,
    sum(CASE WHEN sw.status = 'submitted' THEN 1 ELSE 0 END) AS pending,
    sum(CASE WHEN sw.status = 'draft' THEN 1 ELSE 0 END) AS drafts
FROM schemes_of_work sw
JOIN staff s ON sw.teacher_id = s.id
JOIN persons p ON p.id = s.person_id
LEFT JOIN academic_year_class_learning_areas aycla
    ON aycla.id = sw.academic_year_class_learning_area_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = ayc.academic_year_id AND ayt.status = 'current'
LEFT JOIN learning_areas la ON la.id = aycla.learning_area_id
GROUP BY sw.teacher_id, ayt.id, la.name
""",
)

# ---------------------------------------------------------------------------
# 6. Staff HR: onboarding, leave, loans, performance, payroll
# ---------------------------------------------------------------------------

vw(
    "vw_onboarding_dashboard",
    """
SELECT
    wi.id AS onboarding_id,
    s.id AS staff_id,
    s.contract_type,
    NULL AS probation_months,
    NULL AS probation_outcome,
    date(wi.started_at) AS start_date,
    NULL AS target_completion,
    date(wi.completed_at) AS actual_completion,
    wi.status,
    round(count(ot.status = 'completed') / nullif(count(ot.id), 0) * 100, 0) AS progress_percent,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no,
    s.position,
    d.name AS department,
    sc.category_name AS staff_category,
    stt.name AS staff_type,
    NULL AS mentor_name,
    count(ot.id) AS total_tasks,
    sum(ot.status = 'completed') AS done_tasks,
    sum(ot.status = 'pending') AS pending_tasks,
    sum(ot.status = 'in_progress') AS active_tasks,
    sum(ot.status = 'blocked') AS blocked_tasks,
    sum(ot.status <> 'completed' AND ot.status <> 'skipped' AND ot.due_date < curdate()) AS overdue_tasks,
    to_days(curdate()) - to_days(date(wi.started_at)) AS days_elapsed,
    NULL AS days_remaining,
    count(od.id) AS docs_collected
FROM workflow_instances wi
JOIN staff s ON s.id = wi.reference_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
LEFT JOIN staff_types stt ON s.staff_type_id = stt.id
LEFT JOIN onboarding_tasks ot ON ot.onboarding_id = wi.id
LEFT JOIN onboarding_documents od ON od.onboarding_id = wi.id
WHERE wi.reference_type = 'staff_onboarding'
GROUP BY wi.id
""",
)

vw(
    "vw_onboarding_pending_by_role",
    """
SELECT
    ot.onboarding_id,
    ot.id AS task_id,
    ot.task_name,
    ot.category,
    ot.priority,
    ot.due_date,
    ot.status,
    CASE WHEN ot.due_date < curdate() AND ot.status NOT IN ('completed', 'skipped') THEN 1 ELSE 0 END AS is_overdue,
    to_days(curdate()) - to_days(ot.due_date) AS days_overdue,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no,
    d.name AS department,
    concat(ap.first_name, ' ', ap.last_name) AS assigned_to_name
FROM onboarding_tasks ot
JOIN workflow_instances wi ON wi.id = ot.onboarding_id
JOIN staff s ON s.id = wi.reference_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN staff a ON ot.assigned_to = a.id
LEFT JOIN persons ap ON ap.id = a.person_id
WHERE ot.status NOT IN ('completed', 'skipped')
  AND wi.status NOT IN ('completed', 'terminated')
ORDER BY ot.due_date ASC, ot.priority DESC
""",
)

vw(
    "vw_staff_onboarding_progress",
    """
SELECT
    wi.id AS onboarding_id,
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.position,
    d.name AS department,
    wi.status,
    date(wi.started_at) AS start_date,
    NULL AS expected_end_date,
    date(wi.completed_at) AS completion_date,
    NULL AS mentor_name,
    count(ot.id) AS total_tasks,
    sum(CASE WHEN ot.status = 'completed' THEN 1 ELSE 0 END) AS completed_tasks,
    sum(CASE WHEN ot.status = 'in_progress' THEN 1 ELSE 0 END) AS inprogress_tasks,
    sum(CASE WHEN ot.status = 'pending' THEN 1 ELSE 0 END) AS pending_tasks,
    sum(CASE WHEN ot.status = 'skipped' THEN 1 ELSE 0 END) AS skipped_tasks,
    sum(CASE WHEN ot.status = 'pending' AND ot.due_date < curdate() THEN 1 ELSE 0 END) AS overdue_tasks,
    round(count(ot.status = 'completed') / nullif(count(ot.id), 0) * 100, 0) AS progress_percent
FROM workflow_instances wi
JOIN staff s ON s.id = wi.reference_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN onboarding_tasks ot ON wi.id = ot.onboarding_id
WHERE wi.reference_type = 'staff_onboarding'
GROUP BY wi.id, s.id
""",
)

vw(
    "vw_staff_leave_balance",
    """
SELECT
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    lt.code AS leave_type,
    lt.name AS leave_name,
    lt.days_allowed AS annual_entitlement,
    coalesce(sum(CASE WHEN sl.status = 'approved' AND year(sl.start_date) = year(curdate()) THEN sl.days_requested ELSE 0 END), 0) AS days_taken_this_year,
    CASE
        WHEN lt.days_allowed IS NULL THEN NULL
        ELSE lt.days_allowed - coalesce(sum(CASE WHEN sl.status = 'approved' AND year(sl.start_date) = year(curdate()) THEN sl.days_requested ELSE 0 END), 0)
    END AS days_remaining
FROM staff s
JOIN persons p ON p.id = s.person_id
JOIN leave_types lt
LEFT JOIN staff_leaves sl ON s.id = sl.staff_id AND sl.leave_type_id = lt.id
WHERE s.status = 'active' AND lt.status = 'active'
GROUP BY s.id, s.staff_no, p.first_name, p.last_name, lt.id, lt.code, lt.name, lt.days_allowed
""",
)

vw(
    "vw_staff_leave_balances",
    """
SELECT
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    lt.id AS leave_type_id,
    lt.name AS leave_type_name,
    lt.days_allowed AS entitled_days,
    coalesce(sum(CASE WHEN sl.status IN ('approved', 'taken') AND year(sl.start_date) = year(curdate()) THEN sl.days_requested ELSE 0 END), 0) AS used_days,
    coalesce(sum(CASE WHEN sl.status = 'pending' THEN sl.days_requested ELSE 0 END), 0) AS pending_days,
    lt.days_allowed - coalesce(sum(CASE WHEN sl.status IN ('approved', 'taken') AND year(sl.start_date) = year(curdate()) THEN sl.days_requested ELSE 0 END), 0) AS available_days
FROM staff s
JOIN persons p ON p.id = s.person_id
JOIN leave_types lt
LEFT JOIN staff_leaves sl ON s.id = sl.staff_id AND sl.leave_type_id = lt.id
WHERE s.status = 'active' AND lt.status = 'active'
GROUP BY s.id, s.staff_no, p.first_name, p.last_name, lt.id, lt.name, lt.days_allowed
""",
)

vw(
    "vw_staff_loan_details",
    """
SELECT
    sl.id AS loan_id,
    sl.staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no AS staff_number,
    sl.loan_type,
    sl.principal_amount,
    sl.loan_date,
    sl.agreed_monthly_deduction,
    sl.balance_remaining,
    sl.principal_amount - sl.balance_remaining AS total_paid,
    round((sl.principal_amount - sl.balance_remaining) / sl.principal_amount * 100, 2) AS payment_progress_percent,
    CASE WHEN sl.balance_remaining = 0 THEN 0 ELSE ceiling(sl.balance_remaining / sl.agreed_monthly_deduction) END AS months_remaining,
    sl.status,
    CASE sl.status
        WHEN 'active' THEN 'Active - On Schedule'
        WHEN 'paid_off' THEN 'Fully Paid'
        WHEN 'defaulted' THEN 'Defaulted - Payment Issues'
        WHEN 'suspended' THEN 'Suspended - Temporarily Paused'
    END AS status_description,
    sl.created_at AS loan_created_at,
    sl.updated_at AS last_updated,
    (SELECT count(0) FROM payslips x WHERE x.staff_id = sl.staff_id AND x.loan_deduction > 0 AND x.created_at >= sl.loan_date) AS payments_made_count,
    (SELECT sum(x.loan_deduction) FROM payslips x WHERE x.staff_id = sl.staff_id AND x.created_at >= sl.loan_date) AS total_deducted,
    CASE WHEN sl.status = 'active' AND sl.agreed_monthly_deduction > 0 THEN curdate() + interval ceiling(sl.balance_remaining / sl.agreed_monthly_deduction) month ELSE NULL END AS expected_completion_date
FROM staff_loans sl
JOIN staff s ON sl.staff_id = s.id
JOIN persons p ON p.id = s.person_id
""",
)

vw(
    "vw_staff_performance_summary",
    """
SELECT
    pr.id AS review_id,
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.position,
    d.name AS department,
    ay.year_name AS academic_year,
    pr.period AS review_period,
    pr.status AS review_type,
    pr.status,
    pr.rating AS overall_score,
    NULL AS performance_grade,
    count(prk.id) AS total_kpis,
    sum(CASE WHEN prk.status = 'completed' THEN 1 ELSE 0 END) AS completed_kpis,
    CASE WHEN count(prk.id) > 0 THEN round(sum(CASE WHEN prk.status = 'completed' THEN 1 ELSE 0 END) / count(prk.id) * 100, 0) ELSE 0 END AS completion_percent,
    pr.review_date,
    pr.created_at AS completion_date
FROM performance_reviews pr
JOIN staff s ON pr.staff_id = s.id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN academic_years ay ON ay.is_current = 1
LEFT JOIN performance_review_kpis prk ON pr.id = prk.review_id
GROUP BY pr.id, s.id, s.staff_no, p.first_name, p.last_name, s.position, d.name, ay.year_name, pr.period, pr.status, pr.rating, pr.review_date, pr.created_at
""",
)

vw(
    "vw_payslip_detailed",
    """
SELECT
    p.id AS payslip_id,
    p.staff_id,
    s.staff_no,
    concat(pn.first_name, ' ', pn.last_name) AS staff_name,
    s.position,
    d.name AS department_name,
    coalesce(spp.bank_account, s.bank_account) AS bank_account,
    spp.nssf_no,
    spp.nhif_no,
    spp.kra_pin,
    p.payroll_month,
    p.payroll_year,
    concat(p.payroll_year, '-', lpad(p.payroll_month, 2, '0')) AS payroll_period,
    p.basic_salary,
    p.allowances_total,
    p.gross_salary,
    p.paye_tax,
    p.nssf_contribution,
    p.nhif_contribution,
    p.housing_levy,
    p.loan_deduction,
    p.child_fees_deduction,
    p.sacco_deduction,
    p.salary_advance_deduction,
    p.other_deductions_total,
    p.paye_tax + p.nssf_contribution + p.nhif_contribution + p.housing_levy + p.loan_deduction + p.child_fees_deduction + p.sacco_deduction + p.salary_advance_deduction + p.other_deductions_total AS total_deductions,
    p.net_salary,
    p.payment_method,
    p.payment_date,
    p.payment_status,
    p.payment_reference,
    p.paid_at,
    p.payslip_status,
    p.allowances_breakdown,
    p.deductions_breakdown,
    p.child_fees_breakdown,
    p.notes,
    concat(sg.first_name, ' ', sg.last_name) AS signed_by_name,
    p.created_at,
    p.updated_at,
    (SELECT count(0) FROM staff_children sc WHERE sc.staff_id = p.staff_id AND sc.fee_deduction_enabled = 1) AS children_count
FROM payslips p
JOIN staff s ON p.staff_id = s.id
JOIN persons pn ON pn.id = s.person_id
LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN users su ON p.signed_by = su.id
LEFT JOIN persons sg ON sg.id = su.person_id
""",
)

vw(
    "vw_staff_payroll_summary",
    """
SELECT
    ps.id AS payslip_id,
    ps.staff_id,
    concat(pn.first_name, ' ', pn.last_name) AS staff_name,
    s.staff_no AS staff_number,
    ps.payroll_month,
    ps.payroll_year,
    date_format(concat(ps.payroll_year, '-', lpad(ps.payroll_month, 2, '0'), '-01'), '%M %Y') AS period_display,
    ps.basic_salary,
    ps.allowances_total,
    ps.gross_salary,
    ps.paye_tax,
    ps.nssf_contribution,
    ps.nhif_contribution,
    ps.loan_deduction,
    ps.other_deductions_total,
    ps.paye_tax + ps.nssf_contribution + ps.nhif_contribution + ps.loan_deduction + ps.other_deductions_total AS total_deductions,
    ps.net_salary,
    ps.payment_method,
    ps.payment_date,
    ps.payslip_status,
    concat(ap.first_name, ' ', ap.last_name) AS approved_by_name,
    ps.notes,
    ps.created_at,
    ps.updated_at,
    (SELECT sum(x.gross_salary) FROM payslips x WHERE x.staff_id = ps.staff_id AND x.payroll_year = ps.payroll_year AND (x.payroll_month <= ps.payroll_month OR x.payroll_year < ps.payroll_year)) AS ytd_gross,
    (SELECT sum(x.paye_tax) FROM payslips x WHERE x.staff_id = ps.staff_id AND x.payroll_year = ps.payroll_year AND (x.payroll_month <= ps.payroll_month OR x.payroll_year < ps.payroll_year)) AS ytd_paye,
    (SELECT sum(x.nssf_contribution) FROM payslips x WHERE x.staff_id = ps.staff_id AND x.payroll_year = ps.payroll_year AND (x.payroll_month <= ps.payroll_month OR x.payroll_year < ps.payroll_year)) AS ytd_nssf,
    (SELECT sum(x.nhif_contribution) FROM payslips x WHERE x.staff_id = ps.staff_id AND x.payroll_year = ps.payroll_year AND (x.payroll_month <= ps.payroll_month OR x.payroll_year < ps.payroll_year)) AS ytd_nhif,
    (SELECT sum(x.net_salary) FROM payslips x WHERE x.staff_id = ps.staff_id AND x.payroll_year = ps.payroll_year AND (x.payroll_month <= ps.payroll_month OR x.payroll_year < ps.payroll_year)) AS ytd_net
FROM payslips ps
JOIN staff s ON ps.staff_id = s.id
JOIN persons pn ON pn.id = s.person_id
LEFT JOIN users au ON ps.signed_by = au.id
LEFT JOIN persons ap ON ap.id = au.person_id
""",
)

vw(
    "vw_staff_children_fees",
    """
SELECT
    sc.id AS staff_child_id,
    sc.staff_id,
    s.staff_no,
    concat(sp.first_name, ' ', sp.last_name) AS staff_name,
    spp.basic_salary AS staff_salary,
    sc.student_id,
    st.admission_no,
    concat(tp.first_name, ' ', tp.last_name) AS student_name,
    tp.dob AS student_dob,
    c.name AS class_name,
    sn.name AS stream_name,
    sc.relationship,
    sc.fee_deduction_enabled,
    sc.fee_deduction_percentage,
    0 AS is_sponsored,
    0 AS sponsor_waiver_percentage,
    (SELECT count(0) FROM staff_children x WHERE x.staff_id = sc.staff_id) AS total_children,
    sc.created_at
FROM staff_children sc
JOIN staff s ON sc.staff_id = s.id
JOIN persons sp ON sp.id = s.person_id
LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
JOIN students st ON sc.student_id = st.id
JOIN persons tp ON tp.id = st.person_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = st.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
WHERE s.status = 'active' AND st.status = 'active'
""",
)

vw(
    "vw_staff_attendance_anomalies",
    """
SELECT
    sa.staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no,
    d.name AS department,
    count(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'unauthorized' THEN 1 END) AS unauthorized_absences,
    count(CASE WHEN sa.status = 'late' THEN 1 END) AS late_arrivals,
    count(sa.id) AS total_days,
    round(count(CASE WHEN sa.status IN ('present', 'late') THEN 1 END) * 100.0 / nullif(count(sa.id), 0), 1) AS pct,
    max(CASE WHEN sa.check_in IS NOT NULL AND ss.start_time IS NOT NULL AND sa.check_in > ss.start_time THEN time_to_sec(timediff(sa.check_in, ss.start_time)) / 60 END) AS max_minutes_late
FROM staff_attendance sa
JOIN staff s ON s.id = sa.staff_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN (SELECT staff_id, MIN(start_time) AS start_time FROM staff_shift_assignments WHERE effective_to IS NULL GROUP BY staff_id) ss ON ss.staff_id = s.id
WHERE sa.date >= curdate() - interval 30 day
GROUP BY sa.staff_id
HAVING unauthorized_absences >= 2 OR late_arrivals >= 3
""",
)

vw(
    "vw_staff_attendance_status",
    """
SELECT
    s.id AS staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    s.staff_no,
    d.name AS department,
    s.position,
    s.contract_type,
    sa.date,
    sa.status AS attendance_status,
    sa.check_in AS check_in_time,
    sa.check_out AS check_out_time,
    sl.id AS leave_id,
    lt.name AS leave_type,
    sl.status AS leave_status,
    sdr.id AS duty_id,
    sdt.name AS duty_type,
    CASE
        WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 'on_leave'
        WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 'off_day'
        WHEN sa.status = 'present' THEN 'present'
        WHEN sa.status = 'late' THEN 'late'
        WHEN sa.status = 'absent' THEN 'absent'
        ELSE 'unknown'
    END AS effective_status
FROM staff s
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN staff_attendance sa ON s.id = sa.staff_id AND sa.date = curdate()
LEFT JOIN staff_leaves sl ON s.id = sl.staff_id AND curdate() BETWEEN sl.start_date AND sl.end_date
LEFT JOIN leave_types lt ON sl.leave_type_id = lt.id
LEFT JOIN staff_duty_roster sdr ON s.id = sdr.staff_id AND sdr.date = curdate()
LEFT JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
WHERE s.status = 'active'
""",
)

vw(
    "vw_staff_daily_register",
    """
SELECT
    s.id AS staff_id,
    s.staff_no,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    p.first_name,
    p.last_name,
    s.position,
    ss.start_time AS work_start_time,
    ss.end_time AS work_end_time,
    15 AS late_threshold_minutes,
    d.id AS department_id,
    d.name AS department_name,
    sc.category_name AS staff_category,
    sa.id AS attendance_id,
    sa.date,
    sa.status AS marked_status,
    (SELECT MAX(x.shift) FROM staff_shift_assignments x WHERE x.staff_id = s.id AND x.effective_to IS NULL) AS shift,
    sa.check_in AS check_in_time,
    sa.check_out AS check_out_time,
    NULL AS expected_check_in,
    sa.absence_reason,
    sa.notes AS attendance_notes,
    sa.academic_year_id,
    sl.id AS leave_id,
    lt.name AS leave_type,
    sl.status AS leave_status,
    sl.start_date AS leave_start,
    sl.end_date AS leave_end,
    concat(rp.first_name, ' ', rp.last_name) AS relief_staff_name,
    sdr.id AS duty_roster_id,
    sdt.code AS duty_code,
    sdt.name AS duty_name,
    sdr.shift AS duty_shift,
    sdr.start_time AS duty_start,
    sdr.end_time AS duty_end,
    sdr.location AS duty_location,
    sop.id AS pattern_off_id,
    CASE
        WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 'on_leave'
        WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 'off_day'
        WHEN sop.id IS NOT NULL THEN 'off_day'
        WHEN sa.status = 'present' THEN 'present'
        WHEN sa.status = 'absent' AND sa.absence_reason = 'leave' THEN 'on_leave'
        WHEN sa.status = 'absent' AND sa.absence_reason = 'off_day' THEN 'off_day'
        WHEN sa.status = 'absent' THEN 'absent'
        WHEN sa.status = 'late' THEN 'late'
        WHEN sa.id IS NULL THEN 'not_marked'
        ELSE sa.status
    END AS effective_status,
    CASE
        WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 0
        WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 0
        WHEN sop.id IS NOT NULL THEN 0
        ELSE 1
    END AS can_mark,
    CASE
        WHEN sa.check_in IS NOT NULL AND ss.start_time IS NOT NULL AND sa.check_in > addtime(ss.start_time, sec_to_time(coalesce(15, 15) * 60)) THEN 1
        ELSE 0
    END AS is_late,
    CASE
        WHEN sa.check_in IS NOT NULL AND ss.start_time IS NOT NULL AND sa.check_in > ss.start_time THEN time_to_sec(timediff(sa.check_in, ss.start_time)) / 60
        ELSE 0
    END AS minutes_late
FROM staff s
JOIN persons p ON p.id = s.person_id
LEFT JOIN (SELECT staff_id, MIN(start_time) AS start_time, MAX(end_time) AS end_time FROM staff_shift_assignments WHERE effective_to IS NULL GROUP BY staff_id) ss ON ss.staff_id = s.id
LEFT JOIN staff_department_assignments sda
    ON sda.staff_id = s.id AND sda.effective_to IS NULL
LEFT JOIN departments d ON d.id = sda.department_id
LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
LEFT JOIN staff_attendance sa ON sa.staff_id = s.id
LEFT JOIN staff_leaves sl
    ON sl.staff_id = s.id AND sa.date BETWEEN sl.start_date AND sl.end_date AND sl.status = 'approved'
LEFT JOIN leave_types lt ON lt.id = sl.leave_type_id
LEFT JOIN staff rs ON sl.relief_staff_id = rs.id
LEFT JOIN persons rp ON rp.id = rs.person_id
LEFT JOIN staff_duty_roster sdr ON sdr.staff_id = s.id AND sdr.date = sa.date
LEFT JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id
LEFT JOIN staff_off_day_patterns sop
    ON sop.staff_id = s.id AND sop.day_of_week = dayname(sa.date) AND sop.is_off = 1
   AND sa.date >= sop.effective_from AND (sop.effective_to IS NULL OR sa.date <= sop.effective_to)
WHERE s.status = 'active'
""",
)

vw(
    "vw_staff_monthly_summary",
    """
SELECT
    sa.staff_id,
    sa.academic_year_id,
    ay.year_code,
    year(sa.date) AS attendance_year,
    month(sa.date) AS attendance_month,
    monthname(sa.date) AS month_name,
    count(CASE WHEN sa.status = 'present' THEN 1 END) AS days_present,
    count(CASE WHEN sa.status = 'absent' AND sa.absence_reason NOT IN ('leave', 'off_day') THEN 1 END) AS days_unauthorized_absent,
    count(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'leave' THEN 1 END) AS days_on_leave,
    count(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'off_day' THEN 1 END) AS days_off,
    count(CASE WHEN sa.status = 'late' THEN 1 END) AS days_late,
    count(sa.id) AS total_days_marked,
    round(count(CASE WHEN sa.status IN ('present', 'late') THEN 1 END) * 100.0 / nullif(count(CASE WHEN sa.absence_reason <> 'off_day' OR sa.absence_reason IS NULL THEN 1 END), 0), 1) AS attendance_pct,
    sum(CASE WHEN sa.check_in IS NOT NULL AND ss.start_time IS NOT NULL AND sa.check_in > ss.start_time THEN time_to_sec(timediff(sa.check_in, ss.start_time)) / 60 ELSE 0 END) AS total_minutes_late
FROM staff_attendance sa
JOIN staff s ON s.id = sa.staff_id
LEFT JOIN (SELECT staff_id, MIN(start_time) AS start_time FROM staff_shift_assignments WHERE effective_to IS NULL GROUP BY staff_id) ss ON ss.staff_id = s.id
LEFT JOIN academic_years ay ON ay.id = sa.academic_year_id
GROUP BY sa.staff_id, sa.academic_year_id, year(sa.date), month(sa.date)
""",
)

vw(
    "vw_staff_off_day_schedule",
    """
SELECT
    s.id AS staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    sda.department_id,
    'pattern' AS source,
    sop.day_of_week,
    sop.effective_from,
    sop.effective_to,
    sop.reason
FROM staff s
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
JOIN staff_off_day_patterns sop
    ON sop.staff_id = s.id AND sop.is_off = 1
   AND curdate() BETWEEN sop.effective_from AND coalesce(sop.effective_to, '2099-12-31')
UNION ALL
SELECT
    s.id AS staff_id,
    concat(p.first_name, ' ', p.last_name) AS staff_name,
    sda.department_id,
    'roster' AS source,
    dayname(sdr.date) AS day_of_week,
    sdr.date AS effective_from,
    sdr.date AS effective_to,
    sdt.name AS reason
FROM staff s
JOIN persons p ON p.id = s.person_id
LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
JOIN staff_duty_roster sdr ON sdr.staff_id = s.id AND sdr.date BETWEEN curdate() AND curdate() + interval 7 day
JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id AND sdt.code IN ('OFF', 'WEEKEND_OFF')
""",
)

# ---------------------------------------------------------------------------
# 7. Communications, families, uniforms, catering
# ---------------------------------------------------------------------------

vw(
    "vw_internal_conversations",
    """
SELECT
    c.id AS conversation_id,
    c.title,
    c.conversation_type,
    c.created_by,
    p.first_name,
    p.last_name,
    count(DISTINCT im.id) AS total_messages,
    max(im.created_at) AS last_message_date,
    count(DISTINCT CASE WHEN im.priority = 'high' THEN im.id END) AS high_priority_messages,
    count(cp.id) AS participant_count,
    c.created_at,
    c.updated_at
FROM internal_conversations c
LEFT JOIN users u ON c.created_by = u.id
LEFT JOIN persons p ON p.id = u.person_id
LEFT JOIN internal_messages im ON c.id = im.conversation_id
LEFT JOIN conversation_participants cp ON c.id = cp.conversation_id
GROUP BY c.id
ORDER BY max(im.created_at) DESC
""",
)

vw(
    "vw_pending_sms",
    """
SELECT
    s.id AS sms_id,
    s.parent_id,
    concat(p.first_name, ' ', p.last_name) AS parent_name,
    s.recipient_phone,
    s.message_body,
    s.sms_type,
    s.status,
    mt.name AS template_name,
    up.first_name AS sent_by_first,
    up.last_name AS sent_by_last,
    s.created_at,
    s.sent_at,
    s.delivered_at,
    timestampdiff(HOUR, s.created_at, current_timestamp()) AS hours_pending
FROM sms_communications s
LEFT JOIN parents pr ON s.parent_id = pr.id
LEFT JOIN persons p ON p.id = pr.person_id
LEFT JOIN message_templates mt ON s.template_id = mt.id
LEFT JOIN users u ON s.sent_by = u.id
LEFT JOIN persons up ON up.id = u.person_id
WHERE s.status IN ('pending', 'queued')
ORDER BY s.created_at ASC
""",
)

vw(
    "vw_sent_emails",
    """
SELECT
    e.id AS email_id,
    e.institution_id,
    ei.name AS institution_name,
    ei.contact_person_name,
    e.recipient_email,
    e.subject,
    e.email_type,
    e.status,
    count(e.id) AS attempts,
    max(e.updated_at) AS last_attempt,
    up.first_name AS sent_by_first,
    up.last_name AS sent_by_last,
    e.created_at,
    CASE e.status
        WHEN 'sent' THEN 'Successfully Delivered'
        WHEN 'failed' THEN 'Delivery Failed'
        WHEN 'pending' THEN 'Awaiting Delivery'
        WHEN 'bounced' THEN 'Bounced Back'
        ELSE 'Unknown'
    END AS delivery_status_text
FROM external_emails e
LEFT JOIN external_institutions ei ON e.institution_id = ei.id
LEFT JOIN users u ON e.sent_by = u.id
LEFT JOIN persons up ON up.id = u.person_id
GROUP BY e.id
ORDER BY e.created_at DESC
""",
)

vw(
    "vw_unread_announcements",
    """
SELECT
    a.id AS announcement_id,
    a.title,
    a.content,
    a.priority,
    a.target_audience,
    p.first_name AS published_by_first,
    p.last_name AS published_by_last,
    a.status,
    a.created_at,
    a.updated_at,
    count(av.id) AS total_views
FROM announcements_bulletin a
LEFT JOIN staff st ON a.published_by = st.id
LEFT JOIN persons p ON p.id = st.person_id
LEFT JOIN announcement_views av ON a.id = av.announcement_id
WHERE a.status = 'published'
GROUP BY a.id
ORDER BY a.created_at DESC
""",
)

vw(
    "vw_family_groups",
    """
SELECT
    pr.id AS parent_id,
    concat(p.first_name, coalesce(concat(' ', p.middle_name), ''), ' ', p.last_name) AS parent_full_name,
    p.national_id_no AS parent_id_number,
    p.phone AS parent_phone_primary,
    NULL AS parent_phone_secondary,
    p.email AS parent_email,
    p.gender AS parent_gender,
    pr.occupation AS parent_occupation,
    pr.address AS parent_address,
    pr.status AS parent_status,
    s.id AS student_id,
    s.admission_no,
    concat(ps.first_name, coalesce(concat(' ', ps.middle_name), ''), ' ', ps.last_name) AS student_full_name,
    ps.dob AS student_dob,
    ps.gender AS student_gender,
    s.status AS student_status,
    ps.photo_url AS student_photo,
    c.name AS class_name,
    sn.name AS stream_name,
    concat(c.name, ' - ', sn.name) AS class_stream,
    sp.relationship,
    sp.is_primary_contact,
    sp.is_emergency_contact,
    NULL AS financial_responsibility,
    coalesce((SELECT sum(x.balance) FROM vw_student_fee_balances x WHERE x.student_id = s.id), 0) AS current_fee_balance,
    (SELECT count(0) - 1 FROM student_parents sp2 WHERE sp2.parent_id = pr.id) AS sibling_count
FROM parents pr
JOIN persons p ON p.id = pr.person_id
JOIN student_parents sp ON pr.id = sp.parent_id
JOIN students s ON sp.student_id = s.id
JOIN persons ps ON ps.id = s.person_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
WHERE pr.status = 'active'
""",
)

vw(
    "vw_payment_transactions_with_amount",
    """
SELECT
    p.id,
    p.student_id,
    ay.id AS academic_year,
    ayt.id AS term_id,
    NULL AS term_allocation,
    NULL AS fee_structure_detail_id,
    p.parent_id,
    p.amount AS amount_paid,
    p.payment_date,
    p.method AS payment_method,
    p.reference AS reference_no,
    p.receipt_no,
    p.received_by,
    p.status,
    p.notes,
    p.created_at,
    p.updated_at,
    p.amount AS amount
FROM payments p
LEFT JOIN student_academic_enrollments sae ON sae.student_id = p.student_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = sae.academic_year_id
   AND p.payment_date >= ayt.opening_date AND p.payment_date <= ayt.closing_date
LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
""",
)

vw(
    "vw_uniform_sales_analytics",
    """
SELECT
    us.id AS sale_id,
    s.id AS student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no AS admission_number,
    ii.name AS uniform_item,
    ii.code AS item_code,
    us.size,
    us.quantity,
    us.unit_price,
    us.total_amount,
    us.payment_status,
    us.sale_date,
    us.received_date,
    to_days(curdate()) - to_days(us.sale_date) AS days_since_sale,
    CASE us.payment_status
        WHEN 'paid' THEN 'Paid'
        WHEN 'pending' THEN 'Awaiting Payment'
        WHEN 'partial' THEN 'Partially Paid'
        ELSE 'Unknown'
    END AS payment_status_label,
    up.first_name AS sold_by_first_name,
    up.last_name AS sold_by_last_name
FROM uniform_sales us
JOIN students s ON us.student_id = s.id
JOIN persons p ON p.id = s.person_id
JOIN inventory_items ii ON us.item_id = ii.id
LEFT JOIN users u ON us.sold_by = u.id
LEFT JOIN persons up ON up.id = u.person_id
""",
)

vw(
    "vw_student_uniform_balance",
    """
SELECT
    s.id AS student_id,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    s.admission_no,
    count(us.id) AS total_sales,
    sum(us.total_amount) AS total_billed,
    sum(us.amount_paid) AS total_paid,
    sum(us.balance_due) AS total_balance,
    max(us.sale_date) AS last_purchase
FROM students s
JOIN persons p ON p.id = s.person_id
JOIN uniform_sales us ON us.student_id = s.id
WHERE us.balance_due > 0
GROUP BY s.id
ORDER BY sum(us.balance_due) DESC
""",
)

vw(
    "vw_food_consumption_summary",
    """
SELECT
    fc.consumption_date,
    i.name AS food_item,
    i.code AS code,
    ic.name AS category,
    fc.unit,
    sum(fc.quantity_planned) AS total_quantity_planned,
    sum(fc.quantity_used) AS total_quantity_used,
    sum(fc.waste_quantity) AS total_waste,
    sum(fc.total_cost) AS total_cost_used,
    count(DISTINCT fc.id) AS consumption_records,
    p.first_name AS recorded_by_first,
    p.last_name AS recorded_by_last
FROM food_consumption_records fc
LEFT JOIN inventory_items i ON fc.inventory_item_id = i.id
LEFT JOIN inventory_categories ic ON i.category_id = ic.id
LEFT JOIN staff s ON fc.recorded_by = s.id
LEFT JOIN persons p ON p.id = s.person_id
GROUP BY fc.consumption_date, fc.inventory_item_id
ORDER BY fc.consumption_date DESC
""",
)

vw(
    "vw_parent_payment_activity",
    """
SELECT
    pr.id AS parent_id,
    concat(p.first_name, ' ', p.last_name) AS parent_name,
    p.phone AS contact_number,
    count(DISTINCT pay.id) AS total_payments,
    sum(pay.amount) AS total_amount_paid,
    count(DISTINCT pay.student_id) AS number_of_children,
    group_concat(DISTINCT concat(ps.first_name, ' ', ps.last_name) SEPARATOR ', ') AS children,
    max(pay.payment_date) AS last_payment_date,
    count(DISTINCT CASE WHEN year(pay.payment_date) = year(curdate()) THEN pay.id END) AS payments_this_year,
    round(avg(pay.amount), 2) AS average_payment
FROM parents pr
JOIN persons p ON p.id = pr.person_id
LEFT JOIN payments pay ON pr.id = pay.parent_id AND pay.status IN ('confirmed', 'completed', 'success')
LEFT JOIN students s ON pay.student_id = s.id
LEFT JOIN persons ps ON ps.id = s.person_id
GROUP BY pr.id
ORDER BY sum(pay.amount) DESC
""",
)

vw(
    "vw_active_salary_advances",
    """
SELECT
    sa.id,
    sa.staff_id,
    sa.advance_number,
    sa.approved_amount,
    sa.balance_remaining,
    sa.amount_per_deduction,
    sa.deduction_start_month,
    sa.deduction_schedule,
    sa.amount_deducted,
    concat(p.first_name, ' ', p.last_name) AS staff_name
FROM staff_salary_advances sa
JOIN staff s ON s.id = sa.staff_id
JOIN persons p ON p.id = s.person_id
WHERE sa.status = 'active' AND sa.balance_remaining > 0
""",
)

vw(
    "vw_payment_tracking",
    """
SELECT
    'mpesa' AS payment_source,
    mt.id AS source_id,
    mt.mpesa_code AS reference_code,
    mt.student_id,
    s.admission_no AS admission_number,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    mt.amount,
    mt.transaction_date,
    mt.phone_number AS contact,
    mt.status,
    mt.checkout_request_id,
    mt.created_at
FROM mpesa_transactions mt
JOIN students s ON mt.student_id = s.id
JOIN persons p ON p.id = s.person_id
UNION ALL
SELECT
    'bank' AS payment_source,
    bt.id AS source_id,
    bt.transaction_ref AS reference_code,
    bt.student_id,
    s.admission_no AS admission_number,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    bt.amount,
    bt.transaction_date,
    bt.account_number AS contact,
    bt.status,
    NULL AS checkout_request_id,
    bt.created_at
FROM bank_transactions bt
JOIN students s ON bt.student_id = s.id
JOIN persons p ON p.id = s.person_id
UNION ALL
SELECT
    'cash' AS payment_source,
    pay.id AS source_id,
    convert(pay.reference using utf8mb4) COLLATE utf8mb4_general_ci AS reference_code,
    pay.student_id,
    s.admission_no AS admission_number,
    concat(p.first_name, ' ', p.last_name) AS student_name,
    pay.amount,
    pay.payment_date AS transaction_date,
    NULL AS contact,
    convert(pay.status using utf8mb4) COLLATE utf8mb4_general_ci AS status,
    NULL AS checkout_request_id,
    pay.created_at
FROM payments pay
JOIN students s ON pay.student_id = s.id
JOIN persons p ON p.id = s.person_id
WHERE pay.method = 'cash'
""",
)

vw(
    "vw_parent_summary",
    """
SELECT
    pr.id AS parent_id,
    concat(p.first_name, coalesce(concat(' ', p.middle_name), ''), ' ', p.last_name) AS parent_full_name,
    p.national_id_no AS id_number,
    p.phone AS phone_1,
    NULL AS phone_2,
    p.email,
    p.gender,
    pr.occupation,
    pr.address,
    pr.status,
    pr.created_at,
    count(DISTINCT sp.student_id) AS total_children,
    count(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) AS active_children,
    group_concat(DISTINCT concat(ps.first_name, ' ', ps.last_name, ' (', coalesce(c.name, 'N/A'), ')') ORDER BY ps.first_name ASC SEPARATOR ', ') AS children_names,
    coalesce((SELECT sum(x.balance) FROM vw_student_fee_balances x JOIN student_parents sp2 ON x.student_id = sp2.student_id WHERE sp2.parent_id = pr.id), 0) AS total_fee_balance
FROM parents pr
JOIN persons p ON p.id = pr.person_id
LEFT JOIN student_parents sp ON pr.id = sp.parent_id
LEFT JOIN students s ON sp.student_id = s.id
LEFT JOIN persons ps ON ps.id = s.person_id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
GROUP BY pr.id, p.first_name, p.middle_name, p.last_name, p.national_id_no, p.phone, p.email, p.gender, pr.occupation, pr.address, pr.status, pr.created_at
""",
)


# ---------------------------------------------------------------------------
# NEW analytic views (2026 deliverable round)
# ---------------------------------------------------------------------------
nv(
    "vw_student_fee_ledger",
    """

SELECT
    f.student_academic_enrollment_id,
    f.student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    f.academic_year,
    f.term_code,
    f.term_id,
    f.amount_due,
    f.amount_waived,
    f.amount_paid,
    f.balance,
    sum(f.amount_due)   OVER (PARTITION BY f.student_id ORDER BY f.academic_year, f.term_id ROWS UNBOUNDED PRECEDING) AS cumulative_billed,
    sum(f.amount_waived) OVER (PARTITION BY f.student_id ORDER BY f.academic_year, f.term_id ROWS UNBOUNDED PRECEDING) AS cumulative_waived,
    sum(f.amount_paid)  OVER (PARTITION BY f.student_id ORDER BY f.academic_year, f.term_id ROWS UNBOUNDED PRECEDING) AS cumulative_paid,
    sum(f.balance)      OVER (PARTITION BY f.student_id ORDER BY f.academic_year, f.term_id ROWS UNBOUNDED PRECEDING) AS cumulative_balance,
    f.payment_status,
    f.latest_due_date,
    f.days_overdue
FROM vw_student_fee_balances f
JOIN students s ON s.id = f.student_id
JOIN persons p ON p.id = s.person_id
""",
)
nv(
    "vw_staff_salary_history",
    """

SELECT
    st.id AS staff_id,
    st.staff_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS staff_name,
    st.position AS designation,
    ps.payroll_year,
    ps.payroll_month,
    date_format(concat(ps.payroll_year, '-', lpad(ps.payroll_month, 2, '0'), '-01'), '%Y-%m') AS payroll_period,
    ps.basic_salary,
    ps.allowances_total,
    ps.gross_salary,
    ps.paye_tax,
    ps.nssf_contribution,
    ps.nhif_contribution,
    ps.housing_levy,
    ps.loan_deduction,
    ps.sacco_deduction,
    ps.salary_advance_deduction,
    ps.child_fees_deduction,
    ps.other_deductions_total,
    ps.net_salary,
    ps.payment_status,
    ps.paid_at,
    sum(ps.gross_salary) OVER (PARTITION BY ps.staff_id, ps.payroll_year ORDER BY ps.payroll_month ROWS UNBOUNDED PRECEDING) AS ytd_gross,
    sum(ps.net_salary)   OVER (PARTITION BY ps.staff_id, ps.payroll_year ORDER BY ps.payroll_month ROWS UNBOUNDED PRECEDING) AS ytd_net
FROM payslips ps
JOIN staff st ON st.id = ps.staff_id
JOIN persons p ON p.id = st.person_id
WHERE ps.payslip_status IN ('approved', 'paid')
ORDER BY ps.payroll_year, ps.payroll_month, st.staff_no
""",
)
nv(
    "vw_student_learning_progress",
    """

SELECT
    fs.student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    ay.year_code AS academic_year,
    ayt.term_id AS term_number,
    t.name AS term_name,
    c.name AS class_name,
    sn.name AS stream_name,
    aycl.week_number,
    aycl.week_start,
    aycl.week_end,
    la.name AS learning_area,
    strnd.name AS strand,
    ss.name AS sub_strand,
    count(DISTINCT a.id) AS assessments_count,
    count(fs.id) AS score_records,
    sum(fs.max_score) AS total_max_marks,
    sum(fs.score) AS total_marks,
    coalesce(round(avg(fs.percentage), 2), 0) AS average_percentage,
    max(fs.percentage) AS best_percentage,
    sum(CASE WHEN fs.cbc_grade = 'EE' THEN 1 ELSE 0 END) AS ee_count,
    sum(CASE WHEN fs.cbc_grade = 'ME' THEN 1 ELSE 0 END) AS me_count,
    sum(CASE WHEN fs.cbc_grade = 'AE' THEN 1 ELSE 0 END) AS ae_count,
    sum(CASE WHEN fs.cbc_grade = 'BE' THEN 1 ELSE 0 END) AS be_count
FROM formative_scores fs
JOIN assessments a ON a.id = fs.assessment_id AND a.status = 'approved'
JOIN students s ON s.id = fs.student_id
JOIN persons p ON p.id = s.person_id
JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN academic_years ay ON ay.id = ayc.academic_year_id
JOIN classes c ON c.id = ayc.class_id
JOIN streams sn ON sn.id = aycs.stream_id
JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
JOIN terms t ON t.id = ayt.term_id
LEFT JOIN academic_year_calendar_days aycd ON aycd.id = a.academic_year_calendar_day_id
LEFT JOIN academic_year_calendar aycl ON aycl.id = aycd.academic_year_calendar_id
LEFT JOIN learning_areas la ON la.id = a.learning_area_id
LEFT JOIN strands strnd ON strnd.id = a.strand_id
LEFT JOIN sub_strands ss ON ss.id = a.sub_strand_id
GROUP BY fs.student_id, ayc.id, ayt.term_id, aycl.week_number, a.learning_area_id, a.strand_id, a.sub_strand_id
""",
)
nv(
    "vw_parent_children",
    """

SELECT
    par.id AS parent_id,
    concat(pp.first_name, ' ', coalesce(pp.middle_name, ''), ' ', pp.last_name) AS parent_name,
    pp.email AS parent_email,
    pp.phone AS parent_phone,
    (SELECT COUNT(*) FROM student_parents spc WHERE spc.parent_id = par.id) AS children_count,
    sp.student_id,
    s.admission_no,
    concat(ps.first_name, ' ', coalesce(ps.middle_name, ''), ' ', ps.last_name) AS child_name,
    s.status AS student_status,
    s.admission_date,
    c.name AS class_name,
    sn.name AS stream_name,
    ay.year_code AS current_academic_year,
    sp.relationship,
    sp.is_primary_contact,
    sp.is_emergency_contact
FROM student_parents sp
JOIN parents par ON par.id = sp.parent_id
JOIN persons pp ON pp.id = par.person_id
JOIN students s ON s.id = sp.student_id
JOIN persons ps ON ps.id = s.person_id
LEFT JOIN student_academic_enrollments sae
    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN academic_years ay ON ay.id = ayc.academic_year_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
""",
)
nv(
    "vw_class_learning_area_performance",
    """

SELECT
    ayc.id AS academic_year_class_id,
    ay.year_code AS academic_year,
    ayt.term_id AS term_number,
    t.name AS term_name,
    c.name AS class_name,
    sn.name AS stream_name,
    la.name AS learning_area,
    count(DISTINCT a.id) AS assessments_count,
    count(DISTINCT fs.student_id) AS students_assessed,
    coalesce(round(avg(fs.percentage), 2), 0) AS average_percentage,
    sum(CASE WHEN fs.cbc_grade = 'EE' THEN 1 ELSE 0 END) AS ee_count,
    sum(CASE WHEN fs.cbc_grade = 'ME' THEN 1 ELSE 0 END) AS me_count,
    sum(CASE WHEN fs.cbc_grade = 'AE' THEN 1 ELSE 0 END) AS ae_count,
    sum(CASE WHEN fs.cbc_grade = 'BE' THEN 1 ELSE 0 END) AS be_count
FROM assessments a
JOIN formative_scores fs ON fs.assessment_id = a.id
JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
JOIN academic_years ay ON ay.id = ayc.academic_year_id
JOIN classes c ON c.id = ayc.class_id
JOIN streams sn ON sn.id = aycs.stream_id
JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
JOIN terms t ON t.id = ayt.term_id
JOIN learning_areas la ON la.id = a.learning_area_id
WHERE a.status = 'approved'
GROUP BY ayc.id, ayt.term_id, a.learning_area_id
""",
)
nv(
    "vw_student_term_performance",
    """

SELECT
    tss.student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    ay.year_code AS academic_year,
    ayt.term_id AS term_number,
    t.name AS term_name,
    count(DISTINCT tss.subject_id) AS subjects_count,
    sum(tss.overall_points) AS total_points,
    round(avg(tss.overall_percentage), 2) AS average_percentage,
    CASE
        WHEN round(avg(tss.overall_percentage), 2) >= 80 THEN 'A'
        WHEN round(avg(tss.overall_percentage), 2) >= 70 THEN 'B'
        WHEN round(avg(tss.overall_percentage), 2) >= 60 THEN 'C'
        WHEN round(avg(tss.overall_percentage), 2) >= 50 THEN 'D'
        ELSE 'E'
    END AS overall_grade,
    sum(CASE WHEN tss.overall_percentage >= 50 THEN 1 ELSE 0 END) AS subjects_passed,
    max(tss.calculated_at) AS last_calculated_at
FROM term_subject_scores tss
JOIN students s ON s.id = tss.student_id
JOIN persons p ON p.id = s.person_id
JOIN academic_year_terms ayt
    ON ayt.term_id = tss.term_id
   AND ayt.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1)
JOIN terms t ON t.id = ayt.term_id
JOIN academic_years ay ON ay.id = ayt.academic_year_id
GROUP BY tss.student_id, ayt.id
""",
)
nv(
    "vw_student_attendance_analytics",
    """

SELECT
    sae.id AS student_academic_enrollment_id,
    sae.student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    ay.year_code AS academic_year,
    ayt.term_id AS term_number,
    t.name AS term_name,
    c.name AS class_name,
    sn.name AS stream_name,
    count(DISTINCT sa.date) AS days_marked,
    sum(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present_marks,
    sum(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late_marks,
    sum(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'unexcused' THEN 1 ELSE 0 END) AS unexcused_marks,
    sum(CASE WHEN sa.status = 'absent' AND (sa.absence_reason IS NULL OR sa.absence_reason <> 'unexcused') THEN 1 ELSE 0 END) AS excused_marks,
    round(sum(CASE WHEN sa.status IN ('present', 'late') THEN 1 ELSE 0 END) / nullif(count(sa.id), 0) * 100, 2) AS attendance_rate_pct
FROM student_attendance sa
JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
JOIN students s ON s.id = sae.student_id
JOIN persons p ON p.id = s.person_id
JOIN academic_years ay ON ay.id = sae.academic_year_id
LEFT JOIN academic_year_terms ayt
    ON ayt.academic_year_id = ay.id
   AND sa.date BETWEEN ayt.opening_date AND ayt.closing_date
LEFT JOIN terms t ON t.id = ayt.term_id
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
WHERE sa.register_type = 'class'
GROUP BY sae.id, ayt.id
""",
)
nv(
    "vw_fee_collection_monthly_trend",
    """

SELECT
    m.month,
    coalesce(b.amount_billed, 0) AS amount_billed,
    coalesce(c.amount_collected, 0) AS amount_collected,
    coalesce(c.payment_count, 0) AS payment_count,
    round(coalesce(c.amount_collected, 0) / nullif(coalesce(b.amount_billed, 0), 0) * 100, 2) AS collection_rate_pct
FROM (
    SELECT date_format(due_date, '%Y-%m') AS month FROM student_fee_obligations
    UNION
    SELECT date_format(payment_date, '%Y-%m') AS month FROM payments
) m
LEFT JOIN (
    SELECT date_format(due_date, '%Y-%m') AS month,
           sum(amount_due) AS amount_billed
    FROM student_fee_obligations
    GROUP BY date_format(due_date, '%Y-%m')
) b ON b.month = m.month
LEFT JOIN (
    SELECT date_format(payment_date, '%Y-%m') AS month,
           sum(amount) AS amount_collected,
           count(id) AS payment_count
    FROM payments
    WHERE status IN ('confirmed', 'completed', 'success')
    GROUP BY date_format(payment_date, '%Y-%m')
) c ON c.month = m.month
ORDER BY m.month
""",
)
nv(
    "vw_dormitory_occupancy",
    """

SELECT
    d.id AS dormitory_id,
    d.code,
    d.name AS dormitory_name,
    d.gender,
    d.capacity,
    ay.year_code AS academic_year,
    count(DISTINCT da.id) AS total_assignments,
    count(DISTINCT CASE WHEN da.status = 'active' THEN da.id END) AS active_assignments,
    d.capacity - count(DISTINCT CASE WHEN da.status = 'active' THEN da.id END) AS free_beds,
    round(count(DISTINCT CASE WHEN da.status = 'active' THEN da.id END) / nullif(d.capacity, 0) * 100, 2) AS utilization_pct
FROM dormitories d
LEFT JOIN dormitory_assignments da ON da.dormitory_id = d.id
LEFT JOIN academic_years ay ON ay.id = da.academic_year_id
GROUP BY d.id, ay.id
""",
)
nv(
    "vw_staff_leave_history",
    """

SELECT
    st.id AS staff_id,
    st.staff_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS staff_name,
    year(sl.start_date) AS leave_year,
    lt.code AS leave_type_code,
    lt.name AS leave_type,
    lt.days_allowed,
    sum(sl.days_requested) AS days_taken,
    CASE WHEN lt.days_allowed IS NULL THEN NULL ELSE lt.days_allowed - sum(sl.days_requested) END AS days_balance
FROM staff_leaves sl
JOIN leave_types lt ON lt.id = sl.leave_type_id
JOIN staff st ON st.id = sl.staff_id
JOIN persons p ON p.id = st.person_id
WHERE sl.status = 'approved'
GROUP BY st.id, year(sl.start_date), lt.id
""",
)
nv(
    "vw_student_health_summary",
    """

SELECT
    s.id AS student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    c.name AS class_name,
    sn.name AS stream_name,
    count(DISTINCT shr.id) AS health_records,
    count(CASE WHEN shr.emergency_flag = 1 THEN 1 END) AS emergency_flags,
    group_concat(DISTINCT CASE WHEN shr.health_category = 'allergy' AND shr.status IN ('active', 'monitoring') THEN shr.allergy_name END ORDER BY shr.allergy_name SEPARATOR ', ') AS active_allergies,
    group_concat(DISTINCT CASE WHEN shr.health_category = 'condition' AND shr.status IN ('active', 'monitoring') THEN shr.condition_name END ORDER BY shr.condition_name SEPARATOR ', ') AS active_conditions,
    group_concat(DISTINCT CASE WHEN shr.health_category = 'medication' AND shr.status IN ('active', 'monitoring') THEN shr.medication_name END ORDER BY shr.medication_name SEPARATOR ', ') AS active_medications
FROM students s
JOIN persons p ON p.id = s.person_id
LEFT JOIN student_health_records shr ON shr.student_id = s.id
LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
LEFT JOIN classes c ON c.id = ayc.class_id
LEFT JOIN streams sn ON sn.id = aycs.stream_id
GROUP BY s.id
""",
)
nv(
    "vw_student_transport_summary",
    """

SELECT
    tmb.id AS bill_id,
    tmb.student_id,
    s.admission_no,
    concat(p.first_name, ' ', coalesce(p.middle_name, ''), ' ', p.last_name) AS student_name,
    date_format(tmb.billing_month, '%Y-%m') AS billing_month,
    tmb.amount_due,
    coalesce(sum(tbp.amount), 0) AS amount_paid,
    tmb.amount_due - coalesce(sum(tbp.amount), 0) AS balance_due,
    CASE
        WHEN coalesce(sum(tbp.amount), 0) >= tmb.amount_due THEN 'paid'
        WHEN coalesce(sum(tbp.amount), 0) > 0 THEN 'partial'
        ELSE 'unpaid'
    END AS payment_status
FROM transport_monthly_bills tmb
JOIN students s ON s.id = tmb.student_id
JOIN persons p ON p.id = s.person_id
LEFT JOIN transport_bill_payments tbp ON tbp.bill_id = tmb.id
GROUP BY tmb.id
""",
)
