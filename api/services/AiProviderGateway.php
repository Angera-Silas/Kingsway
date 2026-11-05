<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use RuntimeException;

/** Ordered provider gateway. It retries only by moving to the next provider. */
final class AiProviderGateway implements AiCompletionProvider
{
    /** @param AiCompletionProvider[] $providers */
    public function __construct(private array $providers = [])
    {
        if ($this->providers === []) {
            $this->providers = [new AiProviderClient(null, [
                'name' => (string) Config::get('AI_PROVIDER_NAME', 'primary'),
            ])];
            $configured = Config::get('AI_PROVIDER_FALLBACKS', '');
            $fallbacks = is_string($configured) ? json_decode($configured, true) : [];
            if (is_array($fallbacks)) foreach (array_slice($fallbacks, 0, 4) as $fallback) {
                // API keys are optional for approved local/self-hosted
                // deployments. AiProviderClient still enforces credentials
                // for production providers when a request is attempted.
                if (!is_array($fallback) || trim((string) ($fallback['base_url'] ?? '')) === '' || trim((string) ($fallback['model'] ?? '')) === '') continue;
                $fallback['name'] = trim((string) ($fallback['name'] ?? '')) ?: 'fallback_' . count($this->providers);
                $this->providers[] = new AiProviderClient(null, $fallback);
            }
        }
    }

    public function complete(array $messages, array $options = []): array
    {
        $failures = [];
        foreach ($this->providers as $index => $provider) {
            try {
                $result = $provider->complete($messages, $options);
                FileLogger::write('ai_generation', ['type' => 'provider_gateway_success', 'provider_index' => $index]);
                return $result;
            } catch (\Throwable $e) {
                $failures[] = ['provider_index' => $index, 'error' => get_class($e)];
                $fallbackAllowed = !($e instanceof AiProviderException) || $e->allowsProviderFallback();
                FileLogger::write('ai_generation', [
                    'type' => $fallbackAllowed ? 'provider_gateway_fallback' : 'provider_gateway_failure',
                    'provider_index' => $index,
                    'fallback_attempted' => $fallbackAllowed,
                    'error_class' => get_class($e),
                    // Exception text can contain provider response bodies or
                    // credentials that pattern scrubbing cannot reliably
                    // recognize. Keep correlation useful without retaining it.
                    'error_fingerprint' => hash('sha256', get_class($e) . ':' . $e->getMessage()),
                ]);
                if (!$fallbackAllowed) {
                    throw new RuntimeException('AI provider configuration or request was rejected; fallback was not attempted.');
                }
            }
        }
        throw new RuntimeException('All configured AI providers were unavailable.');
    }

    /** Return safe availability metadata for every configured provider. */
    public function health(): array
    {
        $result = [];
        foreach ($this->providers as $index => $provider) {
            if (!$provider instanceof AiProviderClient) {
                $result[] = ['provider_index' => $index, 'status' => 'custom_provider'];
                continue;
            }
            $result[] = array_merge(['provider_index' => $index], $provider->checkModelAvailability());
        }
        return $result;
    }
}
