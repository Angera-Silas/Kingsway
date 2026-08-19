<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Standard journal recipes shared by incoming and outgoing workflows. */
final class FinancialPostingCoordinator
{
    private PDO $db;
    private AccountingPostingService $journals;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->journals = new AccountingPostingService($db);
    }

    public function postIncoming(string $sourceType, int $sourceId, int $financialAccountId, string $purpose, string $amount, int $actorUserId = 0, ?string $reference = null): array
    {
        $source = $this->sourceAccount($financialAccountId);
        $receivable = ['fees' => '120001', 'transport' => '120002', 'uniforms' => '120003'][$purpose] ?? null;
        if (!$receivable) throw new RuntimeException('No incoming ledger recipe exists for purpose: ' . $purpose);
        return $this->journals->post($sourceType, $sourceId, 'incoming_' . $purpose, 'Incoming ' . $purpose . ($reference ? ' ' . $reference : ''), [
            ['account_code' => $source['ledger_code'], 'debit' => $amount, 'description' => 'Money received into ' . $source['account_name']],
            ['account_code' => $receivable, 'credit' => $amount, 'description' => ucfirst($purpose) . ' receivable settlement'],
        ], $actorUserId);
    }

    public function postDisbursement(string $sourceType, int $sourceId, int $financialAccountId, string $purpose, string $amount, int $actorUserId = 0): array
    {
        $source = $this->sourceAccount($financialAccountId);
        $accountCode = ['payroll' => '510001', 'suppliers' => '520001', 'statutory' => '210011', 'refunds' => '210001', 'operations' => '520001'][$purpose] ?? null;
        if (!$accountCode) throw new RuntimeException('No disbursement ledger recipe exists for purpose: ' . $purpose);
        return $this->journals->post($sourceType, $sourceId, 'outgoing_' . $purpose, 'Outgoing ' . $purpose, [
            ['account_code' => $accountCode, 'debit' => $amount, 'description' => ucfirst($purpose) . ' expense/liability'],
            ['account_code' => $source['ledger_code'], 'credit' => $amount, 'description' => 'Payment from ' . $source['account_name']],
        ], $actorUserId);
    }

    private function sourceAccount(int $id): array
    {
        $s = $this->db->prepare("SELECT a.id,a.account_name,c.account_code AS ledger_code FROM school_financial_accounts a JOIN chart_of_accounts c ON c.id=a.ledger_account_id WHERE a.id=? AND a.status='active' LIMIT 1");
        $s->execute([$id]);
        $account = $s->fetch(PDO::FETCH_ASSOC);
        if (!$account || !$account['ledger_code']) throw new RuntimeException('Financial account is not active or has no ledger mapping.');
        return $account;
    }
}
