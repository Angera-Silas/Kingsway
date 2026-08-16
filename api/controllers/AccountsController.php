<?php

namespace App\API\Controllers;

use App\API\Modules\finance\FinanceAPI;

class AccountsController extends BaseController
{
    protected $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = new FinanceAPI();
    }

    private function canView()
    {
        return $this->userHasAny(
            ['finance.view'],
            [10],
            ['accountant', 'finance', 'admin', 'director']
        );
    }

    private function canManage()
    {
        return $this->userHasAny(
            ['finance.manage'],
            [10],
            ['accountant', 'finance', 'admin', 'director']
        );
    }

    // GET /api/accounts/bank-accounts
    public function getBankAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canView()) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->listBankAccounts();
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load bank accounts');
        }

        return $this->success($result['data'] ?? ['bank_accounts' => []]);
    }

    // POST /api/accounts/bank-accounts - create/update
    public function postBankAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canManage()) {
            return $this->forbidden('Insufficient permissions');
        }

        if (empty($data['name']) || empty($data['account_no'])) {
            return $this->badRequest('Missing required fields');
        }

        $result = $this->api->createBankAccount($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to create bank account');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Bank account created');
    }

    // GET /api/accounts/bank-transactions
    public function getBankTransactions($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canView()) {
            return $this->forbidden('Insufficient permissions');
        }

        $bankId = $_GET['bank_id'] ?? $data['bank_id'] ?? null;
        $result = $this->api->listBankTransactions($bankId);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load bank transactions');
        }

        return $this->success($result['data'] ?? ['transactions' => []]);
    }

    // POST /api/accounts/bank-transactions - create manual entry
    public function postBankTransactions($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canManage()) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->createBankTransaction($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to record bank transaction');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Bank transaction recorded');
    }

    // PUT /api/accounts/bank-transactions/{id} - update or reconcile
    public function putBankTransactions($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canManage()) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->updateBankTransaction($id, $data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to update bank transaction');
        }

        return $this->success($result['data'] ?? ['id' => $id], $result['message'] ?? 'Bank transaction updated');
    }

    // DELETE /api/accounts/bank-transactions/{id} - delete manual entry
    public function deleteBankTransactions($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canManage()) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->deleteBankTransaction($id);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to delete bank transaction');
        }

        return $this->success($result['data'] ?? ['id' => $id], $result['message'] ?? 'Bank transaction deleted');
    }

    // POST /api/accounts/petty-cash - create petty cash entry
    public function postPettyCash($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->canManage()) {
            return $this->forbidden('Insufficient permissions');
        }

        $amount = $data['amount'] ?? null;
        $reason = $data['reason'] ?? null;
        if (!$amount || !$reason) {
            return $this->badRequest('Missing amount or reason');
        }

        $result = $this->api->recordExpense([
            'description'      => $reason,
            'amount'           => $amount,
            'expense_category' => 'petty_cash',
            'expense_date'     => date('Y-m-d'),
            'recorded_by'      => $this->getUserId(),
        ]);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to record petty cash');
        }

        return $this->success(['id' => $result['data']['expense_id'] ?? null], 'Petty cash recorded (as expense)');
    }
}
