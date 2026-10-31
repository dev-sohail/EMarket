<?php

namespace App\Services\Api\Reflection;

use RuntimeException;

/**
 * Raised when reflection would generate a URL that another endpoint already owns.
 *
 * Reflection derives its paths from the model, not from the operator, so it can
 * easily land on a route that already exists. That has to stop the write rather
 * than produce a definition whose endpoints are registered but permanently
 * unreachable behind someone else's route.
 */
class PathConflictException extends RuntimeException
{
    /**
     * @param  list<array{method: string, path: string, conflicts: list<array{id: string, definition: string, path: string}>}>  $conflicts
     */
    public function __construct(protected array $conflicts)
    {
        parent::__construct($this->buildMessage());
    }

    /**
     * @return list<array{method: string, path: string, conflicts: list<array{id: string, definition: string, path: string}>}>
     */
    public function conflicts(): array
    {
        return $this->conflicts;
    }

    protected function buildMessage(): string
    {
        $lines = ['Reflection would generate a URL that another API definition already owns:'];

        foreach ($this->conflicts as $conflict) {
            $lines[] = sprintf('  %s %s', $conflict['method'], $conflict['path']);

            foreach ($conflict['conflicts'] as $other) {
                $lines[] = sprintf(
                    '    already used by %s as %s %s',
                    $other['definition'],
                    $conflict['method'],
                    $other['path']
                );
            }
        }

        $lines[] = '';
        $lines[] = 'Only one endpoint may own a public URL, because Laravel matches the first route registered.';
        $lines[] = 'Rename or deactivate the existing endpoint, or re-run with --force to accept the collision';

        return implode(PHP_EOL, $lines);
    }
}
