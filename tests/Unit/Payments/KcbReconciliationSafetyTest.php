<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\API\Modules\payments\PaymentsAPI;
use App\API\Services\payments\KcbTransferReconciliationService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KcbReconciliationSafetyTest extends TestCase
{
    public function testReconciliationClassesAndPublicContractsResolveThroughComposer(): void
    {
        $contracts = [
            KcbTransferReconciliationService::class => ['list', 'inquire', 'pollDue', 'retry', 'resolveManually'],
            PaymentsAPI::class => ['processKcbTransferCallback', 'processKcbNotification'],
        ];

        foreach ($contracts as $class => $methods) {
            self::assertTrue(class_exists($class), "{$class} must resolve through Composer autoload");
            foreach ($methods as $method) {
                self::assertTrue(method_exists($class, $method), "{$class}::{$method} must exist");
                self::assertTrue((new ReflectionMethod($class, $method))->isPublic());
            }
        }
    }

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

    public function testFrontendUsesCentralApiAndDoesNotExposeUnsafeRetry(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 3) . '/js/pages/kcb_disbursement_reconciliation.js');
        self::assertIsString($controller);
        self::assertStringContainsString('window.API.finance.checkKcbDisbursementStatus', $controller);
        self::assertStringContainsString('Number(row.retry_allowed) === 1', $controller);
        self::assertStringNotContainsString('fetch(', $controller);
    }
}
