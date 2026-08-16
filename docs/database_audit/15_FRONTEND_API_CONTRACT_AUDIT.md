# 15. Frontend ↔ Backend API Contract Audit

> Scope: `js/api.js` (`window.API`) vs the active backend router convention (`api/router/ControllerRouter.php` via `Router.php`).

## 1. Headline numbers

| Metric | Value |
|---|---|
| API modules in `js/api.js` | 29 |

| `apiCall()` references in `js/api.js` | 1217 |

| Unique (HTTP verb, path) pairs | 1152 |

| Resolve to a backend handler under router convention | 1042 |
| Do NOT resolve (404 at runtime) | 110 |

| Backend controller public methods | 1684 |

| Handler-shaped backend methods (`get*`/`post*`/`put*`/`delete*`/`index`) | 1642 |

| Backend handlers with NO `api.js` reference resolving to them | 877 |

| Duplicate (verb,path) references inside `js/api.js` | 53 |

## 2. api.js endpoints that DO NOT resolve to a backend handler

The active router maps `VERB /ctrl/resource(/id)` → `verb+ResourceCamel` on the controller. The following `api.js` calls have no matching handler under that convention, so they 404 at runtime.

### 2.1 naming_mismatch — 61

_Naming mismatch (handler exists under a different resource name — frontend path 404s)_

| Verb | api.js path | Closest backend handler | api.js line(s) |
|---|---|---|---|
| POST | `/academic/library-start-workflow` | `postYearTransitionStartWorkflow` | 3183 |
| GET | `/academic/terms/get/${id}` | `getYearsGet` | 3254 |
| GET | `/activities/resources/activity/${activityId}` | `getResourcesByActivity` | 3596 |
| GET | `/activities/sports/dashboard-stats` | `getStats` | 3669 |
| POST | `/activities/sports/fixtures/record-result/${id}` | `postCompetitionRecordParticipation` | 3653 |
| GET | `/activities/sports/player-stats` | `getStats` | 3657 |
| GET | `/activities/sports/player-stats/career/${playerId}` | `getStats` | 3659 |
| POST | `/activities/sports/team-members` | `postCompetitionPrepareTeam` | 3637 |
| GET | `/activities/sports/upcoming-fixtures` | `getUpcomingList` | 3677 |
| POST | `/activities/workflow/competition/start` | `postCompetitionReportResults` | 3614 |
| POST | `/activities/workflow/evaluation/start` | `postEvaluationVerifyAssessment` | 3616 |
| POST | `/activities/workflow/planning/start` | `postPlanningSchedule` | 3612 |
| POST | `/activities/workflow/registration/start` | `postRegistrationReview` | 3610 |
| GET | `/attendance/my-class` | `getStudentsByClass` | 5713 |
| PUT | `/boarding/exeats/approve/${id}` | `putExeats` | 4911 |
| PUT | `/boarding/exeats/reject/${id}` | `putExeats` | 4913 |
| GET | `/boarding/roll-call/history?dormitory_id=${dormId}` | `getRollCall` | 4899 |
| GET | `/dashboard/school-admin/staff-directory${params}` | `getSchoolAdminStaffDirectory` | 5903 |
| GET | `/dashboard/subject-teacher/assessments` | `getSubjectTeacherPendingAssessments` | 6099 |
| GET | `/dashboard/subject-teacher/students` | `getSubjectTeacherSections` | 6106 |
| GET | `/dashboard/system-admin/api-load` | `getSystemAdminAPILoad` | 5697 |
| GET | `/finance/monthly-report` | `getClassBillingReport` | 5721 |
| GET | `/finance/payroll-status` | `getStudentsPaymentStatus` | 5723 |
| GET | `/inventory/low-stock-alerts` | `getUniformLowStock` | 5727 |
| GET | `/inventory/requisitions-pending` | `getRequisitionsList` | 5729 |
| GET | `/inventory/stock-status` | `getUniformLowStock` | 5725 |
| GET | `/schedules/analytics` | `getScheduleAnalytics` | 5033 |
| GET | `/staff/payroll-download-detailed-payslip?staff_id=${staffId}&month=${month}&year=${year}` | `getPayrollDownloadPayslip` | 4600 |
| POST | `/students/attendance-mark` | `postTransportAttendance` | 3005 |
| GET | `/students/documents-get` | `getStatisticsGet` | 2977 |
| GET | `/students/documents-get/${id}` | `getStatisticsGet` | 2976 |
| POST | `/students/documents-upload` | `postPhotoUpload` | 2979 |
| GET | `/students/family-groups/list` | `getFamilyGroupsV2` | 3064 |
| GET | `/students/family-groups/search?q=${encodeURIComponent(
          searchTerm,
        )}&limit=${limit}&offset=${offset}` | `getFamilyGroupsV2` | 3066 |
| GET | `/students/family-groups/stats` | `getFamilyGroupsV2` | 3073 |
| GET | `/students/family-groups/view` | `getFamilyGroupsV2` | 3075 |
| POST | `/students/import-add-existing` | `postParentsAdd` | 3011 |
| POST | `/students/import-add-multiple` | `postPromotionMultipleClasses` | 3013 |
| POST | `/students/medical-add` | `postParentsAdd` | 2948 |
| PUT | `/students/medical-update/${medicalId}` | `putParentsUpdate` | 2953 |
| GET | `/system/policies` | `getPermissionPolicies` | 5519 |
| POST | `/system/policies` | `postPermissionPolicies` | 5520 |
| PUT | `/system/policies` | `putPermissionPolicies` | 5522 |
| DELETE | `/system/policies` | `deletePermissionPolicies` | 5523 |
| GET | `/transport/driver` | `getTransportDriver` | 4796 |
| POST | `/transport/driver` | `postTransportDriver` | 4799 |
| GET | `/transport/driver/${id}` | `getTransportDriver` | 4795 |
| PUT | `/transport/driver/${id}` | `putTransportDriver` | 4801 |
| DELETE | `/transport/driver/${id}` | `deleteTransportDriver` | 4802 |
| GET | `/transport/route` | `getTransportRoute` | 4766 |
| POST | `/transport/route` | `postTransportRoute` | 4769 |
| GET | `/transport/route/${id}` | `getTransportRoute` | 4765 |
| PUT | `/transport/route/${id}` | `putTransportRoute` | 4771 |
| DELETE | `/transport/route/${id}` | `deleteTransportRoute` | 4772 |
| GET | `/transport/stop` | `getTransportStop` | 4778 |
| POST | `/transport/stop` | `postTransportStop` | 4781 |
| GET | `/transport/stop/${id}` | `getTransportStop` | 4777 |
| PUT | `/transport/stop/${id}` | `putTransportStop` | 4783 |
| DELETE | `/transport/stop/${id}` | `deleteTransportStop` | 4784 |
| GET | `/transport/vehicle` | `getTransportVehicle` | 4790 |
| GET | `/transport/vehicle/${id}` | `getTransportVehicle` | 4789 |

### 2.2 wrong_verb_or_similar — 6

_Wrong verb or only loosely-similar handler_

| Verb | api.js path | Closest backend handler | api.js line(s) |
|---|---|---|---|
| GET | `/activities/sports/recent-results` | `postEvaluationPublishResults` | 3679 |
| GET | `/activities/sports/team-members` | `postCompetitionPrepareTeam` | 3635 |
| DELETE | `/activities/sports/team-members/${id}` | `postCompetitionPrepareTeam` | 3639 |
| GET | `/activities/workflow/status/${workflowId}` | `putResourcesUpdateStatus` | 3618 |
| GET | `/payments/fee-status` | `postMpesaTransactionStatus` | 5719 |
| DELETE | `/students/documents-delete/${documentId}` | `postParentsDelete` | 2987 |

### 2.3 no_backend_handler — 42

_No backend handler exists (feature backend not implemented / route never built)_

| Verb | api.js path | Closest backend handler | api.js line(s) |
|---|---|---|---|
| GET | `/activities/sports/fixtures` | `—` | 3643 |
| POST | `/activities/sports/fixtures` | `—` | 3647 |
| GET | `/activities/sports/fixtures/${id}` | `—` | 3645 |
| PUT | `/activities/sports/fixtures/${id}` | `—` | 3649 |
| DELETE | `/activities/sports/fixtures/${id}` | `—` | 3651 |
| GET | `/activities/sports/head-to-head` | `—` | 3675 |
| GET | `/activities/sports/season-summary` | `—` | 3671 |
| GET | `/activities/sports/standings` | `—` | 3663 |
| POST | `/activities/sports/standings` | `—` | 3665 |
| GET | `/activities/sports/teams` | `—` | 3623 |
| POST | `/activities/sports/teams` | `—` | 3627 |
| GET | `/activities/sports/teams/${id}` | `—` | 3625 |
| PUT | `/activities/sports/teams/${id}` | `—` | 3629 |
| DELETE | `/activities/sports/teams/${id}` | `—` | 3631 |
| GET | `/activities/sports/top-scorers` | `—` | 3673 |
| GET | `/boarding/beds` | `—` | 4888 |
| POST | `/boarding/beds/assign` | `—` | 4889 |
| PUT | `/boarding/beds/unassign/${bedId}` | `—` | 4891 |
| GET | `/boarding/beds?dormitory_id=${dormId}` | `—` | 4887 |
| GET | `/boarding/chapel-services` | `—` | 4933 |
| POST | `/boarding/chapel-services` | `—` | 4935 |
| PUT | `/boarding/chapel-services/${id}` | `—` | 4937 |
| GET | `/boarding/food-store` | `—` | 4916 |
| POST | `/boarding/food-store` | `—` | 4917 |
| PUT | `/boarding/food-store/${id}` | `—` | 4919 |
| POST | `/boarding/food-store/consume` | `—` | 4921 |
| GET | `/boarding/menus` | `—` | 4925 |
| POST | `/boarding/menus` | `—` | 4926 |
| PUT | `/boarding/menus/${id}` | `—` | 4928 |
| DELETE | `/boarding/menus/${id}` | `—` | 4929 |
| GET | `/schedules/my-lessons` | `—` | 5717 |
| POST | `/students/import-existing` | `—` | 3009 |
| GET | `/students/import-template` | `—` | 3015 |
| GET | `/students/stats` | `—` | 5700 |
| GET | `/system/dashboards` | `—` | 5501 |
| POST | `/system/dashboards` | `—` | 5503 |
| PUT | `/system/dashboards` | `—` | 5505 |
| DELETE | `/system/dashboards` | `—` | 5507 |
| GET | `/system/widgets` | `—` | 5511 |
| POST | `/system/widgets` | `—` | 5512 |
| PUT | `/system/widgets` | `—` | 5514 |
| DELETE | `/system/widgets` | `—` | 5515 |

### 2.4 controller_missing — 1

_Controller does not exist_

| Verb | api.js path | Closest backend handler | api.js line(s) |
|---|---|---|---|
| GET | `/assessments/my-results` | `—` | 5715 |

## 3. Redundancy — same (verb,path) referenced from multiple places in api.js

| Verb | Path | api.js lines |
|---|---|---|
| DELETE | `/inventory/inventory/${id}` | 4205, 4442 |
| GET | `/academic/learning-areas/list` | 3293, 3295 |
| GET | `/academic/teachers-list` | 3392, 3394 |
| GET | `/academic/years/current` | 3237, 3239 |
| GET | `/academic/years/get/${id}` | 3230, 3242 |
| GET | `/academic/years/list` | 3229, 3243, 3244 |
| GET | `/accounts/bank-accounts` | 5378, 5793 |
| GET | `/attendance` | 3530, 3538 |
| GET | `/communications/communication` | 3879, 3956, 3960 |
| GET | `/communications/log` | 3917, 5638 |
| GET | `/communications/template` | 3934, 3981, 5639 |
| GET | `/counseling/session` | 3692, 3701 |
| GET | `/finance` | 3991, 4182, 4185, 4188 |
| GET | `/finance/department-budgets-proposals` | 4000, 4015 |
| GET | `/finance/department-budgets-summary` | 4008, 4013 |
| GET | `/finance/fees-annual-summary` | 4136, 4190 |
| GET | `/finance/payrolls-history` | 4062, 4192 |
| GET | `/inventory/categories-list` | 4226, 4445 |
| GET | `/inventory/items-list` | 4209, 4438 |
| GET | `/inventory/items-with-stock` | 4211, 4444 |
| GET | `/inventory/suppliers-list` | 4254, 4446 |
| GET | `/payments/unmatched-mpesa` | 5332, 5785 |
| GET | `/reports` | 5209, 5220 |
| GET | `/reports/audit-trail-summary` | 5180, 5217 |
| GET | `/reports/exam-reports` | 5112, 5213 |
| GET | `/reports/system-logs` | 5174, 5215 |
| GET | `/staff` | 4464, 4482, 4700 |
| GET | `/staff/assignments-get` | 4490, 4493 |
| GET | `/students/qr-info-get` | 2827, 5648, 5650 |
| GET | `/students/statistics-get` | 2831, 2833 |
| GET | `/students/student` | 2773, 2781, 2800 |
| POST | `/academic/years/create` | 3231, 3246 |
| POST | `/attendance` | 3531, 3536 |
| POST | `/auth/forgot-password` | 2577, 5655 |
| POST | `/auth/login` | 2448, 2527 |
| POST | `/auth/reset-password` | 2585, 5671 |
| POST | `/communications/announcement` | 3821, 3958, 5636 |
| POST | `/communications/communication` | 3881, 3954, 5634 |
| POST | `/communications/template` | 3936, 3983, 5641 |
| POST | `/counseling/session` | 3694, 3707 |
| POST | `/finance` | 3992, 4183 |
| POST | `/finance/department-budgets-approve` | 4002, 4019 |
| POST | `/finance/department-budgets-propose` | 3998, 4017 |
| POST | `/inventory/inventory` | 4202, 4439 |
| POST | `/reports` | 5211, 5303 |
| POST | `/schedules/events-create` | 4990, 5073, 5081 |
| POST | `/schedules/timetable-create` | 4962, 5072 |
| POST | `/staff/assign-class` | 4485, 4702 |
| POST | `/students/qr-code-generate` | 2862, 5646 |
| PUT | `/academic/years/set-current/${id}` | 3236, 3248 |
| PUT | `/counseling/session/${id}` | 3696, 3705 |
| PUT | `/inventory/inventory/${id}` | 4204, 4441 |
| PUT | `/staff/${id}` | 4466, 4704 |

## 4. Backend handlers with no api.js reference (by controller)

_(Full 877-method listing generated separately — see companion script output; controllers sorted by gap size.)_

## 5. Known response-contract issue (from existing `docs/DATA_CONTRACT_AUDIT.md`)
- `handleApiResponse()` unwraps to raw `data`; ~40 page controllers test `res?.success` on the unwrapped value and fail to render.
- This audit adds the endpoint-coverage dimension; both must be fixed in the api.js normalization phase.
