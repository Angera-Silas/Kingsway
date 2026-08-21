# staff_onboarding.php

- **File**: `pages/staff_onboarding.php`
- **Controller**: `staff_onboarding.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `Y`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

_(no `API.<module>.<method>` calls detected)_

## Direct `callAPI` endpoints

| Endpoint | Payload keys (sent) | Flags |
|---|---|---|
| `GET /staff/onboarding` | — | — |
| `GET /staff` | — | — |
| `GET /staff/onboarding/` | — | — |
| `GET /staff/onboarding-task/` | `status` | — |
| `POST /staff/onboarding` | `staff_id`, `start_date`, `probation_months` | — |
| `POST /staff/onboarding-document` | `onboarding_id` | — |
| `POST /staff/probation-review` | `onboarding_id` | — |
| `GET /staff/onboarding-pending` | — | — |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Staff::getOnboarding | — | `id` | vw_onboarding_dashboard, onboarding_documents, persons, staff, staff_probation_reviews, vw_onboarding_dashboard, staff_department_assignments |
| Staff::get | — | `id` | staff_experience, staff_qualifications, departments, persons, roles, staff, staff_categories, staff_department_assignments, staff_payroll_profiles, staff_types, user_roles, users |
| Staff::getStaff | — | `id` | — |
| Staff::postOnboarding | `initiated_by`, `staff_id`, `start_date`, `onboarding_start_date`, `probation_months`, `target_completion`, `expected_end_date`, `mentor_id`, `contract_type`, `notes`, `remarks` | — | sp_auto_generate_onboarding_tasks, active, departments, persons, staff, staff_categories, staff_contracts, staff_department_assignments, staff_types, workflow_instances |
| Staff::postOnboardingDocument | `verified_by`, `onboarding_id`, `staff_id`, `document_type`, `document_name`, `is_original_seen`, `is_copy_filed`, `notes` | — | onboarding_documents, onboarding_tasks |
| Staff::postProbationReview | `reviewer_id`, `onboarding_id`, `staff_id`, `review_month`, `review_date`, `overall_rating`, `attendance_score`, `performance_score`, `conduct_score`, `strengths`, `areas_to_improve`, `outcome`, `outcome_notes`, `next_review_date`, `extend_months` | — | staff, staff_contracts, staff_probation_reviews |
| Staff::getOnboardingPending | — | — | vw_onboarding_pending_by_role, vw_onboarding_pending_by_role |

## Response shape (data keys consumed)

- `direct.callAPI -> onboardings`
- `direct.callAPI -> map`
- `direct.callAPI -> tasks_created`
- `direct.callAPI -> is_overdue`
- `direct.callAPI -> length`
- `direct.callAPI -> filter`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `13` (with interpolation: `3`)
- `escapeHtml()` calls: `15` — XSS check: `PASS`
- Bootstrap modal usage: `3`
- Payload/backend param match: `NA`
- Fix flags: none
- Info flags: none
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ✅ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
