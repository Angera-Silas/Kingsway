# manage_academics.php

- **File**: `pages/manage_academics.php`
- **Controller**: `academicsManager.js`
- **Roles**: —
- **Sidebar item(s)**: —

## Init / auth

- `AuthContext.ready()` awaited: `N`
- Permission/RBAC guard: `N`
- Raw `fetch`/`XMLHttpRequest`/`axios`: `0`

## API methods used

| API method | Payload keys (sent) | Flags | Endpoint | Handler | Status |
|---|---|---|---|---|---|
| `academic.listClasses` | — | — | GET /academic/classes-list | Academic::listClasses | ok |
| `academic.listLearningAreas` | — | — | GET /academic/learning-areas/list | Academic::listLearningAreas | ok |
| `academic.listYears` | — | — | GET /academic/years/list | Academic::listYears | ok |
| `academic.updateYear` | — | — | PUT /academic/years/update/ | Academic::updateYear | ok |
| `academic.createYear` | — | — | POST /academic/years/create | Academic::createYear | ok |
| `academic.deleteYear` | — | — | DELETE /academic/years/delete/ | Academic::deleteYear | ok |
| `academic.setCurrentYear` | — | — | PUT /academic/years/set-current/ | Academic::setCurrentYear | ok |
| `academic.listTerms` | — | — | GET /academic/terms-list | Academic::listTerms | ok |
| `academic.updateTerm` | — | — | PUT /academic/terms/update/ | Academic::updateTerm | ok |
| `academic.createTerm` | — | — | POST /academic/terms/create | Academic::createTerm | ok |
| `academic.deleteTerm` | — | — | DELETE /academic/terms/delete/ | Academic::deleteTerm | ok |
| `academic.getClass` | — | — | GET /academic/classes-get/<br>GET /academic/classes-get | Academic::getClass<br>Academic::getClass | ok<br>ok |
| `academic.updateClass` | — | — | PUT /academic/classes/update/ | Academic::updateClass | ok |
| `academic.createClass` | — | — | POST /academic/classes/create | Academic::createClass | ok |
| `academic.deleteClass` | — | — | DELETE /academic/classes/delete/ | Academic::deleteClass | ok |
| `academic.updateLearningArea` | — | — | PUT /academic/learning-areas/update/ | Academic::updateLearningArea | ok |
| `academic.createLearningArea` | — | — | POST /academic/learning-areas/create | Academic::createLearningArea | ok |
| `academic.deleteLearningArea` | — | — | DELETE /academic/learning-areas/delete/ | Academic::deleteLearningArea | ok |
| `academic.listStreams` | — | — | GET /academic/streams-list | Academic::listStreams | ok |
| `academic.updateStream` | — | — | PUT /academic/streams/update/ | Academic::updateStream | ok |
| `academic.createStream` | — | — | POST /academic/streams/create | Academic::createStream | ok |
| `academic.deleteStream` | — | — | DELETE /academic/streams/delete/ | Academic::deleteStream | ok |
| `academic.listTeachers` | — | — | GET /academic/teachers-list | Academic::listTeachers | ok |
| `academic.getTeacherClasses` | — | — | GET /academic/teachers-classes?teacher_id= | Academic::getTeacherClasses | ok |
| `academic.getTeacherSubjects` | — | — | GET /academic/teachers-subjects?teacher_id= | Academic::getTeacherSubjects | ok |
| `academic.getTeacherSchedule` | — | — | GET /academic/teachers-schedule?teacher_id= | Academic::getTeacherSchedule | ok |
| `academic.listSchedules` | — | — | GET /academic/schedules-list | Academic::listSchedules | ok |
| `academic.deleteSchedule` | — | — | DELETE /academic/schedules-delete | Academic::deleteSchedule | ok |
| `academic.listCurriculumUnits` | — | — | GET /academic/curriculum-units-list | Academic::listCurriculumUnits | ok |
| `academic.createSchedule` | — | — | POST /academic/schedules-create | Academic::createSchedule | ok |
| `academic.createCurriculumUnit` | — | — | POST /academic/curriculum-units-create | Academic::createCurriculumUnit | ok |
| `academic.createTopic` | — | — | POST /academic/topics-create | Academic::createTopic | ok |
| `academic.createLessonPlan` | — | — | POST /academic/lesson-plans-create | Academic::createLessonPlan | ok |

## Backend params (expected)

_(no backend handler resolved for param extraction)_

## Response shape (data keys consumed)

- `academic.getClass -> name`
- `academic.getClass -> level_name`
- `academic.getClass -> class_teacher_name`
- `academic.getClass -> room_number`
- `academic.getClass -> capacity`
- `academic.getClass -> student_count`
- `academic.getClass -> academic_year`
- `academic.getClass -> status`
- `academic.getClass -> id`

## UI / security

- `innerHTML`/`insertAdjacentHTML` assignments: `33` (with interpolation: `0`)
- `escapeHtml()` calls: `0` — XSS check: `PASS`
- Bootstrap modal usage: `26`
- Payload/backend param match: `OK`
- Fix flags: none
- Info flags: `NO_AUTH_GUARD`, `ESCAPED_LITERAL_HTML`
- Fix task: `—`

## End-to-end trace

> collect → store → analyse → display: page payload keys → API endpoint → controller method → service passthrough → SQL views/procs/tables (rows above). This is machine-derived; the interactive flow (form submit → render) needs human sign-off.

## Review checklist

| Check | Status | Notes |
|---|---|---|
| Init: `AuthContext.ready()` | ❌ | machine |
| Data load: `window.API.*`, no raw fetch | ✅ | machine |
| Params: sent ≈ backend `$data` | ⚠️ | heuristic |
| Response: unwrapped `data` handled | ✅ | heuristic |
| UI: Bootstrap + `escapeHtml` | ✅ | machine |
| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |
