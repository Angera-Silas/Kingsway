<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class InventoryReportManager extends BaseAPI
{
    public function getTransportReport($filters = [])
    {
        try {
            $sql = "SELECT
                        v.id,
                        v.registration_number,
                        v.make,
                        v.model,
                        v.capacity,
                        v.status,
                        COUNT(DISTINCT sta.student_id) AS assigned_students
                    FROM transport_vehicles v
                    LEFT JOIN transport_vehicle_routes tvr ON tvr.vehicle_id = v.id AND tvr.status = 'active'
                    LEFT JOIN student_transport_assignments sta ON sta.route_id = tvr.route_id
                    GROUP BY v.id, v.registration_number, v.make, v.model, v.capacity, v.status
                    ORDER BY v.registration_number";
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryStockLevels($filters = [])
    {
        try {
            $sql = "SELECT i.id, i.name, i.current_quantity, i.unit, c.name as category
                    FROM inventory_items i
                    LEFT JOIN inventory_categories c ON i.category_id = c.id
                    ORDER BY i.name";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryUsageRates($filters = [])
    {
        try {
            $sql = "SELECT item_id, YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(quantity) as total_used
                    FROM inventory_transactions
                    WHERE transaction_type = 'out'
                    GROUP BY item_id, year, month
                    ORDER BY year DESC, month DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRequisitionsSummary($filters = [])
    {
        try {
            $sql = "SELECT status, COUNT(*) as total
                    FROM requisitions
                    GROUP BY status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAssetMaintenanceStats($filters = [])
    {
        try {
            $sql = "SELECT equipment_id AS asset_id, emt.name AS maintenance_type, COUNT(*) as event_count
                    FROM equipment_maintenance em
                    LEFT JOIN equipment_maintenance_types emt ON emt.id = em.maintenance_type_id
                    GROUP BY equipment_id, emt.name
                    ORDER BY event_count DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryAdjustmentLogs($filters = [])
    {
        try {
            $sql = "SELECT
                        it.id,
                        it.item_id,
                        ii.name AS item_name,
                        it.quantity,
                        it.unit_cost,
                        it.transaction_date AS adjusted_at,
                        it.reference_id,
                        it.notes
                    FROM inventory_transactions it
                    LEFT JOIN inventory_items ii ON ii.id = it.item_id
                    WHERE it.reference_type = 'adjustment'
                    ORDER BY it.transaction_date DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
