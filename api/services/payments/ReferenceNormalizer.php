<?php
declare(strict_types=1);

namespace App\API\Services\payments;

/**
 * Produces searchable canonical values without destroying the submitted value.
 * The raw value must always remain available for audit and reconciliation.
 */
final class ReferenceNormalizer
{
    public function reference(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('normalizer_normalize')) {
            $value = (string) normalizer_normalize($value, \Normalizer::FORM_C);
        }
        if (function_exists('mb_strtoupper')) {
            $value = mb_strtoupper($value, 'UTF-8');
        } else {
            $value = strtoupper($value);
        }

        $value = preg_replace('~[\x{2010}-\x{2015}_/\\.]+~u', '-', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9-]+/u', '-', $value) ?: $value;
        $value = preg_replace('/-+/', '-', $value) ?: $value;
        $value = trim($value, '-');

        // Human-entered fee/transport/uniform prefixes are aliases, not new
        // business references. Do this only at the beginning of the value.
        $value = preg_replace('/^(?:FEES?|F[- ]?E[- ]?E)\-?/', 'FEE-', $value) ?: $value;
        $value = preg_replace('/^(?:TRANSPORT|T[- ]?R[- ]?N|T)\-?/', 'TRN-', $value) ?: $value;
        $value = preg_replace('/^(?:UNIFORMS?|U)\-?/', 'U-', $value) ?: $value;
        return trim(preg_replace('/-+/', '-', $value) ?: $value, '-');
    }

    public function phone(?string $value): string
    {
        $value = preg_replace('/[^0-9+]/', '', trim((string) $value)) ?: '';
        if (strpos($value, '+254') === 0) {
            return '254' . substr($value, 4);
        }
        if (strpos($value, '254') === 0) {
            return $value;
        }
        if (strpos($value, '0') === 0 && strlen($value) === 10) {
            return '254' . substr($value, 1);
        }
        return $value;
    }

    public function accountIdentifier(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?: '';
    }

    public function purposeFromReference(?string $value): ?string
    {
        $reference = $this->reference($value);
        if (strpos($reference, 'FEE-') === 0) return 'fees';
        if (strpos($reference, 'TRN-') === 0) return 'transport';
        if (strpos($reference, 'U-') === 0) return 'uniforms';
        return null;
    }
}
