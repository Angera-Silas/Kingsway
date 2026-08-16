<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class WorkflowReportManager extends BaseAPI
{
    public function getWorkflowInstanceStats($filters = [])
    {
        // Workflow instance statistics: total, completed, running, failed
        try {
            $sql = "SELECT status, COUNT(*) as count
                    FROM workflow_instances
                    GROUP BY status";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getWorkflowStageTimes($filters = [])
    {
        // Workflow stage activity: count of transitions per stage.
        try {
            $sql = "SELECT stage_code, COUNT(*) as stage_count
                    FROM workflow_stage_history
                    GROUP BY stage_code";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getWorkflowTransitionFrequencies($filters = [])
    {
        // Workflow transition frequencies: count per stage transition
        try {
            $sql = "SELECT from_stage, to_stage, COUNT(*) as transition_count
                    FROM workflow_stage_history
                    WHERE from_stage IS NOT NULL AND to_stage IS NOT NULL
                    GROUP BY from_stage, to_stage
                    ORDER BY transition_count DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
