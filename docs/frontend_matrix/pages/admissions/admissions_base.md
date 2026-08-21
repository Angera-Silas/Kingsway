# admissions/admissions_base.php

- **File**: `pages/admissions/admissions_base.php`
- **Controller**: `admissions.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `Y`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `admission.getPolicy` | — | — | GET /admission/policy | Admission::getPolicy | ok |
| `admission.getStageMatrix` | — | — | GET /admission/stage-matrix | Admission::getStageMatrix | ok |
| `admission.getPlacementClasses` | — | — | GET /admission/placement-classes | Admission::getPlacementClasses | ok |
| `academic.listClasses` | `limit` | — | GET /academic/classes-list | Academic::listClasses | ok |
| `students.getParentsList` | — | — | GET /students/parents/list | Students::getParentsList | ok |
| `students.getAllAcademicYears` | — | — | GET /students/academic-year-all | Students::getAllAcademicYears | ok |
| `admission.getQueues` | — | — | GET /admission/queues | Admission::getQueues | ok |
| `admission.getApplication` | — | — | GET /admission/application/ | Admission::getApplication | ok |
| `admission.submitApplication` | — | — | POST /admission/submit-application | Admission::submitApplication | ok |
| `admission.uploadDocument` | — | — | POST /admission/upload-document | Admission::uploadDocument | ok |
| `admission.verifyDocument` | `document_id`, `status` | — | POST /admission/verify-document | Admission::verifyDocument | ok |
| `admission.scheduleInterview` | — | — | POST /admission/schedule-interview | Admission::scheduleInterview | ok |
| `admission.recordInterviewResults` | — | — | POST /admission/record-interview-results | Admission::recordInterviewResults | ok |
| `admission.generatePlacementOffer` | — | — | POST /admission/generate-placement-offer | Admission::generatePlacementOffer | ok |
| `admission.recordFeePayment` | — | — | POST /admission/record-fee-payment | Admission::recordFeePayment | ok |
| `admission.confirmEnrollment` | `application_id` | — | POST /admission/confirm-enrollment | Admission::confirmEnrollment | ok |
| `admission.completeEnrollment` | `application_id` | — | POST /admission/complete-enrollment | Admission::completeEnrollment | ok |
| `admission.getStats` | — | — | GET /admission/stats | Admission::getStats | ok |

## Backend params (expected)

| Handler | Input keys ($data/$_POST/$_GET) | Path id | Data sources (views/procs/tables) |
|---|---|---|---|
| Admission::getPolicy | — | — | — |
| Admission::getStageMatrix | — | — | — |
| Admission::getPlacementClasses | — | — | academic_year_class_streams, academic_year_classes, classes, streams, student_academic_enrollments |
| Students::getParentsList | — | — | vw_student_fee_balances, parents, persons, student_parents, vw_student_fee_balances |
| Admission::getQueues | — | — | admission_applications, admission_documents, parents, persons, workflow_instances |
| Admission::getApplication | — | `id` | admission_applications, admission_documents, media_files, parents, persons, workflow_instances |
| Admission::getStats | — | — | admission_applications, workflow_instances |

## Response shape (data keys consumed)

- `students.getAllAcademicYears -> status`
- `admission.getQueues -> Please`
- `admission.confirmEnrollment -> message`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `24` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `12`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `TEMPLATE_FRAGMENT`, `ESCAPED_LITERAL_HTML`
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
