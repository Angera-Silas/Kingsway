# formative_assessments.php

- **File**: `pages/formative_assessments.php`
- **Controller**: `formative_assessments.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /academic/terms-list` | — | — |
| `GET /academic/learning-areas-list` | — | — |
| `GET /academic/assessment-types?filter=formative` | — | — |
| `GET /academic/learning-outcomes?learning_area_id=` | — | — |
| `GET /academic/strands?learning_area_id=` | — | — |
| `GET /academic/formative-assessments` | — | — |
| `POST /academic/formative-assessments` | — | — |
| `GET /academic/formative-assessments?id=` | `max_marks` | — |
| `GET /academic/formative-assessment-marks?assessment_id=` | — | — |
| `POST /academic/formative-assessment-marks` | `assessment_id` | — |
| `GET /academic/formative-summary` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getLearningAreasList | — | — | learning_areas |
| Academic::getAssessmentTypes | `filter` | — | assessment_types |
| Academic::getLearningOutcomes | — | `id` | learning_areas, learning_outcomes, strands, sub_strands |
| Academic::getStrands | `learning_area_id` | — | learning_areas, strands |
| Academic::getFormativeAssessments | `class_id`, `subject_id`, `term_id`, `type_id`, `year_id`, `teacher_only`, `subject_teacher_only` | — | academic_year_class_learning_area_teachers, academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_results, assessment_types, assessments, classes, learning_areas, persons, staff, terms |
| Academic::postFormativeAssessments | `title`, `name`, `assessment_type_id`, `type`, `term_id`, `class_id`, `status`, `subject_id`, `max_marks`, `assessment_date`, `cat_date` | — | academic_year_terms, academic_years, assessments, staff, users |
| Academic::getFormativeAssessmentMarks | `assessment_id` | `id` | academic_year_class_streams, academic_year_classes, assessments, classes, formative_scores, persons, student_academic_enrollments, students |
| Academic::postFormativeAssessmentMarks | `assessment_id`, `marks`, `scores` | `id` | assessments, formative_scores, score |
| Academic::getFormativeSummary | `class_id`, `subject_id`, `term_id`, `strand_id`, `sub_strand_id`, `group_by` | — | assessment_types, assessments, formative_scores, learning_areas, persons, strands, student_academic_enrollments, students, sub_strands |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `31` (with interpolation: `8`)
- `escapeHtml()` calls: `24` — XSS check: `PASS`
- Bootstrap modal usage: `2`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ❌ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ⚠️ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
