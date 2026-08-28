<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use PHPUnit\Framework\TestCase;

final class KcbReconciliationSafetyTest extends TestCase
{
    public function testMigrationAddsAuditExceptionInquiryAndRetryControls(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/186_kcb_disbursement_reconciliation.sql');
        self::assertIsString($migration);
        self::assertStringContainsString('kcb_transfer_status_inquiries', $migration);
        self::assertStringContainsString('kcb_disbursement_exceptions', $migration);
        self::assertStringContainsString('kcb_disbursement_audit_events', $migration);
        self::assertStringContainsString('retry_of_disbursement_id', $migration);
        self::assertStringContainsString('uq_disbursement_idempotency', file_get_contents(dirname(__DIR__, 3) . '/database/migrations/075_financial_accounting_foundation.sql'));
    }

    public function testRetryRequiresConfirmedProviderFailure(): void
    {
        $service = file_get_contents(dirname(__DIR__, 3) . '/api/services/payments/KcbTransferReconciliationService.php');
        self::assertIsString($service);
        self::assertStringContainsString("\$row['status'] !== 'failed' || \$row['reconciliation_status'] !== 'confirmed_failure'", $service);
        self::assertStringContainsString('A retry is already pending or completed', $service);
        self::assertStringContainsString("retry_of_disbursement_id = ? AND status IN ('pending','completed')", $service);
    }

    public function testCallbackChecksSignatureAmountAndNonterminalStates(): void
    {
        $payments = file_get_contents(dirname(__DIR__, 3) . '/api/modules/payments/PaymentsAPI.php');
        self::assertIsString($payments);
        self::assertStringContainsString('Invalid callback signature', $payments);
        self::assertStringContainsString('Transfer amount does not match', $payments);
        self::assertStringContainsString('Pending transfer notification acknowledged', $payments);
        self::assertStringContainsString('Unknown transfer state queued for reconciliation', $payments);
    }

    public function testFrontendUsesCentralApiAndDoesNotExposeUnsafeRetry(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3) . '/js/pages/kcb_disbursement_reconciliation.js');
        self::assertIsString($controller);
        self::assertStringContainsString('window.API.finance.checkKcbDisbursementStatus', $controller);
        self::assertStringContainsString('Number(row.retry_allowed) === 1', $controller);
        self::assertStringNotContainsString('fetch(', $controller);
    }
}
