<?php
declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\API\Services\payments\FinancialReconciliationMatcher;
use PHPUnit\Framework\TestCase;

final class FinancialReconciliationMatcherTest extends TestCase
{
    public function test_classifies_exact_partial_overpayment_and_conflict(): void
    {
        $m = new FinancialReconciliationMatcher();
        self::assertSame('matched', $m->classify('fee_ka_1', 'FEE-KA-1', '100', '100.00'));
        self::assertSame('partial', $m->classify('FEE-KA-1', 'FEE-KA-1', '50', '100'));
        self::assertSame('overpayment', $m->classify('FEE-KA-1', 'FEE-KA-1', '150', '100'));
        self::assertSame('conflict', $m->classify('FEE-KA-2', 'FEE-KA-1', '100', '100'));
    }
}
