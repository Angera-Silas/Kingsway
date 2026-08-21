# assessment_overview.php

- **File**: `pages/assessment_overview.php`
- **Controller**: `assessment_overview.js`
- **Roles**: `System Administrator`
- **Sidebar item(s)**: `100116`

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
| `GET /academic/assessment-types` | — | — |
| `GET /academic/learning-areas-list` | — | — |
| `GET /academic/strands?learning_area_id=` | — | — |
| `GET /academic/assessments-list?` | — | — |
| `GET /academic/learning-outcomes?learning_area_id=` | — | — |
| `POST /academic/formative-assessments` | `assessment_type_id`, `class_id`, `subject_id`, `strand_id`, `assessment_date`, `max_marks`, `term_id`, `learning_outcome_id` | — |
| `GET /academic/class-students?class_id=` | — | — |
| `GET /academic/formative-assessment-marks?assessment_id=` | — | — |
| `POST /academic/formative-assessment-marks` | `assessment_id` | — |
| `POST /academic/compute-term-scores` | `assessment_id` | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Academic::getTermsList | — | — | academic_year_classes, academic_year_terms, academic_years, terms |
| Academic::getAssessmentTypes | `filter` | — | assessment_types |
| Academic::getLearningAreasList | — | — | learning_areas |
| Academic::getStrands | `learning_area_id` | — | learning_areas, strands |
| Academic::getAssessmentsList | `class_id`, `term_id`, `subject_id`, `status`, `assessment_type_id` | — | academic_year_class_streams, academic_year_classes, academic_year_terms, assessment_types, assessments, classes, formative_scores, learning_areas, streams, student_academic_enrollments, terms |
| Academic::getLearningOutcomes | — | `id` | learning_areas, learning_outcomes, strands, sub_strands |
| Academic::postFormativeAssessments | `title`, `name`, `assessment_type_id`, `type`, `term_id`, `class_id`, `status`, `subject_id`, `max_marks`, `assessment_date`, `cat_date` | — | academic_year_terms, academic_years, assessments, staff, users |
| Academic::getClassStudents | `class_id` | `id` | persons, student_academic_enrollments, students |
| Academic::getFormativeAssessmentMarks | `assessment_id` | `id` | academic_year_class_streams, academic_year_classes, assessments, classes, formative_scores, persons, student_academic_enrollments, students |
| Academic::postFormativeAssessmentMarks | `assessment_id`, `marks`, `scores` | `id` | assessments, formative_scores, score |
| Academic::postComputeTermScores | `class_id`, `term_id`, `subject_id`, `assessment_id` | — | assessment_results, assessment_types, assessments, formative_scores, formative_total, student_academic_enrollments, term_subject_scores |

## Response shape (data keys consumed)

_(no post-await `.prop` consumption detected)_

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `24` (with interpolation: `3`)
- `escapeHtml()` calls: `20` — XSS check: `PASS`
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
