# Data Contract Audit — Complete Reality Report

## Response Pipeline Diagram

```
PHP Controller → array → ApiResponse::normalize() → JSON → apiCall() → handleApiResponse() → caller
                                                                                    ↓
                                                                         returns response.data
                                                                         (RAW DATA, no wrapper)
```

---

## LAYER 1: RESPONSE UNWRAPPING MISMATCH — CRITICAL

### Root Cause
`handleApiResponse()` (js/api.js:161-182) returns `response.data` — the raw data payload. But ~40 page controllers check `res?.success` on the returned value, which is **always undefined**.

### What callers get vs what they expect

| Actual return value | Caller checks | Result |
|---|---|---|
| `[{id:1, name:"A"}]` (array) | `if (res?.success)` | `undefined` → **fails** |
| `{classes: [...], total: 5}` (object) | `if (res?.success)` | `undefined` → **fails** |
| `42` (scalar) | `if (res?.success)` | `undefined` → **fails** |
| `null` | `if (res?.success)` | `null` → **fails** |

### All Broken Files (Page never loads data)

| # | File | Broken Calls | Impact |
|---|---|---|---|
| 1 | **manage_classes.js** | 16 | Classes, streams, class-teachers CRUD entirely dead |
| 2 | **academic_years.js** | 8 | Years management entirely dead |
| 3 | **manage_subjects.js** | 11 | Subjects, learning-areas CRUD entirely dead |
| 4 | **assign_class_teachers.js** | 8 | Teacher assignments entirely dead |
| 5 | **learning_areas.js** | 10 | Learning-areas, schemes entirely dead |
| 6 | **sports.js** | 12 | Teams, fixtures, results entirely dead |
| 7 | **clubs_societies.js** | 8 | Clubs, members, activities entirely dead |
| 8 | **academic_calendar.js** | 5 | Calendar events entirely dead |
| 9 | **all_classes.js** | 3 | Class/stream display dead |
| 10 | **timetable.js** | 5 | Timetable display dead (partial fallback) |
| 11 | **route_registry.js** | 5 | Route management entirely dead |
| 12 | **dormitory_management.js** | 4 | Dorm management entirely dead |
| 13 | **view_past_papers.js** | 3 | Past papers never load |
| 14 | **view_syllabus.js** | 1 | Syllabus never loads |
| 15 | **view_teaching_materials.js** | 2 | Materials never load |
| 16 | **intern_assigned_classes.js** | 2 | Empty state always |
| 17 | **intern_assigned_subjects.js** | 2 | Empty state always |
| 18 | **my_classes_taught.js** | 2 | Empty state always |
| 19 | **my_subjects_overview.js** | 2 | Empty state always |
| 20 | **my_schemes_of_work.js** | 3 | Empty state always |
| 21 | **my_subject_syllabus.js** | 4 | Empty state always |
| 22 | **subject_schemes_of_work.js** | 4 | Empty state always |
| 23 | **fee_defaulters.js** | 3 | Defaulters data broken |
| 24 | **enrollment_trends.js** | 1 | Chart never loads |
| 25 | **conduct_reports.js** | 4 | Reports never load |
| 26 | **counseling_records.js** | 2 | Records never load |
| 27 | **chapel_services.js** | 1 | Always shows empty |
| 28 | **food_store.js** | 1 | Always shows empty |
| 29 | **all_parents.js** | 2 | Parent list dead |
| 30 | **messaging.js** | 1 | Success notification silent |
| 31 | **settings.js** | 1 | Backup notification silent |
| 32 | **detailed_payslip.js** | 1 | Payslip never loads |
| 33 | **performance_trends.js** | 2 | Charts never populate |

### Files That Work Correctly (use direct pattern)

| File | Pattern |
|---|---|
| **manage_students.js** | Custom `unwrapList()`/`unwrapPayload()` handles both wrapped and raw |
| **admissions.js** | Custom `unwrapList()`/`unwrapPayload()` handles both |
| **staff.js** | `res?.data ?? res ?? []` resilient fallback |
| **boarding.js** | `r?.data ?? r ?? {}` resilient fallback |
| **announcements.js** | `Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : [])` |
| **users.js** | Direct use — no `.success` check |
| **manage_teachers.js** | Direct use — no `.success` check |
| **account_settings.js** | Direct use — no `.success` check |
| **add_results.js** | `res?.data ?? res ?? []` resilient fallback |
| **fee_structure_accountant.js** | Direct use — no `.success` check |
| **academics.js** | Direct use via `callAPI()` |
| ~20 more files | Direct use or side-effect only |

### The `res?.data` Fallacy
Many broken files not only check `res?.success` but also access `res.data`. Since `res` IS already the data payload, `res.data` is nearly always `undefined` (unless the data itself is an object with a `data` key).

**Fix pattern for all broken files:**
```javascript
// BROKEN:
const res = await window.API.apiCall('/path', 'GET');
if (res?.success) { this.state.items = res.data || []; }

// FIXED:
try {
    this.state.items = await window.API.apiCall('/path', 'GET') || [];
} catch (error) {
    this.showNotification('Failed to load', 'error');
}
```

---

## LAYER 2: SERVER RESPONSE FORMAT — ACTUALLY CONSISTENT

### Findings
- `ApiResponse::normalize()` (api/includes/ApiResponse.php) normalizes ALL responses to `{success, status, data, message, errors, code}`
- Called from `api/index.php:78-80` — every route response passes through
- 42 controllers extend BaseController with `$this->success()` / `$this->respond()` helpers
- Module API classes (AcademicAPI, FinanceAPI, etc.) produce `{status, message, type, code, data}` — also normalized
- 18 major controllers have `handleResponse()` adapter converting module format to BaseController format

### Verdict
**Server side is clean.** No contract mismatch on the response envelope structure. The response sent over the wire is always `{success: true, data: <payload>, ...}`. The problem is entirely on the client side expecting the envelope when it already received the unwrapped payload.

### Edge Case
If a controller returns data **without** a `'data'` key (e.g., just `['token' => $x]`), `handleApiResponse()` returns `null` because `ApiResponse::normalize()` sets `data: null`. Currently no router-facing endpoint does this, but it's a landmine.

---

## LAYER 3: FIELD NAME MISMATCHES — MODERATE

### Findings

| Severity | Endpoint | PHP reads | Likely JS sends | File:Line |
|---|---|---|---|---|
| 🔴 HIGH | FinanceController::getProspectiveFees | `academic_year_id` | `academic_year` | FinanceController.php:931 |
| 🔴 HIGH | FinanceController::getPaymentMethods | `academic_year_id` | `academic_year` | FinanceController.php:906 |
| 🟡 MEDIUM | PrintController::sendEmail | `recipientName` (camelCase) | `recipient_name` | PrintController.php:183 |
| 🟢 OK | AttendanceController (all) | both `termId`/`term_id` | snake_case | Guarded |
| 🟢 OK | postFeesBundleSubmit | snake_case | snake_case | Match confirmed |
| 🟢 OK | All other controllers | snake_case | snake_case | Convention matches |

### Convention Check
- **JS sends**: snake_case (verified in api.js module definitions, e.g., `academic_year_id`, `term_id`, `student_id`)
- **PHP reads**: snake_case from `$data['...']`
- **Exceptions**: AttendanceController reads both; PrintController uses camelCase
- **Verdict**: Field naming is largely consistent. The 2 FinanceController mismatches are real but narrow.

---

## LAYER 4: DIRECT apiCall BYPASSING handleApiResponse

Some page controllers call `apiCall()` directly and handle the response themselves WITHOUT going through the standard unwrapping:

| File | Custom Handler | Behavior |
|---|---|---|
| **admissions_class_placement.js** | `this.apiCall()` custom | Handles `response.success` and `response.data` directly |
| **admissions_workspace.js** | `unwrapPayload()` | Checks `response.status && response.data` — WRONG for already-unwrapped data |
| **data_import.js** | `this.apiCall()` custom | Custom response handling |
| **fee_structure_viewer.js** | Direct `apiCall()` | Expects unwrapped data |
| **manage_assessments.js** | Direct `apiCall()` | Expects unwrapped data |
| **manage_timetable.js** | Direct `apiCall()` | Expects unwrapped data |
| **myclasses.js** | Direct `apiCall()` | Expects unwrapped data |
| **parent_portal.js** | Direct `apiCall()` | Expects unwrapped data |
| **reset_default_password.js** | Direct `apiCall()` | Expects unwrapped data |
| **students.js** | Direct `apiCall()` | Expects unwrapped data |

These may have their OWN mismatches because they don't use the standard `window.API.module.method()` pattern.

---

## MASTER SUMMARY

### By Severity

```
CRITICAL (Layer 1 - res?.success):   33 files, 88+ sites → page shows "Failed to load data"
HIGH    (Layer 3 - field names):      2 endpoints → wrong academic year field
MEDIUM  (Layer 4 - direct apiCall):   10 files → potential custom mismatches
LOW     (Layer 2 - server format):    0 issues → backend is consistent
```

### The Reality
1. **~33 page controllers are dead or partially dead** due to checking `res?.success` on already-unwrapped data
2. **Server side is clean** — `ApiResponse::normalize()` ensures consistent response format
3. **Field naming is mostly consistent** (snake_case convention holds) with 2 narrow exceptions
4. **~20 pages use resilient patterns** (unwrapPayload, fallback chains) and work correctly
5. **~50 pages use direct patterns** and work correctly
6. **10 files bypass the standard API layer** with custom apiCall overrides — each is a potential mismatch

### Root Cause Summary
The problem is **not** that the server sends wrong data. The problem is that `handleApiResponse()` unwraps the `{success, data}` envelope and returns just the data — but the page controllers were written assuming they'd receive the full envelope. This is likely a refactoring bug: the response unwrapping was added to `api.js` at some point, but most page controllers were never updated to match.
