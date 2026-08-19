-- Transport access is independent of school fees.  This migration adds the
-- period/entitlement/payment-allocation facts needed for day, week, month,
-- term and year coverage while preserving the legacy monthly tables.

CREATE TABLE IF NOT EXISTS transport_entitlement_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period_type ENUM('day','week','month','term','year','custom') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    academic_year_term_id INT UNSIGNED NULL,
    label VARCHAR(120) NULL,
    status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transport_period (period_type, period_start, period_end, academic_year_term_id),
    KEY ix_transport_period_dates (period_start, period_end),
    KEY ix_transport_period_term (academic_year_term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_transport_entitlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    assignment_id INT UNSIGNED NULL,
    route_id INT UNSIGNED NOT NULL,
    period_id BIGINT UNSIGNED NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    entitlement_status ENUM('active','suspended','cancelled','expired') NOT NULL DEFAULT 'active',
    source_type ENUM('subscription','prepaid','bursary','waiver','override') NOT NULL DEFAULT 'subscription',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_student_transport_entitlement (student_id, route_id, period_id),
    KEY ix_student_entitlement_dates (student_id, period_id, entitlement_status),
    KEY ix_route_entitlement (route_id, period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_entitlement_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    provider_name VARCHAR(40) NULL,
    provider_reference VARCHAR(120) NULL,
    payment_status ENUM('pending','confirmed','reversed','cancelled') NOT NULL DEFAULT 'confirmed',
    payment_date DATE NOT NULL,
    received_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transport_provider_reference (provider_name, provider_reference),
    KEY ix_transport_payment_student (student_id, payment_date),
    KEY ix_transport_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_entitlement_payment_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    entitlement_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transport_payment_entitlement (payment_id, entitlement_id),
    KEY ix_transport_allocation_entitlement (entitlement_id),
    CONSTRAINT chk_transport_allocation_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_access_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    valid_from DATETIME NOT NULL,
    valid_until DATETIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    approved_by INT UNSIGNED NOT NULL,
    status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_transport_override_lookup (student_id, route_id, valid_from, valid_until, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill one entitlement period per existing monthly bill. Re-running the
-- migration is safe because the natural keys are unique.
INSERT IGNORE INTO transport_entitlement_periods
    (period_type, period_start, period_end, label, status)
SELECT 'month', b.billing_month, LAST_DAY(b.billing_month),
       DATE_FORMAT(b.billing_month, '%M %Y'), 'open'
FROM transport_monthly_bills b;

INSERT IGNORE INTO student_transport_entitlements
    (student_id, assignment_id, route_id, period_id, amount_due, entitlement_status, source_type)
SELECT b.student_id, b.subscription_id, b.route_id, p.id, b.amount_due, 'active', 'subscription'
FROM transport_monthly_bills b
JOIN transport_entitlement_periods p
  ON p.period_type = 'month' AND p.period_start = b.billing_month
 AND p.period_end = LAST_DAY(b.billing_month);

INSERT IGNORE INTO transport_entitlement_payments
    (student_id, amount, payment_method, provider_name, provider_reference, payment_status, payment_date, received_by, notes)
SELECT b.student_id, bp.amount, bp.payment_method, 'legacy_transport',
       CONCAT('LEGACY-TRANSPORT-PAYMENT-', bp.id), 'confirmed', bp.payment_date, bp.received_by, bp.notes
FROM transport_bill_payments bp
JOIN transport_monthly_bills b ON b.id = bp.bill_id;

INSERT IGNORE INTO transport_entitlement_payment_allocations
    (payment_id, entitlement_id, amount)
SELECT ep.id, e.id, bp.amount
FROM transport_bill_payments bp
JOIN transport_monthly_bills b ON b.id = bp.bill_id
JOIN transport_entitlement_payments ep
  ON ep.provider_name = 'legacy_transport'
 AND ep.provider_reference = CONCAT('LEGACY-TRANSPORT-PAYMENT-', bp.id)
JOIN transport_entitlement_periods p
  ON p.period_type = 'month' AND p.period_start = b.billing_month
 AND p.period_end = LAST_DAY(b.billing_month)
JOIN student_transport_entitlements e
  ON e.student_id = b.student_id AND e.route_id = b.route_id AND e.period_id = p.id;
