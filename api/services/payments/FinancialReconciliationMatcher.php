<?php
declare(strict_types=1);

namespace App\API\Services\payments;

/** Pure matching rules shared by bank, Co-op, M-Pesa and manual imports. */
final class FinancialReconciliationMatcher
{
    public function classify(string $reference, string $expectedReference, string $amount, string $expectedAmount): string
    {
        $normalizer = new ReferenceNormalizer();
        $actual = $normalizer->reference($reference);
        $expected = $normalizer->reference($expectedReference);
        $value = $this->cents($amount);
        $target = $this->cents($expectedAmount);

        if ($actual === '' || $expected === '') return 'unmatched';
        if ($actual !== $expected) return 'conflict';
        if ($value === $target) return 'matched';
        if ($value < $target) return 'partial';
        return 'overpayment';
    }

    private function cents(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) return -1;
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
