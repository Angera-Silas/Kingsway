-- Kingsway financial accounting foundation
-- MariaDB 10.4 compatible. Operational payment tables remain the source documents;
-- this migration adds the authoritative, append-only accounting layer around them.

CREATE TABLE IF NOT EXISTS accounting_account_types (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    normal_balance ENUM('debit','credit') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_account_type_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_periods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period_code VARCHAR(30) NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    status ENUM('open','locked','closed') NOT NULL DEFAULT 'open',
    locked_by INT UNSIGNED DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_period_code (period_code),
    UNIQUE KEY uq_accounting_period_dates (starts_on, ends_on),
    CONSTRAINT chk_accounting_period_dates CHECK (ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_code VARCHAR(30) NOT NULL,
    account_name VARCHAR(160) NOT NULL,
    account_type_id SMALLINT UNSIGNED NOT NULL,
    parent_account_id BIGINT UNSIGNED DEFAULT NULL,
    is_control_account TINYINT(1) NOT NULL DEFAULT 0,
    is_postable TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_chart_account_code (account_code),
    KEY idx_chart_account_type (account_type_id),
    KEY idx_chart_account_parent (parent_account_id),
    CONSTRAINT fk_chart_account_type FOREIGN KEY (account_type_id) REFERENCES accounting_account_types (id),
    CONSTRAINT fk_chart_account_parent FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_account_kinds (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_account_kind_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_account_purposes (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_account_purpose_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_channels (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_financial_channel_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_financial_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_name VARCHAR(160) NOT NULL,
    account_kind_id SMALLINT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED DEFAULT NULL,
    ledger_account_id BIGINT UNSIGNED DEFAULT NULL,
    legacy_bank_account_id INT UNSIGNED DEFAULT NULL,
    account_identifier VARCHAR(120) NOT NULL,
    normalized_account_identifier VARCHAR(120) NOT NULL,
    bank_name VARCHAR(160) DEFAULT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    status ENUM('pending_verification','active','suspended','closed') NOT NULL DEFAULT 'pending_verification',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_financial_account_identifier (account_kind_id, normalized_account_identifier),
    KEY idx_school_financial_account_provider (provider_id),
    KEY idx_school_financial_account_ledger (ledger_account_id),
    KEY idx_school_financial_account_status (status),
    CONSTRAINT fk_school_financial_account_kind FOREIGN KEY (account_kind_id) REFERENCES financial_account_kinds (id),
    CONSTRAINT fk_school_financial_account_provider FOREIGN KEY (provider_id) REFERENCES payment_providers (id),
    CONSTRAINT fk_school_financial_account_ledger FOREIGN KEY (ledger_account_id) REFERENCES chart_of_accounts (id),
    CONSTRAINT fk_school_financial_account_legacy FOREIGN KEY (legacy_bank_account_id) REFERENCES bank_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_financial_account_purposes (
    financial_account_id BIGINT UNSIGNED NOT NULL,
    purpose_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (financial_account_id, purpose_id),
    CONSTRAINT fk_account_purpose_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id),
    CONSTRAINT fk_account_purpose_purpose FOREIGN KEY (purpose_id) REFERENCES financial_account_purposes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_financial_account_channels (
    financial_account_id BIGINT UNSIGNED NOT NULL,
    channel_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (financial_account_id, channel_id),
    CONSTRAINT fk_account_channel_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id),
    CONSTRAINT fk_account_channel_channel FOREIGN KEY (channel_id) REFERENCES financial_channels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_financial_account_permissions (
    financial_account_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    can_receive TINYINT(1) NOT NULL DEFAULT 0,
    can_disburse TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (financial_account_id, role_id),
    CONSTRAINT fk_account_permission_account FOREIGN KEY (financial_account_id) REFERENCES school_financial_accounts (id),
    CONSTRAINT fk_account_permission_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_journal_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_number VARCHAR(50) NOT NULL,
    accounting_period_id BIGINT UNSIGNED NOT NULL,
    batch_type VARCHAR(50) NOT NULL,
    status ENUM('draft','posted','reversed','voided') NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    description VARCHAR(255) NOT NULL,
    correlation_id CHAR(36) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    posted_by INT UNSIGNED DEFAULT NULL,
    posted_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_journal_batch_number (batch_number),
    UNIQUE KEY uq_journal_batch_correlation (correlation_id),
    KEY idx_journal_batch_period_status (accounting_period_id, status),
    CONSTRAINT fk_journal_batch_period FOREIGN KEY (accounting_period_id) REFERENCES accounting_periods (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_journal_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    journal_batch_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL,
    chart_account_id BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    debit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_journal_line_number (journal_batch_id, line_number),
    KEY idx_journal_line_account (chart_account_id),
    KEY idx_journal_line_entity (entity_type, entity_id),
    CONSTRAINT chk_journal_line_one_side CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0)),
    CONSTRAINT fk_journal_line_batch FOREIGN KEY (journal_batch_id) REFERENCES accounting_journal_batches (id),
    CONSTRAINT fk_journal_line_account FOREIGN KEY (chart_account_id) REFERENCES chart_of_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_source_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    journal_batch_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(60) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    source_reference VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_source (source_type, source_id),
    UNIQUE KEY uq_accounting_source_batch (journal_batch_id),
    KEY idx_accounting_source_reference (source_reference),
    CONSTRAINT fk_accounting_source_batch FOREIGN KEY (journal_batch_id) REFERENCES accounting_journal_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_reversals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_journal_batch_id BIGINT UNSIGNED NOT NULL,
    reversal_journal_batch_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_reversal_original (original_journal_batch_id),
    UNIQUE KEY uq_accounting_reversal_reversal (reversal_journal_batch_id),
    CONSTRAINT fk_accounting_reversal_original FOREIGN KEY (original_journal_batch_id) REFERENCES accounting_journal_batches (id),
    CONSTRAINT fk_accounting_reversal_reversal FOREIGN KEY (reversal_journal_batch_id) REFERENCES accounting_journal_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    correlation_id CHAR(36) DEFAULT NULL,
    reason VARCHAR(500) DEFAULT NULL,
    before_state LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (before_state IS NULL OR json_valid(before_state)),
    after_state LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (after_state IS NULL OR json_valid(after_state)),
    request_metadata LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (request_metadata IS NULL OR json_valid(request_metadata)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accounting_audit_entity (entity_type, entity_id, created_at),
    KEY idx_accounting_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE disbursement_transactions
    ADD COLUMN source_financial_account_id BIGINT UNSIGNED NULL AFTER disbursement_type,
    ADD COLUMN idempotency_reference VARCHAR(150) NULL AFTER amount,
    ADD KEY idx_disbursement_source_account (source_financial_account_id),
    ADD UNIQUE KEY uq_disbursement_idempotency (idempotency_reference),
    ADD CONSTRAINT fk_disbursement_source_account FOREIGN KEY (source_financial_account_id) REFERENCES school_financial_accounts (id);

ALTER TABLE payment_routing_references
    ADD COLUMN normalized_reference VARCHAR(150) NULL AFTER reference,
    ADD UNIQUE KEY uq_payment_routing_normalized_reference (normalized_reference);

ALTER TABLE payment_unmatched_cases
    ADD COLUMN normalized_reference VARCHAR(150) NULL AFTER reference_value,
    ADD COLUMN matching_status ENUM('unmatched','matched','duplicate','conflict','partial','overpayment','underpayment','reversed','needs_review') NOT NULL DEFAULT 'unmatched' AFTER status,
    ADD KEY idx_unmatched_normalized_reference (normalized_reference);

INSERT INTO accounting_account_types (code, name, normal_balance) VALUES
    ('asset', 'Asset', 'debit'),
    ('liability', 'Liability', 'credit'),
    ('equity', 'Equity', 'credit'),
    ('revenue', 'Revenue', 'credit'),
    ('expense', 'Expense', 'debit')
ON DUPLICATE KEY UPDATE name = VALUES(name), normal_balance = VALUES(normal_balance);

INSERT INTO financial_account_kinds (code, name) VALUES
    ('bank', 'Bank account'),
    ('mobile_money', 'Mobile money account'),
    ('cash', 'Cash account'),
    ('clearing', 'Payment clearing account')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO financial_account_purposes (code, name) VALUES
    ('fees', 'School fees'),
    ('transport', 'Transport'),
    ('uniforms', 'Uniforms and merchandise'),
    ('payroll', 'Payroll'),
    ('suppliers', 'Suppliers and procurement'),
    ('refunds', 'Parent refunds'),
    ('statutory', 'Statutory remittances'),
    ('operations', 'General operations')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO financial_channels (code, name) VALUES
    ('bank_transfer', 'Bank transfer'),
    ('mpesa_stk', 'M-Pesa STK'),
    ('mpesa_c2b', 'M-Pesa C2B'),
    ('mpesa_b2c', 'M-Pesa B2C'),
    ('buni_ipn', 'KCB Buni IPN'),
    ('buni_transfer', 'KCB Buni funds transfer'),
    ('coop_api', 'Cooperative Bank API'),
    ('cash', 'Cash'),
    ('cheque', 'Cheque')
ON DUPLICATE KEY UPDATE name = VALUES(name);
