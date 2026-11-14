<?php
namespace App\API\Modules\academic;

use App\API\Services\ReadReplicaService;
use PDO;

/**
 * Learning Area Setup Service
 *
 * Seeds per-class CBC learning-area coverage (academic_year_class_learning_areas)
 * for a new academic year from the global curriculum reference data:
 * learning_areas.levels drives which areas apply to a grade; strands and
 * sub_strands are counted/attached by grade_level.
 */
class LearningAreaSetupService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Map a classes.name to the grade label used by learning_areas.levels.
     * Returns null for grades without CBC curriculum (Playgroup).
     */
    private function normalizeGrade(string $className): ?string
    {
        $name = trim($className);
        if ($name === '' || strcasecmp($name, 'Playgroup') === 0 || strcasecmp($name, 'Grade 0') === 0) {
            return null;
        }
        return $name;
    }

    /**
     * Seed learning-area coverage rows for every class in an academic year.
     *
     * @param int $academicYearId
     * @return array Per-class summary
     */
    public function seedForYear(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT academic_year_class_id AS ayc_id, class_name
             FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
             WHERE academic_year_id = ?
             GROUP BY academic_year_class_id, class_name
             ORDER BY class_id"
        );
        $stmt->execute([$academicYearId]);

        $summary = ['year_id' => $academicYearId, 'classes' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary['classes'][] = $this->seedForClass((int) $row['ayc_id'], (string) $row['class_name']);
        }
        return $summary;
    }

    /**
     * Seed coverage rows for a single class.
     *
     * @param int $aycId academic_year_classes.id
     * @param string $className classes.name
     * @return array Coverage summary for the class
     */
    public function seedForClass(int $aycId, string $className): array
    {
        $grade = $this->normalizeGrade($className);
        $result = [
            'ayc_id' => $aycId,
            'class_name' => $className,
            'grade_level' => $grade,
            'learning_areas' => 0,
            'strands' => 0,
            'sub_strands' => 0,
            'created' => 0,
            'skipped' => [],
        ];

        if ($grade === null) {
            $result['skipped'][] = 'no curriculum for this grade';
            return $result;
        }

        // Applicable learning areas (levels is a comma-separated grade list).
        // FIND_IN_SET does exact member matching without trimming spaces, so
        // strip spaces on both sides (members are e.g. ' PP2').
        $laStmt = $this->db->prepare(
            "SELECT id FROM learning_areas
             WHERE status = 'active'
               AND FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(levels, ' ', ''))
             ORDER BY id"
        );
        $laStmt->execute([$grade]);
        $learningAreaIds = array_map('intval', $laStmt->fetchAll(PDO::FETCH_COLUMN));

        $strandStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM strands WHERE grade_level = ? AND status = 'active'"
        );
        $strandStmt->execute([$grade]);
        $result['strands'] = (int) $strandStmt->fetchColumn();

        $subStrandStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM sub_strands WHERE grade_level = ? AND status = 'active'"
        );
        $subStrandStmt->execute([$grade]);
        $result['sub_strands'] = (int) $subStrandStmt->fetchColumn();

        $existingStmt = $this->db->prepare(
            "SELECT 1 FROM academic_year_class_learning_areas
             WHERE academic_year_class_id = ? AND learning_area_id = ?"
        );

        foreach ($learningAreaIds as $learningAreaId) {
            $existingStmt->execute([$aycId, $learningAreaId]);
            if ($existingStmt->fetchColumn()) {
                continue; // already covered - idempotent
            }

            $ins = $this->db->prepare(
                "INSERT INTO academic_year_class_learning_areas
                    (academic_year_class_id, learning_area_id, status, planned_weeks)
                 VALUES (?, ?, 'planned', NULL)"
            );
            $ins->execute([$aycId, $learningAreaId]);
            $result['created']++;
        }

        $result['learning_areas'] = count($learningAreaIds);
        return $result;
    }

    /**
     * Assign one curriculum learning area to selected classes in one year.
     * Existing skipped links are reactivated, and stream links are kept in sync.
     */
    public function assignToClasses(int $learningAreaId, int $academicYearId, array $classIds): array
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if ($learningAreaId < 1 || $academicYearId < 1 || !$classIds) {
            throw new \InvalidArgumentException('Select a learning area, academic year, and at least one class.');
        }

        $area = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ? AND status = 'active'");
        $area->execute([$learningAreaId]);
        if (!$area->fetchColumn()) throw new \InvalidArgumentException('The learning area is missing or inactive.');

        $this->db->beginTransaction();
        try {
            $classQuery = $this->db->prepare(
                "SELECT ayc.id, c.name
                   FROM academic_year_classes ayc
                   JOIN classes c ON c.id = ayc.class_id
                  WHERE ayc.id = ? AND ayc.academic_year_id = ? AND ayc.status = 'active'
                  LIMIT 1"
            );
            $findLink = $this->db->prepare(
                'SELECT id, status FROM academic_year_class_learning_areas WHERE academic_year_class_id = ? AND learning_area_id = ? LIMIT 1'
            );
            $insertLink = $this->db->prepare(
                "INSERT INTO academic_year_class_learning_areas (academic_year_class_id, learning_area_id, status) VALUES (?, ?, 'planned')"
            );
            $restoreLink = $this->db->prepare(
                "UPDATE academic_year_class_learning_areas SET status='planned' WHERE id=? AND status='skipped'"
            );
            $streams = $this->db->prepare(
                "SELECT id FROM academic_year_class_streams WHERE academic_year_class_id = ? AND status = 'active'"
            );
            $findStreamLink = $this->db->prepare(
                'SELECT id, status FROM academic_year_class_stream_learning_areas WHERE academic_year_class_stream_id = ? AND academic_year_class_learning_area_id = ? LIMIT 1'
            );
            $insertStreamLink = $this->db->prepare(
                "INSERT INTO academic_year_class_stream_learning_areas (academic_year_class_stream_id, academic_year_class_learning_area_id, status) VALUES (?, ?, 'active')"
            );
            $restoreStreamLink = $this->db->prepare(
                "UPDATE academic_year_class_stream_learning_areas SET status='active' WHERE id=? AND status='skipped'"
            );

            $assigned = [];
            foreach ($classIds as $classId) {
                $classQuery->execute([$classId, $academicYearId]);
                $class = $classQuery->fetch(PDO::FETCH_ASSOC);
                if (!$class) throw new \InvalidArgumentException('One or more selected classes are not active in the chosen academic year.');

                $findLink->execute([$classId, $learningAreaId]);
                $link = $findLink->fetch(PDO::FETCH_ASSOC);
                if (!$link) {
                    $insertLink->execute([$classId, $learningAreaId]);
                    $linkId = (int) $this->db->lastInsertId();
                } else {
                    $linkId = (int) $link['id'];
                    $restoreLink->execute([$linkId]);
                }

                $streams->execute([$classId]);
                foreach ($streams->fetchAll(PDO::FETCH_COLUMN) as $streamId) {
                    $findStreamLink->execute([(int) $streamId, $linkId]);
                    $streamLink = $findStreamLink->fetch(PDO::FETCH_ASSOC);
                    if (!$streamLink) $insertStreamLink->execute([(int) $streamId, $linkId]);
                    elseif ($streamLink['status'] === 'skipped') $restoreStreamLink->execute([(int) $streamLink['id']]);
                }
                $assigned[] = ['academic_year_class_id' => $classId, 'class_name' => $class['name']];
            }
            $this->db->commit();
            return ['learning_area_id' => $learningAreaId, 'academic_year_id' => $academicYearId, 'classes' => $assigned];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Return the curriculum coverage for one class: each learning area with its
     * strand and sub-strand counts for that grade (for display/planning).
     */
    /**
     * The rule every academic year relies on: each active stream of a class
     * carries every learning area of that class. A learner's learning areas
     * come from their enrolment's stream, so a stream missing an area means
     * every learner placed in it silently loses that area.
     *
     * Adds the missing stream links only (status 'planned'); existing links,
     * including a deliberate per-stream 'skipped', are left as they are.
     * Idempotent: safe to run from the rollover, at promotion and whenever a
     * stream or class learning area is added.
     *
     * @return int number of stream learning-area links added
     */
    public function syncStreamsForClasses(array $academicYearClassIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $academicYearClassIds))));
        if ($ids === []) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "INSERT INTO academic_year_class_stream_learning_areas
                 (academic_year_class_stream_id, academic_year_class_learning_area_id, status)
             SELECT s.id, la.id, 'planned'
               FROM academic_year_class_streams s
               JOIN academic_year_class_learning_areas la
                 ON la.academic_year_class_id = s.academic_year_class_id AND la.status <> 'skipped'
              WHERE s.academic_year_class_id IN ({$in})
                AND s.status IN ('planning', 'active')
                AND NOT EXISTS (
                    SELECT 1 FROM academic_year_class_stream_learning_areas x
                     WHERE x.academic_year_class_stream_id = s.id
                       AND x.academic_year_class_learning_area_id = la.id
                )"
        );
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /** Apply the stream rule to every class of an academic year. */
    public function syncStreamsForYear(int $academicYearId): int
    {
        $stmt = $this->db->prepare('SELECT id FROM academic_year_classes WHERE academic_year_id = ?');
        $stmt->execute([$academicYearId]);
        return $this->syncStreamsForClasses($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Classes (of the given academic_year_classes ids) that have no learning
     * areas at all. Learners must not be enrolled into these: they would have
     * nothing to be taught or assessed in.
     *
     * @return array<int,string> academic_year_class_id => class name
     */
    public function classesWithoutLearningAreas(array $academicYearClassIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $academicYearClassIds))));
        if ($ids === []) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));

        // Single-table reads (no live joins on the read path).
        $withAreas = $this->db->prepare(
            "SELECT DISTINCT academic_year_class_id FROM academic_year_class_learning_areas
              WHERE academic_year_class_id IN ({$in}) AND status <> 'skipped'"
        );
        $withAreas->execute($ids);
        $missing = array_values(array_diff($ids, array_map('intval', $withAreas->fetchAll(PDO::FETCH_COLUMN))));
        if ($missing === []) return [];

        $in = implode(',', array_fill(0, count($missing), '?'));
        $classOf = $this->db->prepare("SELECT id, class_id FROM academic_year_classes WHERE id IN ({$in})");
        $classOf->execute($missing);
        $classIds = $classOf->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($classIds === []) return [];

        $in = implode(',', array_fill(0, count($classIds), '?'));
        $names = $this->db->prepare("SELECT id, name FROM classes WHERE id IN ({$in})");
        $names->execute(array_values(array_unique(array_map('intval', $classIds))));
        $nameOf = $names->fetchAll(PDO::FETCH_KEY_PAIR);

        $result = [];
        foreach ($classIds as $aycId => $classId) {
            $result[(int) $aycId] = (string) ($nameOf[$classId] ?? ('Class ' . $classId));
        }
        uksort($result, static fn (int $a, int $b): int => (int) $classIds[$a] <=> (int) $classIds[$b]);
        return $result;
    }

    public function getClassCoverage(int $aycId): array
    {
        $sql = "
            SELECT la.id AS learning_area_id, la.name AS learning_area_name, la.code,
                   la.is_optional,
                   (SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("strands") . " st
                     WHERE st.learning_area_id = la.id
                       AND st.grade_level = c.name AND st.status = 'active') AS strand_count,
                   (SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("sub_strands") . " ss
                     JOIN " . ReadReplicaService::qualifiedRef("strands") . " st2 ON st2.id = ss.strand_id
                     WHERE st2.learning_area_id = la.id
                       AND ss.grade_level = c.name AND ss.status = 'active') AS sub_strand_count
            FROM " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " acla
            JOIN " . ReadReplicaService::qualifiedRef("learning_areas") . " la ON la.id = acla.learning_area_id
            JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = acla.academic_year_class_id
            JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
            WHERE acla.academic_year_class_id = ?
            ORDER BY la.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$aycId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['strand_count'] = (int) $row['strand_count'];
            $row['sub_strand_count'] = (int) $row['sub_strand_count'];
            $row['is_optional'] = (int) $row['is_optional'];
        }
        return $rows;
    }
}
