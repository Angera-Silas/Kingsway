<?php
namespace App\API\Controllers;

use App\API\Modules\inventory\InventoryAPI;

class VendorsController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = new InventoryAPI();
    }

    // GET /api/vendors - list vendors
    public function getVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->listSuppliers($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load vendors');
        }

        return $this->success(['vendors' => $result['data']['suppliers'] ?? []]);
    }

    // POST /api/vendors - create vendor
    public function postVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $data['supplier_name'] = $data['supplier_name'] ?? $data['name'] ?? null;
        if (!$data['supplier_name']) {
            return $this->badRequest('Missing vendor name');
        }

        $result = $this->api->createSupplier($data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to create vendor');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Vendor created');
    }

    // GET /api/vendors/purchase-orders
    public function getPurchaseOrders($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->listPurchaseOrders($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load purchase orders');
        }

        return $this->success(['purchase_orders' => $result['data']['orders'] ?? []]);
    }

    // POST /api/vendors/purchase-orders - create PO
    public function postPurchaseOrders($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $data['supplier_id'] = $data['vendor_id'] ?? $data['supplier_id'] ?? null;
        $data['total_amount'] = $data['amount'] ?? $data['total_amount'] ?? null;
        $data['remarks'] = $data['remarks'] ?? $data['description'] ?? null;

        if (!$data['supplier_id'] || !$data['total_amount']) {
            return $this->badRequest('Missing vendor or amount');
        }

        $result = $this->api->createPurchaseOrder($data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to create purchase order');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Purchase order created');
    }

    // PUT /api/vendors/{id} - update vendor
    public function putVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Vendor ID required');
        }

        if (isset($data['name']) && !isset($data['supplier_name'])) {
            $data['supplier_name'] = $data['name'];
        }

        $result = $this->api->updateSupplier($id, $data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to update vendor');
        }

        return $this->success(['id' => $id], $result['message'] ?? 'Vendor updated');
    }

    // DELETE /api/vendors/{id} - soft delete vendor
    public function deleteVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Vendor ID required');
        }

        $result = $this->api->deleteSupplier($id, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to deactivate vendor');
        }

        return $this->success(['id' => $id], $result['message'] ?? 'Vendor deactivated');
    }

    // GET /api/vendors/outstanding-liabilities
    public function getOutstandingLiabilities($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->getOutstandingLiabilities();
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load liabilities');
        }

        return $this->success($result['data'] ?? ['outstanding' => []]);
    }
}
