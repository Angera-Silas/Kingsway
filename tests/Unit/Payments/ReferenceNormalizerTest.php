<?php
declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\API\Services\payments\ReferenceNormalizer;
use PHPUnit\Framework\TestCase;

final class ReferenceNormalizerTest extends TestCase
{
    public function test_reference_aliases_share_a_canonical_form(): void
    {
        $normalizer = new ReferenceNormalizer();

        self::assertSame('FEE-KA-2026-00125', $normalizer->reference(' fee_ka_2026_00125 '));
        self::assertSame('FEE-KA-2026-00125', $normalizer->reference('Fees/ka 2026 00125'));
        self::assertSame('TRN-KA-2026-00125', $normalizer->reference(' t-r-n_ka_2026_00125 '));
        self::assertSame('U-KA-2026-00125', $normalizer->reference('uniform/ka/2026/00125'));
    }

    public function test_phone_and_account_identifiers_are_canonicalized(): void
    {
        $normalizer = new ReferenceNormalizer();

        self::assertSame('254797630228', $normalizer->phone('+254 797 630 228'));
        self::assertSame('254797630228', $normalizer->phone('0797630228'));
        self::assertSame('522533', $normalizer->accountIdentifier(' 522-533 '));
    }

    public function test_purpose_is_not_guessed_for_unknown_reference(): void
    {
        $normalizer = new ReferenceNormalizer();
        self::assertSame('fees', $normalizer->purposeFromReference('fees-ka-2026-1'));
        self::assertSame('transport', $normalizer->purposeFromReference('T-KA-2026-1'));
        self::assertNull($normalizer->purposeFromReference('2026-00125'));
    }
}
