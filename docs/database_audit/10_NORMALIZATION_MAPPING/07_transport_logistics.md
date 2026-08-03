# Normalization mapping — Transport & Logistics
Part of `10_NORMALIZATION_MAPPING/`. Covers 18 tables. Base evidence: `08_PER_TABLE_BREAKDOWN/07_transport_logistics.md`, `/tmp/opencode/domains/domain_07.txt`.

### 1. `drivers`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** person attributes (`first_name`/`last_name`/`phone`) duplicate the shared person/staff base; four semantic child FKs never declared [V]
- **Target home(s):** `persons`/`staff` (person base + subtype) + `drivers` (staff/person + driver record) — §4.1 / §4.7
- **Composite / relation key:** `id` + `UNIQUE(license_number)`
- **Migration rule:** Keep MASTER, never re-ID; merge duplicates by `license_number`; person fields re-homed into `persons` (staff subtype) and the driver record retains `license_number`, `status`, `person_id`; declare child FKs (`transport_vehicles.driver_id`, `vehicle_fuel_logs.filled_by`, `route_schedules.driver_id`, `transport_schedules.driver_id`). [V]

### 2. `routes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** clean SYS — the RBAC route registry; `domain` enum distinguishes SYSTEM/SCHOOL [V]
- **Target home(s):** `routes_registry` (system domain, §4.15) — NOT transport
- **Composite / relation key:** `id` + `UNIQUE(name)`
- **Migration rule:** Keep SYS; rename to `routes_registry`; do not conflate with `transport_routes` — this table drives the router/AuthMiddleware, not buses; keep `url`, `module`, `controller`, `action`, `domain`, `is_active`; `route_permissions` matrix keys off this id; no year context. [V]

### 3. `route_schedules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `vehicle_id`/`driver_id` FKs undeclared; `day_of_week` enum diverges from `class_schedules`; weekly recurrence duplicated by `transport_schedules` dated runs [V]
- **Target home(s):** `route_schedules` / `transport_schedules` — §4.7 (route_stop × time × direction)
- **Composite / relation key:** `(route_id, day_of_week, direction, departure_time)`
- **Migration rule:** Keep TXN; declare `vehicle_id`→transport_vehicles and `driver_id`→drivers FKs; align `day_of_week` enum with the shared week enum; keep the weekly pattern as explicit rows (dated runs live in `transport_schedules`); never `curdate()`. [V]

### 4. `route_stops`
- **Disposition:** MERGE
- **Normalization fault(s):** definition (name/sequence/location) duplicated with `transport_stops`; timing (`morning_time`/`afternoon_time`) and capacity (`max_students`) are schedule facts mixed into the stop row; `current_students` is a stored derived counter [V]
- **Target home(s):** `transport_stops` (route_id, name, order_no) + `route_schedules` (route_stop × time × direction) — §4.7
- **Composite / relation key:** `(route_id, name)` merged into `transport_stops`; timings → `(route_id, stop_id, day_of_week, direction, time)` on schedules
- **Migration rule:** Merge rows into `transport_stops` matched by `(route_id, name)`, keeping the surviving id stable (assignment children reference it); `morning_time`/`afternoon_time` → `route_schedules` times (pickup/dropoff); `max_students` → capacity; `current_students` dropped — recomputed as a view over `student_transport_assignments`; undeterminable timing/capacity = NULL + flag. [V]

### 5. `student_transport_assignments`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id` not stream-scoped (no per-year stream binding); `academic_year_id` missing (only `year` int); three stop columns reference the stop list [V]
- **Target home(s):** `student_transport_assignments` (student_academic_enrollment_id, route_id, stop_id, month, year, amount, status, UNIQUE(student_academic_enrollment_id, month, year)) — §4.7
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, month, year)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id` (resolved via `student_academic_enrollments` on `year`); add `academic_year_id` backfilled from `year`; keep `route_id`, `stop_id`/`pickup_stop_id`/`dropoff_stop_id`→transport_stops, `expected_amount`, `status`; never mutate a past month's row — a move adds a new `(student_academic_enrollment_id, month, year)` row; undeterminable stream mapping = NULL + flag. [V]

### 6. `student_transport_attendance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term attribution (year only derivable from `attendance_date`); `student_id` not stream-scoped [V]
- **Target home(s):** `student_transport_attendance` (transport boarding fact keyed (student_academic_enrollment_id, date, trip_session)) — §4.7
- **Composite / relation key:** `UNIQUE(student_academic_enrollment_id, attendance_date, trip_session)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id`; add `academic_year_id`/`academic_year_term_id` backfilled from `attendance_date`; keep `route_id`, `vehicle_id`, `status`, `marked_time`, `marked_by`; keep the `(student, date, trip_session)` uniqueness; append-only. [V]

### 7. `student_transport_incidents`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** no year/term attribution (derivable from `incident_datetime`); `student_id` not stream-scoped [V]
- **Target home(s):** `student_transport_incidents` (student_academic_enrollment_id, route_id, vehicle_id, incident_datetime) — keep
- **Composite / relation key:** `(student_academic_enrollment_id, route_id, incident_datetime)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id`; add `academic_year_id`/`academic_year_term_id` backfilled from `incident_datetime`; keep `incident_type`, `description`, `action_taken`, `escalated`/`escalated_to`/`escalated_at`, `reported_by`; append-only history. [V]

### 8. `student_transport_notes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `student_id` not stream-scoped; resolution flags on the same row are a lifecycle transition, not a rewrite [V]
- **Target home(s):** `student_transport_notes` — keep
- **Composite / relation key:** `(student_academic_enrollment_id, note_type, created_at)`
- **Migration rule:** `student_id`→`student_academic_enrollment_id`; keep `note_type`, `visibility`, `priority`, `note`; resolution (`resolved`/`resolved_by`/`resolved_at`) is a status transition on the same row, never a content rewrite; declare `created_by`/`resolved_by`→users FKs; add year context only if note history must be replayable per year [I]. [V]

### 9. `student_transport_payments`
- **Disposition:** MERGE
- **Normalization fault(s):** standalone per-student payment fact duplicating the finance bill-payment concept; `route_id` FK undeclared; `reversed` is the only correction path [V]
- **Target home(s):** `transport_bill_payments` — §4.7 (finance file reconcile)
- **Composite / relation key:** `(student_academic_enrollment_id, month, year)` on `transport_bill_payments` (linked to a `payment_id` cash fact)
- **Migration rule:** Re-home rows into `transport_bill_payments` keyed by `(student_academic_enrollment_id, month, year)`; `student_id`→`student_academic_enrollment_id`; keep `amount`, `payment_date`, `method`, `reference` (`transaction_id`), `status` incl. `reversed` (preserves history — never DELETE); declare `route_id`→transport_routes FK; reconcile with `transport_bills`/`transport_monthly_bills` on the same key. [V]

### 10. `transport_assignments`
- **Disposition:** RETIRE
- **Normalization fault(s):** bare student-route link with `(student_id, route_id)` key — no month/year, no stops, no amounts, so history cannot be replayed per period; legacy duplicate of `student_transport_assignments` [U]
- **Target home(s):** none — folded into `student_transport_assignments` (state choice; §4.7)
- **Composite / relation key:** `(student_id, route_id)` — no period dimension
- **Migration rule:** No target home of its own; fold rows whose assignment window is determinable into `student_transport_assignments` (`route_id` kept, `stop_id`/`expected_amount` = NULL + flag, month/year from context [I], undeterminable = NULL + flag); keep the old rows read-only in the one-time migration snapshot; confirm no code path reads it before retiring. [U]

### 11. `transport_routes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `current_capacity`/`max_capacity` stored (capacity counters derived from assignments); departure times embedded in the route master [V]
- **Target home(s):** `transport_routes` (id, code UNIQUE, name) — §4.7 master
- **Composite / relation key:** `id` + `UNIQUE(code)`
- **Migration rule:** Keep MASTER, never re-ID; merge duplicate route codes; `current_capacity` dropped — recomputed as a view over `student_transport_assignments`; `morning_departure`/`afternoon_departure`/`estimated_duration` move to schedules where they describe a trip [I]; keep `fee`, `distance`, `start_point`/`end_point`, `status`; ten child tables attach to this id. [V]

### 12. `transport_schedules`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `term_id` column present but FK undeclared; `vehicle_id`/`route_id`/`driver_id` FKs undeclared; no `academic_year_id` [V]
- **Target home(s):** `transport_schedules` / `route_schedules` — §4.7 (dated trip runs)
- **Composite / relation key:** `(date, route_id, vehicle_id)`
- **Migration rule:** Keep TXN; declare `term_id`→academic_year_terms and `vehicle_id`/`route_id`/`driver_id` FKs; add `academic_year_id` backfilled from `date`; keep `pickup_time`, `status`; history = append + `cancelled` status, never rewriting past runs. [V]

### 13. `transport_stops`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** definition row mixes `arrival_time`/`departure_time` (schedule facts) with the stop itself; `route_id` FK undeclared; semantics duplicated with `route_stops` [V]
- **Target home(s):** `transport_stops` (id, route_id, name, order_no) — §4.7
- **Composite / relation key:** `(route_id, order_no)`
- **Migration rule:** Keep REF as the stop-definition list — **its ids are the FK target** for `student_transport_assignments.pickup_stop_id`/`dropoff_stop_id`/`stop_id`, so never re-ID; declare `route_id`→transport_routes FK; `sequence`→`order_no`; move `arrival_time`/`departure_time` into `route_schedules` (route_stop × time × direction); absorb `route_stops` definitions via the `(route_id, name)` merge; keep one definition + one live-plan concept, not two. [V]

### 14. `transport_subscriptions`
- **Disposition:** RETIRE
- **Normalization fault(s):** dated subscription window overlaps `student_transport_assignments.(student_academic_enrollment_id, month, year)` and the monthly billing cycle — two sources of "is this student riding" truth; all FKs undeclared [U]
- **Target home(s):** none — covered by `student_transport_assignments` + monthly billing (§4.7)
- **Composite / relation key:** `(student_id, route_id, start_month)` — overlaps assignment key
- **Migration rule:** No target home of its own; the riding state is expressed by `student_transport_assignments` rows (per month/year) with status mapping active/cancelled/suspended; fold rows whose window is determinable into assignments (overlaps resolved to the earliest assignment row), undeterminable window = NULL + flag; keep old rows read-only in the migration snapshot. [U]

### 15. `transport_vehicle_routes`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** junction lacks effective-dates (only `status`), so historical vehicle-route links cannot be replayed; both FKs undeclared
- **Target home(s):** `transport_vehicle_routes` (vehicle_id, route_id, academic_year_id?, effective_from, effective_to) — §4.7
- **Composite / relation key:** `(vehicle_id, route_id, direction)`
- **Migration rule:** Keep junction; declare `vehicle_id`→transport_vehicles and `route_id`→transport_routes FKs; add `effective_from`/`effective_to` (backfill undeterminable = NULL + flag) and optional `academic_year_id`; status transitions (active→inactive) are append, never rewrites. [V]

### 16. `transport_vehicles`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `driver_id` FK undeclared; `service_due_date` mirrors the latest `vehicle_maintenance.next_maintenance_date` (stored duplicate of a maintenance fact) [V]
- **Target home(s):** `transport_vehicles` (id, reg_no UNIQUE, capacity) — §4.7 master
- **Composite / relation key:** `id` + `UNIQUE(registration_number)`
- **Migration rule:** Keep MASTER, never re-ID; declare `driver_id`→drivers FK; `service_due_date` derived from the latest `vehicle_maintenance.next_maintenance_date` (single source of truth — drop the stored duplicate); keep `type`/`model`/`make`/`year`/`capacity`, `insurance_expiry`, `status`; seven children attach to this id. [V]

### 17. `vehicle_fuel_logs`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `vehicle_id`/`filled_by` FKs undeclared; `total_cost` derivable from `liters` × `cost_per_liter` (stored derived value) [V]
- **Target home(s):** `vehicle_fuel_logs` — §4.7 keep (vehicle_id, date)
- **Composite / relation key:** `(vehicle_id, fill_date, odometer_reading)`
- **Migration rule:** Keep TXN; declare `vehicle_id`→transport_vehicles and `filled_by`→users FKs; `total_cost` recomputed (drop the stored duplicate per zero-redundancy) [I]; history is append-only — a correction is a new log + reversal note, never an UPDATE. [V]

### 18. `vehicle_maintenance`
- **Disposition:** REUSE-ALTER
- **Normalization fault(s):** `vehicle_id` FK undeclared; `next_maintenance_date`/`next_maintenance_reading` on the event duplicate the vehicle's derived service state [V]
- **Target home(s):** `vehicle_maintenance` — §4.7 keep (vehicle_id, date)
- **Composite / relation key:** `(vehicle_id, maintenance_date, odometer_reading)`
- **Migration rule:** Keep TXN; declare `vehicle_id`→transport_vehicles FK; `next_maintenance_date`/`next_maintenance_reading` feed the derived `transport_vehicles.service_due_date` (recomputed, not stored on both); keep `maintenance_type`, `cost`, `parts_replaced`, `mechanic_details`, `documents_folder`; append-only history. [V]
