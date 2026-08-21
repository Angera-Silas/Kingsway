<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class DisciplineReportManager extends BaseAPI
{
    public function getConductCasesStats($filters = [])
    {
        // Count conduct cases by type and status
        try {
            $sql = "SELECT type AS case_type, status, COUNT(*) as total
                    FROM discipline_incidents
                    GROUP BY type, status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getDisciplinaryTrends($filters = [])
    {
        // Count conduct cases per month for trend analysis
        try {
            $sql = "SELECT YEAR(incident_date) as year, MONTH(incident_date) as month, COUNT(*) as total
                    FROM discipline_incidents
                    GROUP BY year, month
                    ORDER BY year DESC, month DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
