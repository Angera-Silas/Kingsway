<?php
declare(strict_types=1);

namespace App\API\Services;

use RuntimeException;

/**
 * Allowlisted execution-key dispatcher. Metadata cannot supply executable SQL.
 */
final class GovernedReportExecutor
{
    /** @var array<string, callable> */
    private array $handlers;

    public function __construct(array $handlers)
    {
        foreach ($handlers as $key => $handler) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_.]{2,159}$/', $key) || !is_callable($handler)) {
                throw new RuntimeException('Invalid governed report handler registration.');
            }
        }
        $this->handlers = $handlers;
    }

    public function execute(string $executionKey, array $parameters)
    {
        if (!isset($this->handlers[$executionKey])) {
            throw new RuntimeException('This report definition has no approved executor.', 409);
        }
        return call_user_func($this->handlers[$executionKey], $parameters);
    }

    public function supports(string $executionKey): bool
    {
        return isset($this->handlers[$executionKey]);
    }
}
