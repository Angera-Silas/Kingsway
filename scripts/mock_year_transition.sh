#!/usr/bin/env bash
# Read-only rollover rehearsal. It never INSERTs, UPDATEs, DELETEs, or calls a
# stored procedure. Use this before starting a real transition.
set -euo pipefail

MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
: "${MYSQL_PWD:?Set MYSQL_PWD to run the mock test}"

sql="
SELECT CONCAT('CURRENT=', ay.year_code, '|START=', SUBSTRING_INDEX(ay.year_code,'/',1),
             '|EXPECTED_NEXT=', CONCAT(CAST(SUBSTRING_INDEX(ay.year_code,'/',1) AS UNSIGNED)+1,'/',
             CAST(SUBSTRING_INDEX(ay.year_code,'/',1) AS UNSIGNED)+2),
             '|YEAR_DATES=', ay.start_date, '..', ay.end_date)
FROM academic_years ay WHERE ay.is_current = 1 LIMIT 1;

SELECT CONCAT('NEXT_YEAR_ROWS=', COUNT(*))
FROM academic_years ay
JOIN (SELECT CAST(SUBSTRING_INDEX(year_code,'/',1) AS UNSIGNED)+1 AS start_year
      FROM academic_years WHERE is_current = 1 LIMIT 1) n
  ON ay.year_code = CONCAT(n.start_year,'/',n.start_year+1);

SELECT CONCAT('CURRENT_TERMS=', COUNT(*), '|OPEN_TERMS=',
       SUM(ayt.status <> 'completed'))
FROM academic_year_terms ayt
JOIN academic_years ay ON ay.id = ayt.academic_year_id
WHERE ay.is_current = 1;

SELECT CONCAT('CURRENT_CLASSES=', COUNT(DISTINCT ayc.id),
              '|STREAMS=', COUNT(DISTINCT aycs.id),
              '|LEARNING_AREAS=', COUNT(DISTINCT acla.id),
              '|TEACHER_BINDINGS=', COUNT(DISTINCT aclat.id))
FROM academic_years ay
LEFT JOIN academic_year_classes ayc ON ayc.academic_year_id = ay.id
LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
LEFT JOIN academic_year_class_learning_areas acla ON acla.academic_year_class_id = ayc.id
LEFT JOIN academic_year_class_learning_area_teachers aclat ON aclat.academic_year_class_learning_area_id = acla.id
WHERE ay.is_current = 1;

SELECT CONCAT('ACTIVE_FEE_ROWS=', COUNT(*), '|ACTIVE_STUDENTS=',
       (SELECT COUNT(*) FROM students WHERE status='active'))
FROM academic_year_fee_schedules fs
JOIN academic_years ay ON ay.id = fs.academic_year_id
WHERE ay.is_current = 1 AND fs.status='active';

SELECT CONCAT('WORKFLOW=', ws.code, '|SEQUENCE=', ws.sequence)
FROM workflow_stages ws JOIN workflow_definitions wd ON wd.id=ws.workflow_id
WHERE wd.code='academic_year_transition' AND ws.is_active=1 ORDER BY ws.sequence;
"

echo "=== READ-ONLY ACADEMIC YEAR TRANSITION MOCK ==="
echo "No live transition is being executed."
"$MYSQL" --batch --raw -u "$DBU" -p"$MYSQL_PWD" "$DB" -e "$sql"
echo "=== MOCK COMPLETE: database unchanged ==="
