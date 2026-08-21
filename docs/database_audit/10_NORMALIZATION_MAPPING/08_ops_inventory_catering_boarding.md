# Normalization mapping — Ops: Inventory, Catering, Boarding, Assets
Part of `10_NORMALIZATION_MAPPING/`. Covers 37 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/08_ops_inventory_catering_boarding.md`, `/tmp/opencode/domains/domain_08.txt`.

### 1. `asset_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF — depreciation defaults (method/life/rate/residual) attached to the category as the single source; `fixed_assets.category_id` FK undeclared [V]
- **Target home(s):** `asset_categories` — keep master (§4.9 assets)
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep REF, never re-ID; merge duplicate codes; declare `fixed_assets.category_id` FK; depreciation defaults flow to `fixed_assets`; no year context. [V]

### 2. `asset_disposals`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `asset_id`/`authorised_by` FKs undeclared; year derivable from `disposal_date` (no year FK needed) [V]
- **Target home(s):** `asset_disposals` — keep (asset lifecycle fact)
- **Composite / relation key:** `(asset_id, disposal_date)`
- **Migration rule:** Keep TXN; declare `asset_id`→fixed_assets and `authorised_by`→users FKs; keep `disposal_type`, `book_value_at_disposal`, `proceeds`, `gain_loss`, `buyer_name`, `reason`; append-only (a reversal is a new row); the disposal row drives `fixed_assets.status`/`condition`, never an overwrite of history. [V]

### 3. `boarding_attendance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id`+`dormitory_id`+`date` carries **no year/term**, so 2027 marks are indistinguishable from 2026; the roll-call mark references the student directly instead of the year-scoped dormitory instance [V]
- **Target home(s):** `boarding_attendance` (dormitory_assignment_id, date, session_id, status, UNIQUE(dormitory_assignment_id, date, session_id)) — §4.8
- **Composite / relation key:** `UNIQUE(dormitory_assignment_id, date, session_id)`
- **Migration rule:** Re-key the fact: `student_id`+`dormitory_id` → `dormitory_assignment_id` resolved via `dormitory_assignments` on `(student_academic_enrollment_id, academic_year_id)` (year from `date`); the year context now comes **from the assignment**, so no year column is added here; keep `session_id`→attendance_sessions, `status`, `check_time`, `location_verified`, `absence_reason`, `permission_id`, `marked_by`; undeterminable assignment = NULL + flag; roll-call views filter by `dormitory_assignment_id`/year, never `curdate()`. [V]

### 4. `catering_meal_statuses`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `meal_date`); `recorded_by` FK undeclared; `student_id` not stream-scoped [V]
- **Target home(s):** `catering_meal_statuses` — keep (catering fact keyed to date/meal) — §4.10
- **Composite / relation key:** `(student_academic_enrollment_id, meal_date, meal_type)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id`; add `academic_year_id`/`academic_year_term_id` backfilled from `meal_date`; declare `recorded_by`→users FK; keep `status`, `notes`; append-only. [V]

### 5. `daily_meal_allocations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** class-bound row with no year context (03 note); `boarding_house_id` FK undeclared (points at dormitories); `student_count` is a per-day target fact [V]
- **Target home(s):** `daily_meal_allocations` — keep (catering fact keyed to date) — §4.10
- **Composite / relation key:** `(allocation_date, dormitory_id, academic_year_class_stream_id)`
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `allocation_date`; `class_id` → `academic_year_class_stream_id` (class instance for that year); `boarding_house_id`→dormitories FK declared; keep `student_count`, `notes`, `created_by`→staff. [V]

### 6. `depreciation_schedule`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `asset_id`/`computed_by` FKs undeclared; `financial_year` is the correct fiscal period key [V]
- **Target home(s):** `depreciation_schedule` — keep (depreciation fact)
- **Composite / relation key:** `(asset_id, financial_year)`
- **Migration rule:** Keep TXN; declare `asset_id`→fixed_assets and `computed_by`→users FKs; keep `financial_year` as the period key — **do not** replace with `academic_year_id` (depreciation is fiscal, not academic); keep `opening_value`/`depreciation_amount`/`closing_value`/`accumulated_total`, `is_posted`; recompute allowed only for un-posted rows. [V]

### 7. `dormitories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `current_occupancy` stored derived counter (duplicates `dormitory_assignments`) [V]
- **Target home(s):** `dormitories` (id, code UNIQUE, name, capacity) — §4.8 master
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep MASTER, never re-ID; `current_occupancy` dropped — recomputed as a view over `dormitory_assignments`; keep `gender`, `capacity`, `floor_count`, `house_parent_id`/`assistant_house_parent_id`→staff FKs, `location`, `facilities`, `status`. [V]

### 8. `dormitory_assignments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id` not stream-scoped (academic year present but student binding missing); `bed_number` is a varchar, not an FK to the `beds` master [V]
- **Target home(s):** `dormitory_assignments` (student_academic_enrollment_id, dormitory_id, bed_id?, academic_year_id, start_date, end_date?, UNIQUE(student_academic_enrollment_id, academic_year_id)) — §4.8
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, academic_year_id)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id` (resolved via `student_academic_enrollments` on `academic_year_id`); `bed_number`→`bed_id` (beds master) where the bed resolves, else NULL + flag; keep `dormitory_id`, `assigned_date`/`end_date`, `status`, `assigned_by`; history by appending — a 2027 move adds a new row with `status='transferred'` on the old one, never mutating the 2026 row; this table provides the year context that `boarding_attendance` needs. [V]

### 9. `equipment_maintenance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** schedule row carries a `status` lifecycle while executions belong in logs; `next_maintenance_date`-driven `curdate()` views (time-bomb family) [V]
- **Target home(s):** `equipment_maintenance` — keep (asset maintenance schedule) + `maintenance_logs` (executions)
- **Composite / relation key:** `(equipment_id, next_maintenance_date)` (equipment_id→item_serials)
- **Migration rule:** Keep TXN; each execution appends a `maintenance_logs` row rather than rewriting the schedule; status transitions (pending→…→overdue) on the schedule row; `equipment_id`→item_serials and `maintenance_type_id`→equipment_maintenance_types FKs already wired; replace `curdate()` urgency in views with an explicit date parameter. [V]

### 10. `equipment_maintenance_types`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF [V]
- **Target home(s):** `equipment_maintenance_types` — keep
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep REF, never re-ID; merge duplicate codes; `frequency_days` drives the equipment maintenance schedule and the derived `next_service_date`; no year context. [V]

### 11. `fixed_assets`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `category_id`/`supplier_id`/`expense_id`/`added_by` FKs undeclared; `current_book_value`/`accumulated_depr`/`last_depreciation_date` duplicate the `depreciation_schedule` fact [V]
- **Target home(s):** `fixed_assets` — keep (assets master register)
- **Composite / relation key:** `id` + `UNIQUE(asset_code)`
- **Migration rule:** Keep MASTER, never re-ID; declare the missing FKs; `current_book_value`/`accumulated_depr` derived from `depreciation_schedule` (drop the stored duplicates per zero-redundancy) — keep only the last-posted snapshot if audit requires it [I]; `deleted_at` is soft-delete only — history rows survive; disposals/depreciation rows attach here. [V]

### 12. `food_consumption_records`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `consumption_date`); `total_cost` derivable from `quantity_used` × `cost_per_unit` [V]
- **Target home(s):** `food_consumption_records` — keep (catering fact keyed to date/meal) — §4.10
- **Composite / relation key:** `(consumption_date, meal_plan_id, inventory_item_id)`
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `consumption_date`; `total_cost` recomputed (drop the stored duplicate) [I]; keep `quantity_planned`/`quantity_used`/`waste_quantity`, `unit`, `recorded_by`→staff, `recorded_at`; append-only. [V]

### 13. `inventory_adjustments`
- **Disposition:** MERGE
- **Normalization fault(s):** separate stock-change event duplicating the `inventory_transactions` ledger; `quantity_change` signed but not expressed as in/out ledger entries; `reference_type`/`reference_id` polymorphic [V]
- **Target home(s):** `inventory_transactions` (transaction_type='adjustment', reference_entity) — §4.9
- **Composite / relation key:** `(item_id, transaction_date, reference_entity_type, reference_entity_id)` on the ledger
- **Migration rule:** Re-home each adjustment as an `inventory_transactions` row with `transaction_type='adjustment'` (signed `quantity_change`→quantity, direction by sign), `transaction_date=created_at`, `reference_entity_type`/`reference_entity_id` preserving `reference_type`/`reference_id` (e.g. count_id); keep the `reason` via reference or a reason column [I]; approval workflow remains a status transition on the pre-posting record; undeterminable location = NULL + flag. [V]

### 14. `inventory_allocations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** class-bound without year context (03 note); `returned_quantity`/`returned_at` partial-return state on the same row is a lifecycle transition, not a rewrite [V]
- **Target home(s):** `inventory_allocations` — keep (§4.9)
- **Composite / relation key:** `UNIQUE(allocation_number)`
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `allocation_date`; `allocated_to_class_id` → class instance for that year; keep `item_id`, `allocated_quantity`, `allocated_to_department_id`, `allocated_to_event`, `expected_return_date`, `status` lifecycle, `allocated_by`/`issued_by`→staff; returns are `returned_quantity` transitions, never rewrites. [V]

### 15. `inventory_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `category_name` duplicates `name`; hierarchical `parent_id` self-FK [V]
- **Target home(s):** `inventory_categories` — keep master
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep REF, never re-ID; drop `category_name` (keep `name`); merge duplicates by code; keep `parent_id` self-FK; `inventory_items` cascade-orphan if a category is deleted. [V]

### 16. `inventory_count_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `difference` stored derivable (actual − expected) [V]
- **Target home(s):** `inventory_count_items` — keep
- **Composite / relation key:** `(count_id, item_id)`
- **Migration rule:** Keep TXN; `difference` recomputed (drop the stored duplicate); `count_id`→inventory_counts and `item_id`→inventory_items FKs already wired; append-only per count round — differences flow to `inventory_adjustments` (reason=count_adjustment), never silent UPDATEs. [V]

### 17. `inventory_counts`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `count_date`) [V]
- **Target home(s):** `inventory_counts` — keep (stocktake event)
- **Composite / relation key:** `(count_date, counted_by)`
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `count_date`; keep `counted_by`/`verified_by`→staff, `completed_at` (locks the round), `status` lifecycle (draft→…→completed); status transitions only. [V]

### 18. `inventory_departments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF of consuming departments; overlaps the HR `departments` master conceptually [V]
- **Target home(s):** `inventory_departments` — keep (consuming-department lookup)
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep REF, never re-ID; merge duplicate codes; keep `department_head_id`→staff; allocations + requisitions attach to it; reconcile against the HR `departments` master only if a single source is required [I]; no year context. [V]

### 19. `inventory_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `name`/`item_name` and `current_quantity`/`quantity_on_hand` duplicate column pairs; `location_id`/`supplier_id` FKs undeclared; `last_purchase_*`/`last_audit_date` derivable from the ledger [V]
- **Target home(s):** `inventory_items` (id, code UNIQUE, name, category_id, unit_of_measure) — §4.9 master
- **Composite / relation key:** `id` + `UNIQUE(code)/UNIQUE(barcode)/UNIQUE(sku)`
- **Migration rule:** Keep MASTER, never re-ID; de-duplicate to `name`; `current_quantity`/`quantity_on_hand` → derived view over the `inventory_transactions` ledger sum (drop stored duplicates); declare `location_id`→inventory_locations and `supplier_id`→suppliers FKs; keep `minimum_quantity`/`reorder_level`, `unit`, `batch_tracking`/`serial_tracking`; nine children attach to this id. [V]

### 20. `inventory_locations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** overlaps `storage_locations` (two location concepts); `inventory_items.location_id` FK undeclared [V]
- **Target home(s):** `inventory_locations` / `storage_locations` — keep (§4.9)
- **Composite / relation key:** `id` + `location_name`
- **Migration rule:** Keep REF as the item-location FK target; declare `inventory_items.location_id` FK; reconcile `storage_locations` separately into one location concept [I]; keep `location_type`, `description`, `status`. [V]

### 21. `inventory_requisitions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `requisition_date`); `required_date`-relative `curdate()` view (time-bomb family) [V]
- **Target home(s):** `requisitions` + `requisition_items` — §4.9 (renamed/reshaped from inventory_requisitions)
- **Composite / relation key:** `UNIQUE(requisition_number)`
- **Migration rule:** Rename to `requisitions`; add `academic_year_id`/`academic_year_term_id` backfilled from `requisition_date`; keep `department_id`→inventory_departments, `required_date`, `priority`, `status` lifecycle, `created_by`/`approved_by`→staff, `rejection_reason`/`approved_at`/`fulfilled_at`; approval/fulfilment are status transitions, never rewrites; replace `curdate()` window with an explicit date parameter. [V]

### 22. `inventory_transactions`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** polymorphic `reference_type`/`reference_id`; no year/term columns; `total_cost` derivable from `quantity` × `unit_cost` [V]
- **Target home(s):** `inventory_transactions` (item_id, location_id, transaction_type, quantity, unit_price, transaction_date, reference_entity_type, reference_entity_id) — §4.9 ledger
- **Composite / relation key:** `(item_id, location_id, transaction_type, quantity, transaction_date, reference_entity_type, reference_entity_id)`
- **Migration rule:** Keep TXN — the append-only ledger; add `academic_year_id`/`academic_year_term_id` backfilled from `transaction_date`; rename `reference_type`/`reference_id` → `reference_entity_type`/`reference_entity_id`; keep `batch_id`/`serial_id` FKs; `total_cost` recomputed (drop the stored duplicate); corrections are reversing entries, never edits. [V]

### 23. `item_batches`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean TXN — batch lifecycle (active/expired/depleted); `quantity` is a receipt fact [V]
- **Target home(s):** `item_batches` (item_id, batch_no, expiry) — §4.9 keep
- **Composite / relation key:** `(item_id, batch_number)`
- **Migration rule:** Keep TXN; append-only — batch status transitions (active→depleted) never rewrite; `item_id`→inventory_items and `supplier_id`→suppliers FKs already wired; `expiry_date` drives FIFO; no year context (lifecycle is date-based). [V]

### 24. `item_serials`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean TXN — individually tracked units; equipment maintenance and transactions key off it [V]
- **Target home(s):** `item_serials` (item_id, serial_no) — §4.9 keep
- **Composite / relation key:** `(item_id, serial_number)`
- **Migration rule:** Keep TXN; never re-ID a serial (equipment_maintenance/maintenance_logs children detach otherwise); status transitions only (in_stock→sold/defective); keep `batch_id`→item_batches; no year context. [V]

### 25. `library_books`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `available_copies` stored derived (`total_copies − open issues`); `category_id` FK undeclared [V]
- **Target home(s):** `library_books` — keep (library catalog master)
- **Composite / relation key:** `id` + `isbn`
- **Migration rule:** Keep MASTER, never re-ID; declare `category_id`→library_categories FK; `available_copies` → derived view (drop the stored duplicate) [I]; keep `total_copies`, `title`/`author`/`publisher`, `status`; `deleted_at` soft-delete preserves issue history. [V]

### 26. `library_categories`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean REF [V]
- **Target home(s):** `library_categories` — keep
- **Composite / relation key:** `id` + `UNIQUE(name)`
- **Migration rule:** Keep REF, never re-ID; merge duplicate names; books reference the category — renaming must keep ids stable; no year context. [V]

### 27. `library_fines`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `issue_id`/`waived_by` FKs undeclared; `days_overdue` stored derived from `library_issues.due_date` [V]
- **Target home(s):** `library_fines` — keep (issue/return + fine facts)
- **Composite / relation key:** `(issue_id)`
- **Migration rule:** Keep TXN; declare `issue_id`→library_issues and `waived_by`→users FKs; `days_overdue`/`fine_amount` computed from `due_date` vs an explicit date, never `curdate()`; waiver is a status transition (pending→paid/waived), preserving the pending amount. [V]

### 28. `library_issues`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** polymorphic `borrower_id` (student/staff by `borrower_type`) cannot be a hard FK; `overdue` derived from `due_date` vs today [V]
- **Target home(s):** `library_issues` — keep (issue/return fact)
- **Composite / relation key:** `(book_id, borrower_type, borrower_id, issued_date)`
- **Migration rule:** Keep TXN; keep the `borrower_type` discriminator with a composite index (no hard FK on polymorphic `borrower_id`); declare `book_id`→library_books; `overdue` computed from `due_date` vs an explicit date, not `curdate()`; returns/overdue are status transitions; never re-number books. [V]

### 29. `maintenance_logs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean TXN — one executed maintenance event per schedule; `next_service_date` derivable from type frequency + last log [V]
- **Target home(s):** `maintenance_logs` — keep (asset maintenance facts)
- **Composite / relation key:** `(maintenance_schedule_id, maintenance_date)`
- **Migration rule:** Keep TXN; append-only execution log; `next_service_date` derived from `equipment_maintenance_types.frequency_days` + last log (drop the stored duplicate on the schedule) [I]; FKs already wired (`equipment_id`→item_serials, `maintenance_staff_id`→staff); no year context. [V]

### 30. `meal_plans`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `plan_date`); planned/prepared/actual servings + status lifecycle on one fact row [V]
- **Target home(s):** `meal_plans` (id, name, academic_year_term_id?) — §4.10 keep
- **Composite / relation key:** `(plan_date, meal_type, menu_item_id)` (or keyed to term)
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `plan_date`; keep `meal_type`, `menu_item_id`, `planned_servings`/`prepared_quantity`/`actual_servings`, `status` lifecycle (planned→…→served/cancelled), `prepared_by`/`created_by`→staff; consumption records attach via `meal_plan_id`; append-only with status lifecycle. [V]

### 31. `menu_item_ingredients`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `menu_item_id` FK undeclared; clean JXN [V]
- **Target home(s):** `menu_item_ingredients` — keep (recipe junction) — §4.10
- **Composite / relation key:** `(menu_item_id, inventory_item_id)`
- **Migration rule:** Keep JXN; declare `menu_item_id`→menu_items FK; keep `quantity_per_portion`, `unit`, `notes`; junction rows freely re-created; consumption projections derive from portions; no year context. [V]

### 32. `menu_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean MASTER — no year context [V]
- **Target home(s):** `menu_items` (id, name, unit) — §4.10 keep
- **Composite / relation key:** `id` + name
- **Migration rule:** Keep MASTER, never re-ID; merge duplicate names by id; meal plans and recipe lines attach to it — renaming must keep ids stable. [V]

### 33. `purchase_orders`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term columns (derivable from `order_date`); `total_amount` derivable from order lines once lines exist [V]
- **Target home(s):** `purchase_orders` — keep (§4.9 procurement)
- **Composite / relation key:** `UNIQUE(order_number)`
- **Migration rule:** Add `academic_year_id`/`academic_year_term_id` backfilled from `order_date`; keep `supplier_id`→suppliers, `expected_delivery_date`, `status` lifecycle (draft→…→received/cancelled), `created_by`/`approved_by`→staff, `payment_terms`; `total_amount` retained as a receipt snapshot fact; approval/receipt are status transitions, never rewrites; receipt drives `inventory_transactions` (reference_type=purchase). [V]

### 34. `requisition_items`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean TXN line item; `fulfilled_quantity` growth is the only legal mutation [V]
- **Target home(s):** `requisition_items` (parent renamed `requisitions`) — §4.9
- **Composite / relation key:** `(requisition_id, item_id)`
- **Migration rule:** Keep TXN; parent references re-pointed to `requisitions`; keep `requested_quantity`/`approved_quantity`/`fulfilled_quantity`, `unit`, `unit_cost`, `notes`; fulfilment is additive (`fulfilled_quantity` growth), never rewriting the request; year context inherited from the parent requisition. [V]

### 35. `storage_locations`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** overlaps `inventory_locations` (two location concepts); `parent_id` self-FK undeclared [V]
- **Target home(s):** `storage_locations` / `inventory_locations` — keep (§4.9)
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep REF; reconcile with `inventory_locations` into one location concept [I] — declare `parent_id` self-FK; keep `capacity`, `description`, `status`; no year context. [V]

### 36. `suppliers`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `supplier_name` duplicates `name`; `inventory_items.supplier_id`/`fixed_assets.supplier_id` FKs undeclared [V]
- **Target home(s):** `suppliers` — keep master (§4.9)
- **Composite / relation key:** `id`
- **Migration rule:** Keep MASTER, never re-ID; drop `supplier_name` (keep `name`); declare the missing child FKs (inventory_items, fixed_assets); purchase orders + batches attach to it — renumbering breaks procurement history. [V]

### 37. `vw_inventory_low_stock`
- **Disposition:** RETIRE
- **Normalization fault(s):** real table misnamed `vw_` (no PK, all columns nullable); materialized low-stock snapshot that reports stale quantities unless refreshed [V]
- **Target home(s):** none as a table — logic re-created as a genuine VIEW over `inventory_items` + `inventory_categories` (§4.9)
- **Composite / relation key:** none (derived projection)
- **Migration rule:** RETIRE-as-view: drop the materialized table and re-create a real `CREATE VIEW` over `inventory_items` (join `inventory_categories` for `category`), filtering on `current_quantity ≤ minimum_quantity` where stock is the derived ledger view — never `curdate()`; rename with a `v_`/`summary_` prefix; prefer the live `vw_inventory_health` logic; no data migration (all values re-derivable from `inventory_items`). [U]
