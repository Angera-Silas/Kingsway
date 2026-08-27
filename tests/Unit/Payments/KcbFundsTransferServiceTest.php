<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\API\Services\payments\KcbFundsTransferService;
use PHPUnit\Framework\TestCase;

final class KcbFundsTransferServiceTest extends TestCase
{
    private KcbFundsTransferService $service;

    protected function setUp(): void
    {
        $this->service = new KcbFundsTransferService();
    }

    public function testNormalizesExplicitSuccessfulTransfer(): void
    {
        $result = $this->service->normalizeTransferStatusResponse([
            'response' => ['transactionStatus' => 'COMPLETED', 'transactionReference' => 'FT123', 'ftReference' => 'KCB456'],
        ]);
        self::assertSame('successful', $result['normalized_status']);
        self::assertSame('FT123', $result['transaction_reference']);
        self::assertSame('KCB456', $result['transaction_id']);
    }

    public function testNormalizesExplicitFailureAndPendingStates(): void
    {
        self::assertSame('failed', $this->service->normalizeTransferStatusResponse([
            'transactionStatus' => 'REJECTED',
        ])['normalized_status']);
        self::assertSame('pending', $this->service->normalizeTransferStatusResponse([
            'transactionStatus' => 'ACCEPTED',
        ])['normalized_status']);
    }

    public function testDoesNotMistakeSuccessfulInquiryEnvelopeForSuccessfulTransfer(): void
    {
        $result = $this->service->normalizeTransferStatusResponse([
            'header' => ['statusCode' => '0', 'statusDescription' => 'Inquiry accepted'],
        ]);
        self::assertSame('unknown', $result['normalized_status']);
    }

    public function testUnrecognizedProviderShapeRemainsUnknown(): void
    {
        $result = $this->service->normalizeTransferStatusResponse(['message' => 'New provider response']);
        self::assertSame('unknown', $result['normalized_status']);
    }
}
