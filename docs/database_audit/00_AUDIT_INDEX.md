# KingsWay Database Architecture Audit — Master Index

**Status:** DRAFT (awaiting review) · **Generated:** 2026-08-01
**Source of truth:** `database/KingsWayDatabase_2026_08_01_1409hrs.sql` (mirrors live `KingsWayAcademy`, verified structurally identical).
**Method:** 22-phase senior-architect audit per `docs/DATABASE_ARCHITECTURE_GUIDE.md`. No implementation code written. All numbers verified against the actual schema.

## Deliverables (this folder)

| File | Phase | Content |
|---|---|---|
| `09_NORMALIZED_TARGET_ARCHITECTURE.md` | 5–11 | **The "to-be" model** — the authoritative normalized target (3NF/4NF) built on the guide chain: `classes → academic_year_classes → academic_year_class_streams`, `students → student_academic_enrollments`, `learning_areas → academic_year_class_learning_areas`, `staff → academic_year_class_learning_area_teachers`. Masters → Context → Operational → History; history never overwritten |
| `10_NORMALIZATION_MAPPING/` | 13/14/16 | **Disposition mapping of ALL 431 legacy tables** onto the target — 11 domain files, each table: disposition (REUSE-ALTER / SPLIT / MERGE / RETIRE) + normalization fault + target home(s) + composite key + migration rule. **Verified 431/431, no dupes, no missing** |
| `08_PER_TABLE_BREAKDOWN/` | 3 | **Per-table analysis of ALL 431 base tables** — 11 domain files, every table individually: classification + column evidence, every FK in/out, composite history key, effects on dependents, migration treatment |
| `01_tables_inventory.md` | 1 | Complete inventory of all **431 base tables** — every column, type, null, key, default, FK, unique, index (generated from information_schema; no omissions) |
| `02_objects_inventory.md` | 2 | Complete inventory of **84 views, 150 procedures, 21 functions, 58 triggers, 10 events** |
| `03_table_classification.md` | 3/20 | Full classification of all 431 tables into MASTER/REF/ACTX/SCTX/TXN/HIST/SYS/JXN/CFG/DEP + RESTR flags + per-column methodology (the legacy "as-is" classification) |
| `04_phase4_bad_current_state.md` | 4 | Verified bad-current-state catalogue with live-DB measurements (the 6 structural defects + 7 time-bomb patterns) |

## Executive summary

1. **Verified inventory:** 431 base tables · 84 views · 150 procedures · 21 functions · 58 triggers · 10 events. (Reconciles the "515 CREATE TABLE" dump lines = 431 tables + 84 view placeholders.)

2. **The authoritative guide** (`docs/DATABASE_ARCHITECTURE_GUIDE.md`) mandates a full redesign, not a "spine already exists" refactor: master records never move between academic years; context records do; operational records attach to context; history is never overwritten. The owner's canonical chain is followed verbatim (see the spine in file 09 §2).

3. **Six structural defects** (verified):
   - `classes` carries `academic_year` + unique `uk_name_year(name, academic_year)` → duplicate `Grade 1` exists for 2026 and 2027; `setupNewYear` inserts year-spawned class rows.
   - `students.stream_id` stores a single "current stream" → 2026 context destroyed by a 2027 move (61/61 students affected).
   - `class_streams` bound to `classes` instead of year context; trigger `trg_auto_create_default_stream` compounds it.
   - `assessments` has no `academic_year_id` and its `subject_id NOT NULL` is 100% orphaned (3/3 rows point to a purged table).
   - `student_discipline` has no year/term/class context.
   - 8 real tables are misnamed `vw_*`, and `class_assignments` is conversely a VIEW that app code may treat as a table.

4. **The 2026→2040 guarantee** is achieved by: year-keyed context tables (`academic_year_classes`/`academic_year_class_streams`/`student_academic_enrollments`), parameterizing the **23 views** that currently use `year(curdate())`, closing `students.stream_id`, moving class-master/stream ownership into year context, and routing all history through append-only `audit_logs`. A 2026→2027 dry-run is the acceptance test.

5. **Time bombs flagged (not fixed silently):** 23 views bound to the DB clock; 6 backup/duplicate tables live in schema; 3 orphaned `assessments.subject_id`; `academic_years` contains junk planning rows (2031–2033) and a `2026/2027` string row; parallel empty promotion mechanisms vs `class_enrollments.promotion_*`.

6. **Normalization-fault catalogue** (in file 09 §6): stored balances on obligations/uniforms, `classes.academic_year`, `students.stream_id`, dual-key teacher assignments, mixed-context fee uniqueness, denormalized `academic_terms.year`, `subject_id` orphans — all removed in the target.

## Suggested review order

1. Read `docs/DATABASE_ARCHITECTURE_GUIDE.md` (the authoritative methodology).
2. Read `09_NORMALIZED_TARGET_ARCHITECTURE.md` (the to-be model — the map of everything).
3. Read `10_NORMALIZATION_MAPPING/` (how all 431 legacy tables land on the target).
4. Read `04_phase4_bad_current_state.md` (the problems, with evidence).
5. Approve or amend → then freeze → implementation begins (migrations + app-code pass).
