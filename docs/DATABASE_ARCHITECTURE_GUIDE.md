# KingsWay Database Architecture Guide

> Saved verbatim from the engineering directive (2026-08-01). This guide is the authoritative
> methodology for the database architecture audit and migration of the KingsWay School Management System.
> Source of truth: `database/KingsWayDatabase_2026_08_01_1409hrs.sql` (and the live `KingsWayAcademy` DB).

ACT AS A SENIOR DATABASE ARCHITECT, DATA MODELER, DATABASE MIGRATION ENGINEER, AND SCHOOL ERP DOMAIN ARCHITECT.

You have been given the actual KingsWay School Management System MySQL database dump:

`KingsWayDatabase_2026_08_01_1409hrs.sql`

THIS DATABASE FILE IS THE SOURCE OF TRUTH.

Do not rely on assumptions, previous conversations, generic school ERP designs, or invented table names. Read and inspect the actual SQL dump thoroughly before making architectural recommendations.

IMPORTANT:
Do NOT focus primarily on security.

Security is only one part of a database/system review. The purpose of this exercise is to understand the ENTIRE database and redesign its data architecture so the school system can operate correctly across academic terms, academic years, student progression, staff changes, curriculum changes, financial periods, and historical reporting WITHOUT destroying historical data.

Do NOT declare the system complete merely because security is strong.

============================================================
PRIMARY OBJECTIVE
============================================================

Perform a complete architectural audit of the existing database and design a transition-safe, history-preserving database architecture.

The database must be capable of handling:

- Term 1 → Term 2 → Term 3
- 2026 → 2027 → 2028 → 2029
- Grade 1 → Grade 2 → Grade 3 → ... → Grade 9
- Student promotion / repetition / transfer / admission / withdrawal / exit
- Stream changes
- Teacher changes / class-teacher changes / subject(learning-area) teacher changes
- Learning-area changes
- Curriculum changes
- Assessment changes
- Fee changes
- Financial transactions
- Transport subscriptions / uniform purchases / staff payroll / school expenditure
- Communication history

WITHOUT overwriting historical records.

Example: a student who was in Grade 4 Stream A in 2026 and Grade 5 Stream B in 2027 must retain BOTH contexts. The database must NOT simply update `student.class_id = Grade 5` and destroy the student's Grade 4 history.

============================================================
CORE ARCHITECTURAL PRINCIPLE
============================================================

Separate the database into:

1. MASTER DATA
2. REFERENCE DATA
3. ACADEMIC CONTEXT DATA
4. STUDENT CONTEXT DATA
5. TRANSACTIONAL DATA
6. HISTORY/AUDIT DATA
7. SYSTEM DATA
8. JUNCTION/RELATIONSHIP DATA
9. CONFIGURATION DATA

Fundamental principle:

    MASTER DATA → CONTEXT DATA → TRANSACTIONAL DATA → HISTORY / REPORTING

Master entities remain stable. Context entities describe the state/relationship of master entities within a particular academic year, term, class, stream, learning area, assignment, etc. Transactions reference the correct historical context. Historical information is never overwritten.

============================================================
TARGET MODEL (conceptual, adapt to the actual schema)
============================================================

    academic_years → academic_year_terms
    classes → academic_year_classes → academic_year_class_streams
    students → student_academic_enrollments
    learning_areas → academic_year_class_learning_areas
    staff → academic_year_class_learning_area_teachers

Then academic operations reference these contextual entities:

    academic_year_class_learning_area
      → scheme_of_work → lesson_plan → assignment → assessment → rubric → student_result → student_portfolio

The actual architecture must be derived from the existing database and must reuse/refactor existing canonical tables wherever possible. DO NOT create duplicate tables when an existing canonical table can be refactored.

============================================================
PHASES
============================================================

- PHASE 1 — COMPLETE DATABASE INVENTORY (every table: name, purpose, all columns, types, nullability, defaults, AUTO_INCREMENT, PK, FKs, unique constraints, indexes, checks, generated columns, comments)
- PHASE 2 — COMPLETE DATABASE OBJECT INVENTORY (every VIEW, PROCEDURE, FUNCTION, TRIGGER, EVENT)
- PHASE 3 — CLASSIFY EVERY TABLE (MASTER / REFERENCE / ACADEMIC CONTEXT / STUDENT CONTEXT / TRANSACTIONAL / HISTORY/AUDIT / SYSTEM / JUNCTION / CONFIGURATION / DEPRECATED/DUPLICATE / REQUIRES RESTRUCTURING)
- PHASE 4 — IDENTIFY BAD CURRENT-STATE DESIGN (`student.class_id`, `staff.class_id`, `class.academic_year_id`, result/attendance without context, etc.) → CURRENT TABLE | COLUMN | CURRENT PURPOSE | PROBLEM | TARGET CONTEXT
- PHASE 5 — DESIGN MASTER TABLES (KEEP / KEEP+ALTER / MERGE / SPLIT / RENAME / DEPRECATE / REPLACE)
- PHASE 6 — DESIGN ACADEMIC CONTEXT (year / term / class-in-year / stream-in-year / enrollment / learning-areas-offered / teacher assignment)
- PHASE 7 — STUDENT TRANSITION MODEL (new / promotion / repetition / transfer in-out / withdrawal / exit / graduation / stream change / class change) with relational examples
- PHASE 8 — CURRICULUM / LEARNING AREA MODEL (curriculum master not duplicated per year; school implementation/context can change)
- PHASE 9 — ACADEMIC OPERATIONS (schemes, lesson plans, timetable, assignments, submissions, assessments, marks, rubrics, competencies, results, report cards, portfolios, attendance → what context does each record belong to?)
- PHASE 10 — FINANCE (separate STUDENT/ACADEMIC-CONTEXT finance from SCHOOL-WIDE financial transactions; do not attach school electricity bills to student enrollment)
- PHASE 11 — COMMUNICATION (preserve historical class/stream-targeted communication)
- PHASE 12 — DEPENDENCY ANALYSIS (table → views/procedures/functions/triggers/events/tables/application code; state when app deps cannot be verified from the SQL dump)
- PHASE 13 — EXACT CHANGE PLAN (per table: classification, action, columns/FKs/unique/indexes added/removed/changed)
- PHASE 14 — DO NOT DUPLICATE EXISTING CANONICAL TABLES (for every new table: why existing tables cannot support it)
- PHASE 15 — MIGRATION PLAN (15 safe stages: backup → new context structures → backfill → migrate relationships → FKs → unique/indexes → views → procedures → functions → triggers → events → remove obsolete → integrity → application/API → historical transition testing)
- PHASE 16 — DATA BACKFILL (exact mapping; identify data gaps; DO NOT INVENT VALUES)
- PHASE 17 — CONSTRAINTS AND INDEXES (composite business uniqueness like year+class, year-class+stream, year-class+learning-area, student+year; surrogate PKs + composite UNIQUEs)
- PHASE 18 — VIEWS, PROCEDURES, FUNCTIONS, TRIGGERS, EVENTS migration matrix (NO CHANGE / MINOR / MAJOR REWRITE / RECREATE / DEPRECATE)
- PHASE 19 — FINAL ERD (Mermaid, with PK/FK relationships)
- PHASE 20 — FINAL TABLE CLASSIFICATION MATRIX (every table: # | Table | Classification | Current Role | Action | Context Required? | Main Dependencies | Priority)
- PHASE 21 — FINAL DATABASE OBJECT MATRIX (views/procedures/functions/triggers/events)
- PHASE 22 — FINAL MIGRATION ROADMAP (numbered steps; no implementation code yet)

============================================================
STRICT RULES
============================================================

1. THE SQL FILE IS THE SOURCE OF TRUTH.
2. READ THE ACTUAL SQL FILE.
3. DO NOT INVENT TABLES.
4. DO NOT INVENT COLUMNS.
5. DO NOT INVENT RELATIONSHIPS.
6. DO NOT CLAIM SOMETHING EXISTS UNLESS IT EXISTS IN THE SQL.
7. DO NOT OMIT TABLES.
8. DO NOT ONLY DISCUSS SECURITY.
9. DO NOT DECLARE THE SYSTEM COMPLETE.
10. DO NOT CREATE DUPLICATE TABLES WHEN AN EXISTING CANONICAL TABLE CAN BE REFACTORED.
11. DO NOT destroy historical records.
12. Do not overwrite a student's previous class/stream/year.
13. Do not assume the current class is the only class a student has ever belonged to.
14. Do not assume the current teacher assignment represents historical assignments.
15. Do not assume the current learning-area assignment represents historical assignments.
16. Do not assume the current academic year is the only academic year.
17. Do not silently "fix" data gaps. Identify them.
18. Distinguish VERIFIED FACT from INFERENCE.
19. If information cannot be determined from the SQL dump, explicitly state that.
20. Do not generate implementation code until the architecture review is complete.

============================================================
MOST IMPORTANT FINAL REQUIREMENT
============================================================

The goal is NOT merely to make the database "look normalized." The goal is to make the actual KingsWay School Management System capable of operating continuously for many academic years without losing historical context, preserving historical records across TERM→TERM, YEAR→YEAR, CLASS→CLASS, STREAM→STREAM, TEACHER→TEACHER, LEARNING AREA→LEARNING AREA.

The output must be an engineering-grade database architecture audit and migration blueprint based on the ACTUAL SQL FILE, not a generic school ERP explanation.
