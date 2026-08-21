# Kitchen Staff — Role Matrix

| Field | Value |
|---|---|
| role_id | 32 |
| scope | school |
| is_system | 0 |
| is_active | 1 |
| users | 1 |
| sidebar items | 0 |
| route-level auth (role_routes) | 10 |
| dashboards | 1 |
| distinct endpoints | 22 |
| unresolved endpoints | 0 |

## Dashboards

| Key | Display | Component | Endpoints | Unresolved |
|---|---|---|---|---|
| support_staff_dashboard | Support Staff Dashboard | components/dashboards/support_staff_dashboard.php | 22 | 0 |

## Sidebar items

| id | Label | Type | Parent | URL | Route | Page | Controllers | Status |
|---|---|---|---|---|---|---|---|---|

## Endpoint usage (dedup, all sources)

| Endpoint | Module | API method | Handler | Status | Source |
|---|---|---|---|---|---|
| `GET /staff/access-context` | staff | getAccessContext | `Staff::getAccessContext` | OK | dashboard:support_staff_dashboard |
| `GET /staff/profile-get/` | staff | getProfile | `Staff::getProfile` | OK | dashboard:support_staff_dashboard |
| `GET /staff/profile-get` | staff | getProfile | `Staff::getProfile` | OK | dashboard:support_staff_dashboard |
| `GET /staff/attendance-get/` | staff | getAttendance | `Staff::getAttendance` | OK | dashboard:support_staff_dashboard |
| `GET /staff/attendance-get` | staff | getAttendance | `Staff::getAttendance` | OK | dashboard:support_staff_dashboard |
| `GET /staff/payroll-history?staff_id=` | staff | getPayrollHistory | `Staff::getPayrollHistory` | OK | dashboard:support_staff_dashboard |
| `GET /staff/leave-types` | staff | getLeaveTypes | `Staff::getLeaveTypes` | OK | dashboard:support_staff_dashboard |
| `GET /staff/leave-balance` | staff | getLeaveBalance | `Staff::getLeaveBalance` | OK | dashboard:support_staff_dashboard |
| `GET /staff/leave-requests` | staff | getLeaveRequests | `Staff::getLeaveRequests` | OK | dashboard:support_staff_dashboard |
| `GET /communications/announcement/` | communications | getAnnouncement | `Communications::getAnnouncement` | OK | dashboard:support_staff_dashboard |
| `GET /communications/announcement` | communications | getAnnouncement | `Communications::getAnnouncement` | OK | dashboard:support_staff_dashboard |
| `GET /staff/internal-opportunities` | staff | getInternalOpportunities | `Staff::getInternalOpportunities` | OK | dashboard:support_staff_dashboard |
| `GET /staff/incidents` | staff | getIncidentReports | `Staff::getIncidentReports` | OK | dashboard:support_staff_dashboard |
| `GET /catering/stats` | catering | getStats | `Catering::getStats` | OK | dashboard:support_staff_dashboard |
| `GET /catering/food-stock` | catering | getFoodStock | `Catering::getFoodStock` | OK | dashboard:support_staff_dashboard |
| `GET /boarding/exeats` | boarding | getExeats | `Boarding::getExeats` | OK | dashboard:support_staff_dashboard |
| `GET /maintenance/dashboard-summary` | maintenance | getDashboardSummary | `Maintenance::getDashboardSummary` | OK | dashboard:support_staff_dashboard |
| `POST /staff/leave-requests` | staff | createLeaveRequest | `Staff::createLeaveRequest` | OK | dashboard:support_staff_dashboard |
| `POST /staff/incidents` | staff | createIncidentReport | `Staff::createIncidentReport` | OK | dashboard:support_staff_dashboard |
| `POST /staff/internal-opportunities-apply` | staff | applyForInternalOpportunity | `Staff::applyForInternalOpportunity` | OK | dashboard:support_staff_dashboard |
| `GET /staff/payroll-download-payslip?staff_id=` | staff | downloadPayslip | `Staff::downloadPayslip` | OK | dashboard:support_staff_dashboard |
| `GET /staff/payroll-download-p9?staff_id=&year=` | staff | downloadP9 | `Staff::downloadP9` | OK | dashboard:support_staff_dashboard |

## Authorized routes (role_routes)

| route_id | Name | URL | Controller | Action |
|---|---|---|---|---|
| 100008 | support_staff_dashboard | home.php?route=support_staff_dashboard |  |  |
| 78 | manage_communications | home.php?route=manage_communications |  |  |
| 79 | manage_announcements | home.php?route=manage_announcements |  |  |
| 73 | food_store | home.php?route=food_store |  |  |
| 100020 | detailed_payslip | home.php?route=detailed_payslip |  |  |
| 100048 | catering_boarding_students | home.php?route=catering_boarding_students |  |  |
| 100471 | Complete Staff Profile | complete_staff_profile |  |  |
| 100476 | My Attendance | my_attendance |  |  |
| 100477 | Staff Leave | staff_leave |  |  |
| 100480 | Detailed Payslip | detailed_payslip |  |  |
