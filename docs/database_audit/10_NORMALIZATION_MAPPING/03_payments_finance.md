# Normalization mapping — Payments & Finance (69 tables)

Base evidence: `docs/database_audit/08_PER_TABLE_BREAKDOWN/03_payments_finance.md`, `/tmp/opencode/domains/domain_03.txt`, `docs/database_audit/09_NORMALIZED_TARGET_ARCHITECTURE.md` §4.6.

Target of truth: file 09. Legacy tables are mapped ONTO it; nothing is re-designed here. No invented values: any legacy cell that cannot be re-homed is migrated as `NULL` with a flag. Year context follows the `academic_years` id-map (2026 = id 5, 2027 = id 6); `academic_year year(4)` on legacy tables becomes `academic_year_id` throughout. Tags: `[V]` column/row evidence verified in slice + 08, `[I]` migration relies on inferred links (FK/id-map), `[U]` genuinely undeterminable — migrated `NULL` + flag.

### 1. `allowance_templates`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — pure master; missing UNIQUE guard on `(name, allowance_type, department_id, staff_type_id, role_id, contract_type)` allows template drift
- **Target home(s):** `staff_allowances` allowance-type REF (`staff_allowances.type_id`)
- **Composite / relation key:** `id`; UNIQUE `(name, allowance_type, department_id, staff_type_id, role_id, contract_type)`
- **Migration rule:** keep as REF master; amounts are snapshotted into `payslip_items` at pay time so template edits never rewrite history; `department_id`/`staff_type_id`/`role_id` become real FKs where rows resolve. [V]

### 2. `arrears_settlement_plans`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored-derived `amount_paid`/`installments_paid` (derivable from obligation payments); `arrears_id` link points at a RETIRED snapshot table (`student_arrears`)
- **Target home(s):** `arrears_settlement_plans` (kept fact), re-keyed to `student_academic_enrollment_id` + `academic_year_term_id`
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_term_id)` + `id`; installment dates
- **Migration rule:** re-home `student_id` → `student_academic_enrollment_id` via the enrollment map; `arrears_id` → re-keyed to the derived arrears `(student_academic_enrollment_id, academic_year_id, term_id)`; `amount_paid`/`installments_paid` → derived from obligation payments; `approved_by` → `users`. [I]

### 3. `bank_accounts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master
- **Target home(s):** `bank_accounts`
- **Composite / relation key:** `account_no` UNIQUE
- **Migration rule:** keep as-is; `bank_transactions.bank_name`/`account_number` wired as real FK for reconciliation integrity; `is_active` soft-delete kept. [V]

### 4. `bank_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** mixed-context money movement with no `financial_period_id`/`academic_year_id`; `matched_mpesa_code` stored matching column
- **Target home(s):** `bank_transactions` (dated money-movement fact keyed to `financial_periods` + `payments` reference)
- **Composite / relation key:** `transaction_ref` UNIQUE + `financial_period_id` (derived from `transaction_date`)
- **Migration rule:** add `financial_period_id` from `transaction_date`; each `processed` row posts exactly one `school_transactions` row; matching to `payments` via `matched_mpesa_code`/`student_id`; unmatched → `finance_exceptions`. [V]

### 5. `budget_amendments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** index-only FKs (`budget_id`/`line_item_id`/actors); append-only discipline not enforced
- **Target home(s):** `budget_amendments`
- **Composite / relation key:** `(budget_id, line_item_id, amendment_type)` + `created_at`; year context inherited from `budgets`
- **Migration rule:** add FKs `budget_id` → `budgets.id`, `line_item_id` → `budget_line_items.id`, `requested_by`/`approved_by` → `users.id`; amendments stay append-only — a reversal is a new amendment, never an UPDATE to an approved row. [V]

### 6. `budget_line_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored-derived `spent_amount`/`committed_amount` duplicating the `expenses` roll-up
- **Target home(s):** `budget_line_items`
- **Composite / relation key:** `(budget_id, category_id)`
- **Migration rule:** keep `allocated_amount`; `spent_amount`/`committed_amount` → derived view over `expenses`; add FKs `budget_id` → `budgets.id`, `category_id` → `expense_categories.id`. [V]

### 7. `budgets`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` + `term tinyint` (redundant/partial-key vs `academic_years`/`academic_year_terms`); no `department_id` binding; approval workflow state carried in-table
- **Target home(s):** `budgets (academic_year_id, term_id?, department_id?, name, amount, status, workflow)`
- **Composite / relation key:** `(academic_year_id, term_id, name)`
- **Migration rule:** backfill `academic_year_id` via the year id-map; `term` → `academic_year_terms.id`; add `department_id`; status transitions → `workflow_instances`; keep the approval state machine. [V]

### 8. `cash_reconciliation_sessions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no `academic_year_id`/`financial_period_id` context; `variance` stored-derived (`system_cash_total − physical_cash_count`)
- **Target home(s):** `cash_reconciliation_sessions`
- **Composite / relation key:** `(financial_period_id, reconciliation_date, cashier_id)`
- **Migration rule:** add `academic_year_id`/`financial_period_id` from `reconciliation_date`; `variance` → derived; non-zero variance spawns a `finance_exceptions` row; approval closes the session. [V]

### 9. `deduction_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master
- **Target home(s):** `deduction_types` (feeds `staff_deductions.type_id`)
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep as REF; `payslip_items.item_code` wired as soft ref; `default_amount`/`default_perctage`/`default_rate` spellings preserved verbatim (no rename — typo carried). [V]

### 10. `department_budget_proposals`
- **Disposition:** MERGE
- **Normalization fault(s):** no year context (attributable only via `created_at`); proposal workflow parallel to the budget approval workflow
- **Target home(s):** `budgets` + `budget_amendments`
- **Composite / relation key:** `(department_id, academic_year_id, title)` → budget draft; proposal approval → budget row
- **Migration rule:** approved proposals seed `budgets` rows (`amount_requested` → `amount`, `department_id`, status → workflow); proposal review/rejection trail → `audit_logs`; undeterminable year → `NULL` + flag. [I]

### 11. `department_fund_requests`
- **Disposition:** MERGE
- **Normalization fault(s):** no year context; no link to `expenses`/`budget_line_items` (spend traceability absent)
- **Target home(s):** `budget_amendments` + `budgets`
- **Composite / relation key:** `(department_id, requested_at)` → amendment request row
- **Migration rule:** re-home as `budget_amendments` (`amount` → `amount_change`, `reason` → `reason`, status → amendment state); approved spend traces to `expenses` via `budget_line_item_id`; undeterminable year → `NULL` + flag. [I]

### 12. `expense_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master; `parent_id` self-reference not declared
- **Target home(s):** `expense_categories`
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep as-is; add `parent_id` self-FK; `expenses.category_id` and `budget_line_items.category_id` become real FKs. [V]

### 13. `expenses`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** denormalized `expense_category varchar` duplicate (redundancy); `academic_year year(4)` + `term` duplicating `financial_period_id`; `vendor_name` duplicate
- **Target home(s):** `expenses (budget_line_item_id?, department_id?, vendor_id?, amount, expense_date, method, status, workflow)`
- **Composite / relation key:** `expense_number` UNIQUE; `(academic_year_id, financial_period_id)`
- **Migration rule:** backfill `academic_year_id` via id-map; drop `expense_category`/`vendor_name` (keep `category_id`/`vendor_id` FKs); add FKs to `financial_periods`, `departments`, `users` (created/approved/paid/rejected), `budget_line_items`. [V]

### 14. `fee_credit_notes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)` + `applied_to_year year(4)` (dual scalar year context); stored-derived `applied_amount`/`remaining_amount`
- **Target home(s):** `fee_credit_notes` (fact keyed `(student_academic_enrollment_id, academic_year_term_id)`)
- **Composite / relation key:** `credit_number` UNIQUE; `(student_academic_enrollment_id, academic_year_id, term_id)` + `applied_to_year_id`/`applied_to_term_id`
- **Migration rule:** re-home `student_id` → `student_academic_enrollment_id`; `year(4)` → `academic_year_id`/`applied_to_year_id`; `applied_amount`/`remaining_amount` → derived from allocations; expiry-driven availability → view, no `curdate()`. [V]

### 15. `fee_discounts_waivers`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)`; dual `discount_value`/`discount_percentage`; absorbs the sponsored-waiver context currently on obligations
- **Target home(s):** `fee_discounts_waivers` (fact keyed `(student_academic_enrollment_id, academic_year_term_id)`)
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_id, term_id)` + `student_fee_obligation_id`; `discount_type` discriminates value/percentage
- **Migration rule:** re-home `student_id` → `student_academic_enrollment_id`; `year(4)` → `academic_year_id`; move `student_fee_obligations.is_sponsored`/`sponsored_waiver_amount` here as sponsored-waiver rows; `valid_until` expiry → view. [V]

### 16. `fee_invoices`
- **Disposition:** RETIRE
- **Normalization fault(s):** stored-derived `balance`; dormant (0 rows); duplicates the obligations+allocations roll-up
- **Target home(s):** derived per-term invoice report over `student_fee_obligations` + `payments` + `payment_allocations`
- **Composite / relation key:** `(student_id, academic_year_id, term_id)` view key — a legitimate snapshot, reproduced as a view only if snapshots are ever required
- **Migration rule:** no data; table dropped; the per-student/year/term invoice is a derived report. [V]

### 17. `fee_reminders`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `academic_year year(4)`; `outstanding_amount` is a snapshot — kept (valid communication fact, written at send time)
- **Target home(s):** `fee_reminders` (communication fact keyed to obligations/terms)
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_id, term_id)` + `sent_date`; `reminder_type`
- **Migration rule:** re-home `student_id`/`parent_id` → `student_academic_enrollment_id`/`parent`; `year(4)` → `academic_year_id`; `outstanding_amount` = obligation balance at send time (snapshot, never re-derived). [V]

### 18. `fee_structure_approvals`
- **Disposition:** RETIRE
- **Normalization fault(s):** approval workflow parallel to the schedule's own workflow columns; `academic_year year(4)`; `obligations_generated`/`obligations_count` stored-derived
- **Target home(s):** `workflow_instances` + `audit_logs` (on `academic_year_fee_schedules`)
- **Composite / relation key:** `(academic_year_fee_schedule_id, stage, acted_at)`
- **Migration rule:** status + actor/timestamp columns → `workflow_instances` stage transitions; `obligations_generated`/`obligations_count` → derived; re-approval for a changed term is a new workflow instance, never an UPDATE. [I]

### 19. `fee_structure_change_log`
- **Disposition:** RETIRE
- **Normalization fault(s):** append-only change trail parallel to the single `audit_logs` mechanism (redundancy)
- **Target home(s):** `audit_logs`
- **Composite / relation key:** `(entity_type='academic_year_fee_schedule', entity_id=fee_structure_detail_id, action=change_type, acted_at)`
- **Migration rule:** rows re-homed as `audit_logs` with `old_amount`/`new_amount`/`old_status`/`new_status` in `old_values`/`new_values`; `ip_address` retained; survives the fee-table re-keying via schedule lineage. [I]

### 20. `fee_structure_rollover_log`
- **Disposition:** RETIRE
- **Normalization fault(s):** parallel rollover history; `source_academic_year`/`target_academic_year year(4)` scalar context
- **Target home(s):** `audit_logs` + `workflow_instances`
- **Composite / relation key:** `(entity_type='academic_year_fee_schedule', action='rollover', source_year_id, target_year_id, rollover_date)`
- **Migration rule:** rows → `audit_logs` (`executed_by` → actor); `structures_copied` → derived count; `rollover_status` → workflow stage; kept append-only. [I]

### 21. `fee_structure_rollover_schedule`
- **Disposition:** RETIRE
- **Normalization fault(s):** per-year CFG row parallel to the workflow scheduling mechanism; no audit of execution
- **Target home(s):** `workflow_instances` (rollover workflow definition/instance per year) + system settings
- **Composite / relation key:** `academic_year_id` UNIQUE
- **Migration rule:** the schedule becomes the rollover workflow instance for the year; `scheduled_date`/`review_deadline` → workflow stage deadlines; `notification_days_before`/`reminder_sent` → workflow notifications; `executed` → workflow stage. [I]

### 22. `fee_structures`
- **Disposition:** RETIRE
- **Normalization fault(s):** mixed-context legacy bucket (no context/status/timestamps); superseded by the detailed chain; DEP — 9 rows
- **Target home(s):** `fee_catalog` (master: id, code UNIQUE, name, fee_type_id, student_type_id?, default_amount)
- **Composite / relation key:** `id`; target key `code` UNIQUE
- **Migration rule:** 9 rows → `fee_catalog` (name kept, `default_amount` = `amount`); `code` undeterminable → `NULL` + flag; `fee_type_id`/`student_type_id` undeterminable → `NULL` + flag; `due_date` → schedule level, `NULL` + flag; `payment_allocations.fee_structure_id` rows re-pointed before the table is dropped. [U]

### 23. `fee_structures_detailed`
- **Disposition:** SPLIT
- **Normalization fault(s):** mixed-context uniqueness `(level_id, academic_year, term_id, student_type_id, fee_type_id)`; `academic_year year(4)`; price + approval workflow in one row
- **Target home(s):** `academic_year_fee_schedules` (+ `fee_catalog`, `fee_types`)
- **Composite / relation key:** `(academic_year_id, academic_year_term_id, class_id, stream_id, student_type_id, fee_catalog_id)` — the legacy UNIQUE key becomes the schedule composite key
- **Migration rule:** `level_id` → `class_id` via `school_levels` map; `fee_type_id` → `fee_catalog_id`; `year(4)` → `academic_year_id`; status/review/approval columns → workflow columns on the schedule; `copied_from_id` → lineage field in `audit_logs`; `is_auto_rollover` drives the next-year copy job; 180 rows re-homed 1:1. [V]

### 24. `fee_transition_history`
- **Disposition:** RETIRE
- **Normalization fault(s):** parallel balance-carryover trail; `from/to_academic_year int(11)`; `previous_balance`/`new_balance` stored-derived
- **Target home(s):** `audit_logs`
- **Composite / relation key:** `(student_academic_enrollment_id, from_year_id, to_year_id, balance_action, acted_at)`
- **Migration rule:** rows → `audit_logs` entries (carryover actions, `amount_transferred` retained); `from/to_year_id` → `academic_years.id`; balances → derived from obligations. [I]

### 25. `fee_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master
- **Target home(s):** `fee_types` (feeds `fee_catalog.fee_type_id`)
- **Composite / relation key:** `code` UNIQUE
- **Migration rule:** keep as-is; `fee_catalog.fee_type_id` wired as real FK; `status` soft-delete kept. [V]

### 26. `finance_approval_log`
- **Disposition:** RETIRE
- **Normalization fault(s):** generic cross-module approval trail parallel to the single `audit_logs` mechanism; `module`/`record_id` soft refs
- **Target home(s):** `audit_logs`
- **Composite / relation key:** `(entity_type=module, entity_id=record_id, action, acted_at)`
- **Migration rule:** rows re-homed as `audit_logs` (`actor_id` → actor; `from_status`/`to_status` → `old_values`/`new_values`); survives module-table re-keying (budgets → `academic_year_id`). [I]

### 27. `finance_exceptions`
- **Disposition:** RETIRE
- **Normalization fault(s):** operational exception queue parallel to the workflow mechanism; `reference_table`/`reference_id` polymorphism
- **Target home(s):** `workflow_instances` (exception-as-issue) + `audit_logs`
- **Composite / relation key:** `(type, reference_table, reference_id)` + `created_at` → workflow issue key
- **Migration rule:** open exceptions → `workflow_instances` issues (`severity` → priority); `flagged_by`/`resolved_by` → `users`; resolution → workflow stage transition + `audit_logs`; dismissed/resolved history append-only. [I]

### 28. `financial_adjustments`
- **Disposition:** SPLIT
- **Normalization fault(s):** mixed-context — seven adjustment kinds (`credit_note`, `fee_reversal`, `write_off`, `discount`, `penalty`, `arrears_write_off`, `overpayment_refund`) in one table; `academic_year year(4)` + `term tinyint`
- **Target home(s):** `fee_credit_notes` + `fee_discounts_waivers` + `payments` + `audit_logs`
- **Composite / relation key:** `adjustment_number` UNIQUE
- **Migration rule:** `credit_note`/`overpayment_refund` → `fee_credit_notes`; `discount`/`write_off`/`arrears_write_off` → `fee_discounts_waivers`; `fee_reversal` → `payments.status` change + `audit_logs`; `penalty` → `fee_credit_notes` debit-side with `reason`; `reference_payment_id` re-points to `payments`; undeterminable kind → `NULL` + flag. [I]

### 29. `financial_periods`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master; target adds `code UNIQUE` (absent in legacy)
- **Target home(s):** `financial_periods (id, code UNIQUE, name, start_date, end_date)`
- **Composite / relation key:** `code` UNIQUE; surrogate `(name, start_date, end_date)`
- **Migration rule:** keep rows; add `code` (derive from `name` where unique, else `NULL` + flag); add an `academic_years` link so fiscal and academic contexts stay reconcilable. [V]

### 30. `financial_transactions`
- **Disposition:** MERGE
- **Normalization fault(s):** generic double-entry-ish ledger parallel to `school_transactions` (redundancy); `reconciliation_status` stored-derived
- **Target home(s):** `school_transactions` (ledger spine)
- **Composite / relation key:** `(transaction_date, type, reference_no)` → `school_transactions(source='other')`
- **Migration rule:** rows re-homed into `school_transactions` with `source` derived from `payment_method` (undeterminable → `other`); `reconciliation_status` → derived over `payment_reconciliations`; `processed_by`/`reconciled_by` → `users`; table retired after merge. [I]

### 31. `mpesa_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `bill_ref_number` not normalized to student; raw callback/webhook payload stored (retained as audit); `transaction_date` carries no fiscal context
- **Target home(s):** `mpesa_transactions` (dated money-movement fact keyed to `financial_periods` + `payments` reference)
- **Composite / relation key:** `mpesa_code` UNIQUE (idempotency key); `checkout_request_id` matches STK pushes
- **Migration rule:** add `financial_period_id` from `transaction_date`; each `reconciled` row posts exactly one `school_transactions` row; `bill_ref_number` normalized to `student` where determinable; unmatched codes → `finance_exceptions`; status transitions forward-only. [V]

### 32. `payment_allocations`
- **Disposition:** MERGE
- **Normalization fault(s):** points at legacy `fee_structures` (DEP); no obligation link; split-brain with `payment_allocations_detailed`
- **Target home(s):** `payment_allocations (payment_id, student_fee_obligation_id, amount_allocated, allocated_by, allocated_at)`
- **Composite / relation key:** `(payment_id, student_fee_obligation_id)` UNIQUE
- **Migration rule:** 0-count legacy path; any rows re-mapped where a `payments`→`student_fee_obligations` pair exists; otherwise no data and the table is dropped after `fee_structures` unlink. [U]

### 33. `payment_allocations_detailed`
- **Disposition:** MERGE
- **Normalization fault(s):** split-brain double allocation table; no UNIQUE guard (double-allocation risk)
- **Target home(s):** `payment_allocations (payment_id, student_fee_obligation_id, amount_allocated, allocated_by, allocated_at)`
- **Composite / relation key:** `(payment_transaction_id, student_fee_obligation_id)` → `(payment_id, student_fee_obligation_id)` UNIQUE
- **Migration rule:** rows re-homed into the canonical `payment_allocations` (`payment_transaction_id` → `payment_id`); add UNIQUE + FKs (`allocated_by` → `users`); 60 rows re-keyed 1:1. [V]

### 34. `payment_reconciliations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none material; `bank_statement_ref` free-text (integrity soft)
- **Target home(s):** `payment_reconciliations`
- **Composite / relation key:** `transaction_id` UNIQUE → `school_transactions.id`
- **Migration rule:** keep the UNIQUE; add a `bank_transactions.transaction_ref` link so statement imports auto-reconcile; reconciled state flips `school_transactions.status` (derived). [V]

### 35. `payment_security_audit`
- **Disposition:** MERGE
- **Normalization fault(s):** append-only security event trail parallel to the system audit; soft refs only
- **Target home(s):** `security_incidents` (+ `audit_logs`)
- **Composite / relation key:** `(event_type, transaction_ref, created_at)`
- **Migration rule:** rows re-homed as `security_incidents` (`event_type`, `webhook_source`, `transaction_ref`, `ip_address`, `details`); retention per `system_retention_policies`; `duplicate_payment_attempt`/`race_condition_detected` events block the corresponding webhook processing. [I]

### 36. `payment_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** mixed-context (fee + transport payments in one table); `academic_year year(4)`/`term_id` redundant vs obligations; `term_allocation` stored-derived; `amount_paid` naming
- **Target home(s):** `payments (id, receipt_no UNIQUE, student_id, amount, payment_date, method, reference, received_by, status)`
- **Composite / relation key:** `receipt_no` UNIQUE
- **Migration rule:** rename → `payments`; drop `academic_year`/`term_id` (allocation resolves term via `student_fee_obligations`); `transport_bill_id` rows → `transport_bill_payments`; `term_allocation` → derived; `parent_id`/`received_by` → FKs; 119 rows re-homed 1:1. [V]

### 37. `payment_webhooks_log`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no `payload_hash` idempotency key; longtext `webhook_data` (retained as ingest audit)
- **Target home(s):** `payment_webhooks_log` (webhook ingest audit)
- **Composite / relation key:** `(source, created_at, signature_verified)` + `payload_hash` UNIQUE
- **Migration rule:** add `payload_hash` UNIQUE for replay idempotency; `requires_review` rows spawn an exception/issue; validated rows create `mpesa_transactions`/`bank_transactions` entries. [I]

### 38. `payroll_configurations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no UNIQUE `(config_key, financial_year)`; CFG-vs-fact separation is correct
- **Target home(s):** `payroll_configurations`
- **Composite / relation key:** `(config_key, financial_year)` UNIQUE
- **Migration rule:** keep as config; `financial_year` documented as the statutory KRA year (distinct from `academic_years`); each new year adds rows. [V]

### 39. `payslip_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none material — snapshot semantics are correct; `reference_id`/`reference_type` soft refs
- **Target home(s):** `payslip_items (payslip_id, item_type, item_name, amount)`
- **Composite / relation key:** `(payslip_id, item_type, item_code)`
- **Migration rule:** keep snapshots — never re-derive from templates; `item_code` → `staff_allowances`/`staff_deductions` type refs where determinable; `loan`/`advance`/`child_fees` items → `staff_loans`/`staff_salary_advances` via `reference_id`. [V]

### 40. `payslips`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored-derived totals (`gross_salary = basic + allowances`, `net_salary = gross − deductions`); longtext breakdowns mixed-context; `payroll_month`/`payroll_year int` vs `staff_payroll.payroll_period varchar(7)` format drift
- **Target home(s):** `payslips (id, payroll_run_id, staff_id, gross, deductions, net, UNIQUE(payroll_run_id, staff_id))`
- **Composite / relation key:** `(payroll_run_id, staff_id)` UNIQUE
- **Migration rule:** `payroll_month`/`payroll_year` → `payroll_run_id` via `payroll_runs`; add UNIQUE; keep the `net_salary` snapshot; breakdown longtexts → `payslip_items`; `signed_by` → `users`. [V]

### 41. `petty_cash_funds`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `current_balance` stored state (derivable from transactions); `last_reconciled_at/by` state in master
- **Target home(s):** `petty_cash_funds`
- **Composite / relation key:** `fund_name`
- **Migration rule:** keep the fund master; `current_balance` → derived from `petty_cash_transactions`; add FKs `custodian_id` → `staff`, `last_reconciled_by` → `users`; funds never re-keyed. [V]

### 42. `petty_cash_reconciliations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `variance` stored-derived; no `financial_period_id` context
- **Target home(s):** `petty_cash_reconciliations`
- **Composite / relation key:** `(fund_id, financial_period_id, period_from, period_to)`
- **Migration rule:** add `financial_period_id` from period dates; `variance` → derived; non-zero variance → `finance_exceptions.petty_cash_shortfall`; approval closes the period and resets the fund's reconciliation state. [V]

### 43. `petty_cash_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `balance_after` stored-derived chain; no `financial_period_id`; `(fund_id, receipt_number)` not UNIQUE
- **Target home(s):** `petty_cash_transactions`
- **Composite / relation key:** `(fund_id, transaction_date, receipt_number)` UNIQUE
- **Migration rule:** add `financial_period_id`; `balance_after` → derived; top-ups pair with a `school_transactions`/`expenses` entry; `category_id` → `expense_categories` FK; `recorded_by`/`approved_by` → `users`. [V]

### 44. `school_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `financial_period_id` is the only fiscal context — no `academic_year_id`
- **Target home(s):** `school_transactions` (consolidating ledger)
- **Composite / relation key:** `(financial_period_id, transaction_date, source, reference)` + `academic_year_id`
- **Migration rule:** add `academic_year_id` FK; every confirmed `mpesa_transactions`/`bank_transactions` row posts exactly one `school_transactions` row (idempotent); `payment_allocations`/`payment_reconciliations` attach here. [V]

### 45. `staff_payroll`
- **Disposition:** MERGE
- **Normalization fault(s):** per-staff monthly rows duplicate `payslips` (redundancy); two overlapping period keys (`payroll_month`/`payroll_year int` vs `payroll_period varchar(7)`) — drift risk
- **Target home(s):** `payroll_runs` (+ `payslips`)
- **Composite / relation key:** `(month, year)` run-level UNIQUE; `(staff_id, payroll_period)` per-staff line
- **Migration rule:** run-level status/approval/payment → `payroll_runs (financial_period_id?, month, year, status, workflow)`; staff-level totals → `payslips` rows keyed `(payroll_run_id, staff_id)`; period keys reconciled to a single canonical `YYYY-MM`; table retired after merge. [I]

### 46. `staff_payroll_adjustments`
- **Disposition:** SPLIT
- **Normalization fault(s):** append-only salary timeline parallel to the pay master; `previous_salary`/`new_salary` event pair (retained as audit data)
- **Target home(s):** `staff_allowances` (basic-salary effective line) + `audit_logs`
- **Composite / relation key:** `(staff_id, effective_date)` UNIQUE
- **Migration rule:** `new_salary` → `staff_allowances` basic-pay binding (`effective_from` = `effective_date`, `effective_to` = next event date); the event itself → `audit_logs`; `source_id` → `staff_appointments` where determinable; `staff_payroll_profiles.basic_salary` derived forward from these lines. [I]

### 47. `staff_payroll_profiles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `basic_salary` current-state duplicates the adjustment timeline (stored-derived)
- **Target home(s):** `staff_payroll_profiles` (staff pay master)
- **Composite / relation key:** `staff_id` UNIQUE
- **Migration rule:** keep bank/statutory refs (`bank_name`, `bank_account`, `kra_pin`, `nssf_no`, `nhif_no`); `basic_salary` → derived forward from `staff_allowances`/`staff_payroll_adjustments`; `status` soft-delete kept; add FK `staff_id` → `staff.id`. [V]

### 48. `staff_salary_advances`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `balance_remaining` stored-derived; `deduction_schedule` mixed context (single/two/three-month)
- **Target home(s):** `staff_salary_advances` (staff-loans pattern: `staff_id`, `requested/approved_amount`, `request_date`, repayment schedule)
- **Composite / relation key:** `advance_number` UNIQUE
- **Migration rule:** keep as a loan-type fact; `balance_remaining` → derived from `payslip_items` `item_type='advance'` deductions; `amount_deducted` monotonic (deductions only reduce); `approved_by` → `users`. [V]

### 49. `student_arrears`
- **Disposition:** RETIRE
- **Normalization fault(s):** stored-derived snapshot (`total_arrears`, `last_payment_date`); `days_overdue` uses `curdate()` (time-bomb); duplicates the obligations+allocations roll-up
- **Target home(s):** derived arrears view over `student_fee_obligations` + `payment_allocations` + `fee_credit_notes`/`fee_discounts_waivers`
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_id, term_id)` view key
- **Migration rule:** no stored rows; `arrears_settlement_plans.arrears_id` re-keyed to `(student_academic_enrollment_id, academic_year_term_id)`; view is year-scoped, never `curdate()`. [V]

### 50. `student_fee_balances`
- **Disposition:** RETIRE
- **Normalization fault(s):** denormalized running balance (redundancy); keyed on legacy `fee_structures` (DEP); inconsistent `academic_term_id` naming
- **Target home(s):** derived balance view over obligations − allocations − credits
- **Composite / relation key:** n/a
- **Migration rule:** no data; balances derived, never stored. [V]

### 51. `student_fee_carryover`
- **Disposition:** RETIRE
- **Normalization fault(s):** parallel balance-move history; `academic_year int(11)`; `previous_balance`/`surplus_amount` stored-derived
- **Target home(s):** `audit_logs`
- **Composite / relation key:** `(student_academic_enrollment_id, action_taken, acted_at)` + from/to year
- **Migration rule:** rows → `audit_logs` entries (carryover applied exactly once — idempotency guard); `from/to_year_id` → `academic_years.id`; amounts retained as event data; obligations never store carryover balances. [I]

### 52. `student_fee_obligations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stored-derived balances (`balance`/`year_balance`/`term_balance`/`previous_year_balance`/`previous_term_balance`); `academic_year year(4)`; `is_sponsored`/`sponsored_waiver_amount` mixed context
- **Target home(s):** `student_fee_obligations`
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_fee_schedule_id)` UNIQUE
- **Migration rule:** re-key `student_id` → `student_academic_enrollment_id`; `fee_structure_detail_id` → `academic_year_fee_schedule_id`; REMOVE all stored balances (→ derived views); move `is_sponsored`/`sponsored_waiver_amount` → `fee_discounts_waivers`; keep `amount_due`/`due_date`; 915 rows re-homed 1:1. [V]

### 53. `student_payment_history_summary`
- **Disposition:** RETIRE
- **Normalization fault(s):** materialized roll-up duplicating obligations+payments+allocations (redundancy); `academic_year year(4)`; drift risk on every mutation
- **Target home(s):** derived view over `student_fee_obligations` + `payments` + `payment_allocations`
- **Composite / relation key:** `(student_academic_enrollment_id, academic_year_id, term_id)` view key
- **Migration rule:** no data; replaced by a view; no `curdate()`. [V]

### 54. `tax_brackets`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** none — REF master; no UNIQUE guard
- **Target home(s):** `tax_brackets`
- **Composite / relation key:** `(financial_year, min_income, max_income)` UNIQUE
- **Migration rule:** keep as-is; add the UNIQUE; statutory-year vs academic-year mapping documented; bracket changes only affect future payslips. [V]

### 55. `tax_withholding_history`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `cumulative_tax` stored-derived (annual running total); otherwise append-only KRA record — correct
- **Target home(s):** `tax_withholding_history`
- **Composite / relation key:** `(staff_id, financial_year, payroll_month)` UNIQUE
- **Migration rule:** keep append-only KRA records; `cumulative_tax` → derived or retained as filing snapshot; `kra_pin` snapshot kept (can change later); rows never mutate post-filing. [V]

### 56. `transport_bill_payments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `transaction_id varchar` external ref with no integrity; no receipt key
- **Target home(s):** `transport_bill_payments` (payment fact)
- **Composite / relation key:** `(bill_id, payment_date, transaction_id)` UNIQUE
- **Migration rule:** keep as payment fact; add FKs `bill_id` → `transport_monthly_bills.id`, `received_by` → `users`; UNIQUE `transaction_id` prevents double-posting. [V]

### 57. `transport_bills`
- **Disposition:** MERGE
- **Normalization fault(s):** legacy duplicate of `transport_monthly_bills` (redundancy); `billing_month date` only period key; `amount_paid` stored
- **Target home(s):** `transport_monthly_bills`
- **Composite / relation key:** `(student_academic_enrollment_id, subscription_id, billing_month)`
- **Migration rule:** rows merged into `transport_monthly_bills` dedupe by key (`amount` → `amount_due`; `amount_paid` → payment history); then the table is retired. [I]

### 58. `transport_monthly_bills`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `balance`/`amount_paid` stored-derived; `billing_month date` vs month/year split; no `academic_year_id`
- **Target home(s):** `transport_monthly_bills (student_academic_enrollment_id, month, year, amount)`
- **Composite / relation key:** `(student_academic_enrollment_id, month, year)` + `subscription_id`
- **Migration rule:** re-key `student_id` → `student_academic_enrollment_id`; `billing_month` → `month`/`year` columns; `balance`/`amount_paid` → derived from `transport_bill_payments`; add `academic_year_id` so transport bills are year-attributable. [V]

### 59. `transport_payments`
- **Disposition:** MERGE
- **Normalization fault(s):** thin legacy duplicate of `transport_bill_payments`/`payments`; no bill link; no year/term/month context; status lifecycle unenforced
- **Target home(s):** `transport_bill_payments`
- **Composite / relation key:** `(paybill_reference, paid_at)` → matched monthly bill
- **Migration rule:** confirmed rows matched to a `transport_monthly_bills` by student + month where determinable; unmatched → `NULL` + flag; table retired after merge. [I]

### 60. `uniform_payment_records`
- **Disposition:** MERGE
- **Normalization fault(s):** parallel payment table duplicating `uniform_sale_payments` (redundancy/drift)
- **Target home(s):** `uniform_sale_payments`
- **Composite / relation key:** `(sale_id, payment_date, reference_no)`
- **Migration rule:** rows folded into the canonical `uniform_sale_payments` keeping `reference_no` + `recorded_by`; then the table is retired. [V]

### 61. `uniform_purchase_items`
- **Disposition:** MERGE
- **Normalization fault(s):** `total_cost` stored-derived (`quantity × unit_cost`); `size` snapshot (kept)
- **Target home(s):** `requisition_items` (under `purchase_orders`)
- **Composite / relation key:** `(purchase_order_id, item_id, size)`
- **Migration rule:** re-home as `requisition_items` (`quantity`, `unit_cost`); `total_cost` → derived; `item_id` → `inventory_items`; receiving posts stock via `inventory_transactions`. [I]

### 62. `uniform_purchases`
- **Disposition:** MERGE
- **Normalization fault(s):** `supplier_name` denormalized (redundancy); `total_cost` stored-derived; `payment_status`/`amount_paid` stored (payments belong in payment facts)
- **Target home(s):** `purchase_orders` (+ `suppliers`)
- **Composite / relation key:** `invoice_number` + `purchase_date`
- **Migration rule:** re-home as `purchase_orders`; `supplier_name` → `suppliers` FK (undeterminable → `NULL` + flag); `payment_status`/`amount_paid` → derived from purchase payments; `received_by`/`created_by` → `users`. [I]

### 63. `uniform_sale_payments`
- **Disposition:** MERGE
- **Normalization fault(s):** `amount_paid` naming; duplicate of `uniform_payment_records` (drift risk)
- **Target home(s):** `uniform_sale_payments (sale_id, amount, method, payment_date, receipt_no)`
- **Composite / relation key:** `(sale_id, payment_date, receipt_no)`
- **Migration rule:** canonical sale-payment fact; absorbs `uniform_payment_records` rows; add FKs `sale_id` → `uniform_sales.id`, `received_by` → `users`; `receipt_no` is the integrity key. [V]

### 64. `uniform_sales`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** duplicate `balance_due`/`balance` columns (stored-derived); `student_id` not stream-bound; `size` string duplicates `uniform_sizes`
- **Target home(s):** `uniform_sales (student_academic_enrollment_id, uniform_item_id, quantity, unit_price, sale_date, sold_by)`
- **Composite / relation key:** `(student_academic_enrollment_id, uniform_item_id, sale_date, receipt_no)`
- **Migration rule:** re-key `student_id` → `student_academic_enrollment_id`; `item_id` → `uniform_item_id`; REMOVE `balance`/`balance_due`; `size` → `uniform_sizes` ref; `total_amount`/`amount_paid` → derived. [V]

### 65. `uniform_sales_summary`
- **Disposition:** RETIRE
- **Normalization fault(s):** materialized monthly roll-up (redundancy); `month_year` context only; drift risk
- **Target home(s):** derived view over `uniform_sales` + `uniform_sale_payments`
- **Composite / relation key:** `(month_year, item_id)` view key
- **Migration rule:** no data; view parameterized by `academic_year_id`; no `curdate()`. [V]

### 66. `uniform_sizes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** stock counters (`quantity_available`/`reserved`/`sold`) are mutable state in a master; no UNIQUE `(item_id, size)`
- **Target home(s):** `uniform_sizes` (size catalogue REF; counters → `inventory_transactions` derived)
- **Composite / relation key:** `(uniform_item_id, size)` UNIQUE
- **Migration rule:** keep the size catalogue (`size`, `size_label`, `size_type`, `unit_price`, `reorder_level`); counters → derived from `inventory_transactions`; add the UNIQUE; `item_id` → `uniform_items` (inventory-domain catalog). [V]

### 67. `vw_all_school_payments`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed `vw_` (DEP, no PK); stale materialized union of mpesa/bank/school transactions; refresh-job dependency
- **Target home(s):** derived view unioning `mpesa_transactions` + `bank_transactions` + `school_transactions`
- **Composite / relation key:** `(source, reference, transaction_date)` view key
- **Migration rule:** drop the physical table; replace with a `CREATE VIEW` parameterized by `academic_year_id`; never `curdate()`. [V]

### 68. `vw_financial_period_summary`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed `vw_`; aggregates require a refresh job (drift); no PK
- **Target home(s):** derived view over `financial_periods` + `school_transactions` + `payment_reconciliations`
- **Composite / relation key:** `period_id` view key
- **Migration rule:** drop the physical table; replace with a view; no period-year `curdate()` filtering. [V]

### 69. `vw_outstanding_fees`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed `vw_`; denormalized outstanding-balance snapshot; no year scoping; stale
- **Target home(s):** derived view over `student_fee_obligations` (year-scoped) + `students`/`parents`
- **Composite / relation key:** `student_academic_enrollment_id` view key
- **Migration rule:** drop the physical table; replace with a view filtered by `academic_year_id` (2026 = id 5, 2027 = id 6); never `curdate()`. [V]
